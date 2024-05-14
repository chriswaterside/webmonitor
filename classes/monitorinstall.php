<?php

// installed from server

class Monitorinstall {

    const UPDATE_OK = 0;
    const UPDATE_FAILED = 1;

    private $version;
    private $session;
    private $update;

    public function __construct($session, $update) {
        $this->session = $session;
        $this->update = $update;
        $this->deleteFolder(BASE_PATH . "/monitorOLD");
        // delete old zip files
        $this->deleteOldZipFiles();
    }

    public function install() {
        $version = $this->update->getVersion();
        $zipFileName = 'webmonitor-' . $version . '.zip';
        $zipFilePath = '../webmonitor-' . $version . '.zip';
        $zipFile = $this->update->getZipName();
        $result = file_get_contents($zipFile);
        if ($result === false) {
            $this->sendErrorEmail('Unable to download new version of software from ' . $zipFile);
            return self::UPDATE_FAILED;
        } else {
            $bytes = file_put_contents($zipFilePath, $result);
            if ($bytes === false) {
                $this->sendErrorEmail('Unable to save new software as ' . $zipFileName);
                return self::UPDATE_FAILED;
            } else {
                Logfile::writeWhen('New version of software saved as ' . $zipFileName);
            }
        }

        $ok = $this->update($zipFilePath, $version);
        if ($ok === Monitorinstall::UPDATE_OK) {
            $subject = "WebMonitor: " . $this->session->domain() . " software updated";
            $body = "Web monitor software has been updated to version: " . $version;
            $mailed = $this->session->sendStatusEmail($subject, $body);
            if (!$mailed) {
                Logfile::writeWhen("ERROR: Unable to send software update email");
            }
        } else {
            $subject = "ERROR: WebMonitor " . $this->session->domain() . " software update failed";
            $body = "Web monitor ERROR software update failed version(" . $version . ")";
            $mailed = $this->session->sendAdminEmail($subject, $body);
            if (!$mailed) {
                Logfile::writeWhen("ERROR: Unable to send update failed email");
            }
        }
    }

    private function update($zipFilePath, $version) {
        $this->version = $version;

        // unzip new software
        $zip = new ZipArchive;
        $opened = $zip->open($zipFilePath);
        if ($opened === true) {
            $extracted = $zip->extractTo(BASE_PATH);
            $zip->close();
            if ($extracted === true) {
                Logfile::writeWhen('New software extracted from zip file');
            } else {
                $this->sendErrorEmail('FAILED to extract new software from zip file - ' . $zip->getStatusString());
                return self::UPDATE_FAILED;
            }
        } else {
            $this->sendErrorEmail('FAILED to open zip file - ' . $zip->getStatusString());
            return self::UPDATE_FAILED;
        }

        // move config files into new folder
        $this->moveConfigFile("config.php");

        // rename old version
        $ok = rename(BASE_PATH . "/monitor", BASE_PATH . "/monitorOLD");
        if ($ok) {
            Logfile::writeWhen("Renamed old software");
        } else {
            $this->sendErrorEmail("FAILED to rename old software");
            return self::UPDATE_FAILED;
        }
        // rename new software
        $ok = rename(BASE_PATH . "/webmonitor-" . $version, BASE_PATH . "/monitor");
        if ($ok) {
            Logfile::writeWhen("Renamed NEW software");
        } else {
            $this->sendErrorEmail("FAILED to rename NEW software");
            return self::UPDATE_FAILED;
        }

        return self::UPDATE_OK;
    }

    private function deleteOldZipFiles() {
        foreach (glob(BASE_PATH . "/*.zip") as $filename) {
            if (str_contains($filename, "webmonitor")) {
                unlink($filename);
                logfile::writeWhen("Old zip file deleted: " . $filename);
            }
        }
    }

    private function deleteFolder($dir) {
        if (file_exists($dir)) {
            // delete folder and its contents
            foreach (glob($dir . '/*') as $file) {
                if (is_dir($file)) {
                    $this->deleteFolder($file);
                } else {
                    unlink($file);
                }
            } rmdir($dir);
        }
    }

    private function moveConfigFile($filename) {
        $from = BASE_PATH . "/monitor/" . $filename;
        $to = BASE_PATH . "/webmonitor-" . $this->version . "/" . $filename;

        if (!file_exists($from)) {
            Logfile::writeError("Config file(" . $filename . ") does not exist and cannot be moved to new installation");
            return;
        }

        // move config into new folder
        $ok = rename($from, $to);
        if ($ok) {
            Logfile::writeWhen("Moved config file(" . $filename . ") to new directory");
        } else {
            Logfile::writeError("FAILED to move config file(" . $filename . ") to new directory");
            return;
        }
    }

    private function sendErrorEmail($msg) {
        Logfile::writeError($msg);
        $error = error_get_last();
        If ($error !== null) {
            Logfile::writeError("Last error encountered: " . $error['message']);
        }
        $subject = "WebMonitor ERROR: " . $this->session->domain() . " software update error";
        $body = $msg + "<br>" + "Last error encountered: " . $error['message'];
        $mailed = $this->session->sendStatusEmail($subject, $body);
        if (!$mailed) {
            Logfile::writeError("ERROR: Unable to send software update email");
        }
    }
}
