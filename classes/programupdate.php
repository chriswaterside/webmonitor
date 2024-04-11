<?php

/*
 * See if a program update is available
 */

class Programupdate {

    const UPDATESITE = 'https://cache.ramblers-webs.org.uk/downloadupdate.php?key=a63fj*jhf';
    const NEWUPDATESITE="https://raw.githubusercontent.com/chriswaterside/Joomla-Update-Server/main/webmonitor.json";
    const INSTALLFILE = 'classes/monitorinstall.php';

    private $error = true;
    private $errormsg = "Could not access update site";
    private $version = '';
    private $installprogram = '';
    private $webmonitorzip = '';
    private $remoteservertime;
    private $localservertime;

    function __construct() {
        $details = null;
        ini_set('default_socket_timeout', 30);
        $latestupdate = file_get_contents(self::UPDATESITE);
        if ($latestupdate !== false) {
            $details = json_decode($latestupdate);
        }
        If ($details != NULL) {
            $this->version = $details->version;
            $this->installprogram = $details->installprogram;
            $this->webmonitorzip = $details->webmonitor;
            $this->error = $details->error;
            $this->errormsg = $details->errormsg;
            $zone = new DateTimeZone($details->rundate->timezone);
            $this->remoteservertime = new DateTime($details->rundate->date, $zone);
            $this->localservertime = new DateTime;
        } else {
            $this->remoteservertime = new DateTime;
            $this->localservertime = new DateTime;
        }
    }

    //If ($update->found() == true) {
//    $version = $update->getVersion();
//    $webmonitor = '../' . $version . '.zip';
//    if (file_exists($webmonitor)) {
//        Logfile::writeWhen('Web monitor is up to date');
//    } else {
//} else {
//    $subject = "ERROR WebMonitor: " . $domain . " no update info";
//    $body = "Web Monitor program update information could not be retrieved";
//    $session->sendAdminEmail($subject, $body);
//    Logfile::writeError($body);
//}

    public function found() {
        return !$this->error;
    }

    public function getVersion() {
        return $this->version;
    }

    public function download($install, $webmonitor) {
        if (!$this->error) {
            $this->retrieveFile($install, $this->installprogram);
            $this->retrieveFile($webmonitor, $this->webmonitorzip);
        }
    }

    public function getServerTimeDiff() {
        return $this->remoteservertime->diff($this->localservertime);
    }

    private function retrieveFile($name, $url) {
        $result = file_get_contents($url);
        if ($result === false) {
            // write error
        } else {
            file_put_contents($name, $result);
        }
    }
}
