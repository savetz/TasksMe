#!/usr/local/bin/php -q
<?
/*

This script processes incoming email from registered accounts line by line into task entries in the database.

Requires https://github.com/soundasleep/html2text

If more robust reply parsing is needed, check 
  https://github.com/willdurand/EmailReplyParser
  https://pastebin.com/J0R5aCR1
*/


require_once('db_config.php');
$dbh = mysqli_connect($conn_hostname, $conn_username, $conn_password,$conn_database);
mysqli_set_charset($dbh,'utf8'); //sets connection charset to utf8 so smart quotes and other chars are stored correctly


// read from stdin

$fd = fopen("php://stdin", "r");
$email = "";


while (!feof($fd)) {
    $email .= fread($fd, 1024);
}


fclose($fd);


function is_registered_email($email)
{
	/* 
	
	looks for registered email address in user table
	
	*/

	global $dbh;
	$email=mysqli_real_escape_string($dbh,$email);
	

	$SQL="SELECT * FROM users where user_email_address='$email' AND status='active' LIMIT 1";
	$result=mysqli_query($dbh,$SQL);

	if ($result)
	{

		return mysqli_fetch_assoc($result);
	}
	return false;
	
}
$hit = preg_match('/\AFrom (?P<email_addr>[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4})\b/i',$email,$matches); //find incoming email address
$hit = true;
if(!$hit) {

    die();
}
$email_addr=trim($matches['email_addr']);

if (!filter_var($email_addr, FILTER_VALIDATE_EMAIL,FILTER_NULL_ON_FAILURE)) //validate email address
{
	die();
}
$user_match=is_registered_email($email_addr); // checks if it's from a registered email address
if (!$user_match)
{
	die();
}


	$hit = preg_match('/boundary=(?P<boundary>.+)/i',$email,$matches);
if (!$hit) // cannot find boundary. Might be pure plain text?
{
	//if ($user_match['user_type']=='script') // removed this condition because iPhone will send text-only non-multipart mail

	// Remove all lines that starts with html keywords
	$text_content_array=preg_split('/^Date\:.*/mi',$email);
	
	$text_content=$text_content_array[1];
	$text_content=quoted_printable_decode($text_content); // decode quoted printable content
	$email_keyword_pattern='/^[\w\-]+:.*?(\r\n|\n|\r){2,}/mis';
	$text_content=preg_replace($email_keyword_pattern,'',$text_content,1);
	
	if (stristr($text_content_array[0], "Content-Type: text/html") !== false)
	{
		require_once($html2text_loc.'html2text.php');
		$text_content= trim(convert_html_to_text($text_content));
	}
	  
	$text_content= preg_replace('/(^\w.+:\n)?(^>.*(\n|$)){2,}/mi', '', $text_content); // strip out replies

	$quoted_email=preg_quote($from_email,'/');	
	$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text from reminder email
	$quoted_email=preg_quote(trim($email_addr),'/');
	$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text from sender email


}
else
{
	$matches['boundary']=trim($matches['boundary'],'"');
	$boundary_str='/'.preg_quote('--'.$matches['boundary']).'/';
	$content_array=preg_split($boundary_str,$email);
	array_shift($content_array);

	$text_content=false;

	foreach ($content_array as $segment)
	{
		
		if (stristr($segment, "Content-Type: text/html") !== false)
		{
			$text_content = trim(preg_replace('/Content-Type:[^;]+;(\s*charset=.*)?/i', "", $segment));
			$text_content = trim(preg_replace('/Content-(Type|ID|Disposition|Transfer-Encoding):.*?(\r\n|\n|\r)/is', "", $text_content));
			$text_content=quoted_printable_decode($text_content); // decode quoted printable content
			require_once($html2text_loc.'html2text.php');

			$text_content= trim(convert_html_to_text($text_content));
		  
			$text_content= preg_replace('/(^\w.+:\n)?(^>.*(\n|$)){2,}/mi', '', $text_content); // strip out replies

			$quoted_email=preg_quote($from_email,'/');	
			$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text from reminder email 
			$quoted_email=preg_quote(trim($email_addr),'/');
			$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text from sender email


			break;
		}
		
	}


	if (!$text_content) // can't find html. Try to look for plain text
	{
		foreach ($content_array as $segment)
		{
			 if (stristr($segment, "Content-Type: text/plain") !== false)
			{
				$text_content = trim(preg_replace('/Content-Type:[^;]+;(\s*charset=.*)?/i', "", $segment));
				$text_content = trim(preg_replace('/Content-(Type|ID|Disposition|Transfer-Encoding):.*?(\r\n|\n|\r)/is', "", $text_content));
				$text_content=quoted_printable_decode($text_content);

				$text_content= preg_replace('/(^\w.+:\n)?(^>.*(\n|$)){2,}/mi', '', $text_content); // strip out replies

				 // need to remove auto inserted texts in reply
				$quoted_email=preg_quote('tasks@savetzpublishing.com','/');	
				$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text
				$quoted_email=preg_quote(trim($email_addr),'/');
				$text_content= preg_replace('/(\r\n|\n|\r){1}[^\n\r]*' . $quoted_email. '(.|\r\n|\n|\r)*/is', '', $text_content,1); // strip out auto inserted reply text

				break;
			}
		}
		
	}
}
$tasks_array=preg_split("/\r\n|\n|\r/", $text_content); // split lines into tasks and record each task

 
foreach ($tasks_array as $task)
{
	if (trim($task)!='')
	{
		$task=mysqli_real_escape_string($dbh,$task);
		$user_id=$user_match['user_id'];
		$SQL="INSERT INTO tasks (user_id, body) VALUES ($user_id, '$task')";

		mysqli_query($dbh,$SQL);
	}
}
?>