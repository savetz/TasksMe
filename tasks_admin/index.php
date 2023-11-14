<?
/*
An admin dashboard for managing task report settings, and for changing admin password
*/
require_once('db_config.php');
session_start();

function change_pw($new_pw)
{
	global $dbh;
	$options = ['cost' => 11];
	$new_hash = base64_encode(password_hash($new_pw, PASSWORD_BCRYPT, $options));
	$new_hash=mysqli_real_escape_string($dbh,$new_hash);

	$SQL="UPDATE config SET admin_pw='$new_hash' WHERE config_id=1";
	mysqli_query($dbh,$SQL);
	show_login('Password Updated');

}
function reset_timestamps()
{
	global $dbh;
	$SQL="DELETE FROM email_history";
	mysqli_query($dbh,$SQL);
	$config=get_config();
	show_config($config, 'Timestamps deleted');
}
function get_users()
{
	global $dbh;
	$SQL="SELECT * FROM users";
	$result=mysqli_query($dbh, $SQL);
	$user_array=array();
	while ($row=mysqli_fetch_assoc($result))
	{
		$user_array[]=$row;
	}
	return $user_array;
}
function get_config()
{
	global $dbh;
	$SQL="SELECT * FROM config WHERE config_id=1 LIMIT 1";
	$result=mysqli_query($dbh, $SQL);
	return mysqli_fetch_assoc($result);
	
	
}
function change_config()
{
	global $dbh, $config;
	$daily_report_time=mysqli_real_escape_string($dbh,$_POST['report_time']);
	$daily_reminder_time=mysqli_real_escape_string($dbh,$_POST['daily_reminder_time']);
	$reminder_time=intval($_POST['reminder_time']);
	if ($reminder_time>12)
	{
		$reminder_time=12;
	}
	elseif ($reminder_time<1)
	{
		$reminder_time=1;
	}
	
	$SQL="UPDATE config SET daily_report_time='$daily_report_time', reminder_time=$reminder_time, daily_reminder_time='$daily_reminder_time' WHERE config_id=1";
	mysqli_query($dbh,$SQL);
	$config=get_config();
	show_config($config, 'Daily report configuration updated.');
	
}
function register_user() // check if user already exists, validate email address, validate user type
{
	global $dbh,$config;
	$valid_user_types=array('normal','super','script');
	$user_type = $_POST['user_type'];
	$user_email=trim($_POST['user_email']);
	$original_user_email=$user_email;
	$user_name=trim($_POST['user_name']);
	if (!in_array($user_type,$valid_user_types))
	{
		show_config($config,'Error: Invalid user type detected during user registration.');	
	}
	if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) 
	{
		show_config($config,'Error: Invalid email during user registration.');
	}
	if ($user_name=='')
	{
		show_config($config,'error: Empty user name during user registration.');
	}
	$user_email=mysqli_real_escape_string($dbh, $user_email);
	
	$check_duplicate_email="SELECT * FROM users WHERE user_email_address LIKE '$user_email' LIMIT 1";
	$result=mysqli_query($dbh, $check_duplicate_email);
	if (mysqli_num_rows($result)==1)
	{
		show_config($config,'error: Duplicate email address found. Cannot register '.$original_user_email.'.');
	}
	
	$user_name=mysqli_real_escape_string($dbh, $user_name);
	$SQL="INSERT INTO users (user_email_address,user_name,status,user_type) VALUES ('$user_email', '$user_name','active', '$user_type')";
	mysqli_query($dbh, $SQL);
	show_config($config,'New user '.$original_user_email.' added successfully.');
}
function show_config($config, $msg='')
{
	global $dbh;
	?>
	<head>
		<link rel="stylesheet" href="styles.css">
	</head>
	<body>
	<span><? echo $msg;?></span><br />
	<div style="border-style:solid;">
	Daily report configuration<br /><br />
	<form method="POST">
	<label for="report_time">Daily report time:
	<input type="time" id="report_time" name="report_time" value="<? echo $config['daily_report_time'];?>"></label><br /><br />
	<label for="daily)reminder_time">Daily reminder time:
	<input type="time" id="daily_reminder_time" name="daily_reminder_time" value="<? echo $config['daily_reminder_time'];?>"></label><br /><br />
	<label for="reminder_time">Send additional reminder</label>
	<Select name="reminder_time" id="reminder_time">
	<?
	for ($i=1;$i<=12;$i++)
	{
		echo '<option value="'.$i.'"';
		if ($config['reminder_time']==$i)
		{
			echo ' SELECTED';
		}
		echo '>'.$i.'</option>';
	}
	?>
	</select>
	hours before the daily report time if no tasks have been received from user
	
	<input name="operation" type="hidden" value="config_changes"><br /><br />
	<button type="submit">Submit</button>
	</form>
	</div>
		<br />
	<div style="border-style:solid;">
	<form method="POST">
	<input name="operation" type="hidden" value="reset_timestamps">
	<button type="submit">Reset Timestamps</button>
	Used to delete all the timestamps related to scheduling of reports and reminders after changing the setting.
	</form>
	</div>
	<br />

	<div style="border-style:solid;">
	Register user
	<form method="POST">
	<label>User email: 
	<input type="text" id="user_email" name="user_email" size="40"></label><br /><br />
	<label>User name: 
	<input type="text" id="user_name" name="user_name" size="40"></label><br /><br />
	<label for="user_type">User type:
	<Select name="user_type" id="user_type">
	<option value="normal">Normal</option>
	<option value="super">Super</option>
	<option value="script">Script</option>
	</select></label>
	<input name="operation" type="hidden" value="add_user"><br /><br />
	<button type="submit">Submit</button>
	</form>
	</div>

	<br />
	<div style="border-style:solid;">
	Edit user
	<form name="edit_user_settings" id="edit_user_settings" method="POST">

		<input type="hidden" name="operation" value="edit_user">
	<select id="selected_user" onchange="show_user_info();">
	<option option hidden disabled selected value> -- select a user -- </option>
	<?
	$users=get_users();
	
	foreach ($users as $user)
	{
	
		echo '<option name="user_pulldown" value="'.$user['user_id'].'">'.$user['user_email_address'].'</option>';
	
	}
	?>
	</select>
	
	<br />
	<div>
	<?
	foreach ($users as $user)
	{
		echo '<div style="display:none;" id="user_'.$user['user_id'].'_settings"><br />';
		echo '<label>User Email: <input type="text" id="user_'.$user['user_id'].'_email" name="user_'.$user['user_id'].'_email" value="'.$user['user_email_address'].'"></label><br /><br />';
		echo '<label>User Name: <input type="text" id="user_'.$user['user_id'].'_name" name="user_'.$user['user_id'].'_name" value="'.$user['user_name'].'"></label>';
		
		echo '<p>User Status</p>';
		echo '<input value="active" type="radio" name="user_'.$user['user_id'].'_status"';
		if ($user['status']=='active')
		{
			echo ' checked';
		}
		echo '><label for="user_'.$user['user_id'].'_status">Active</label>';
		echo '<input value="inactive" type="radio" name="user_'.$user['user_id'].'_status"';
		if ($user['status']=='inactive')
		{
			echo ' checked';
		}
		echo '><label for="user_'.$user['user_id'].'_status">Inactive</label><br />';
		
		echo '<p>User Type</p>';
		echo '<label><input value="normal" type="radio" name="user_'.$user['user_id'].'_type"';
		if ($user['user_type']=='normal')
		{
			echo ' checked';
		}
		echo '>Normal</label>';
		echo '<lable><input value="super" type="radio" name="user_'.$user['user_id'].'_type"';
		if ($user['user_type']=='super')
		{
			echo ' checked';
		}
		echo '>Super</label>';
		echo '<lable><input value="script" type="radio" name="user_'.$user['user_id'].'_type"';
		if ($user['user_type']=='script')
		{
			echo ' checked';
		}
		echo '>Script</label>';
		
		echo '<label><input value="inactive" type="radio" name="user_'.$user['user_id'].'_type"';
		if ($user['user_type']=='inactive')
		{
			echo ' checked';
		}
		echo '>Inactive</label><br />';
		
		echo '<br /><button name="edit_user" type="submit" value="'.$user['user_id'].'" onclick="submit_user_changes(this);">Submit</button>';
		echo '</div>';
	}
	?>
	<span id="edit_msg"></span>
	</div>
	</form>
	<script>
	// change display of options based on user selection
	//depends on the user selected, submit only changes for that user
	var previous_selected_id=false;
	function show_user_info()
	{
		user_id=document.getElementById('selected_user').value;
		
		if (previous_selected_id!=false)
		{
			document.getElementById('user_'+previous_selected_id+'_settings').style.display='none';
		}
		document.getElementById('user_'+user_id+'_settings').style.display='block';
		previous_selected_id=user_id;
	
	}
	function submit_user_changes(button_ele)
	{
		user_id=button_ele.value;
		 var xhttp = new XMLHttpRequest();
	  xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
		  document.getElementById("edit_msg").innerHTML = this.responseText;
		}
	  };
	  user_form=document.getElementById('edit_user_settings');
	  
	  xhttp.open("POST", "proc_user_changes.php", true);
	  xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	  new_user_email=encodeURIComponent(document.getElementById('user_'+user_id+'_email').value);
	  new_user_name=encodeURIComponent(document.getElementById('user_'+user_id+'_name').value);
	  new_user_status=encodeURIComponent(user_form.elements['user_'+user_id+'_status'].value);
	  new_user_type=encodeURIComponent(user_form.elements['user_'+user_id+'_type'].value);
	  
	  xhttp.send("user_id="+user_id+'&user_email_address='+new_user_email+'&user_name='+new_user_name+'&status='+new_user_status+'&user_type='+new_user_type);
		
	}
	document.getElementById('edit_user_settings').onsubmit = function(event) {
    event.preventDefault();
    return false;
	}

	</script>
	</div>
	<br />
	</div>
		<div style="border-style:solid;">
	<p>Change Admin Password</p><br />
	<form name="pw_change_form" id="pw_change_form" method="POST">
	<label>Old Password:<input type="password" name="old_password" id="old_password"></label><br /><br />
	<label>New Password:<input type="password" name="new_password" id="new_password" minlength="8" required> (8 to 72 characters)</label><br /><br />
	<label>New Password again:<input type="password" name="new_password_2" id="new_password_2" minlength="8" required></label><br /><br />
	<input type="hidden" name="operation" value="change_pw">
	<button type="submit" onclick="verify_new_pw();">Change Password</button>
	<span id="pw_msg"> &nbsp </span>
	</form>
	<script>
	function verify_new_pw()
	{
		if (document.getElementById('new_password').value==document.getElementById('new_password_2').value)
		{
			console.log('good');
			document.getElementById('pw_change_form').submit();
		}
		else
		{

			document.getElementById('pw_msg').innerHTML="The two new passwords do not match. Please make sure they are identical";
			
		}
		
		
	}
		document.getElementById('pw_change_form').onsubmit = function(event) {
    event.preventDefault();
    return false;
	}
	</script>
	</div>
	</body>
	<?
	die();
	
}
function verify_pw($password,$hashedPasswordFromDB) //verify password and set session as authenticated
{
	

	if (password_verify($password, $hashedPasswordFromDB))
	{
		return true;
	}
	return false;
}
function is_preauthenticated()
{
	if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']==true && isset($_SESSION['login_expiration']) && $_SESSION['login_expiration']>=time())
	{
		return true;
	}	
	return false;
	
}


function show_login($msg='')
{
	if ($msg)
	{
		echo '<p>'.$msg.'</p>';
	}
	?>
	<form method="POST">
<div>
    <label for="auth_code">Password: </label>
    <input type="password" id="auth_code" name="auth_code">
		   <button type="submit">Login</button>
		   <input type="hidden" name="operation" value="login">
</div>
</form>
	<?
	die();
}


$dbh = mysqli_connect($conn_hostname, $conn_username, $conn_password,$conn_database);
$config=get_config();

if (isset($_POST['operation']) && $_POST['operation']=='login') // if it's submitting a login, then verify it, otherwise check if it's already logged in
{
	if (verify_pw(trim($_POST['auth_code']),base64_decode($config['admin_pw'])))
	{
		$_SESSION['authenticated']=true;
		$_SESSION['login_expiration']=time()+10*60; // expires in 10 minutes

	}
	else
	{
		sleep(2);
		show_login('Login incorrect. Please try again');

	}
}
else // not submitting a login
{
	// check if changing pw
	if (isset($_POST['operation']) && $_POST['operation']=='change_pw')
	{
		if (verify_pw(trim($_POST['old_password']),base64_decode($config['admin_pw'])))
		{
			$_SESSION['authenticated']=true;
			$_SESSION['login_expiration']=time()+10*60; // expires in 10 minutes

			change_pw(trim($_POST['new_password']));
			
		}
		else
		{
			sleep(2);
			show_login('Old password incorrect. Please try again');
		}
		
	}
	else // not changing pw. Check to see if it has a logged in session
	{
		if (!is_preauthenticated()) // not logged in. Show login page
		{
			show_login('Login credentials expired. Please re-login');
		}
	}
}
if (!isset($_POST['operation']))
{
	$_POST['operation']='none';
}
switch ($_POST['operation'])
{
	
	case 'none': show_config($config);
	break;
	case 'add_user': register_user();
	break;
	case 'config_changes': change_config();
	break;
	case 'reset_timestamps': reset_timestamps();
	break;
	case 'edit_user': edit_user();
	break;
	
	default: show_config($config);
}


?>