<?php

// installed from server

class Monitorinstall {

    const UPDATE_OK = 0;
    const UPDATE_FAILED = 1;

    public function __construct() {
        $this->deleteFolder(BASE_PATH . "/monitorOLD");
    }

    public function update($webmonitor, $version) {

        // unzip new software
        $zip = new ZipArchive;
        $res = $zip->open($webmonitor);
        if ($res === TRUE) {
            $ok = $zip->extractTo(BASE_PATH);
            $zip->close();
            if ($ok === true) {
                Logfile::writeWhen('Extracted new software from zip file');
            } else {
                Logfile::writeWhen('FAILED to extract new software from zip file');
                return self::UPDATE_FAILED;
            }
        } else {
            Logfile::writeWhen('FAILED - ' . $this->zipmessage($res));
            return self::UPDATE_FAILED;
        }

        // move config.php file into new folder
        $ok = rename(BASE_PATH . "/monitor/config.php", BASE_PATH . "/" . $version . "/config.php");
        if ($ok) {
            Logfile::writeWhen("Moved config file to new directory");
        } else {
            Logfile::writeWhen("FAILED to move config file to new directory");
            return self::UPDATE_FAILED;
        }
        
        // move config.centralaccounts.hph into new folder
        
        // ???????????????????????????????

        // rename old version
        $ok = rename(BASE_PATH . "/monitor", BASE_PATH . "/monitorOLD");
        if ($ok) {
            Logfile::writeWhen("Renamed old software");
        } else {
            Logfile::writeWhen("FAILED to rename old software");
            return self::UPDATE_FAILED;
        }
        // rename new software
        $ok = rename(BASE_PATH . "/" . $version, BASE_PATH . "/monitor");
        if ($ok) {
            Logfile::writeWhen("Renamed NEW software");
        } else {
            Logfile::writeWhen("FAILED to rename NEW software");
            return self::UPDATE_FAILED;
        }
        // delete old zip files
        $this->deleteOldZipFiles($version);
        return self::UPDATE_OK;
    }

    private function zipmessage($code) {
        switch ($code) {
            case 0:
                return 'No error';
            case 1:
                return 'Multi-disk zip archives not supported';
            case 2:
                return 'Renaming temporary file failed';
            case 3:
                return 'Closing zip archive failed';
            case 4:
                return 'Seek error';
            case 5:
                return 'Read error';
            case 6:
                return 'Write error';
            case 7:
                return 'CRC error';
            case 8:
                return 'Containing zip archive was closed';
            case 9:
                return 'No such file';
            case 10:
                return 'File already exists';
            case 11:
                return 'Can\'t open file';
            case 12:
                return 'Failure to create temporary file';
            case 13:
                return 'Zlib error';
            case 14:
                return 'Malloc failure';
            case 15:
                return 'Entry has been changed';
            case 16:
                return 'Compression method not supported';
            case 17:
                return 'Premature EOF';
            case 18:
                return 'Invalid argument';
            case 19:
                return 'Not a zip archive';
            case 20:
                return 'Internal error';
            case 21:
                return 'Zip archive inconsistent';
            case 22:
                return 'Can\'t remove file';
            case 23:
                return 'Entry has been deleted';
            default:
                return 'An unknown error has occurred(' . intval($code) . ')';
        }
    }

    private function deleteOldZipFiles($version) {
        foreach (glob(BASE_PATH . "/*.zip") as $filename) {
            if ($filename != BASE_PATH . "/" . $version . ".zip") {
                unlink($filename);
                logfile::writeWhen("Old zip file deleted: " . $filename);
            }
        }
    }

    public function deleteFolder($dir) {
        if (file_exists($dir)) {
            // delete folder and its contents
            foreach (glob($dir . '/*') as $file) {
                if (is_dir($file))
                    $this->deleteFolder($file);
                else
                    unlink($file);
            } rmdir($dir);
        }
    }

}
