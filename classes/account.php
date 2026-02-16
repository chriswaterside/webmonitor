<?php

/**
 * Description of accounts
 *
 * @author Chris Vaughan
 */
class Account {

    private $report;
    private $path;
    private $domain;
    private $organisation;
    private $supportEmail;
    private $storeKey = null;
    private $storeUrl = null;

    const STATUS_FILE = 'webmonitor_status_domain.json.log';

    public function __construct($session, $org, $email, $url, $key) {
        $this->domain = $session->domain();
        $this->path = $session->path();
        $this->organisation = $org;
        $this->supportEmail = $email;
        $this->storeUrl = $url;
        $this->storeKey = $key;
        $this->report = new AccountReport($session);
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
        $this->report->setScanValues($scan);
        $json = json_encode($this->report);
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
        $data['json'] = base64_encode($json);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data), // This AUTOMATICALLY sets correct Content-Type
            CURLINFO_HEADER_OUT => true,
            CURLOPT_TIMEOUT => 60, // Total timeout
            CURLOPT_CONNECTTIMEOUT => 30, // Connection timeout  
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Force TLS 1.2
            //CURLOPT_SSL_VERIFYPEER => false, // TEMP for testing
            //CURLOPT_SSL_VERIFYHOST => false, // TEMP for testing
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WebMonitor/1.0)',
        ]);

        $json_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // var_dump($json_response);
        // var_dump(curl_getinfo($curl));

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