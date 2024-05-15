<?php

/*
 * See if a program update is available
 */

class Programupdate {

    const UPDATESITE = "https://raw.githubusercontent.com/chriswaterside/WebMonitorUpdateService/main/webmonitor.json";
 
    private $version = '';
    private $webmonitorzip = '';

    function __construct($session) {
        $updateInformation = null;
        ini_set('default_socket_timeout', 30);
        $updateInfoString = file_get_contents(self::UPDATESITE);
        if ($updateInfoString !== false) {
            $updateInformation = json_decode($updateInfoString);
            if ($updateInformation !== NULL) {
                $this->version = $updateInformation->version;
                $this->webmonitorzip = $updateInformation->programzip;
                Logfile::writeWhen("Program update information retrieved");
            } else {
                $subject = "ERROR WebMonitor: " . $session->domain . " JSON Error reading program update information";
                $body = "Web Monitor program update information was invalid<br/>" . $updateInfoString;
                $this->sendAdminEmail($subject, $body);
                Logfile::writeError($body);
            }
        } else {
            $subject = "ERROR WebMonitor: " . $session->domain . " no update info";
            $body = "Web Monitor program update information could not be retrieved";
            $this->sendAdminEmail($subject, $body);
            Logfile::writeError($body);
        }
    }

    public function getVersion() {
        return $this->version;
    }

    public function getZipName() {
        return $this->webmonitorzip;
    }

    public function required() {
        $needed = version_compare($this->version, VERSION_NUMBER, ">");
        if ($needed) {
            Logfile::writeWhen("Web monitor software: new version needs to be installed (v" . $this->version . ")");
        } else {
            Logfile::writeWhen("Web monitor software is up to date (" . $this->version . ")");
        }
        return $needed;
    }
}
