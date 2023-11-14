<?
$conn_hostname="localhost"; //mysql hostname
$conn_username=''; // mysql username
$conn_password=''; //mysql password
$conn_database=''; // mysql database
$from_email=''; // email address to use for notifications and reports. It should be the same email address that pipes to tasks_me.php so user can simply reply to the emails to enter their tasks.
$main_reminder_subject='What did you do today?'; //email subject for the daily reminder.
$group_name="Example Group Name"; // name of the group. Used in email
$html2text_loc='html2text/'; // The location of html2text.php
date_default_timezone_set('America/Los_Angeles'); //Sets the timezone used for scheduling reports
$time_to_act=15; // Emails are to be sent once when the script is run within $time_to_act minutes past the designated time. Default value is 15
				//The cron job should be run at a smaller interval than this value

$cool_down_time=5; // Timestamps for the previous email are deleted after the $cool_down_time minutes after the timestamp. This is to prevent accidental spam
?>