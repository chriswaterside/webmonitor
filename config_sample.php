<?php

// start of config file - copy this sample and rename it to config.php
//   then fill in the details below
// version v1.04
//    Chnage to make web monitor much more of general use rather then ramblers-webs speciifc
// version v1.03
//    change email address(es) to be an array
// version v1.02
//    addition of $key field to set key to upload json log file to ramblers-webs.org.uk
// The name of the organisation setting up the monitoring system or blank
// this is included in any generated report 
$organisation = "Ramblers Webs";

// the email address of who to contact if the web master for a domain requires support
// this is included in any generated report 
$supportemail = "someone@somewhere.org.uk";

// $email - email address to send reports of changes to the server files or null
$email = ["someone@somewhere.org.uk"];

// $domain - domain being scanned
$domain = "somewhere.org.uk";

// the smtp details of a mail server or null (web monitor will use PHP Mail option)
$smtp = (object) [
            'Host' => 'xxxx',
            'SMTPAuth' => true,
            'Username' => 'xxx',
            'Password' => 'xxx',
            'Port' => 587,
            'FromEmail' => 'xxx@yyy',
];

// $host, $database, $user, $password 
//      Set up a mysql database and record its details in this section  
$host = "localhost";
$database = "";
$user = "";
$password = "";

// $path - top level to search, hashscan will monitor this folder and all subfolders
//          except for those folders specified in the $skipFolders field
//$path = "D:/Data/XAMPPServer/htdocs/";
$path = BASE_PATH;

// $skipFolders - skip the following folders, these should be defined relative to the $path value
// NOTE: end all subfolders with a / otherwise folders with similar names will also be excluded
$skipFolders = ["folder1/", "folder2/", "folder3/"];

// If you are using a CMS then you should consider which folders to exclude
// 
// If you are using Joomla then specify the following item 
// $joomlaFolders - An array of folders contaioning Joomla installs
//              specify the folders that contain Joomla.
//              This will add the subfolders tmp,log,cache and administrator/cache to the $skipFolders item
//              An array of folders contaioning Joomla installs
//  $joomlaFolders = [];
//  $joomlaFolders = ["abc","cde"];
$joomlaFolders = ["folder*"];

// $processExtensions - specify the file extensions that you wish to be checked, supply them in lower case.
//      extensions are not treated as case sensitive so jpg will scan for both JPG and jpg files
//      It is recommended to monitor at least the following file types
//      "txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess"
//      $processExtensions = ["txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess"];
//      $processExtensions = NULL; processes all file types.
$processExtensions = NULL;

// $skipExtensions - if the above item is null then this option will ignore these extensions
//         $skipExtensions = ["log", "pdf"];
//         $skipExtensions = [];
$skipExtensions = ["log", "pdf"];

// $emailinterval - If changes are found then hashscan will send an email
//      If no changes are found then hashscan will send an email if this time period has elapsed.
//      This is so you get a regular email and know that the scheduled task is still running
//      Interval between emails if no changes P10D - ten days

$emailinterval = "P10D";

//   If you wish to store the webmonitor_status_domain.json.log from each domain on a central server then set up the next items
//    $central_store_url is a central form that accepts the upload of these files or NULL
//    $central_store_key is a security key to stop others from posting to the form
//
//         $central_store_url = 'https://myserver/storejson.php';
//         $central_store_url = null;
$central_store_url = 'https://myserver/storejson.php';
$central_store_key = "";
// This URL must point to a form which accepts the fields for 
//      the domain name
//      a key
//      the json text
//      the form must use POST and have the fields: domain, key and json
//       $data['domain'] = $this->domain;
//       $data['key'] = $this->centralStoreKey;
//       $data['json'] = $json;

// end of config

