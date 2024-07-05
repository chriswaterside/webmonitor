<?php

/**
 * Description of session
 *  this class holds information about the current session being run
 *
 * @author chris
 */
class Session {

    private $smtp = null;
    private $adminEmail = null;
    private $email = [];
    private $domain = null;
    private $host, $database, $user, $password;
    private $path;
    private $skipFolders = [];
    private $skipExtensions = [];
    private $processExtensions = null;
    private $account = null;
    private $emailinterval = null;

    public function __construct() {
        date_default_timezone_set('Europe/London');
        Logfile::create("logfiles/logfile");
        if (file_exists('config.php')) {
            require('config.php');
        } else {
            require('config_master.php');
        }

        if (isset($smtp)) {
            $this->smtp = $smtp;
        }
        if (!isset($adminEmail)) {
            $this->adminEmail = "admin@ramblers-webs.org.uk";
        } else {
            $this->adminEmail = $adminEmail;
        }
        $this->checkConfigValue('Domain', $domain);
        $this->checkConfigValue('Email', $email);
        $this->checkConfigValue('Host', $host);
        $this->checkConfigValue('Database', $database);
        $this->checkConfigValue('User', $user);
        $this->checkConfigValue('Path', $path);
        $this->checkConfigValue('Skip Extensions', $skipExtensions);
        $this->checkConfigValue('Email Interval', $emailinterval);
        $this->domain = trim($domain);
        $this->host = trim($host);
        $this->database = trim($database);
        $this->user = trim($user);
        $this->password = trim($password);
        // check $path, ifit  endswith / then remove /
        $this->path = rtrim($path, "/");
        if (!file_exists($this->path)) {
            Logfile::writeError("Path to be scanned does not exist: " . $this->path);
            die();
            // $this->path = BASE_PATH; // revert to base folder
        }

        if (is_array($email)) {
            foreach ($email as $value) {
                $this->email[] = $value;
            }
        } else {
            $this->email[] = $email;
        }


        if (isset($skipFolders)) {
            if ($skipFolders !== null) {
                $this->skipFolders = $skipFolders;
            }
        }

        if (isset($joomlaFolders)) {
            foreach ($joomlaFolders as $value) {
                array_push($this->skipFolders, $value . "/tmp/");
                array_push($this->skipFolders, $value . "/logs/");
                array_push($this->skipFolders, $value . "/administrator/logs/");
                array_push($this->skipFolders, $value . "/cache/");
                array_push($this->skipFolders, $value . "/administrator/cache/");
                array_push($this->skipFolders, $value . "/plugins/captcha/hydra/imagex/");
                array_push($this->skipFolders, $value . "/administrator/components/com_akeeba/backup/");
            }
        }
        if (isset($wordPressFolders)) {
            foreach ($wordPressFolders as $value) {
                array_push($this->skipFolders, $value . "/tmp/");
                array_push($this->skipFolders, $value . "/logs/");
            }
        }

        if (is_array($skipExtensions)) {
            foreach ($skipExtensions as $value) {
                array_push($this->skipExtensions, $value);
            }
        }
        if (isset($joomlaFolders)) {
            array_push($this->skipExtensions, "log.php");
        }

        $this->emailinterval = $emailinterval;

        if (!isset($organisation)) {
            $organisation = null;
        }
        if (!isset($supportemail)) {
            $supportemail = null;
        }
        if (!isset($central_store_url)) {
            $central_store_url = null;
        }
        if (!isset($central_store_key)) {
            $central_store_key = null;
        }
        $this->account = new Account($this, $organisation, $supportemail, $central_store_url, $central_store_key);
    }

    public function path() {
        return $this->path;
    }

    public function domain() {
        return $this->domain;
    }

    public function getAccount() {
        return $this->account;
    }

    public function emailHeader() {
        return $this->account->emailHeader();
    }

    public function skipFolders() {
        return $this->skipFolders;
    }

    public function processExtensions() {
        return $this->processExtensions;
    }

    public function skipExtensions() {
        return $this->skipExtensions;
    }

    public function emailinterval() {
        return $this->emailinterval;
    }

    public function sendAdminEmail($subject, $body) {
        $mailer = new PHPMailer\PHPMailer\PHPMailer;
        $mailer->addAddress($this->adminEmail, 'Administrator');
        $mailer->Subject = $subject;
        $mailer->msgHTML($body);
        $mailer->isHTML(true);
        if (isset($this->smtp)) {
            $smtp = $this->smtp;
            $mailer->isSMTP();
            $mailer->Host = $smtp->Host;
            $mailer->SMTPAuth = $smtp->SMTPAuth;
            $mailer->Username = $smtp->Username;
            $mailer->Password = $smtp->Password;
            $mailer->Port = $smtp->Port;
            $mailer->setFrom($smtp->FromEmail, $this->domain);
        } else {
            $mailer->setFrom("admin@" . $this->domain, $this->domain);
        }

// only send to administrator for program update and last run not completed errors
        $mailer->addAddress($this->adminEmail, 'Administrator');
        $okay = $mailer->send();
        if ($okay) {
            Logfile::writeWhen('Message has been sent');
        } else {
            Logfile::writeWhen('Message could not be sent.');
            Logfile::writeWhen('Mailer Error: ' . $mailer->ErrorInfo);
        }
        return $okay;
    }

    public function sendStatusEmail($subject, $body) {
        $mailer = new PHPMailer\PHPMailer\PHPMailer;

        $mailer->Subject = $subject;
        $mailer->msgHTML($body);
        $mailer->isHTML(true);
        if (isset($this->smtp)) {
            $smtp = $this->smtp;
            $mailer->isSMTP();
            $mailer->Host = $smtp->Host;
            $mailer->SMTPAuth = $smtp->SMTPAuth;
            $mailer->Username = $smtp->Username;
            $mailer->Password = $smtp->Password;
            $mailer->Port = $smtp->Port;
            $mailer->setFrom($smtp->FromEmail, $this->domain);
        } else {
            $mailer->setFrom("admin@" . $this->domain, $this->domain);
        }
        $mailer->addReplyTo("sitestatus@" . $this->domain, $this->domain);

        foreach ($this->email as $value) {
            $mailer->addAddress($value, 'Web Master');
            Logfile::writeWhen('Email address: ' . $value);
        }

        $okay = $mailer->send();
        if ($okay) {
            Logfile::writeWhen('Message has been sent');
        } else {
            Logfile::writeWhen('Message could not be sent.');
            Logfile::writeWhen('Mailer Error: ' . $mailer->ErrorInfo);
        }
        return $okay;
    }

    public function getDBconfig() {
        $host = $this->host;
        if (!Functions::startsWith($host, "p:")) {
            // make connection persistent
            $host = "p:" . $host;
        }
        return new Dbconfig($host, $this->database, $this->user, $this->password);
    }

    private function checkConfigValue($name, $value) {
        if (!isset($value)) {
            Logfile::writeError($name . ' is not set in config.php file');
            die();
        }
    }
}
