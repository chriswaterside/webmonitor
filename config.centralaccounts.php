<?php

/*
 * Config file to contain parameters to store account json information in a central location
 * mainly used by Ramblers-webs.org.uk
 * 
 *    CENTRAL_STATUS_FILE is name of json file to contain domain information;
 *    CENTRAL_STORE_URL is the central form that accepts the upload of these files 
 */

$central_store_url = 'https://cache.ramblers-webs.org.uk/storejson.php';
$central_store_key = md5($domain);
// This URL must point to a form which accepts the fields for 
//      the domain name
//      a key
//      the json text
//      the form must use POST and have the fields: domain, key and json
