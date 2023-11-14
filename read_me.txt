Introduction: 
This project contains two main components.

tasks_me.php: A program to process emails sent by registered user and record the content as tasks. The program presumes 1 tasks per line in the email.
A email forwarder must be setup to pipe to it.

task_reports.php: A program meant to run as a cron script, to sort the recorded tasks by time and user, and sends out a report to all appropriate users at the specified time of day. It also sends out reminders and extra reminders each day at specified time.
The assumption to not send out reports is currently hardcoded into the program.

Third party requirement: 
PHP 5.6+
Included a copy of html2text from https://github.com/soundasleep/html2text per MIT License.

Installation steps.

1. tasks_me.php needs to be set to 0755 permission so it's executable.
2. The top of tasks_me.php needs to point to the correct php path
3. tasks_me.php must have Unix EOL or the piping will not work
4. html2text must be installed, and its path specified in db_config.php. A copy is included since it is MIT License
5. db_config.php must be filled in with mysql user credential with all privileges.
6. Run setup.php.
7. Copy the tasks_admin folder to a web accessible location. It is a dashboard for modifying settings and add users.
8. The db_config.php file in the admin folder must correctly include the real db_config.php in the same folder as setup.php 
9. Access the dashboard in /tasks_admin/index.php. Register users and configure the report schedule.
User types
Normal: Gets regular, extra reminder, and reports. Can input complete tasks.
Super: Like Normal user, but does not get extra reminder.
Script: Is an automated emailing script. Can input complete task, but does not receive any email.

10. Add task_reports to cron. 5 minutes intervals recommended.

