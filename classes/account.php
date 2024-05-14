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
    private $organisation;
    private $supportEmail;
    private $storeKey = null;
    private $storeUrl = null;

    const STATUS_FILE = 'webmonitor_status_domain.json.log';

    // const CENTRAL_STORE_URL = 'https://cache.ramblers-webs.org.uk/storejson.php';

    public function __construct($session, $org, $email, $url, $key) {
        $this->domain = $session->domain();
        $this->path = $session->path();
        $this->organisation = $org;
        $this->supportEmail = $email;
        $this->storeUrl = $url;
        $this->storeKey = $key;
        $this->site = new AccountReport($this->domain, $this->path);
    }

    public function emailHeader() {
        $header = "";
        if ($this->organisation !== null) {
            $org = "<b>" . $this->organisation . " </b>";
        } else {
            $org = "";
        }
        $header .= "<ul>";
        $header .= "<li><small>You are receiving this email because we believe you are one of the web masters for this domain.</small></li>" . PHP_EOL;
        if ($this->supportEmail !== null) {
            $header .= "<li><small>If this is not correct or you are changing your email address or a new web master is taking over, then please raise a support ticket/email</small></li>" . PHP_EOL;
            $header .= "<li><small>" . $org . "Support email: <a href='mailto:" . $this->supportEmail . "?Subject=" . $this->domain . "' target='_blank'>" . $this->supportEmail . "</a></small></li><hr/>" . PHP_EOL;
        }
        $header .= "</ul>";
        $header .= "<p>Report of changes to domain <b>" . $this->domain . "</b></p>" . PHP_EOL;
        return $header;
    }

    public function StoreStatus($scan) {
        if ($scan != null) {
            $this->site->nofilesscanned = $scan->getNoFilesScanned();
            $this->site->totalsizescanned = $scan->getTotalSizeScanned();
            $this->site->latestfile = $scan->getLatestFile();
            $this->site->largestfiles = $scan->getLargestFiles();
        }

        $json = json_encode($this->site);
        $ok = file_put_contents($this->path . DIRECTORY_SEPARATOR . self::STATUS_FILE, $json);
        if ($ok === false) {
            Logfile::writeError("Unable to write local json log file: " . self::STATUS_FILE);
        } else {
            Logfile::writeWhen("Json log file written: " . self::STATUS_FILE);
        }

        if ($this->storeUrl === null) {
            return;
        }
        if ($this->storeKey === null) {
            return;
        }

        $url = $this->storeUrl;
        $data = [];
        $data['domain'] = $this->domain;
        $data['key'] = $this->storeKey;
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
        curl_close($curl);
        if ($status != 200) {
            $msg = "Error: call to URL $url failed with status $status, response $json_response, curl_error: " . curl_error($curl) . ", curl_errno: " . curl_errno($curl);
            Logfile::writeError($msg);
            echo $msg;
        } else {
            $msg = "Log file copied to central server";
            Logfile::writeWhen($msg);
            echo $msg;
        }
    }
}
