<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of akeeba
 *
 * @author Chris
 */
class AccountAkeeba {

    public $nofiles = 0;
    public $totalsize = 0;
    public $folder = "";
    public $file = "";

    public function __construct($no, $size, $folder, $file) {
        $this->nofiles = $no;
        $this->totalsize = $size;
        $this->folder = $folder;
        $this->file = $file;
    }

}
