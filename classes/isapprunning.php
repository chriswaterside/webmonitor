<?php

/**
 * Description of isapprunning
 *
 * @author Chris Vaughan
 */
class Isapprunning {

    private $testrunningfile = "application_is_running.log";
    private $isalreadyrunning = false;
    private $timediff = null;
    private $errorText = "";
    private $mstatus;

    const NotRunning = 0;
    const WasRunning = 1;
    const GaveError = 2;

    public function __construct() {
        $this->check();
    }

    public function status() {
        return $this->mstatus;
    }

    public function errorText() {
        return $this->errorText;
    }

    public function timePeriod() {
        return $this->timediff;
    }

    private function check() {
        clearstatcache();
        $this->isalreadyrunning = \file_exists($this->testrunningfile);
        if (!$this->isalreadyrunning) {
            $scanningFile = fopen($this->testrunningfile, "w");
            if ($scanningFile === false) {
                $this->errorText = "Unable to create " . $this->isalreadyrunningfile . " file!";
                $this->mstatus = self::GaveError;
                return;
            }
            fclose($scanningFile);
            $this->mstatus = self::NotRunning;
            return;
        } else {
            // filemtime has been known to fail
            $datetext = date("F d Y H:i:s.", filemtime($this->testrunningfile));
            if ($datetext === false) {
                $this->errorText = "Unable to retrieve create date for " . $this->isalreadyrunningfile . " file!";
                $this->mstatus = self::GaveError;
                return;
            }
            $created = DateTime::createFromFormat("F d Y H:i:s.", $datetext);
            $now = new DateTime();
            $this->timediff = $created->diff($now);
            $this->mstatus = self::WasRunning;
        }
    }

    public function close() {
        if (file_exists($this->testrunningfile)) {
            unlink($this->testrunningfile);
        }
        if (file_exists($this->testrunningfile)) {
            Logfile::writeWhen("ERROR: Application file NOT Deleted: " . $this->testrunningfile);
        } else {
            Logfile::writeWhen("File deleted: " . $this->testrunningfile);
        }
    }

}
