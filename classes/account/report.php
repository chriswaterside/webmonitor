<?php

/**
 * Description of accountreport
 *
 * @author Admin
 */
class AccountReport implements JsonSerializable {

    public $domain;
    public $path;
    public $webmonitorversion;
    public $reportversion;
    public $nofilesscanned;
    public $totalsizescanned;
    public $directory;
    public $directories;
    public $files;
    public $wordpressversions;
    public $joomlaversions;
    public $joomlabackups;
    public $config;
    public $creationdate;
    public $latestfile;
    public $timediff;
    public $largestfiles;

// format 1.04
//     addition of Joomla and WP versions
// format 1.03
//     removal of dns records
//     addition of ip address
//     addition of latest file date
// format 1.02
//     addition of date created field

    public function __construct($domain, $path) {
        $this->webmonitorversion = VERSION_NUMBER;
        $this->reportversion = "1.04";
        $this->creationdate = date("Y-m-d h:i:s");
        $this->domain = $domain;
        $this->path = $path . DIRECTORY_SEPARATOR;
        $this->files = array();
        $this->config = array();
        $this->wordpressversions = array();
        $this->joomlaversions = array();

        $this->getDirs();
        $this->getPublicDirs();
        $this->getFileContent($this->path . "php.ini");
        $this->getFileContent($this->path . ".htaccess");
        $this->getFileContent($this->path . ".user.ini");
        $this->getFileContent($this->path . "public_html/.htaccess");
        $this->getFileContent($this->path . "public_html/php.ini");
        $this->getFileContent($this->path . "public_html/.user.ini");
        $this->getJoomlaBackups();
        $this->getJoomlaConfigs();
        $this->getWPVersions();
        $this->getJoomlaVersions();
    }

    private function getDirs() {
        $this->directory = $this->path . "public_html/";
    }

    private function getPublicDirs() {
        $this->directories = array();
        foreach (glob($this->directory . '*', GLOB_ONLYDIR) as $dir) {
            $dir = str_replace(__DIR__ . '/', '', $dir);
            array_push($this->directories, $dir);
        }
    }

    private function getFileContent($filename) {
        if (file_exists($filename)) {
            $this->files[$filename] = file_get_contents($filename);
        }
    }

    private function getJoomlaConfigs() {
        $this->config = array();
        foreach ($this->directories as $folder) {
            $this->getJoomlaConfig($folder);
        }
    }

    private function getJoomlaConfig($folder) {
        $path = $folder . "/configuration.php";
        if (file_exists($path)) {
            $this->findConfigValues($path);
        }
    }

    private function findConfigValues($file) {
        $parts = explode("/", $file);
        $count = count($parts);
        $folder = $parts[$count - 2];
        $contents = file_get_contents($file);
        $parts = explode("\n", $contents);
        foreach ($parts as $item) {
            $this->processConfigItem($folder, "\$sitename", $item);
            $this->processConfigItem($folder, "\$gzip", $item);
            $this->processConfigItem($folder, "\$caching", $item);
            $this->processConfigItem($folder, "\$sef", $item);
            $this->processConfigItem($folder, "\$sef_rewrite", $item);
            $this->processConfigItem($folder, "\$sef_suffix", $item);
            $this->processConfigItem($folder, "\$tmp_path", $item);
            $this->processConfigItem($folder, "\$log_path", $item);
        }
    }

    private function processConfigItem($folder, $value, $config) {
        $pos = strpos($config, $value . " ");
        if ($pos !== false) {
            $parts = explode("=", $config);
            $item = trim(end($parts));
            $item = str_replace("'", "", $item);
            $item = str_replace(";", "", $item);
            $this->config[] = $folder . "," . $value . "," . $item;
        }
    }

    private function getJoomlaBackups() {
        $this->joomlabackups = array();
        foreach ($this->directories as $folder) {
            $this->getAkeebaBackups($folder);
        }
    }

    private function getAkeebaBackups($folder) {
        $path = $folder . "/administrator/components/com_akeeba/backup";
        if (file_exists($path)) {
            $this->findJPAfiles($path);
        }
    }

    private function findJPAfiles($path) {
        $files = glob($path . "/*.jpa");
        foreach ($files as $file) {
            $this->setJoomlaBackups($file);
        }
    }

    private function setJoomlaBackups($file) {
        $search = str_replace(".jpa", ".*", $file);
        $files = glob($search);
        $no = 0;
        $size = 0;
        foreach ($files as $value) {
            $no += 1;
            $size += filesize($value);
        }
        $parts = explode("/", $file);
        $count = count($parts);
        $folder = $parts[$count - 6];
        $file = end($parts);
        $save = new AccountAkeeba($no, $size, $folder, $file);
        $this->joomlabackups[] = $save;
    }

    private function getWPVersions() {
// search public_html and sub folders
        $folder = $this->directory;
        $release = $this->findWPVersion($folder);
        if ($release != "") {
            $this->wordpressversions[$folder] = $release;
        }
        foreach ($this->directories as $folder) {
            $release = $this->findWPVersion($folder);
            if ($release != "") {
                $this->wordpressversions[$folder] = $release;
            }
        }
    }

    private function findWPVersion($folder) {
        $path = $folder . "wp-includes/version.php";
        $release = $this->findWPRelease($path);

        if ($release != "")
            return $release;
    }

    private function findWPRelease($path) {
        $release = "";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $parts = explode("\n", $contents);
            foreach ($parts as $item) {
                $value = $this->processPHPLineItem("\$wp_version", $item);
                if ($value != "") {
                    $release = $value;
                    break;
                }
            }
        }
        return $release;
    }

    private function getJoomlaVersions() {
// search public_html and sub folders
        $folder = $this->directory;
        $release = $this->findJoomlaVersion($folder);
        if ($release != "") {
            $this->joomlaversions[$folder] = $release;
        }
        foreach ($this->directories as $folder) {
            $release = $this->findJoomlaVersion($folder);
            if ($release != "") {
                $this->joomlaversions[$folder] = $release;
            }
        }
    }

    private function findJoomlaVersion($folder) {
        // 3.8+
        $path = $folder . "/libraries/src/Version.php";
        $release = $this->findJoomlaRelease($path);
        // if Joomla 2.5 or above read htaccess and .ini files
        $this->getFileContent($folder . "/.htaccess");
        $this->getFileContent($folder . "/php.ini");
        $this->getFileContent($folder . "/.user.ini");

        if ($release != "")
            return $release;
        // 2.5 and 3.5
        $path = $folder . "/libraries/cms/version/version.php";
        $release = $this->findJoomlaRelease($path);
        // if Joomla 2.5 or above read htaccess and .ini files
        $this->getFileContent($folder . "/.htaccess");
        $this->getFileContent($folder . "/php.ini");
        $this->getFileContent($folder . "/.user.ini");

        if ($release != "")
            return $release;

        // 1.5 
        $path = $folder . "/libraries/joomla/version.php";
        $release = $this->findJoomlaRelease($path);
        return $release;
    }

    private function findJoomlaRelease($path) {
        $release = "";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $parts = explode("\n", $contents);
            foreach ($parts as $item) {
                $value = $this->processPHPLineItem("RELEASE", $item);
                if ($value != "") {
                    $release = $value;
                    break;
                }
            }
            foreach ($parts as $item) {
                $value = $this->processPHPLineItem("DEV_LEVEL", $item);
                if ($value != "") {
                    $release .= "." . $value;
                    break;
                }
            }
        }
        return $release;
    }

    private function processPHPLineItem($value, $line) {
        $pos = strpos($line, $value . " ");
        if ($pos !== false) {
            $pos = strpos($line, "=");
            if ($pos !== false) {
                $parts = explode("=", $line);
                $item = trim(end($parts));
                $item = str_replace("'", "", $item);
                $item = str_replace(";", "", $item);
                return $item;
            }
        }
        return "";
    }

    public function jsonSerialize(): mixed {
        return [
            'webmonitorversion' => $this->webmonitorversion,
            'reportversion' => $this->reportversion,
            'domain' => $this->domain,
            'path' => $this->path,
            'nofilesscanned' => $this->nofilesscanned,
            'totalsizescanned' => $this->totalsizescanned,
            'directory' => $this->directory,
            'directories' => $this->directories,
            'files' => $this->files,
            'wordpressversions' => $this->wordpressversions,
            'joomlaversions' => $this->joomlaversions,
            'joomlabackups' => $this->joomlabackups,
            'creationdate' => $this->creationdate,
            'config' => $this->config,
            'latestfile' => $this->latestfile,
            'largestfiles' => $this->largestfiles
        ];
    }
}
