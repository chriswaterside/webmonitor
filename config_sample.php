<?php

// start of config  version v1.03
// version v1.03
//    change email address(es) to be an array
// version v1.02
//    addition of $key field to set key to upload json log file to ramblers-webs.org.uk
//    
// $email - email address to send results
// $domain - domain/account being scanned
//
// $host, $database, $user, $password 
//      Set up a mysql database and record its details in this section
//      For ramblers webs accounts set $host to "localhost";
//          set the $database and $user to the name of the database 
//          
// $path - top level to search, hashscan will monitor this folder and all subfolders
//          except for those folders specified in the $skipFolders field
//          
// $skipFolders - skip the following folders, these should be defined relative to the $path value
// 
// $key field to set key to upload json log file to ramblers-webs.org.uk
// 
// NOTE: end all subfolders with a / otherwise folders with similar names will also be excluded
// If you are using a CMS then you should consider which folders to exclude
// 
// If you are using Joomla then specify the following item 
// $joomlaFolders - specify the folders that contain Joomla.
//              This will add the subfolders tmp,log,cache and administrator/cache to the $skipFolders item
// 
// $processExtensions - specify the file extensions that you wish to be checked, supply them in lower case.
//      extensions are not treated as case sensitive so jpg will scan for both JPG and jpg files
//      for Ramblers-webs it is recommended to monitor the following file types
//      "txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess"
//      
// $emailinterval - If no changes are found then hashscan will send an email if this time period has elapsed.
//      This is so you get a regular email and know that the scheduled task is still running
//      Interval between emails if no changes P10D - ten days
//      For Ramblers-webs sites it is recommended to use a value of P30D - thirty days 

$domain = "derbyramblers.org.uk";
$email = ["sitestatus@" . $domain];

$host = "localhost";
$database = "monitorv01";
$user = $database;
$password = "password";
$key = md5($domain);

$path = BASE_PATH;
$skipFolders = NULL;

//$joomlaFolders = NULL;
$joomlaFolders = ["public_html/*"];
$wordPressFolders = ["public_html/*"];

//$processExtensions = ["txt", "php", "jpg", "htm", "html", "cgi", "pdf", "ini", "htaccess"];
$processExtensions = NULL; // process all file types
$skipExtensions = ["log"];

$emailinterval = "P14D";
// end of config