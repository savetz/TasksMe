<?
/*
Checks if settings in db_config can connect to the database.
Checks if tasks_me.php has preferred permissions set
Checks if html2text.php exists in specified location
Validates email address contained in $from_email
Create tables if doesn't exist.
Will also set and reset the admin password.
*/
if (file_exists('db_config.php'))
{
	require_once('db_config.php');
}
else
{
	die ('Error: cannot find db_config.php');
}
if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) 
{
	die ('Error: $from_email variable in db_config must contain a valid email address from your domain.');
}

if (!file_exists($html2text_loc.'html2text.php'))
{

	echo 'Error: html2text.php cannot be found in the location specified in db_config.php'."\n";
}
$tasks_me_permission = substr(sprintf('%o',fileperms('tasks_me.php')), -4);

if ($tasks_me_permission!='0755')
{
	echo 'Permissibon for tasks_me.php is currently set to '.$tasks_me_permission.". It is highly recommended that the permission is set to 0755.\n";
}

$dbh = mysqli_connect($conn_hostname, $conn_username, $conn_password,$conn_database);
if (!$dbh)
{
	echo 'Error: Cannot connect to database. Please check settings in db_config.php'."\n";
}

//Create config table

$SQL="CREATE TABLE IF NOT EXISTS `config` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_pw` blob NOT NULL,
  `daily_report_time` time NOT NULL,
  `reminder_time` int(11) NOT NULL,
  `daily_reminder_time` time NOT NULL,
  PRIMARY KEY (config_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
mysqli_query($dbh, $SQL) or die (mysqli_error($dbh));

// Create email_history table
$SQL="CREATE TABLE IF NOT EXISTS `email_history` (
  `email_type` varchar(200) NOT NULL,
  `prev_email_unix_time` int(13) NOT NULL DEFAULT '0',
  PRIMARY KEY (email_type)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
mysqli_query($dbh, $SQL) or die (mysqli_error($dbh));


$SQL="CREATE TABLE IF NOT EXISTS tasks (`task_id` int(11) NOT NULL AUTO_INCREMENT,`user_id` int(11) NOT NULL ,`body` text COLLATE utf8_unicode_ci NOT NULL,`occurred_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (task_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
mysqli_query($dbh, $SQL) or die (mysqli_error($dbh));

$SQL="CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email_address` varchar(200) NOT NULL,
  `user_name` varchar(200) NOT NULL,
  `status` varchar(30) DEFAULT NULL,
  `user_type` varchar(100) DEFAULT NULL,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
mysqli_query($dbh, $SQL) or die (mysqli_error($dbh));

$done=false;
while ($done==false)
{
	echo "Set Admin password (8 characters minimum): ";
	$handle = fopen ("php://stdin","r");
	$password = trim(fgets($handle));
	if (strlen($password)>=8)
	{
		echo "Please confirm password again: ";
		$handle = fopen ("php://stdin","r");
		$password_cc = trim(fgets($handle));
		if ($password==$password_cc)
		{
			$done=true;	
		}
		else
		{
			echo "Passwords do not match\n";
		}
	}
}
	$options = ['cost' => 11];
$hash=password_hash($password, PASSWORD_BCRYPT, $options);

$hash = base64_encode($hash);
$hash = mysqli_real_escape_string($dbh,$hash);
$SQL="INSERT INTO config (config_id,admin_pw,daily_report_time,reminder_time,daily_reminder_time) VALUES (1,'$hash','12:00:00',1,'19:00:00') ON DUPLICATE KEY UPDATE admin_pw = '$hash'";

mysqli_query($dbh,$SQL);
echo mysqli_error($dbh);
echo "Admin password set.\nPlease complete the configuration using the admin dashboard.";
?>