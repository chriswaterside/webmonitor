<?php

error_reporting(E_ALL);

// Code to scan a web site's account for changes in any of the files.
// Author Chris Vaughan chris@cevsystems.co.uk
//                      copyright 2014-2023
//
//  User options - all user options are contained in the config.php file
//  If you wish to store the account json file in a central location then you need a url that will accept and store the file
//    This URL is stored in an additional config file called config.centralaccounts.php 

define("VERSION_NUMBER", "6.0.0Dev");
//   version 6.0.0Dev
//          Changes to make system general and not Ramblers-Webs specific
//          addition of config.centralaccounts.php
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
//            Add timedifference to ramblers-webs
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
//  version 4.2
//            Convert email(s) to array to support domains prior to transfer
//  version 4.1
//            remove message from scan.php
//  version 4.0
//            changed to use PHPMailer
//  version 3.9
//            ignore logs folder is unreadable
//  version 3.8
//            correction to PHP version checks
//  version 3.7
//            correction to json log to get correct path
//  version 3.6
//            after 10 errors code now skips remaining files
//            rework directory scanning
//  version 3.5
//            change emails to list first few errors encountered
//            format the number of bytes scanned
//  version 3.4
//            ignore com_akeeba/backup folder
//            do not hash files larger than 10Mb
//  version 3.3
//            change to report format 1.03
//  version 3.2
//            change to support second server copying log file to RW
//  version 3.1
//            additional items in json log file, format 1.02
//  version 3.01
//            correction to IP address
//  version 3.00
//            copy json log file to ramblers-webs.org.uk
//  version 2.17
//            use hash but only if date changes a not size
//            new database format
//  version 2.16
//            adjust email headers and titles
//  version 2.15
//            add listing of joomla backup files in the account status file
//            make sure emails all come from admin@
//            change md5 hash to file size and modification date
//  version 2.14
//            correction to table to allow filename to be case sensitive
//            reduce number of changes listed in emails to 1000
//            add creation of account status file
//  version 2.13
//            corrections when removing logfiles
//  version 2.12
//            addition of multiple log files
//            number files in log file
//            set time limit to 0
//            check error code from database change
//            use Logfile::writeError for all database errors
//  version 2.11
//             add j01,j02,j03 to ignore list in sample config
//  version 2.10/2.09
//             check to make sure $path ends in a /
//             add email content for application still running
//  version 2.08
//             minor corrections
//  version 2.07
//             addition of email if too close to previous run of program
//             recode of isapprunning class
//  version 2.06
//             add $skipExtensions option
//             now ignores files with extension .nfsxxxxxx which linux can produce
//  version 2.05
//             correction to ignore virtual directories
//  version 2.04
//             add php to email title
//             change config sample to have / at end of $path
//  version 2.03
//             change of order to statements when closing application
//  version 2.02
//             recode directory iteration to make it quicker
//  version 2.01
//             check if already running, check filename length, bug fixes
//  version 2.00 
//             change to coding to be use classes and wild cards corrently
//  version 1.04
//             Addition of use of wild char * in excluded folders
//  version 1.03
//              Remove second error reporting code
//              Add running time of 60 seconds
//  version 1.02
//              Correction to error email
//  version 1.01
//              Change to add in Joomla subfolders that should be ignored
//  version 1.00
//              Change to email title to make it easier to recognise , 
//              if you have a few emails you will be able to sort them by domain
// version 0.99
//              change to how extensions are displayed to reduce length of output email
//              change to how folders are specified in config file with / rather than \\
//              change to skipFolders so that folders with simialar names are handled correctly
// version 0.98
//              check that code is running on php version 5.3 or higher
// version 0.97
//              correction to record test date, no errors whether email sent of not
// version 0.96
//              added email in the case of a serious error
// version 0.95
//              change to check upper and lower case file extensions
// version 0.94
//              Change files processed msgs to echo
// version 0.93
//              Add version number to email title
// version 0.92
//              fix to only delete from tested table entries over a year old
// version 0.91
//              fix to get the email anyway option working correctly
// version 0.9 - 23 June 2014
//              First release
// 	initialize

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
$update = new Programupdate();
//$installfile = 'classes/monitorinstall.php';
if ($update->required()){
    

//If ($update->found() == true) {
//    $version = $update->getVersion();
//    $webmonitor = '../' . $version . '.zip';
//    if (file_exists($webmonitor)) {
//        Logfile::writeWhen('Web monitor is up to date');
//    } else {
        $update->download($installfile, $webmonitor);
        // See if install script update is present and if so update software
        $installer = new Monitorinstall();
        $ok = $installer->update($webmonitor, $version);
        if ($ok === Monitorinstall::UPDATE_OK) {
            $subject = "WebMonitor: " . $domain . " software updated";
            $body = "Web monitor software has been updated to version: " . $version;
            $mailed = $session->sendStatusEmail($subject, $body);
            if (!$mailed) {
                Logfile::writeWhen("ERROR: Unable to send software update email");
            }
        } else {
            $subject = "ERROR: WebMonitor " . $domain . " software update failed";
            $body = "Web monitor ERROR software update failed version(" . $version . ")";
            $mailed = $session->sendAdminEmail($subject, $body);
            if (!$mailed) {
                Logfile::writeWhen("ERROR: Unable to send update failed email");
            }
        }
        logfile::close();
        exit();
    }
//} else {
//    $subject = "ERROR WebMonitor: " . $domain . " no update info";
//    $body = "Web Monitor program update information could not be retrieved";
//    $session->sendAdminEmail($subject, $body);
//    Logfile::writeError($body);
//}

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

$scan = new Scan($session->getDBconfig(), $domain);
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
    $account = new Account($session, $scan);
    $account->setLargestFiles($scan->getLargestFiles());
    $account->setTimeDiff($update->getServerTimeDiff());
    $account->StoreStatus();
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
