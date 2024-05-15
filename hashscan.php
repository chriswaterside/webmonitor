<?php

// Code to scan a web site's account for changes in any of the files.
// Author Chris Vaughan chris@cevsystems.co.uk
//                      copyright 2014-2024
//                      https://github.com/chriswaterside/WebMonitor
//
//  User options - all user options are contained in the config.php file

define("VERSION_NUMBER", "6.0.1");
//   version 6.0.1
//          Changes to make system general and not Ramblers-Webs specific
//   Version 5.6.3
//          Do not save account data if key==none
//   Version 5.6.2
//          Echo email issue if not sent
//          PHPMailer 6.7.1
//   Version 5.6.1
//          Correct setting of $key in session class
//   Version 5.6.0
//          Improved structure to hashscan.php
//          Added admin email so most errors don't go to webmaster
//   Version 5.5.0
//          Now uses SMTP to send emails
//   Version 5.4.8
//          Error handling and timeout on program update
//   Version 5.4.7
//          improve error handling!
//   Version 5.4.6
//          Add overall error handling
//   Version 5.4.5
//           Change to better handle errors contecting to database
//           Change to use p: to make connection persistent
//   Version 5.4.4
//           Updated version of PHPMailer 6.1.4
//           Change of addReplyTo for emails to sitestatus to help bounce msgs 
//           Addition of why you are recieving this email
//   Version 5.4.1
//           correction to delete old logfiles
//   Version 5.4.0
//           change to autoload to support PHP 7.2
//   Version 5.3.9
//           change to email titles
//   Version 5.3.8
//           change web monitor log to go in folder
//           keep last 28 days of log files
//           change Install email subject to hep monitoring software
//           look for suspect files
//   Version 5.3.7
//           change Joomla log folders
//           remove monitorOLD folder
//           get update information via https
//   Version 5.3.6
//           move log file into monitor folder
//           save log file using https
//           phpMailer version 6.0.2
//           initialise $key to null to avoid warning
//   Version 5.3.5
//            check if path exists, add JCH Optimze cache folder
//   Version 5.3.4
//            report log and tmp directories
//   Version 5.3.3
//            fix to support Joomla 3.8
//   Version 5.3.2
//            add .user.ini settings 
//   Version 5.3.1
//            Correction to timezone.
//   Version 5.3.0
//            Add Wordpress and Joomla version numbers
//            Add largest files top ten
//  Version 5.2.1
//            Add automatic update of software
//  Version 5.1.1
//	      Remove extra curl call in accounts.php
//  Version 5.1 
//            Email changed if new install
//  Version 5.0 
//            Corrections to work with new hosting platform
//            Change to way json log file is saved centrally


if (version_compare(PHP_VERSION, '8.0.0') < 0) {
    echo 'You MUST be running on PHP version 8.0.0 or higher, running version: ' . \PHP_VERSION . "\n";
    die();
}
// set current directory to current run directory
$exepath = dirname(__FILE__);
define('BASE_PATH', dirname(realpath(dirname(__FILE__))));
chdir($exepath);
require('classes/autoload.php');
spl_autoload_register('autoload');

require 'classes/PHPMailer-6.7.1/src/PHPMailer.php';
require 'classes/PHPMailer-6.7.1/src/SMTP.php';
require 'classes/PHPMailer-6.7.1/src/Exception.php';
$session = new Session();
$domain = $session->domain();
// set to the user defined error handler
$old_error_handler = set_error_handler("Functions::errorHandler", E_ALL);
register_shutdown_function("Functions::fatalHandler");
Timeout::setTime();
// first check if program update and if so install program
$update = new Programupdate($session);
if ($update->required()) {
    $installer = new Monitorinstall($session, $update);
    $installer->install();
    logfile::close();
    exit();
} 
$appStatus = new Isapprunning();
$alreadyRunning = false;
switch ($appStatus->status()) {
    case Isapprunning::NotRunning:
        break;
    case Isapprunning::WasRunning:
        $period = $appStatus->timePeriod();
        $periodMin = Functions::getTotalInterval($period, "minutes");
        if ($periodMin < 10) { // 10 minutes
            // too close to last run about
            $subject = "ERROR WebMonitor: " . $domain . " Run too soon";
            $body = "Application execution is too soon after previous run";
            $body = $body . "<br/>Time since last run: " . Functions::formatDateDiff($period);
            $session->sendAdminEmail($subject, $body);
            Logfile::writeError($body);
            die($body);
        } else {
            $subject = "ERROR WebMonitor: " . $domain . " Last run failed to complete";
            $body = "Last run of Web Monitor application did not complete properly";
            $body = $body . "<br/>Time since last run: " . Functions::formatDateDiff($period);
            $session->sendAdminEmail($subject, $body);
            Logfile::writeError($body);
            $alreadyRunning = true;
        }
        break;
    case Isapprunning::GaveError:
        $body = $appStatus->ErrorText();
        $subject = "ERROR WebMonitor: " . $domain . " Unable to set up running status";
        $session->sendAdminEmail($subject, $body);
        Logfile::writeError($body);
        die($body);
        break;
}

Logfile::resetNoErrrors();
//
//  Now run normal scan 
//

$scan = new Scan($session);
$newdatabase = false;

$result = $scan->Connect();
if ($result == Scan::NOT_CONNECTED) {
    // retry one time if not connected
    Logfile::writeError("Unable to connect to database while running hashscan.php for this domain");
    sleep(60);
    Logfile::writeWhen("Attempting to connect to database a second time");
    $result = $scan->Connect();
}
if ($result <> Scan::NOT_CONNECTED) {
    $send = scan::EMAIL_NORMAL;
    if ($result == scan::CONNECTEDNEWTABLES) {
        $send = scan::EMAIL_NEWDATABASE;
    }
    // check to see if last scan completed correctly
    if ($alreadyRunning) {
        //  $scan->emailResults($session, scan::EMAIL_RUNNING);
        //  $scan->deleteOldTestedRecords();
        Logfile::writeWhen("***************************************************");
        Logfile::writeWhen("Last run did not complete");
        Logfile::writeWhen("Starting normal scan");
        $send = scan::EMAIL_SEND; // force normal scan to send email after above error
        Logfile::resetNoErrrors();
        $scan->restart();
    }

    $scan->scanFiles($session);
    $scan->getSuspectFiles();
    $scan->emailResults($session, $send);
    $scan->deleteOldTestedRecords();

    // Lastly create a report of basic status of account
    $account = $session->getAccount();
    $account->StoreStatus($scan);
    Functions::deleteFolder(BASE_PATH . "/monitorOLD");
} else {
    $status = $scan->getStatus();
    $subject = "ERROR WebMonitor: " . $domain . " Unable to connect to database";
    $body = "Unable to connect to database while running hashscan.php for this domain";
    $body .= $status;
    $session->sendAdminEmail($subject, $body);
    Logfile::writeError($body);
    echo $body;
}

$appStatus->close(); // delete "is running file",  only delete file if task completes
Logfile::writeWhen("Closing Logfile");
Logfile::close();
echo "\n<p>Hashscan completed successfully</p>";
