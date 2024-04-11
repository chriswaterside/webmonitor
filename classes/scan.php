<?php

class Scan {

    private $db;
    private $report;
    private $scancode = 0;
    private $nofilesscanned = 0;
    private $totalsizescanned = 0;

    Const EMAIL_NORMAL = 0;
    Const EMAIL_RUNNING = 1;
    Const EMAIL_SEND = 2;
    Const EMAIL_NEWDATABASE = 3;
    Const NOT_CONNECTED = -1;
    Const CONNECTED = 0;
    Const CONNECTEDNEWTABLES = 1;

    //const SQL_DATE_FORMAT = "%Y-%m-%d %H:%M:%S"; // strftime() format

    public function __construct($dbconfig, $domain) {

        $this->db = new ScanDatabase($dbconfig);
        $this->domain = $domain;
        $this->restart();
    }

    public function restart() {
        $this->db->restart();
        $this->report = "";
        $this->report .= "<p><small>You are receiving this email because we believe you are one of the web masters for this domain.</small></p>" . PHP_EOL;
        $this->report .= "<p><small>If this is not correct or you are changing your email address or a new web master is taking over, then please raise a support ticket</small></p>" . PHP_EOL;
        $this->report .= "<p><small><a href='mailto:support@ramblers-websites.zendesk.com?Subject=" . $this->domain . "' target='_blank'>support@ramblers-websites.zendesk.com</a></small></p><hr/>" . PHP_EOL;
        $this->report .= "<p>Report of changes to domain <b>" . $this->domain . "</b></p>" . PHP_EOL;
    }

    public function __destruct() {
        
    }

    public function Connect() {
        // open database
        if ($this->db->connect()) {
            $newdatabase = $this->db->checkTables();
            if ($newdatabase) {
                return self::CONNECTEDNEWTABLES;
            } else {
                return self::CONNECTED;
            }
        } else {
            return self::NOT_CONNECTED;
        }
    }

    public function getStatus() {
        return $this->db->getStatus();
    }

    public function getSuspectFiles() {
        $this->db->getSuspectFiles();
    }

    public function getNoFilesScanned() {
        return $this->nofilesscanned;
    }

    public function getTotalSizeScanned() {
        return $this->totalsizescanned;
    }

    public function getLatestFile() {
        return $this->db->getLatestFile();
    }

    public function getLargestFiles() {
        return $this->db->getLargestFiles();
    }

    public function scanFiles($session) {
        $path = $session->path();
        $skipFolders = $session->skipFolders();
        $processExtensions = $session->processExtensions();
        $skipExtensions = $session->skipExtensions();
        $this->report .= $this->displayOptions($path, $skipFolders, $processExtensions, $skipExtensions);
        $ok = $this->db->setRunning();
        If ($ok == false) {
            $subject = "ERROR WebMonitor: " . $session->domain() . " Running Tags not set";
            $body = "Unable to set running tags for files";
            Logfile::writeError($body);
            $session->sendAdminEmail($subject, $body);
            return;
        }
        $iter = new ScanIterator($path);
        $iter->addExtensions($processExtensions);
        $iter->addSkipExtensions($skipExtensions);
        $iter->addFolders($skipFolders);
        $iter->process($this, "processFile");
        $this->db->setDeleted();
        $this->nofilesscanned = $iter->getNoProcessed();
        $this->totalsizescanned = $this->db->getTotalSizeScanned();
    }

    function processFile($progresstext, $basepath, $filename) {
        $scancode = $this->db->process_file($progresstext, $basepath, $filename);
        if ($scancode > $this->scancode) {
            $this->scancode > $scancode;
        }
    }

    private function displayOptions($path, $skipFolders, $processExtensions, $skipExtensions) {
        $text = "";
        $text .= "<p>Scanning directory - " . $path . " and its sub directories</p>" . PHP_EOL;
        if (!$skipFolders == NULL) {
            $text .= "<p>Excluding the following directories</p><ul>" . PHP_EOL;
            foreach ($skipFolders as $value) {
                $text .= "<li>" . $value . "</li>" . PHP_EOL;
            }
            $text .= "</ul>" . PHP_EOL;
        }
        if (!$processExtensions == NULL) {

            $text .= "<p>File types being scanned</p><ul><li>" . PHP_EOL;
            $first = true;
            foreach ($processExtensions as $value) {
                if (!$first) {
                    $text .= ", ";
                } else {
                    $first = false;
                }
                $text .= $value;
            }
            $text .= "</li></ul>" . PHP_EOL;
        }
        if (!$skipExtensions == NULL) {

            $text .= "<p>File types being ignored</p><ul><li>" . PHP_EOL;
            $first = true;
            foreach ($skipExtensions as $value) {
                if (!$first) {
                    $text .= ", ";
                } else {
                    $first = false;
                }
                $text .= $value;
            }
            $text .= "</li></ul>" . PHP_EOL;
        }
        return $text;
    }

    public function emailResults($session, $sendstatus) {

        $emailinterval = $session->emailinterval();
        $subject = "WebMonitor: " . $this->domain;
        if ($sendstatus == self::EMAIL_RUNNING) {
            $this->report .= "<h2>ERROR</h2><p>Last scan failed to complete, displaying results from last scan</p>";
            $subject = "ERROR: WebMonitor: " . $this->domain;
        }
        if (Logfile::getNoErrors() > 0) {
            $subject = "With ERRORS: WebMonitor: " . $this->domain;
        }
        if ($this->db->getTotals() === 0) {
            $subject .= '  Integrity Report';
        } else {
            $subject .= '  Change Report (' . $this->db->getTotals() . ')';
        }
        if ($sendstatus == self::EMAIL_NEWDATABASE) {
            $this->report .= "<h2>NOTE</h2><p>The Web Monitor on your domain has been updated, the monitor database has also been initialised and hence all files are flagged as new</p>";
            $subject .= " NEW INSTALL: ";
        }
        echo $subject;
        $tested = $this->db->getLastRunDate();
        $this->report .= "<p>Last tested " . $tested . "</p>" . PHP_EOL;
        $lastemailsent = $this->db->getLastEmailSentRunDate();
        $this->report .= "<p>Last email sent $lastemailsent.</p>" . PHP_EOL;
        $this->report .= "<p>Total number of files scanned " . $this->nofilesscanned . "</p>" . PHP_EOL;
        // $this->report.="<p>Will now attempt to rescan this account</p>" . PHP_EOL;
        // $this->report.="<p>You should recieve a second email with the results of this rescan</p>" . PHP_EOL;
//	E-Mail Results
// 	display discrepancies
        $send = $this->shouldSendEmail($lastemailsent, $emailinterval, $sendstatus);

        $this->report .= $this->db->summaryReport();
        Logfile::write($this->report);
        echo $this->report;
        $this->report .= $this->db->changedFiles();
        $mailed = false;
        $sent = 0;
        if ($send) {
            $mailed = $session->sendStatusEmail($subject, $this->report);
            if (!$mailed) {
                Logfile::writeWhen("ERROR: Unable to send report email");
            } else {
                $sent = 1;
            }
        } else {
            Logfile::writeWhen("Email not required");
        }
        $this->db->recordtestDate($sent);
    }

    public function shouldSendEmail($lastemailsent, $emailinterval, $sendstatus) {
        $emailreport = false;
        If ($this->db->getTotals() === 0) {
            $emailreport = $this->sendEmailAnyway($lastemailsent, $emailinterval);
        } else {
            $emailreport = true;
        }
        if (Logfile::getNoErrors() > 0) {
            $emailreport = true;
        }
        if ($sendstatus <> self::EMAIL_NORMAL) {
            $emailreport = true;
        }
        return $emailreport;
    }

    public function sendEmailAnyway($lastemailsent, $emailinterval) {
// return boolean if we last sent email outside interval
        $emailreport = false;
        if ($lastemailsent == 'Never') {
            return true;
        }
        $today = new DateTime();
        $date = new DateTime($lastemailsent);
        $interval = new DateInterval($emailinterval);
        $date->add($interval);
        if ($date < $today) {
            $emailreport = true;
        }
        return $emailreport;
    }

    public function deleteOldTestedRecords() {
        $this->db->removeDeleted();
    }

}
