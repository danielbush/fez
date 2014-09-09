<?php

use fezapi\client as c;

// These are manual tests.  Use to run stuff and debug.
// For automated testing, run behat on features/ .
// See README.txt .

// USAGE:
//
// Alter tests.php to taste.
// Then:
//   sudo -u www-data php tests.php
// Or even:
//   sudo -u www-data php tests.php | xmllint -format -
//
// Change 'www-data' to be the user that runs your fez application.

require_once(__DIR__ . '/client/Client.php');
require_once(__DIR__ . '/../../public/include/class.api.php' );

$inifilepath = __DIR__ . '/conf.ini';
$conf = parse_ini_file($inifilepath,true);
define('BASE_URI', $conf['general']['base_uri']);
define('USERNAME', $conf['credentials']['superadmin_username']);
define('PASSWORD', $conf['credentials']['superadmin_password']);
$client = new c\Client($inifilepath);

function testhttpful()
{
    $uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
    $response = \Httpful\Request::get($uri)
        ->expectsJson()
        ->sendIt();
    echo 'The Dead Weather has ' . count($response->body->result->album) . " albums.\n";
}

// Just a sanity test with curl
// function testCreate2($value = '')
function curl() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, "http://cdu.local/workflow/enter_metadata.php?id=77&wfs_id=897");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, USERNAME . ":" . PASSWORD);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "<hello>h</hello>");
    $content=curl_exec($ch);
    return $content;
}

function readContent($filename = "create_record.xml")
{
    // echo "./test_xml/${filename}";
    return file_get_contents("${filename}", true);
}

// ------------------------------------------------------------

// Get records, collections, communities.
//
// @param string $format 'xml'|'json'
// @return httpful response object

function get($pid, $type='record', $format='xml', $parse=true, $username=null, $password=null)
{
    global $client;
    switch ($type) {
    case 'record':
        $uri = BASE_URI . "/view/$pid";
        break;
    case 'collection':
        $uri = BASE_URI . "/collection/$pid";
        break;
    case 'community':
        $uri = BASE_URI . "/community/$pid";
        break;
    default:
        throw new Exception('bad $type passed to get()');
        break;
    }
    return $client->requestGET($uri, $format, $parse, $username, $password);
}

// Edit a record.

function edit()
{
    $uri = BASE_URI . '/workflow/enter_metadata.php?id=77&wfs_id=897';
    $client->requestGET($uri, 'xml');
}


// Create record.

function create()
{
    // Test enter metadata to create record
    $uri = BASE_URI . '/workflow/enter_metadata.php?id=77&wfs_id=897';
    $body = readContent();
    $response = requestPOST($uri, $body, 'json', USERNAME, PASSWORD);
    return $response;
}

// Update record metadata.

function update()
{
}


// Delete record.

function delete()
{
}

// Upload attachment to record.

function upload()
{
    global $client;
    $uri = BASE_URI . '/uploader_upload_files.php?workflowId=288&xsdmf_id=6441&fileNumber=0&format=xml';

    // $req = \Httpful\Request::post($uri);
    // $req->followRedirects(true);
    // $req->addHeader('Accept', 'application/xml');
    // // $req->sendsXml();
    // $req->attach(array('FileUpload' => './conf.ini'));
    // $response = $req->send();

    $response = $client->requestPOST($uri, $body, 'xml', false, USERNAME, PASSWORD, './conf.ini');

    return $response;
}





function test_parse_edit()
{
    $sxml = @new SimpleXmlElement(readContent('./fixtures/edit_record.xml'));
    API::extractXsdmfFields($sxml);
}


//echo readContent();

// ------------------------------------------------------------
// Communities

//$ret = get('cdu:30222', 'community', 'xml');
// $ret = upload();

// $ret = get('cdu:30222', 'community', 'xml');
//$ret = get('cdu:60', 'community', 'xml'); // closed access

// ------------------------------------------------------------
// Collections

//echo(get('cdu:30242', 'collection', 'xml')->body->asXML()); // closed acce3ss

// ------------------------------------------------------------
// Record

//$ret = get('cdu:6522', 'record', 'xml'); // closed
// $ret = get('cdu:6522', 'record', 'xml', true, 'catadmin', '(SOZ)2Long' );



// ------------------------------------------------------------

//var_export($ret);
// print_r($ret->body->saveXML());

// var_export($ret);
//print_r(parse_ini_file('conf.ini',TRUE));

// I want to POST this xml to this URI

// test_parse_edit();

// $uri = BASE_URI . '/workflow/edit_metadata.php?id=327&xdis_id=179&sta_id=2&workflow=983&workflow_val=Save Changes';
// $body = readContent('./fixtures/edit_record.xml');
// $response = $client->requestPOST($uri, $body, 'xml', USERNAME, PASSWORD);

class SimpleXMLExtended extends SimpleXMLElement
{
    public function addCData($cdata_text)
    {
        $node = dom_import_simplexml($this);
        $no   = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }
}


function transposeXML()
{
    $sxml = new SimpleXmlElement(readContent('./fixtures/full_edit_record.xml'));

    $newxml = new SimpleXMLExtended('<workflow><xsd_display_fields></xsd_display_fields></workflow>');


    foreach ($sxml->xsd_display_fields->xsd_display_field as $field) {
        if ((int)$field->xsdmf_required == 1) {
            $xsdf = $newxml->xsd_display_fields->addChild('xsd_display_field');
            $xsdf->addChild('xsdmf_id', (int)$field->xsdmf_id);

            echo (string)$field->xsdmf_html_input . "\r\n";
            echo (string)$field->xsdmf_title . "\r\n" . "\r\n";

            // Get the id, look at the html input type and add in values based on that
            // Take these new xsdmf_fields and add them to a workflow>xsd_display_fields xml object
            if ((string)$field->xsdmf_html_input == 'multiple' && $field->xsdmf_title == 'Member of Collections:') {
                $xsdf->addChild('xsdmf_value', 'cdu:29713');
                $xsdf->addChild('xsdmf_value', 'cdu:29181');
                $xsdf->addChild('xsdmf_value', 'cdu:23790');
            } elseif ((string)$field->xsdmf_html_input == 'checkbox') {
                $xsdf->addChild('xsdmf_value', '1');
            } elseif ((string)$field->xsdmf_html_input == 'contvocab_selector' && $field->xsdmf_title == "Subjects") {
                $xsdf->addChild('xsdmf_value', 'cdu:29713'); // Probably not a valid subject
            } elseif ((string)$field->xsdmf_html_input == 'textarea') {
                $xsdff = $xsdf->addChild('xsdmf_value');
                $xsdff->addCData('This is a bunch of text');
            }
        }
    }

    return $newxml;
}

$t = transposeXML();
echo $t->asXML();
// Add anything else to the xml you need
