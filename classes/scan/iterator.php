<?php

/**
 * Description of scanfolders
 *
 * @author Chris Vaughan
 */
class ScanIterator {

    private $no = 0;
    private $extensions;
    private $skipExtensions;
    private $folders;  // folders to be ignored
    private $basePath;
    private $basePathLen;

    const SCAN_FOLDER = 0;
    const SCAN_VIRTUAL = 1;
    const SCAN_IGNORE = 2;

    public function __construct($mpath) {
        $path = str_replace("/", DIRECTORY_SEPARATOR, $mpath);
        $this->basePath = $path;
        $this->basePathLen = strlen($path) + 1; // allow for DIRECTORY_SEPARATOR
        $this->folders = [];
        $this->extensions = [];
    }

    public function addExtensions($some) {
        If (isset($some)) {
            foreach ($some as $ext) {
                $ext = str_replace("/", DIRECTORY_SEPARATOR, $ext);
                $this->extensions[] = $ext;
            }
        }
    }

    public function addSkipExtensions($some) {
        If (isset($some)) {
            foreach ($some as $ext) {
                $ext = str_replace("/", DIRECTORY_SEPARATOR, $ext);
                $this->skipExtensions[] = $ext;
            }
        }
    }

    public function addFolders($some) {
        If (isset($some)) {
            foreach ($some as $folder) {
                $folder = str_replace("/", DIRECTORY_SEPARATOR, $folder);
                if (!Functions::endsWith($folder, DIRECTORY_SEPARATOR)) {
                    $folder = $folder . DIRECTORY_SEPARATOR;
                }
                $this->folders[] = $folder;
            }
        }
    }

    public function process($class, $func) {
        $this->getDirContents($this->basePath, $class, $func);
        return;
    }

    private function getDirContents($dir, $class, $func) {
        $files = scandir($dir);
        foreach ($files as $file) {
            //$path = realpath($dir . DIRECTORY_SEPARATOR . $file);
            switch ($file) {
                case ".":
                    break;
                case "..":
                    break;
                default:
                    $path = $dir . DIRECTORY_SEPARATOR . $file;
                    $isdirectory = is_dir($path);
                    switch ($isdirectory) {
                        case true:
                            if ($this->processFolder($path) == self::SCAN_FOLDER) {
                                $this->getDirContents($path, $class, $func);
                            }
                            break;
                        case false:
                            if ($this->shouldProcessFile($path)) {
                                $this->no += 1;
                                $progresstext = " " . strval($this->no) . " ";
                                $subpath = substr($path, $this->basePathLen);
                                if (Logfile::getNoErrors() > 10) {
                                    Logfile::writeWhen($progresstext . "Skipped: " . $subpath);
                                } else {
                                    $class->$func($progresstext, $this->basePath, $subpath);
                                }
                            }

                            break;
                    }
                    break;
            }
        }
    }

    public function getNoProcessed() {
        return $this->no;
    }

    private function shouldProcessFile($path) {
        $extension = Functions::getExtension($path);
        // $extension can be null, "", or a value
        if ($extension == null) {
            return true;
        }
        if ($extension == "") {
            return true;
        }
        $extLower = strtolower($extension);
        $pathLower = strtolower($path);
        // is extension in ignore list
        if (!empty($this->skipExtensions)) {
            foreach ($this->skipExtensions as $ext) {
                if (Functions::endsWith($pathLower, "." . $ext)) {
                    return false;
                }
            }
//            if (in_array($extlower, $this->skipExtensions)) {
//                return false;
//            }
        }
        // linux can produce temporary files starting .nfs and these need to be ignored
        if (Functions::startsWith($extLower, "nfs") AND strlen($extlower) > 3) {
            return false;
        }
        // if extension list is null then include all
        if (empty($this->extensions)) {
            return true;
        }
        // is extension in include list
        if (in_array($extLower, $this->extensions)) {
            return true;
        }
        foreach ($this->extensions as $ext) {
            if (Functions::endsWith($pathLower, "." . $ext)) {
                return true;
            }
        }

        return false;
    }

    private Function processFolder($path) {
        $subpath = substr($path, $this->basePathLen);
        if (!isset($subpath)) {
            return self::SCAN_FOLDER;
        }
        if ($subpath === "") {
            return self::SCAN_FOLDER;
        }

        if (empty($this->folders)) {
            return self::SCAN_FOLDER;
        }
        $realpath = realpath($path);
        if ($path <> $realpath) {
            Logfile::writeWhen("Virtual path ignored: " . $subpath . " (" . $realpath . ")");
            return self::SCAN_VIRTUAL;
        }
        foreach ($this->folders as $value) {
            $ok = $this->isFolderSame($subpath . DIRECTORY_SEPARATOR, $value);
            if ($ok) {
                Logfile::writeWhen("Folder excluded: " . $subpath);
                return self::SCAN_IGNORE;
            }
        }
        return self::SCAN_FOLDER;
    }

    private function isFolderSame($folder, $skipfolder) {
        $parts = explode(DIRECTORY_SEPARATOR, $folder);
        $skipparts = explode(DIRECTORY_SEPARATOR, $skipfolder);
        unset($parts[count($parts) - 1]);
        unset($skipparts[count($skipparts) - 1]);
        if (count($parts) >= count($skipparts)) {
            foreach ($skipparts as $key => $value) {
//  echo $value . "  " . $key . "  " . $parts[$key];
                if (!fnmatch($value, $parts[$key])) {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

}
