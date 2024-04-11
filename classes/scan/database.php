<?php

/**
 * Description of scandatabase
 *
 * @author Chris Vaughan
 */
class ScanDatabase extends Database {

    const STATE_OK = 0;
    const STATE_RUNNING = 1;
    const STATE_NEW = 2;
    const STATE_CHANGED = 3;
    const STATE_DELETED = 4;

    private $total = 0;
    private $new = 0;
    private $changed = 0;
    private $deleted = 0;
    private $calcTotals = true;
    private $totalSize = 0;
    private $calc_hash = 0;

    public function restart() {
        $this->total = 0;
        $this->new = 0;
        $this->changed = 0;
        $this->deleted = 0;
        $this->calcTotals = true;
        $this->totalSize = 0;
        $this->calc_hash = 0;
    }

    public function checkTables() {
        $newdatabase = false;
        if (!parent::tableExists('baseline')) {
            $newdatabase = true;
            $text = "CREATE TABLE IF NOT EXISTS `baseline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filepath` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `filesize` int(11),
  `filedate` datetime NOT NULL,
  `filehash` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `state` tinyint(4) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_checked` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Filepath` (`filepath`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
            $ok = parent::runQuery($text);
            if ($ok) {
                Logfile::writeWhen("Table 'baseline' created");
            } else {
                Logfile::writeError("Table creation 'baseline' FAILED");
            }
        }
        if (!parent::tableExists('tested')) {
            $newdatabase = true;
            $text = "CREATE TABLE `tested` (
  `tested` datetime NOT NULL,
  `total` int(11),
  `new` int(11),
  `changed` int(11),
  `deleted` int(11),
  `emailsent` tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
            $ok = parent::runQuery($text);
            if ($ok) {
                Logfile::writeWhen("Table 'tested' created");
            } else {
                Logfile::writeError("Table creation 'tested' FAILED");
            }
        }
        return $newdatabase;
    }

    public function getLargestFiles() {
        $ok = parent::runQuery("SELECT filepath,filesize FROM baseline ORDER BY filesize DESC LIMIT 10");
        if ($ok) {
            $result = parent::getResult();
            $files = array();
            while ($row = $result->fetch_row()) {
                $file = $row[0];
                $size = $row[1];
                $files[$file] = $size;
            }
            return $files;
        } else {
            Logfile::writeError('Unable to retrieve largest files (' . parent::error());
        }
        return Null;
    }

    public function getTotalSizeScanned() {
        return $this->totalSize;
    }

//-------------------------------------------------------------------------------
// Process one file
// Returns 0 if no errors, 1 for a file error, or 2 for a database error
//

    public function process_file($progresstext, $basepath, $filepath) {
        $file = $basepath . DIRECTORY_SEPARATOR . $filepath;
        //$utf8_filepath = utf8_encode($file);
        if (!is_readable($file)) {
            if ($filepath <> "logs") {
                Logfile::writeError($progresstext . "Unreadable file: " . $file);
            } else {
                Logfile::writeWhen($progresstext . "Ignored as not readable: " . $file);
            }
            return 1;
        }
        // $hash = hash_file("md5", $file);
        $filesize = filesize($file);
        $filedate = date("Y-m-d H:i:s", filemtime($file));
        $this->totalSize += $filesize;
        $hash = "";
        // check if $filename is longer than 255
        if (strlen($filepath) >= 255) {
            Logfile::writeError($progresstext . "Filename is longer than 255 chars");
        }
        $query = "Select filehash, filesize, filedate, state From baseline Where filepath = '" . $this->escapeString($filepath) . "'";
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::writeError($progresstext . "Select from baseline failed for file: " . $filepath);
            return 2;
        }

        // if the file is not registered in the database, insert it in state NEW
        $res = parent::getResult();
        $norows = $res->num_rows;
        if ($norows == 0) {
            $hash = $this->calc_hash($file);
            $query = "Insert into baseline (filepath, filehash, filesize, filedate, state,  date_added, date_checked)
			values ('" . $this->escapeString($filepath) . "', '$hash', '$filesize', '$filedate', " . self ::STATE_NEW . ", NOW(),NOW())";
            $ok = parent::runQuery($query);
            if (!$ok) {
                Logfile::writeError($progresstext . "Unable to add NEW entry for " . $filepath);
                return 2;
            }
            Logfile::writeWhen($progresstext . "NEW: " . $filepath);
            return 0; // we're done for this file
        }

        // here when the file is in the database
        // if it already has a problem flagged, leave it alone
        $datarow = $res->fetch_array();
        if ($datarow["state"] != self::STATE_RUNNING) {
            $out = self::stateText($datarow["state"]);
            Logfile::writeError($progresstext . "File already has state set[" . $out . "]: " . $filepath);
            return 1;
        }

        // if the filesize and date match, set the state to OK
        if ($filesize == $datarow["filesize"] AND $filedate == $datarow["filedate"]) {
            $hash = $datarow["filehash"];
            if ($hash == "") {
                $hash = $this->calc_Hash($file);
            }
            $status = self::STATE_OK;
        } else {
            // only the date has changed
            $status = self::STATE_CHANGED;
            if ($filesize == $datarow["filesize"] AND $filedate <> $datarow["filedate"]) {
                $hash = $this->getHash($file);
                if ($datarow["filehash"] == $hash) {
                    $status = self::STATE_OK;
                }
            }
        }
        $query = "UPDATE `baseline` SET `state`=" . $status . ",`filehash` = '" . $hash . "',`filesize` = '" . $filesize . "',`filedate` = '" . $filedate . "', date_checked = NOW() WHERE `filepath` = '" . $this->escapeString($filepath) . "'";
        $ok = parent::runQuery($query);

        switch ($status) {
            case self::STATE_OK:
                if (!$ok) {
                    Logfile::writeError($progresstext . "Unable to reset entry, to OK, for " . $filepath);
                    return 2;
                }
                Logfile::writeWhen($progresstext . "OK: " . $filepath);
                return 0; // we're done for this file

            case self::STATE_CHANGED:
                if (!$ok) {
                    Logfile::writeError($progresstext . "Unable to add CHANGED entry for " . $filepath);
                    return 2;
                }
                Logfile::writeWhen($progresstext . "CHANGED: " . $filepath);
                return 0;

            default:
                Logfile::writeError($progresstext . "Unable to identify if item changed " . $filepath);
                break;
        }
    }

    public function setRunning() {
        $query = "Update baseline Set state = " . self::STATE_RUNNING;
        $ok = parent::runQuery($query);
        if (!$ok) {
            return false;
        }
        return true;
    }

    public function setDeleted() {
        $query = "Select filepath From baseline Where state = '" . self::STATE_RUNNING . "'";
        $ok = parent::runQuery($query);
        if ($ok) {
// Cycle through results
            $result = parent::getResult();
            while ($obj = mysqli_fetch_object($result)) {
                Logfile::writeWhen("DELETED: " . $obj->filepath);
            }
            /* free result set */
            mysqli_free_result($result);
        } else {
            Logfile::writeWhen("EREOR getting deleted file name: " . parent::error());
        }
        $query = "Update baseline Set state = '" . self::STATE_DELETED . "' Where state = '" . self::STATE_RUNNING . "'";
        $ok = parent::runQuery($query);
        if (!$ok) {
            Logfile::writeError("Unable to set Deleted state");
            return false;
        }
        return true;
    }

    public function removeDeleted() {
        if ($this->deleted > 0) {
            $query = "DELETE FROM baseline WHERE state=" . self::STATE_DELETED;
            $ok = parent::runQuery($query);
            if (!$ok) {
                Logfile::writeError("Unable to remove Deleted records");
                return false;
            }
            Logfile::writeWhen("Deleted records removed");
        }
        return true;
    }

    public function listFilesInState($state, $no) {
        $text = "";
        if ($no > 1000) {
            $text .= "<p>First 1000 files displayed</p>";
        }
        $ok = parent::runQuery("SELECT filepath FROM baseline WHERE state='" . $state . "' LIMIT 1000");
        if ($ok) {
            $result = parent::getResult();
            while ($row = $result->fetch_row()) {
                $text .= "    <li>" . $row[0] . "</li>" . PHP_EOL;
            }

            /* free result set */
            $result->close();
        }
        return $text;
    }

    public function recordTestDate($mailed) {
        $this->total = $this->getTotals();
        $today = new DateTime("now");
        $when = $today->format('Y-m-d H:i:s');
        $ok = parent::runQuery("INSERT INTO `tested` (`tested`, `emailsent`"
                        . ",`total`,`new`,`changed`,`deleted`) VALUES ('$when', '$mailed',"
                        . "'$this->total','$this->new','$this->changed','$this->deleted')");

        if ($ok) {
            Logfile::writeWhen("Tested table updated");
        } else {
            Logfile::writeError("Unable to update tested date");
        }
    }

    public function summaryReport() {
        $text = $this->displayErrors();

        if ($this->totalSize > 0) {
            $text .= "<p>Total size of files: " . number_format($this->totalSize) . " bytes</p>" . PHP_EOL;
        }
        $text .= "<p> </p>" . PHP_EOL;
        If ($this->total === 0) {
            $text .= "<p>File structure has NOT changed.</p>" . PHP_EOL;
        } else {
            $text .= "<p>File structure has changed:-</p><ul>" . PHP_EOL;
            $text .= "<li>     NEW files:" . $this->new . "</li>" . PHP_EOL;
            $text .= "<li>     CHANGED files:" . $this->changed . "</li>" . PHP_EOL;
            $text .= "<li>     DELETED files:" . $this->deleted . "</li></ul>" . PHP_EOL;
            $text .= "<p> </p>" . PHP_EOL;
            $text .= "<p>PLEASE review the changes, if you expected these changes then ignore this email</p>" . PHP_EOL;
            $text .= "<p>This email should also go to Administrators who may also review the changes</p>" . PHP_EOL;
            $text .= "<p>If you have any concerns that your site has been hacked then please raise a Support Issue</p>" . PHP_EOL;
            $text .= "<p> </p>" . PHP_EOL;
        }
        return $text;
    }

    private function displayErrors() {
        $text = "";
        if (Logfile::getNoErrors() > 0) {
            if (Logfile::getNoErrors() > 10) {
                $text .= "<h2>ERROR</h2><p>More than 10 errors occurred during the scanning process, scanning was terminated, first 10 errors from logfile are displayed below.</p>";
            } else {
                $text .= "<h2>WARNING</h2><p>Errors occurred during the scanning process, errors from logfile are displayed below.</p>";
            }
            $errors = Logfile::getErrors();
            $text .= "<ol>";
            foreach ($errors as $error) {
                $text .= "<li>" . $error . "</li>";
            }

            $text .= "</ol>";
        }
        return $text;
    }

    public function changedFiles() {
        $text = "";
        if ($this->new > 0) {
            $text .= "<p>     NEW files</p>" . PHP_EOL;
            $text .= "<ul>" . PHP_EOL;
            $text .= $this->listFilesInState(self::STATE_NEW, $this->new);
            $text .= "</ul>" . PHP_EOL;
        }
        if ($this->changed > 0) {
            $text .= "<p>     CHANGED files</p>" . PHP_EOL;
            $text .= "<ul>" . PHP_EOL;
            $text .= $this->listFilesInState(self::STATE_CHANGED, $this->changed);
            $text .= "</ul>" . PHP_EOL;
        }
        if ($this->deleted > 0) {
            $text .= "<p>     DELETED files</p>" . PHP_EOL;
            $text .= "<ul>" . PHP_EOL;
            $text .= $this->listFilesInState(self::STATE_DELETED, $this->deleted);
            $text .= "</ul>" . PHP_EOL;
        }
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<p> </p>" . PHP_EOL;
        $text .= "<hr/><p><small>PHP version: " . PHP_VERSION . "  Web Monitor version: " . VERSION_NUMBER . "</small></p>" . PHP_EOL;

        return $text;
    }

    public function deleteOldTestedRecords() {
        // delete records older then a year
        $today = new DateTime();
        $date = $today;
        $date->sub(new DateInterval('P365D'));
        $formatdate = $date->format('Y-m-d');
        $ok = parent::runQuery("DELETE FROM tested WHERE tested < '$formatdate'");
        if (!$ok) {
            Logfile::writeError('Unable to delete old records in tested table(' . parent::error());
        }
    }

    public function getLastRunDate() {
        $tested = "Never";
        $ok = parent::runQuery("SELECT tested FROM tested ORDER BY tested DESC LIMIT 1");
        if ($ok) {
            $result = parent::getResult();
            while ($row = $result->fetch_row()) {
                $tested = $row[0];
            }
        } else {
            Logfile::writeError('Unable to retrieve last test date(' . parent::error());
        }
        Logfile::writeWhen("Last scan date " . $tested);
        return $tested;
    }

    public function getLastEmailSentRunDate() {
        $lastemailsent = 'Never';
        $ok = parent::runQuery("SELECT tested FROM tested WHERE emailsent=true ORDER BY tested DESC LIMIT 1");
        if ($ok) {
            $result = parent::getResult();
            while ($row = $result->fetch_row()) {
                $lastemailsent = $row[0];
            }
        }
        return $lastemailsent;
    }

    public function getLatestFile() {
        $tested = "";
        $ok = parent::runQuery("SELECT filedate,id FROM baseline WHERE filepath LIKE 'public_html%' ORDER BY filedate DESC LIMIT 1 ");
        if ($ok) {
            $result = parent::getResult();
            $row = $result->fetch_row();
            if (!$row == null) {
                $tested = $row[0];
            }
        } else {
            Logfile::writeError('Unable to retrieve latest file date(' . parent::error());
        }
        return $tested;
    }

    public function getSuspectFiles() {
        $tested = "";
        $ok = parent::runQuery("SELECT filepath FROM baseline WHERE filepath LIKE '%media%cms%log.php'");
        if ($ok) {
            $result = parent::getResult();
            while ($row = $result->fetch_row()) {
                Logfile::writeError("Suspect file: " . $row[0]);
            }
        } else {
            Logfile::writeError('Unable to retrieve latest file date(' . parent::error());
        }
    }

    public function GetStateCount($state) {
        $ok = parent::runQuery("SELECT COUNT(*) FROM `baseline` WHERE state=" . $state);
        $result = parent::getResult();
        $row = $result->fetch_row();
        return intval($row[0]);
    }

    public function getTotals() {
        if ($this->calcTotals) {
            $this->new = $this->GetStateCount(self::STATE_NEW);
            $this->changed = $this->GetStateCount(self::STATE_CHANGED);
            $this->deleted = $this->GetStateCount(self::STATE_DELETED);
            $this->total = $this->new + $this->changed + $this->deleted;
            $this->calcTotals = false;
        }
        return $this->total;
    }

    private function calc_Hash($file) {
        if ($this->calc_hash < 1000) {
            $hash = $this->getHash($file);
            $this->calc_hash += 1;
            if (!$hash) {
                Logfile::writeError($progresstext . "Unable to calculate hash for: $file");
                return "";
            }
            return $hash;
        }
        return "";
    }

    private function getHash($file) {
        $filesize = filesize($file);
        if ($filesize <= 10000000) {
            return hash_file("md5", $file);
        }
        return "large file";
    }

    static function stateText($state) {
        switch ($state) {
            case self::STATE_OK:
                return "OK";
            case self::STATE_RUNNING:
                return "RUNNING";
            case self::STATE_NEW:
                return "NEW";
            case self::STATE_CHANGED:
                return "CHANGED";
            case self::STATE_DELETED:
                return "DELETED";
            default:
                return "Unknown";
        }
    }

}
