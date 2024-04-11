<?php

/**
 * Description of accounts
 *
 * @author Chris Vaughan
 */
class Account {

    private $site;
    private $path;
    private $domain;
    private $centralStoreKey = null;
    private $centralStoreUrl = null;

    const STATUS_FILE_OLD = 'webstatus.ramblers.webs.json.log';
    const STATUS_FILE = 'webmonitor_status_domain.json.log';

    // const CENTRAL_STORE_URL = 'https://cache.ramblers-webs.org.uk/storejson.php';

    public function __construct($session, $scan) {
        $this->domain = $session->domain();
        $this->path = $session->path();
        $this->site = new AccountReport($this->domain, $this->path);
        if ($scan != null) {
            $this->site->nofilesscanned = $scan->getNoFilesScanned();
            $this->site->totalsizescanned = $scan->getTotalSizeScanned();
            $this->site->latestfile = $scan->getLatestFile();
        }
        $this->centralStoreKey = $session->centralStoreKey();
        $this->centralStoreUrl = $session->centralStoreUrl();
    }

    public function setTimeDiff($value) {
        $this->site->timediff = $value;
    }

    public function setLargestFiles($value) {
        $this->site->largestfiles = $value;
    }

    public function StoreStatus() {
        // delete old file location
        if (file_exists($this->path . DIRECTORY_SEPARATOR . self::STATUS_FILE_OLD)) {
            unlink($this->path . DIRECTORY_SEPARATOR . self::STATUS_FILE_OLD);
        }
        $json = json_encode($this->site);
        //Logfile::write($json);
        $ok = file_put_contents($this->path . DIRECTORY_SEPARATOR . self::STATUS_FILE, $json);
        if ($ok === false) {
            Logfile::writeError("Unable to write local json log file: " . self::STATUS_FILE);
        } else {
            Logfile::writeWhen("Json log file written: " . self::STATUS_FILE);
        }

        if (!isset($this->centralStoreKey)) {
            return;
        }
        if ($this->centralStoreKey == null) {
            return;
        }
        if ($this->centralStoreKey == 'none') {
            return;
        }
        if ($this->centralStoreUrl !== null) {
            $url = $this->centralStoreUrl;

            $data = [];
            $data['domain'] = $this->domain;
            $data['key'] = $this->centralStoreKey;
            $data['json'] = $json;

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLINFO_HEADER_OUT, true);

            $json_response = curl_exec($curl);

            // var_dump($json_response);
            // var_dump(curl_getinfo($curl));

            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status != 200) {
                $msg = "Error: call to URL $url failed with status $status, response $json_response, curl_error: " . curl_error($curl) . ", curl_errno: " . curl_errno($curl);
                Logfile::writeError($msg);
                echo $msg;
            } else {
                $msg = "Log file copied to central server";
                Logfile::writeWhen($msg);
                echo $msg;
            }
            curl_close($curl);
        }
    }

}
