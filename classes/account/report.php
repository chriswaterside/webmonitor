<?php

/**
 * Description of accountreport
 *
 * @author Admin
 */
class AccountReport implements JsonSerializable {

    private $domain;
    private $path;
    private $webMonitorVersion;
    private $reportVersion = "1.05";
    private $noFilesScanned;
    private $totalSizeScanned;
    private $topLevelDirectories = [];
    private $controlFiles = [];
    private $wordPressVersions = [];
    private $joomlaVersions = [];
    private $joomlaBackups = [];
    private $config;
    private $creationDate;
    private $latestFile;
    private $largestFiles;

// Format 1.05
//     change case of variables
//     rationalise structure to scan top two levels of folders
//     add scanning for Joomla 4 and 5   
// format 1.04
//     addition of Joomla and WP versions
// format 1.03
//     removal of dns records
//     addition of ip address
//     addition of latest file date
// format 1.02
//     addition of date created field

    public function __construct($session) {
        $this->webMonitorVersion = VERSION_NUMBER;
        $this->creationDate = date("Y-m-d h:i:s");
        $this->domain = $session->domain();
        $this->path = $session->path() . DIRECTORY_SEPARATOR;
        $this->config = new stdClass();

        $this->getTopLevelDirs();
        $this->getControlFiles();

        $this->getJoomlaBackups();
        $this->getJoomlaConfigs();
        $this->getJoomlaVersions();
        $this->getWPVersions();
    }

    private function removePath($dir) {
        if (str_starts_with($dir, $this->path)) {
            $folder = substr($dir, strlen($this->path));
            return $folder;
        }
        return $dir;
    }

    private function addPath($dir) {
        return $this->path . $dir;
    }

    private function getTopLevelDirs() {
        array_push($this->topLevelDirectories, "");
        $directories = glob($this->path . '*', GLOB_ONLYDIR);

        // remove Web Monitor folders
        foreach ($directories as $key => $dir) {
            switch ($this->removePath($dir)) {
                case "monitor":
                case "monitorOLD":
                    unset($directories[$key]);
            }
        }

        $this->topLevelDirectories = array_merge($this->topLevelDirectories, $directories);

        foreach ($directories as $dir) {
            $directories = glob($dir . '/*', GLOB_ONLYDIR);
            $this->topLevelDirectories = array_merge($this->topLevelDirectories, $directories);
        }
        foreach ($this->topLevelDirectories as $key => $dir) {
            $this->topLevelDirectories[$key] = $this->removePath($dir);
        }
    }

    private function getControlFiles() {
        foreach ($this->topLevelDirectories as $dir) {
            $folder = $this->addPath($dir);
            $this->getFileContent($folder . "/php.ini");
            $this->getFileContent($folder . "/.htaccess");
            $this->getFileContent($folder . "/.user.ini");
        }
    }

    private function getFileContent($filename) {
        if (file_exists($filename)) {
            $this->controlFiles[$this->removePath($filename)] = file_get_contents($filename);
        }
    }

    private function getJoomlaConfigs() {
        foreach ($this->topLevelDirectories as $dir) {
            $folder = $this->addPath($dir);
            $this->getJoomlaConfig($folder);
        }
    }

    private function getJoomlaConfig($folder) {
        if (file_exists($folder . "/configuration.php")) {
            $this->findConfigValues($folder, "/configuration.php");
        }
    }

    private function findConfigValues($folder, $file) {

        $contents = file_get_contents($folder . $file);

        $store = new AccountJoomlaconfig();
        $store->setSitename($this->processConfig($contents, "\$sitename"));
        $store->setTmp_path($this->processConfig($contents, "\$tmp_path"));
        $store->setLog_path($this->processConfig($contents, "\$log_path"));
        $dir = $this->removePath($folder);
        $this->config->$dir = $store;
    }

    private function processConfig($contents, $searchItem) {
        $item = null;
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            $pos = strpos($line, $searchItem . " ");

            if ($pos !== false) {
                $parts = explode("=", $line);
                $item = trim(end($parts));
                $item = str_replace("'", "", $item);
                $item = str_replace(";", "", $item);
                return $item;
            }
        }
        return $item;
    }

    private function getJoomlaBackups() {
        foreach ($this->topLevelDirectories as $dir) {
            $folder = $this->addPath($dir);
            $this->getAkeebaBackups($folder);
        }
    }

    private function getAkeebaBackups($folder) {
        $path = $folder . "/administrator/components/com_akeebabackup/backup";
        if (file_exists($path)) {
            $this->findJPAfiles($path);
        }
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
        $this->joomlaBackups[] = $save;
    }

    private function getWPVersions() {
        foreach ($this->topLevelDirectories as $dir) {
            $folder = $this->addPath($dir);
            $release = $this->findWPVersion($folder);
            if ($release != "") {
                $this->wordPressVersions[$this->removePath($folder)] = $release;
            }
        }
    }

    private function findWPVersion($folder) {
        $path = $folder . "/wp-includes/version.php";
        $release = $this->findWPRelease($path);

        if ($release != "") {
            return $release;
        }
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
        foreach ($this->topLevelDirectories as $dir) {
            $folder = $this->addPath($dir);
            $release = $this->findJoomlaVersion($folder);
            if ($release != "") {
                $this->joomlaVersions[$this->removePath($folder)] = $release;
            }
        }
    }

    private function findJoomlaVersion($folder) {
        // 5.0+
        $path = $folder . "/administrator/manifests/files/joomla.xml";
        $release = $this->findJoomlaRelease($path);
        if ($release != "") {
            return $release;
        }
        // 3.8+
        $path = $folder . "/libraries/src/Version.php";
        $release = $this->findJoomlaRelease($path);
        if ($release != "") {
            return $release;
        }
        // 2.5 and 3.5
        $path = $folder . "/libraries/cms/version/version.php";
        $release = $this->findJoomlaRelease($path);
        if ($release != "") {
            return $release;
        }
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
                $value = $this->processPHPXmlLineItem($item);
                if ($value != "") {
                    $release = $value;
                    break;
                }
            }
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

    private function processPHPXmlLineItem($line) {
        $pos = strpos($line, "<version>");
        if ($pos !== false) {
            $pos = strpos($line, "</version>");
            if ($pos !== false) {
                $item = str_replace(["<version>", "</version>"], "", $line);
                return trim($item);
            }
        }
        return "";
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

    public function setScanValues($scan) {
        if ($scan != null) {
            $this->noFilesScanned = $scan->getNoFilesScanned();
            $this->totalSizeScanned = $scan->getTotalSizeScanned();
            $this->latestFile = $scan->getLatestFile();
            $this->largestFiles = $scan->getLargestFiles();
        }
    }

    public function jsonSerialize(): mixed {
        return [
            'webMonitorVersion' => $this->webMonitorVersion,
            'reportVersion' => $this->reportVersion,
            'domain' => $this->domain,
            'path' => $this->path,
            'noFilesScanned' => $this->noFilesScanned,
            'totalSizeScanned' => $this->totalSizeScanned,
            'topLevelDirectories' => $this->topLevelDirectories,
            'controlFiles' => $this->controlFiles,
            'wordPressVersions' => $this->wordPressVersions,
            'joomlaVersions' => $this->joomlaVersions,
            'joomlaBackups' => $this->joomlaBackups,
            'creationDate' => $this->creationDate,
            'config' => $this->config,
            'latestFile' => $this->latestFile,
            'largestFiles' => $this->largestFiles
        ];
    }
}
