<?

require_once('db_config.php');
session_start();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']==1 && isset($_SESSION['login_expiration']) && $_SESSION['login_expiration'] >=time() && $_SESSION['login_expiration'] <=time()+10*60)
{
//file_put_contents('/var/www/sites/savetzpublishing.com/tasks_admin/post_log.txt', print_r($_POST, true).print_r($_SESSION,true),FILE_APPEND);
$dbh = mysqli_connect($conn_hostname, $conn_username, $conn_password,$conn_database);
$error=mysqli_error($dbh);
if ($error)
{
	die ($error);
}
$user_email_address=trim($_POST['user_email_address']);
$user_name=trim($_POST['user_name']);
$valid_status_array=array('active','inactive');
$valid_user_type_array=array('normal','super','inactive','script');
$user_type=trim($_POST['user_type']);
$status=trim($_POST['status']);
if (!in_array($status,$valid_status_array))
{
	die('Error: Invalid user status.');
}
if (!in_array($user_type,$valid_user_type_array))
{
	die('Error: Invalid user type.');
}
if ($user_name=='')
{
	die ('Error: Empty user name.');
}
if (!filter_var($user_email_address, FILTER_VALIDATE_EMAIL)) 
{
	die ('Invalid user email');
}
// check email duplicate
$user_id=intval(trim($_POST['user_id']));

$user_email_address=mysqli_real_escape_string($dbh, $user_email_address);

$check_duplicate_email="SELECT * FROM users WHERE user_id != $user_id AND user_email_address LIKE '$user_email_address' LIMIT 1";
$result=mysqli_query($dbh, $check_duplicate_email);
if (mysqli_num_rows($result)==1)
{
	die('Error: Email address already used by another user.');
}
	

$user_name=mysqli_real_escape_string($dbh, trim($_POST['user_name']));

$SQL="UPDATE users SET user_email_address='$user_email_address', user_name='$user_name', status='$status', user_type='$user_type' WHERE user_id=$user_id LIMIT 1";
mysqli_query($dbh,$SQL);

echo 'Changes to '.$_POST['user_email_address'].' updated.';
	
}
else
{
echo 'Authentication expired. Please reload the page to relogin.';
}
?>