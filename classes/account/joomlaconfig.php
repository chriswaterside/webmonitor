<?php

class AccountJoomlaconfig implements JsonSerializable {

    private $sitename;
    private $tmp_path;
    private $log_path;

    public function __construct() {
        
    }

    public function setSitename($sitename) {
        $this->sitename = $sitename;
    }

    public function setTmp_path($tmp_path) {
        $this->tmp_path = $tmp_path;
    }

    public function setLog_path($log_path) {
        $this->log_path = $log_path;
    }

    public function jsonSerialize(): mixed {
        return [
            'sitename' => $this->sitename,
            'tmp_path' => $this->tmp_path,
            'log_path' => $this->log_path
        ];
    }
}
