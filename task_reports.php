<?
//He tasks me. He tasks me and I shall have him! I'll chase him 'round the moons of Nibia and 'round the Antares Maelstrom and 'round perdition's flames before I give him up!
/*
Send out report every day for all tasks entered previous day. Send out reminder to enter tasks once a day.
Also send out extra reminder if we haven't heard from user x hour before report compilation time.

The report also includes historic tasks completed by the user from either 1 year ago, 3 months ago, or last week
*/
function html_mail($destination,$subject,$body, $from_email)
{
// set html mime type header and wordwrap body to prevent rejection from line too long
    $from = 'MIME-Version: 1.0' . "\r\n";
    $from .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    $from .="From: ".$from_email."\r\n";


//mail($destination, $subject, wordwrap($body, 70,"\r\n"), $from, '-f'.$from_email); // some server settings does not allow changing envelope address
	mail($destination, $subject, wordwrap($body, 70,"\r\n"), $from); // Set a from address without changing envelope for wider compatibility
	
	
}
function get_config() //retrieves configuration info from table
{
	global $dbh;
	$SQL="SELECT * FROM config WHERE config_id=1 LIMIT 1";
	$result=mysqli_query($dbh, $SQL);
	return mysqli_fetch_assoc($result);
	
	
}
function get_users($user_types='active')
{
	// get list of users. Default only returns active and non-script accounts which are used for sending emails
	global $dbh;
	

	switch ($user_types)
	{
		case 'active':
		$SQL="SELECT * FROM users WHERE status='active' AND (user_type='normal' OR user_type='super')";
		break;
		case 'normal':
		$SQL="SELECT * FROM users WHERE status='active' AND user_type='normal'";
		break;
		case 'all':
		$SQL="SELECT * FROM users";
		break;
		default:
		$SQL="SELECT * FROM users WHERE status='active' AND (user_type='normal' OR user_type='super')";
		
	}

	$result=mysqli_query($dbh, $SQL);
	$user_array=array();
	while ($row=mysqli_fetch_assoc($result))
	{
		$user_array[$row['user_id']]=$row;
	}
	return $user_array;
}


function make_report($report_time, $last_report_time, &$users_table)
{
	// Get all tasks between report time and last report time
	// Then sort by user and date
	global $dbh;

	$SQL="SELECT *,UNIX_TIMESTAMP(occurred_on) AS unix_occurred from tasks WHERE UNIX_TIMESTAMP(occurred_on) >= $last_report_time && UNIX_TIMESTAMP (occurred_on) < $report_time ORDER BY occurred_on ASC";
	$result=mysqli_query($dbh, $SQL);
	$total_tasks=mysqli_num_rows($result);
	$empty_user_array=array();
	if ($total_tasks>1)
	{
		$item='entries';
	}
	else
	{
		$item='entry';
	}
	$report_str=$total_tasks.' '.$item.'</p>';
	while ($row=mysqli_fetch_assoc($result))
	{

		$tasks_date=date('l, F j, Y',$row['unix_occurred']);
		$users_table[$row['user_id']]['tasks'][$tasks_date][]=$row['body'];
		
	}

	foreach ($users_table as $user) // now go through the table and list tasks for each user
	{
		if ($user['status']=='active' && !empty($user['tasks']))
		{
			$report_str.='<p><b>'.$user['user_name'].'</b><br /><br />';
			foreach ($user['tasks'] as $date_index=>$tasks_date_array)
			{
				$report_str.='<b>'.$date_index.'</b><br />';
				foreach ($tasks_date_array as $task)
				{
					$report_str.='<span style="color:green;"><b>&#x2713;</b></span> '.htmlentities($task).'<br />'."\r\n";
					
				}
				
			}
			
		
			$report_str.='</p><br />';
		
		}
		if ($user['status']=='active' && empty($user['tasks']))
		{
			$empty_user_array[]=$user['user_name'];
		}
		
		
	}
	$total_empty=count($empty_user_array);
	if ($total_empty >=1)
	{
		if ($total_empty>1)
		{
			$empty_user_array[$total_empty-1]='or '.$empty_user_array[$total_empty-1];	
		}
		$report_str.='<p>We have not heard from '.implode(', ',$empty_user_array).'</p>';
	}
	return $report_str;
}
function get_past_tasks($user_id,$time_frame=null)
{
	// get tasks done by user_id from the default set of 3 time frames
	global $dbh;
	if ($time_frame==null) // not specified, get all preset time frame
	{
	
	$SQL="SELECT *,DATE(occurred_on) as task_date from tasks WHERE user_id=$user_id AND (DATE(occurred_on)= DATE(DATE_SUB(NOW(),INTERVAL 1 YEAR)) OR DATE(occurred_on)= DATE(DATE_SUB(NOW(),INTERVAL 3 MONTH)) OR DATE(occurred_on)= DATE(DATE_SUB(NOW(),INTERVAL 1 WEEK)))";
		
	}
	else // get only specified time frame
	{
		$SQL="SELECT *, DATE(occurred_on) as task_date from tasks WHERE user_id=$user_id AND (DATE(occurred_on)= DATE(DATE_SUB(NOW(),INTERVAL $time_frame))";
		
	}
	$result=mysqli_query($dbh,$SQL);
	if (!$result)
	{
		return null;
	}
	$tasks_array=array();
	while ($row=mysqli_fetch_assoc($result))
	{
		$tasks_array[$row['task_date']][]=$row['body'];

		
	}
	
	if (!empty($tasks_array))
	{
		if ($time_frame==null)
		{
			$key_chosen_date=array_rand($tasks_array);
			$chosen_date=strtotime($key_chosen_date);
			
			if ($chosen_date <=  strtotime('1 year ago'))
			{
				$en_time='One year ago';
			}
			elseif($chosen_date<=strtotime('3 months ago'))
			{
				$en_time='Three months ago';
			}
			else // last week
			{
				$en_time='Last week';
			}
			return array('time'=>$en_time, 'date'=>date('l, F d, Y',$chosen_date),'tasks'=>$tasks_array[$key_chosen_date]);
		}
		else
		{

			$chosen_date=strtotime($row['task_date']);
			return array('time'=>$time_frame.' ago', 'date'=>date('l, F d, Y',$chosen_date),'tasks'=>$tasks_array[$row['task_date']]);

		}
	}
	return null;
}
function make_regular_reminder($last_report_time, $user)
{
	//Creates a reminder to enter tasks for the specific user. Also shows tasks that they've already entered that day
	global $dbh;

	$user_id=$user['user_id'];
	$SQL="SELECT user_id, UNIX_TIMESTAMP(occurred_on) AS unix_occurred, count(task_id) as total from tasks WHERE UNIX_TIMESTAMP(occurred_on) > $last_report_time GROUP BY user_id";

	$result=mysqli_query($dbh, $SQL);
	$alert_text='';
	$total_tasks=0;
	$user_tasks=0;
	while ($row=mysqli_fetch_assoc($result))
	{
		$total_tasks=$total_tasks+$row['total'];
		if ($user_id==$row['user_id'])
		{
			$user_tasks=$row['total'];
		}
	}
	if ($total_tasks==0)
	{
		$total_tasks='no';
	}
	if ($total_tasks==1)
	{
		$item='entry';
	}
	else
	{
		$item='entries';
	}
	$dow=date('w');
	$last_report_dow=date('w',$last_report_time);

	if ($last_report_dow==date('w',strtotime('yesterday')))
	{
		$last_report_time_en='the report from yesterday';	
	}
	elseif($last_report_dow==$dow)
	{

		$last_report_time_en='the report from earlier today';	
	}
	else
	{
			//Not today and not yesterday, then last report must be Friday
		$last_report_time_en='the report from last Friday';	
	}
	$alert_text="<b>If you haven't already, now's a good time to take 30 seconds to write out what you got done today. </b><br />"."\r\n";
	if ($user_tasks >0)
	{
		$alert_text.='You have already made some entries for today, so take a look below and see if you have anything to update or add before this goes out to your whole team tomorrow.<br />'."\r\n";
		$alert_text.='If you want to add anything, just reply to this email with a few items (separate entries on separate lines)<br /><br />'."\r\n";
		$SQL="SELECT * from tasks WHERE user_id=$user_id AND UNIX_TIMESTAMP(occurred_on) > $last_report_time";
		$result=mysqli_query($dbh, $SQL);

		while ($user_tasks_row=mysqli_fetch_assoc($result))
		{
			$alert_text.='<span style="color:green;"><b>&#x2713;</b></span> '.htmlentities($user_tasks_row['body']).'<br /><br />'."\r\n";
			
		}
	}
	else
	{
		$alert_text.="You can do that by replying to this email (separate entries on separate lines): list a few accomplishments for the day and your teammates will see what you've been up to.<br />"."\r\n";

	}
	
	

	$alert_text.='Since '.$last_report_time_en.', '.$group_name.' has logged <b>'.$total_tasks.' '.$item.'</b>. ';
	if ($user_tasks >0)
	{
		if ($user_tasks==1)
		{
			$be='is';
		}
		else
		{
			$be='are';
		}
		$alert_text.='Of these, '.$user_tasks.' '.$be.' yours.<br />'."\r\n";
	}
	
	$past_tasks_result=get_past_tasks($user_id);
	if ($past_tasks_result)
	{
		$alert_text.='<br /><br /><b>'.$past_tasks_result['time'].'</b><br />This is what you got done on this day '.strtolower($past_tasks_result['time']).', '.$past_tasks_result['date'].'<br /><br />';
		foreach ($past_tasks_result['tasks'] as $past_tasks)
		{
			$alert_text.='<span style="color:green;"><b>&#x2713;</b></span> '.htmlentities($past_tasks).'<br />'."\r\n";
		}
	}
	
	return $alert_text;
}
function make_extra_reminder($reminder_hour_en)
{
	// makes the text for extra reminder based on how many hours before the report will sent out
	$text='The daily tasks report will go out to the team in '.$reminder_hour_en.'. If you want to add more completed tasks to the report, now would be a great time.<br />'."\n".'Simply reply to this email with your tasks (one task per line).';
	return $text;
}
function make_extra_reminder_list($last_report_time,$users_table)
{
	// returns a list of users that have not entered any tasks for the day
	global $dbh;
	$SQL="SELECT * from tasks WHERE UNIX_TIMESTAMP(occurred_on) > $last_report_time GROUP BY user_id"; // Get all the users that have entered something
	$result=mysqli_query($dbh, $SQL);
	while ($row=mysqli_fetch_assoc($result))
	{
		unset($users_table[$row['user_id']]);
	}
	return $users_table;
}

function get_email_time($email_type)
{
	// retrieves the unix timestamp for when the specified email type was last sent, to use in scheduling next email 
	global $dbh;
	$SQL="SELECT prev_email_unix_time FROM email_history WHERE email_type='$email_type' LIMIT 1";
	$result=mysqli_query($dbh, $SQL);
	if (mysqli_num_rows($result)==1)
	{
		$row=mysqli_fetch_assoc($result);
		return $row['prev_email_unix_time'];
	}
	else
	{
		return false;
	}
}
function get_last_report_time()
{
	// Get or calculate the last time a report was or should have been sent
	
	global $config, $sent_report;
	$last_report_time=get_email_time('last_report');
	if ($last_report_time==false)
	{

		$dow=date('N',time());
		if ($dow <= 5)
		{
			// try to figure out if it has already been sent today, and determine the time. // can assume is that it must not have been sent today otherwise there would be a file
			$report_time= strtotime($config['daily_report_time']); // today's time of report in unix time
			if ($report_time >time() && $sent_report==false) // today's report hasn't been sent
			{

				if ($dow==1) // if it's Monday then last report time was Friday. 
				{
					$last_report_time = $report_time-3*24*3600;
				}
				else
				{
					$last_report_time = $report_time-24*3600; 

				}
			}
			else // today's report was just sent by this script call
			{
				$last_report_time=$report_time;
			}
		}
		else
		{
			// if it's Saturday or Sunday, then last report was Friday
			$last_report_time=$report_time-(($dow-5)*24);
			
		}
	}
	return $last_report_time;
}
function get_next_report_time($last_report_time)
{
	// calculate the time the next report should be sent.
	
	$last_report_dow=date('w',$last_report_time);


	if ($last_report_dow==5) // just reported on Friday, so next report would be Monday
	{
		$next_report_time=$last_report_time+3*24*3600;
	}
	else //last report wasn't Friday
	{
		$next_reoprt_time=$last_report_time+24*3600; //next report is a day later
		
	}
	return $next_report_time;
}

require_once('db_config.php');
$wait_time=$cool_down_time+$time_to_act; //email timestamps are deleted after wait_time. This is to prevent spamming since cron job interval is smaller than the time_to_act in order to make more timely delivery of reports and reminders.

$dbh = mysqli_connect($conn_hostname, $conn_username, $conn_password,$conn_database);
mysqli_set_charset($dbh,'utf8'); //sets connection charset to utf8 so smart quotes and other chars are stored correctly

$config=get_config();
//check to see if it's time to generate a report, check if report has already been sent today
//echo $config['daily_report_time'];
$report_time= strtotime($config['daily_report_time']); // today's time of report in unix time

$sent_report=false;

	
$dow=date('N'); //Get day of week. No report will be sent on weekend

$task_report_sent=get_email_time('task_report_sent');
	
if ($dow<=5 && time()>=$report_time && time()<=$report_time+$time_to_act*60 && $task_report_sent===false)
{
	//mail reports

	$users_table=get_users('all'); // get all users, including inactive and scripts because their tasks should be included in report
	// if report time is Monday, then last report time was Friday
	// otherwise, it's always a day before
	if ($dow==1) // if it's Monday then last report time was Friday
	{
		$last_report_time = $report_time-3*24*3600;
		$last_reported_day="Friday";
	}
	else //if it's not Monday then last report time was yesterday
	{
		$last_report_time = $report_time-24*3600;
		$last_reported_day="yesterday";
	}
	$report=make_report($report_time,$last_report_time, $users_table);
$report='<p style="text-align:center; font-weight: 900; font-size:1.3em;">'.date('l, F j Y',$report_time).'</p><p>Since '.$last_reported_day.', <b>'.$group_name.'</b> has logged '.$report;

	foreach ($users_table as $user)
	{
		if ($user['status']=='active' && ($user['user_type']=='normal' || $user['user_type']=='super')) // only send email to active and non-script users
		{

			html_mail($user['user_email_address'],$group_name.' Digest for '.date('F j',$report_time),$report, $from_email);
			
//			echo 'time:'.date('Y-m-d H:i:s');
			echo 'mailing to '.$user['user_email_address']."\n";
		//	echo 'body:'.$report."\n";

		}
	}
	mysqli_query ($dbh,"INSERT INTO email_history (email_type,prev_email_unix_time) VALUES ('last_report', $report_time) ON DUPLICATE KEY UPDATE prev_email_unix_time = $report_time"); //record the report time
	//record timestamp to prevent duplicate emails
	mysqli_query ($dbh,"INSERT INTO email_history (email_type,prev_email_unix_time) VALUES ('task_report_sent',UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE prev_email_unix_time = UNIX_TIMESTAMP()");// record timestamp when report was sent

	$sent_report=true;
}
else //delete the report sent timestamp after $wait_time minutes, this will allow new reports to be sent after if configuration is changed to send report at a new time.
{
	if ($task_report_sent!==false && $task_report_sent <time()-$wait_time*60)
	{
		mysqli_query($dbh,"DELETE FROM email_history WHERE email_type='task_report_sent' LIMIT 1");
	}
}


$reminder_time=strtotime($config['daily_reminder_time']); // today's time of reminder in unix

$task_reminder_sent=get_email_time('task_reminder_sent');

// do not send reminder on weekends
if ($dow!=7 && $dow!=6 && time()>=$reminder_time && time()<=$reminder_time+$time_to_act*60 && $task_reminder_sent===false)
{
	$users_table=get_users('active'); // regular reminder are sent to all active non-script users
	
	$last_report_time=get_last_report_time();
	
	//record timestamp to prevent duplicate emails
	mysqli_query($dbh,"INSERT INTO email_history (email_type,prev_email_unix_time) VALUES ('task_reminder_sent',UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE prev_email_unix_time = UNIX_TIMESTAMP()");


	foreach ($users_table as $user)
	{

		$reminder=make_regular_reminder($last_report_time, $user);
		html_mail($user['user_email_address'],$main_reminder_subject,$reminder, $from_email);
		
	}
}
else
{
	if ($task_reminder_sent!==false && $task_reminder_sent <time()-$wait_time*60) //delete the timestamp after $wait_time minutes, this will allow new reminder to be sent after if configuration is changed.
	{
		mysqli_query($dbh,"DELETE FROM email_history WHERE email_type='task_reminder_sent' LIMIT 1");
	}
}

// need to make sure the report is actually going out reminder_hours later, so check dow or report time
$last_report_time=get_last_report_time();

$next_report_time=get_next_report_time($last_report_time);

$extra_reminder_time=$next_report_time-3600*$config['reminder_time']; // extra reminder is sent x hour before the next report is set to go out

$extra_reminder_sent=get_email_time('extra_reminder_sent');

//Checks for users needing extra reminder if it's no more than 15 minutes past the designated reminder time
//Excludes Saturday since it's not possible for extra reminder to go out on Saturday because it is restricted to max of 12 hours before report (which can't be sent on Sunday)

if ($dow!=6 && time()>=$extra_reminder_time && time()<=$extra_reminder_time+$time_to_act*60 && $extra_reminder_sent===false)
{
	echo 'in here';
	$users_table=get_users('normal'); // extra reminders are only sent ot normal active users, excluding super and script
	$reminder_list=make_extra_reminder_list($last_report_time, $users_table);
	
	if (!empty($reminder_list))
	{
		if ($config['reminder_time']>1)
		{
			$reminder_hour_en=$config['reminder_time']." hours";
			
		}
		else
		{
			$reminder_hour_en="1 hour";
		}
		$reminder=make_extra_reminder($reminder_hour_en);
		//only send extra reminder for those that haven't entered anything since last report
		foreach ($reminder_list as $user)
		{
			
			$subject=$reminder_hour_en." before report goes out today";
			html_mail($user['user_email_address'],$subject,$reminder, $from_email);
		}
		//record timestamp to prevent duplicate emails
		mysqli_query($dbh,"INSERT INTO email_history (email_type,prev_email_unix_time) VALUES ('extra_reminder_sent',UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE prev_email_unix_time = UNIX_TIMESTAMP()");

	}
}
else
{
	if ($extra_reminder_sent!==false && $extra_reminder_sent <time()-$wait_time*60) //delete the timestamp after $wait_time minutes, this will allow new extra reminder to be sent after if configuration is changed.
	{
		mysqli_query($dbh,"DELETE FROM email_history WHERE email_type='extra_reminder_sent' LIMIT 1");
	}
	
}


?>