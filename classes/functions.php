<?php

/**
 * Description of functions
 *
 * @author Chris
 */
class Functions {

    public static function startsWith($haystack, $needle) {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }

    public static function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }

    public static function formatDateDiff($interval) {

        $doPlural = function ($nb, $str) {
            return $nb > 1 ? $str . 's' : $str;
        }; // adds plurals

        $format = array();
        if ($interval->y !== 0) {
            $format[] = "%y " . $doPlural($interval->y, "year");
        }
        if ($interval->m !== 0) {
            $format[] = "%m " . $doPlural($interval->m, "month");
        }
        if ($interval->d !== 0) {
            $format[] = "%d " . $doPlural($interval->d, "day");
        }
        if ($interval->h !== 0) {
            $format[] = "%h " . $doPlural($interval->h, "hour");
        }
        if ($interval->i !== 0) {
            $format[] = "%i " . $doPlural($interval->i, "minute");
        }
        if ($interval->s !== 0) {
            if (!count($format)) {
                return "less than a minute ago";
            } else {
                $format[] = "%s " . $doPlural($interval->s, "second");
            }
        }

        // We use the two biggest parts
        if (count($format) > 1) {
            $format = array_shift($format) . " and " . array_shift($format);
        } else {
            $format = array_pop($format);
        }

        // Prepend 'since ' or whatever you like
        return $interval->format($format);
    }

    public static function getExtension($path) {
        $parts = explode(".", $path);
        if (count($parts) == 1) {
            return null;
        }
        return $parts[count($parts) - 1];
    }

    public static function deleteFolder($dir) {
        if (file_exists($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                if (is_dir("$dir/$file")) {
                    self::deleteFolder("$dir/$file");
                } else {
                    $okay = unlink("$dir/$file");
                    if (!$okay) {
                        Logfile::writeError('Unable to delete file ' . $dir / $file);
                    }
                }
            }
            return rmdir($dir);
        }
    }

    public static function getTotalInterval($interval, $type) {
        $years = $interval->y;
        $months = $years * 12 + $interval->m;
        $days = $months * 31 + $interval->d;
        $hours = $days * 24 + $interval->h;
        $minutes = $hours * 60 + $interval->i;
        $seconds = $minutes * 60 + $interval->s;
        switch ($type) {
            case 'years':
                return $years;
            case 'months':
                return $months;
            case 'days':
                return $days;
            case 'hours':
                return $hours;
            case 'minutes':
                return $minutes;
            case 'seconds':
                return $seconds;
            default:
                return $seconds;
        }
    }

    public static function errorHandler($errno, $errstrin, $errfile, $errline) {
        // $errstr may need to be escaped:
        $errstr = htmlspecialchars($errstrin);

        if ($errno == E_USER_ERROR) {
            Functions::reportError('FATAL ERROR');
        }
        $error = "[$errno] $errstr Line $errline in file $errfile";
        Functions::reportError('Error encountered: ' . $error);
        $calls = debug_backtrace();
        Functions::displayCallBack($calls);

        switch ($errno) {
            case E_USER_ERROR:
                $msg = ", PHP " . PHP_VERSION . " (" . PHP_OS . ")";
                Functions::reportError($msg);
                Functions::reportError("Aborting...");
                Logfile::writeWhen("Closing Logfile");
                Logfile::close();
                exit(1);
            default:
                break;
        }
        /* Don't execute PHP internal error handler */
        return true;
    }

    public static function fatalHandler() {
        $error = error_get_last();

        if ($error !== NULL) {
            $errno = Functions::getArrayItem($error, "type");
            $errfile = Functions::getArrayItem($error, "file");
            $errline = Functions::getArrayItem($error, "line");
            $errstr = Functions::getArrayItem($error, "message");
            Functions::reportError("FATAL ERROR: Type: " . $errno);
            Functions::reportError("FATAL ERROR: Line: " . $errline . " in " . $errfile);
            Functions::reportError("FATAL ERROR: " . $errstr);
            $msg = "PHP version " . PHP_VERSION . " (OS: " . PHP_OS . ")";
            Functions::reportWhen($msg);
            Functions::reportWhen("Aborting...");
            Logfile::writeWhen("Closing Logfile");
            Logfile::close();
            die();
        }
    }

    private static function getArrayItem($array, $element) {
        if (array_key_exists($element, $array)) {
            return $array[$element];
        } else {
            return "?";
        }
    }

    private static function displayCallBack($values) {
        Functions::reportWhen("Trace: ");
        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_array($value)) {
                    $errfile = Functions::getArrayItem($value, "file");
                    $errline = Functions::getArrayItem($value, "line");
                    $errstr = Functions::getArrayItem($value, "function");
                    if ($errline !== "?" and $errline !== "?") {
                        Functions::reportWhen("Function: " . $errstr . ",   line " . $errline . " in " . $errfile);
                    }
                }
            }
        }
    }

    private static function reportError($text) {
        Logfile::writeError($text);
        echo "<p style='color:red'>" . $text . "</p>\n";
    }

    private static function reportWhen($text) {
        Logfile::writeWhen($text);
        echo "<p style='color:red'>" . $text . "</p>\n";
    }

}
