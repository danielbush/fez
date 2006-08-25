<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Fez - Digital Repository System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2005, 2006 The University of Queensland,               |
// | Australian Partnership for Sustainable Repositories,                 |
// | eScholarship Project                                                 |
// |                                                                      |
// | Some of the Fez code was derived from Eventum (Copyright 2003, 2004  |
// | MySQL AB - http://dev.mysql.com/downloads/other/eventum/ - GPL)      |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Christiaan Kortekaas <c.kortekaas@library.uq.edu.au>,       |
// |          Matthew Smith <m.smith@library.uq.edu.au>                   |
// +----------------------------------------------------------------------+
//
//
/***************** Fedora API calls ******************/
/*
This code has many functions that use the nusoap class files
and instantiate a new soapclient object for the Fedora API-A(access)
or API-M(management) of Fedora Objects. There is also a debugInfo($client)
function at the bottom of this page that can be used with any of the SOAP
Fedora API functions.

Originally Written by Elly Cramer 2004 - elly@cs.cornell.edu (Thanks Elly!)
Modified heavily into PHP 5 Class form for Fez by Christiaan Kortekaas 2005 - c.kortekaas@library.uq.edu.au
*/
// which is included in top.php
include_once(APP_INC_PATH . "class.error_handler.php");
include_once(APP_INC_PATH . "class.setup.php");
include_once(APP_INC_PATH . "class.misc.php");
require_once(APP_INC_PATH . "nusoap.php");
include_once(APP_PEAR_PATH . "/HTTP/Request.php");

class Fedora_API {

    /**
     * Check to be sure the Fedora server is up and running!
     *
     * Developer Note: This function is not actually used for anything just yet, but will be in future releases. Should be in Fez 1.2.
     *
     * @access  public
     * @return  void (Logs an error message)
     */
	function checkFedoraServer() {
		$ch = curl_init(APP_BASE_FEDORA_APIA_DOMAIN."/search");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$results = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close ($ch);
		if ($info['http_code'] == '200') {
			//proceed!!
		} else {
			$errMsg = 'It appears that the content repository server is down, Please try again later.';
			Error_Handler::logError(array($errMsg, $info), __FILE__, __LINE__);
		}
	}

    /**
     * Opens a given url and reads it into a variable and returns the string variable.
     *
     * @access  public
	 * @param string $ur1 The URL of the website to read
     * @return  string $result The URL in text
     */
	function URLopen($url)
	{
       // Fake the browser type
       ini_set('user_agent','MSIE 4\.0b2;');
       $dh = fopen("$url",'r');
       $result = "";
       $temp_result = "";
       while ($temp_result = fread($dh,8192)) {
	       $result .= $temp_result;
	   }
	   fclose($dh);
       return $result;
	}

    /**
     * Gets the next available persistent identifier from the Fedora PID Handler webservice.
     *
     * @access  public
     * @return  string $pid The next avaiable PID in from the Fedora PID handler
     */
	function getNextPID() {
		$pid = false;
		$getString = APP_BASE_FEDORA_APIM_DOMAIN."/mgmt/getNextPID?xml=true";
		$ch = curl_init($getString);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$results = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close ($ch);
		$xml = $results;
		$dom = new DomDocument;
		$dom->loadXML($xml); // Now this works with php5 - CK 7/4/2005
		$result = $dom->getElementsByTagName("pid");
		foreach($result as $item) {
			$pid = $item->nodeValue;
			break;
		}
	   return $pid;
	}

    /**
     * Gets the XML of a given object by PID.
     *
     * Developer Note: This function is not actually used for anything just yet, but might be in future releases.
	 *
     * @access  public
	 * @param string $pid The persistent identifier
     * @return  string $result The XML of the object
     */
	function getObjectXMLByPID($pid) {
		$parms = array('pid' => $pid);
		$result = Fedora_API::openSoapCall('getObjectXML', $parms);
		return $result;
	}

    /**
     * This function ingests a FOXML object and base64 encodes it
     *
     * @access  public
	 * @param string $foxml The XML object itself in FOXML format
     * @return  void
     */
	function callIngestObject($foxml) {
		$foxml = base64_encode(trim($foxml));
		$logmsg = 'Fedora Object ingested';
		$parms=array(new soapval("XML","base64Binary",$foxml), 'format' => 'foxml1.0', 'logMessage' => $logmsg);
		$result = Fedora_API::openSoapCall('ingest', $parms);
        return $result;
	}

    function export($pid, $format="foxml1.0", $context="migrate") {
        $parms = compact('pid','format','context');
		$result = Fedora_API::openSoapCall('export', $parms);
        return $result;
    }

    /**
     * This function uses Fedora's simple search service which only really works against Dublin Core records,
	 * so is not heavily used. Searches are mostly carried out against Fez's own (much more powerful) index.
     *
     * @access  public
	 * @param string $searchTerms The terms by which the search will be carried out.
	 * @param integer $maxResults The maximum amount of results that will be returned.
	 * @param array $searchTerms The list of DC and Fedora basic fields to search against.
     * @return  array $resultList The search results.
     */
	function getListObjectsXML($searchTerms, $maxResults=2147483647, $returnfields=null) {
		$resultlist = array();
		$searchTerms = urlencode("*$searchTerms*"); // encode it for url parsing
		if (empty($returnfields)) {
			$returnfields = array('pid', 'title', 'identifier', 'description', 'type');
		}
		$fieldPhrase = '';
		foreach ($returnfields as $rField) {
			$fieldPhrase .= "&$rField=true";
		}
		$searchPhrase = "?xml=true$fieldPhrase&terms=$searchTerms";
		if (is_numeric($maxResults)) {
			$searchPhrase .= "&maxResults=$maxResults";
		}
		$filename = APP_FEDORA_SEARCH_URL.$searchPhrase;
//		$xml = file_get_contents($filename);
		list($xml,$info) = Misc::processURL($filename);
		$xml = preg_replace("'<object uri\=\"info\:fedora\/(.*)\"\/>'", "<pid>\\1</pid>", $xml); // fix the pid tags
		$doc = DOMDocument::loadXML($xml);
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('r', 'http://www.fedora.info/definitions/1/0/types/');
		$objectFieldsNodeList = $xpath->query('/r:result/r:resultList/r:objectFields');
		// loop through the objectFields elements
		foreach ($objectFieldsNodeList as $objectFieldsNode) {
			// look for the return fields and group them in an array
			foreach ($returnfields as $rfield) {
				$rFieldNodeList = $objectFieldsNode->getElementsByTagName($rfield);
				if ($rFieldNodeList->length) {
					$rItem[$rfield] = trim($rFieldNodeList->item(0)->nodeValue);
				}
			}
			// add the return fields array to out list of results
			$resultlist[] = $rItem;
		}
		return $resultlist;
	}

    /**
     * This function uses Fedora's Kowari Index ITQL search service which only really works against Dublin Core records,
	 * RELS-EXT and basic Fedora information so is not heavily used. Searches are mostly carried out against Fez's own (much more powerful) index.
     *
     * @access  public
	 * @param string $itql The ITQL query (Kind of like SQL, but for triplestores, and probably more complicated and less powerful..)
	 * @param array $returnfields The fields you want results to be returned for.
     * @return  array $resultlist The search results.
     */
	function getITQLQuery($itql, $returnfields) {
		$searchPhrase = "";
		$itql = urlencode($itql); // encode it for url parsing
		// create the fedora web service URL query string to run the ITQL
//		$searchPhrase = "?type=tuples&lang=itql&format=Sparql&limit=1000&dt=on&query=".$itql;
		$searchPhrase = "?type=tuples&lang=itql&format=Sparql&limit=&dt=on&query=".$itql;
		// format the return fields URL query string
		// Should abstract the below for into a function in here
		$stringfields = array();
		for($x=0;$x<count($returnfields);$x++) {
		 $stringfields[$x] = $returnfields[$x] . "=true";
		}
		$stringfields = join("&", $stringfields);
		// do the query - we are querying the fedora web service here (need to be able to open an URL as a file)
		$filename = APP_FEDORA_RISEARCH_URL.$searchPhrase;
//		$xml = file_get_contents($filename);
		list($xml,$info) = Misc::processURL($filename);
		$xml = preg_replace("'<object uri\=\"info\:fedora\/(.*)\"\/>'", "<pid>\\1</pid>", $xml); // fix the pid tags
		// The query has returned XML. Parse the xml into a DOMDocument
		$doc = @DOMDocument::loadXML($xml);
        if (!$doc) {
            echo "The ITQL query failed. This is probably due to the Fez Fedora Kowari Resource Index being switched off in the fedora.fcfg config file.
			<br/> To use the Fez Fedora maintenance reindexer tools the Kowari resource index needs to be turned on. To do this edit fedora.fcfg and change the value of resourceIndex from 0 to 1 then stop fedora. Then run fedora-rebuild
			and choose option 1. After the Kowari resource index has been rebuilt start fedora. See more about the Kowari resource index config settings at http://www.fedora.info.
			<br/><br/> The Error returned from Fedora was: ";
			echo "<pre>";
            echo nl2br(htmlspecialchars(print_r($xml,true)));
			echo "</pre>";
            return array();
        }
		$resultlist = array();
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('r', 'http://www.w3.org/2001/sw/DataAccess/rf1/result');
		$resultNodeList = $xpath->query('/r:sparql/r:results/r:result');
		// loop through results to assemble the result list array
		foreach ($resultNodeList as $resultNode) {
			// use first item in returnfield as key to resultlist
			// probably the pid
			$rkeyName = $returnfields[0];
			$rkeyValue = $resultNode->getElementsByTagName($rkeyName)->item(0)->nodeValue;
			$rItem = &$resultlist[$rkeyValue];
			// pick out the result fields we are interested in
			foreach ($returnfields as $returnField) {
				$returnFieldNodes = $resultNode->getElementsByTagName($returnField);
				if ($returnFieldNodes->length) {
					$rValue = trim($returnFieldNodes->item(0)->nodeValue);
					if (!empty($rValue)) {
						// Where there are multiple results in the same field, merge them
						if (isset($rItem[$returnField])) {
							if (is_array($rItem[$returnField])) {
								// If we already have arrayed results for this item, then just add to the array
								$rItem[$returnField][] = $rValue;
							} else {
								// if we don't have arrayed results already, then decide whether to make them into an array
								if ($rItem[$returnField] != $rValue) {
									// if the value isn't the same as the one there, then make an array
									$prevValue = $rItem[$returnField];
									$rItem[$returnField] = array($prevValue, $rValue);
								}
								// else do nothing (item is the same as already found)
							}
						} else {
							// there is no previous value for this return field so just set it
							$rItem[$returnField] = $rValue;
						}
					}
				}
			}
		}
		// strip the keys out
		$resultlist = array_values($resultlist);
		return $resultlist;
	}

    /**
     * This function removes an object and all its datastreams from Fedora
     *
     * @access  public
	 * @param string $pid The persistant identifier of the object to be purged
     * @return  integer
     */
	function callPurgeObject($pid) {
	   $logmsg = 'Fedora Object Purged using Fez';
	   $parms=array('PID' => $pid, 'logMessage' => $logmsg, 'force' => false);
	   Fedora_API::openSoapCall('purgeObject', $parms);
 	   return 1;
	}

    /**
     * This function uses curl to upload a file into the fedora upload manager and calls the addDatastream or modifyDatastream as needed.
     *
     * @access  public
	 * @param string $pid The persistant identifier of the object to be purged
	 * @param string $dsIDName The datastream name
	 * @param string $dsLabel The datastream label
	 * @param string $mimetype The mimetype of the datastream
	 * @param string $controlGroup The control group of the datastream
	 * @param string $dsID The ID of the datastream
     * @return  integer
     */
	function getUploadLocation ($pid, $dsIDName, $file, $dsLabel, $mimetype='text/xml', $controlGroup='M', $dsID=NULL) {
		$loc_dir = "";
		if (!is_numeric(strpos($dsIDName, "/"))) {
			$loc_dir = APP_TEMP_DIR;
		}
		if ($mimetype == 'text/xml') {
			$config = array(
			  'indent'         => true,
			  'input-xml'   => true,
			  'output-xml'   => true,
			  'wrap'           => 200);

			$tidy = new tidy;
			$tidy->parseString($file, $config, 'utf8');
			$tidy->cleanRepair();
			$file = $tidy;
		}
		if (!empty($file) && (trim($file) != "")) {
			$fp = fopen($loc_dir.$dsIDName, "w"); //@@@ CK - 28/7/2005 - Trying to make the file name in /tmp the uploaded file name
			fwrite($fp, $file);
			fclose($fp);
			$ch = curl_init(APP_FEDORA_UPLOAD_URL);
		    curl_setopt($ch, CURLOPT_VERBOSE, 0);
		    curl_setopt($ch, CURLOPT_HEADER, 0);
 		    curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => "@".$loc_dir.$dsIDName)); //@@@ CK - 28/7/2005 - Trying to make the file name in /tmp the uploaded file name
		    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		    $uploadLocation = curl_exec($ch);
		    curl_close ($ch);

		    $uploadLocation = trim(str_replace("\n", "", $uploadLocation));
			$dsIDNameOld = $dsIDName;
			if (is_numeric(strpos($dsIDName, chr(92)))) {
				$dsIDName = substr($dsIDName, strrpos($dsIDName, chr(92))+1);
				if ($dsLabel == $dsIDNameOld) {
					$dsLabel = $dsIDName;
				}
			}
		   $dsExists = Fedora_API::datastreamExists($pid, $dsIDName);
		   if ($dsExists !== true) {
              //Call callAddDatastream
              $dsID = Fedora_API::callCreateDatastream ($pid, $dsIDName, $uploadLocation, $dsLabel, $mimetype, $controlGroup);
              return $dsID;
              exit;
           } elseif ($dsIDName != NULL) {
              //Call ModifyDatastreamByReference
			  Fedora_API::callPurgeDatastream ($pid, $dsIDName);
			  $dsID = Fedora_API::callCreateDatastream ($pid, $dsIDName, $uploadLocation, $dsLabel, $mimetype, $controlGroup);
			  return $dsID;
//              Fedora_API::callModifyDatastreamByReference ($pid, $dsIDName, $dsIDName, $uploadLocation, $mimetype);
           }
		}
	}

   /**
    * This function uses curl to geta file from a local file location and upload it into the fedora upload manager and calls the addDatastream or modifyDatastream as needed.
	*
    * Developer Note: Mainly used by batch import of a SAN directory
    *
    * @access  public
	* @param string $pid The persistant identifier of the object to be purged
	* @param string $dsIDName The datastream name
	* @param string $local_file_location The location of the file on a local server directory
	* @param string $dsLabel The datastream label
	* @param string $mimetype The mimetype of the datastream
	* @param string $controlGroup The control group of the datastream
	* @param string $dsID The ID of the datastream
    * @return  integer
    */
	function getUploadLocationByLocalRef ($pid, $dsIDName, $local_file_location, $dsLabel, $mimetype, $controlGroup='M', $dsID=NULL) {
        // take out any nasty slashes from the ds name itself
		$dsIDNameOld = $dsIDName;
		if (is_numeric(strpos($dsIDName, "/"))) {
			$dsIDName = substr($dsIDName, strrpos($dsIDName, "/")+1);
		}
		if (is_numeric(strpos($dsIDName, chr(92)))) {
			$dsIDName = substr($dsIDName, strrpos($dsIDName, chr(92))+1);
			if ($dsLabel == $dsIDNameOld) {
				$dsLabel = $dsIDName;
			}
		}
        // fix path if local filename has no path already
//        if (!is_numeric(strpos($local_file_location,'/'))) {
        if (!is_numeric(strpos($local_file_location,"/"))) {
            $local_file_location = APP_TEMP_DIR.$local_file_location;
        }
        // get mimetype
        if ($mimetype == "") {
            $mimetype = Misc::mime_content_type($local_file_location);
        }
        // convert extension to lowercase on dsIDName
		$dsIDName = str_replace(" ", "_", $dsIDName);
		if (is_numeric(strpos($dsIDName, "."))) {
			$filename_ext = strtolower(substr($dsIDName, (strrpos($dsIDName, ".") + 1)));
			$dsIDName = substr($dsIDName, 0, strrpos($dsIDName, ".") + 1).$filename_ext;
		}
		if (!empty($local_file_location) && (trim($local_file_location) != "")) {
		   //Send multipart/form-data via curl
		   $ch = curl_init(APP_FEDORA_UPLOAD_URL);
		   curl_setopt($ch, CURLOPT_VERBOSE, 0);
		   curl_setopt($ch, CURLOPT_HEADER, 0);
		   curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => "@".$local_file_location));
		   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		   $uploadLocation = curl_exec($ch);
		   curl_close ($ch);
		   $uploadLocation = trim(str_replace("\n", "", $uploadLocation));
		   $dsExists = Fedora_API::datastreamExists($pid, $dsIDName);
		   if ($dsExists !== true) {
			  //Call callAddDatastream
			  $dsID = Fedora_API::callCreateDatastream ($pid, $dsIDName, $uploadLocation, $dsLabel, $mimetype, $controlGroup);
			  return $dsID;
		   } elseif (!empty($dsIDName)) {
			  //Call ModifyDatastreamByReference
			  Fedora_API::callPurgeDatastream ($pid, $dsIDName);
			  $dsID = Fedora_API::callCreateDatastream ($pid, $dsIDName, $uploadLocation, $dsLabel, $mimetype, $controlGroup);
			  return $dsID;
//			  Fedora_API::callModifyDatastreamByReference ($pid, $dsIDName, $dsIDName, $uploadLocation, $mimetype);
		   }
		}
	}

   /**
    * This function adds datastreams to object $pid.
	*
    * @access  public
	* @param string $pid The persistant identifier of the object to be purged
	* @param string $dsID The ID of the datastream
	* @param string $uploadLocation The location of the file to add
	* @param string $dsLabel The datastream label
	* @param string $dsState The datastream state
	* @param string $mimetype The mimetype of the datastream
	* @param string $controlGroup The control group of the datastream
    * @return void
    */
	function callAddDatastream ($pid, $dsID, $uploadLocation, $dsLabel, $dsState, $mimetype, $controlGroup='M') {
		if ($mimetype == "") {
			$mimetype = "text/xml";
		}
		$dsIDOld = $dsID;
		if (is_numeric(strpos($dsID, chr(92)))) {
			$dsID = substr($dsID, strrpos($dsID, chr(92))+1);
			if ($dsLabel == $dsIDOld) {
				$dsLabel = $dsID;
			}
		}
	   $versionable = 'false';
	   $parms=array('PID' => $pid, 'dsID' => $dsID, 'altIDs' => array(), 'dsLabel' => $dsLabel, new soapval('versionable', 'boolean', $versionable), 'MIMEType' => $mimetype, 'formatURI' => 'unknown', new soapval('dsLocation', 'string', $uploadLocation), 'controlGroup' => $controlGroup, 'dsState' => 'A', 'logMessage' => 'Added Datastream');//
	   //Call addDatastream
	   Fedora_API::openSoapCall('addDatastream', $parms);
	}

   /**
    * This function adds datastreams to object $pid.
	*
    * @access  public
	* @param string $pid The persistant identifier of the object to be purged
	* @param string $dsIDName The name of the datastream
	* @param string $uploadLocation The location of the file to add
	* @param string $dsLabel The datastream label
	* @param string $dsState The datastream state
	* @param string $mimetype The mimetype of the datastream
	* @param string $controlGroup The control group of the datastream
    * @return void
    */
	function callCreateDatastream($pid, $dsIDName, $uploadLocation, $dsLabel, $mimetype, $controlGroup='M') {
  	   $versionable = 'false';
		$dsIDNameOld = $dsIDName;
  	   	if (is_numeric(strpos($dsIDName, chr(92)))) {
	   		$dsIDName = substr($dsIDName, strrpos($dsIDName, chr(92))+1);
			if ($dsLabel == $dsIDNameOld) {
		   		$dsLabel = $dsIDName;
			}
	   	}
	   $parms=array('PID' => $pid, 'dsID' => $dsIDName, 'altIDs' => array(), 'dsLabel' => $dsLabel, new soapval('versionable', 'boolean', $versionable), 'MIMEType' => $mimetype, 'formatURI' => 'unknown', 'dsLocation' => $uploadLocation, 'controlGroup' => $controlGroup, 'dsState' => 'A', 'logMessage' => 'Added new datastream from Fez');
	   $dsID = Fedora_API::openSoapCall('addDatastream', $parms);
	   return $dsID;
	}

   /**
    *This function creates an array of all the datastreams for a specific object.
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
    * @return array $dsIDListArray The list of datastreams in an array.
    */
    function callGetDatastreams($pid) {
        if (!is_numeric($pid)) {
            $parms=array('pid' => $pid, 'asOfDateTime' => NULL, 'dsState' => NULL);
            $dsIDListArray = Fedora_API::openSoapCall('getDatastreams', $parms);
            if (empty($dsIDListArray) || (is_array($dsIDListArray) && isset($dsIDListArray['faultcode']))) {
                return false;
            }
            sort($dsIDListArray);
            reset($dsIDListArray);
            $returns[$pid] = $dsIDListArray;
            return $dsIDListArray;
        } else {
            return array();
        }
    }

   /**
    *This function creates an array of all the datastreams for a specific object.
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
    * @return array $dsIDListArray The list of datastreams in an array.
    */
    function callListDatastreams($pid) {
        if (!is_numeric($pid)) {
            $parms=array('pid' => $pid, 'asOfDateTime' => NULL);
            $dsIDListArray = Fedora_API::openSoapCallAccess('listDatastreams', $parms);
            sort($dsIDListArray);
            reset($dsIDListArray);
            $returns[$pid] = $dsIDListArray;
            return $dsIDListArray;
        } else {
            return array();
        }
    }


    function objectExists($pid) {
		$parms = array('pid' => $pid);
		$result = Fedora_API::openSoapCall('getObjectXML', $parms, false);
        if (is_array($result) && isset($result['faultcode'])) {
            return false;
        } else {
            return true;
        }
    }

   /**
    * This function creates an array of a specific datastream of a specific object
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The ID of the datastream
    * @return array $dsIDListArray The requested of datastream in an array.
    */
	function callGetDatastream($pid, $dsID) {
	   $parms=array('pid' => $pid, 'dsID' => $dsID);
	   $dsIDListArray = Fedora_API::openSoapCall('getDatastream', $parms);
	   return $dsIDListArray;
	}

   /**
    * Does a datastream with a given ID already exist in an object
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The ID of the datastream to be checked
    * @return boolean
    */
	function datastreamExists ($pid, $dsID) {
		$dsExists = false;
		$rs = Fedora_API::callListDatastreams($pid, $dsID);
		foreach ($rs as $row) {
			if (isset($row['ID']) && $row['ID'] == $dsID) {
				$dsExists = true;
			}
		}
		return $dsExists;
	}

   /**
    * This function creates an array of a specific datastream of a specific object
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The ID of the datastream to be checked
	* @param string $asofDateTime Optional Gets a specified version at a datetime stamp
    * @return array $dsIDListArray The datastream returned in an array
    */
	function callGetDatastreamDissemination($pid, $dsID, $asofDateTime="") {

	   if ($asofDateTime == "") {
		   $parms=array('pid' => $pid, 'dsID' => $dsID);
		} else {
		   $parms=array('pid' => $pid, 'dsID' => $dsID, 'asofDateTime' => $asofDateTime);
		}
	   $dsIDListArray = Fedora_API::openSoapCallAccess('getDatastreamDissemination', $parms);
	   return $dsIDListArray;
	}

   /**
    * This function creates an array of a specific datastream of a specific object
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The ID of the datastream
	* @param boolean $getxml Get as xml
    * @return array $resultlist The requested of datastream in an array.
    */
	function callGetDatastreamContents($pid, $dsID, $getxml = false) {
		$resultlist = array();
		$dsExists = Fedora_API::datastreamExists($pid, $dsID);
		if ($dsExists === true) {			
			$filename = APP_FEDORA_GET_URL."/".$pid."/".$dsID;
			list($blob,$info) = Misc::processURL($filename);
            // check if this is even XML, it might be binary, in which case we'll just return it.
            if ($info['content_type'] != 'text/xml' || $getxml) {
				return $blob;
			}
            // We've checked the mimetype is XML so lets parse it and make a simple array
			if (!empty($blob) && $blob != false) {
				$doc = DOMDocument::loadXML($blob);
				$xpath = new DOMXPath($doc);
				$fieldNodeList = $xpath->query("/*/*");
				foreach ($fieldNodeList as $fieldNode) {
					$resultlist[$fieldNode->nodeName][] = trim($fieldNode->nodeValue);
					// get attributes
					$fieldAttList = $xpath->query("@*",$fieldNode);
					foreach ($fieldAttList as $fieldAtt) {
						$resultlist[$fieldAtt->nodeName][] = trim($fieldAtt->nodeValue);
					}
				}
			}
		}
		return $resultlist;
	}

   /**
    * This function creates an array of specific fields from a specific datastream of a specific object
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The ID of the datastream
	* @param array $returnfields
    * @return array $dsIDListArray The requested of datastream in an array.
    */
	function callGetDatastreamContentsField($pid, $dsID, $returnfields) {
		$resultlist = array();
		$dsExists = Fedora_API::datastreamExists($pid, $dsID);
		if ($dsExists == true) {
			$filename = APP_FEDORA_GET_URL."/".$pid."/".$dsID;
			list($xml, $info) = Misc::processURL($filename);
			if (!empty($xml) && $xml != false) {
				$doc = DOMDocument::loadXML($xml);
				$xpath = new DOMXPath($doc);
				$fieldNodeList = $xpath->query("/$dsID/*");
				foreach ($fieldNodeList as $fieldNode) {
					if (in_array($fieldNode->nodeName, $returnfields)) {
						$resultlist[$fieldNode->nodeName][] = trim($fieldNode->nodeValue);
					}
				}
			}
		}
		return $resultlist;
	}

  /**
    * This function modifies inline xml datastreams (ByValue)
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The name of the datastream
	* @param string $state The datastream state
	* @param string $label The datastream label
	* @param string $dsContent The datastream content
	* @param string $mimetype The mimetype of the datastream
	* @param boolean $versionable Whether to version control this datastream or not
    * @return void
    */
	function callModifyDatastreamByValue ($pid, $dsID, $state, $label, $dsContent, $mimetype='text/xml', $versionable="false") {
//		echo "\n\n before tidy for modify ".$dsID." "; echo date("l dS of F Y h:i:s A");
		if ($mimetype == 'text/xml') {
			$config = array(
			  'indent'         => true,
			  'input-xml'   => true,
			  'output-xml'   => true,
			  'wrap'           => 200);

			$tidy = new tidy;
			$tidy->parseString($dsContent, $config, 'utf8');
			$tidy->cleanRepair();
			$dsContent = $tidy;
		}
	    $dsContent = base64_encode(trim($dsContent));
	    $logmsg = 'Modifying datastream from Fez';
		if (empty($versionable)) {
			$versionable = 'false';
		}
		if ($versionable == "true") {
			$versionable = 'true';
		} elseif ($versionable == "false") {
			$versionable = 'false';
		}
		$versionable = 'false'; //overriding this here.
		$parms= array('pid' => $pid, 'dsID' => $dsID, 'altIDs' => array(), 'dsLabel' => $label, new soapval('versionable', 'boolean', $versionable), 'MIMEType' => $mimetype, 'formatURI' => 'unknown',  new soapval("dsContent","base64Binary",$dsContent), 'dsState' => $state, 'logMessage' => $logmsg, 'force' => true);
//		echo "\n\n before open soap call,after tidy and base64encode for modify ".$dsID." "; echo date("l dS of F Y h:i:s A");
	    Fedora_API::openSoapCall('modifyDatastreamByValue', $parms);
//		echo "\n\n after open soal call for modify ".$dsID." "; echo date("l dS of F Y h:i:s A");
	}

  /**
    * This function modifies non-in-line datastreams, either a chunk o'text, a url, or a file.
	*
    * @access  public
	* @param string $pid The persistant identifier of the object
	* @param string $dsID The name of the datastream
	* @param string $dsLabel The datastream label
	* @param string $dsLocation The location of the datastream
    * @return void
    */
	function callModifyDatastreamByReference ($pid, $dsID, $dsLabel, $dsLocation=NULL, $mimetype) {
	   $logmsg = 'Modifying datastream by reference';
	   $versionable = 'false';
	   $parms= array('pid' => $pid, 'dsID' => $dsID, 'altIDs' => array(), 'dsLabel' => $dsLabel, new soapval('versionable', 'boolean', $versionable),  'MIMEType' => $mimetype, 'formatURI' => 'unknown', 'dsLocation' => $dsLocation, 'dsState' => 'A', 'logMessage' => $logmsg, 'force' => true);
	   Fedora_API::openSoapCall('modifyDatastreamByReference', $parms);
	}

  /**
    * This function deletes a datastream
	*
    * @access  public
	* @param string $pid The persistant identifier of the object to be purged
	* @param string $dsID The name of the datastream
	* @param string $endDT The end datetime of the purge
	* @param string $logMessage
	* @param boolean $force
    * @return boolean
    */
	function callPurgeDatastream ($pid, $dsID, $endDT=NULL, $logMessage="Purged Datastream from Fez", $force=false) {
	   $parms= array('PID' => $pid, 'dsID' => $dsID, 'endDT' => $endDT, 'logMessage' => $logMessage, 'force' => $force);
	   return Fedora_API::openSoapCall('purgeDatastream', $parms);
	}

	// This function is not used, as disseminators are not used in Fez, but it will be left in in for now
	function callAddDisseminator ($pid, $dsID, $bDefPID, $bMechPID, $dissLabel, $key) {
	   global $_REQUEST;
	   //Builds a four level namespaced typed array.
	   //$dsBindings[] is used by $bindingMap,
	   //which is used by the soap call $parms.
	   $dsBindings[0] = array('bindKeyName' => $key,
					'bindLabel' => $dissLabel,
					'datastreamID' => $dsID,
					'seqNo' => '0');
	   $bindingMap = array('dsBindMapID'  => 'foo',
					'dsBindMechanismPID' => $bMechPID,
					'dsBindMapLabel' => 'Label for dsBindMapLabel',
					'state' => 'A',
					new soapval('dsBindings', 'DatastreamBinding', $dsBindings[0], '', 'http://www.fedora.info/definitions/1/0/types/'));
	   // soap call parms.
	   $parms=array('pid' => $pid,
					'bDefPid' => $bDefPID,
					'bMechPid' => $bMechPID,
					'dissLabel' => $dissLabel,
					'bDefLabel' => 'Label for bDef',
					'bMechLabel' => 'Label for bMech',
					new soapval('bindingMap', 'DatastreamBindingMap', $bindingMap, '', 'http://www.fedora.info/definitions/1/0/types/'),
					'dissState' => 'A');
	   //Call createDatastream
	   $dissID = Fedora_API::openSoapCall('addDisseminator', $parms);
	   return $dissID;
	}

	// This function is not used, as disseminators are not used in Fez, but it will be left in in for now
	function callGetDisseminators($pid) {
	   /********************************************
	   * This call gets a list of disseminators per
	   * pid.
	   ********************************************/
	   $parms=array('PID' => $pid);
	   return $dissArray = Fedora_API::openSoapCall('getDisseminators', $parms);
	}

   /**
	* Passes as Soap call to the NUSOAP engine through to the Fedora Management webservice API-M
	*
	* @access  public
	* @param array $call The name of the fedora web service to call
	* @param array $parms The parameters
	* @return array $result
	*/
	function openSoapCall ($call, $parms, $debug_error=true) {
	   /********************************************
	   * This is a primary function called by all of
	   * the preceding functions.
	   * $call is the api call to the fedora api-m.
	   ********************************************/
	   $client = new soapclient_internal(APP_FEDORA_MANAGEMENT_API);
	   $client->setCredentials(APP_FEDORA_USERNAME, APP_FEDORA_PWD);
	   $result = $client->call($call, $parms);
       if ($debug_error && is_array($result) && (isset($result['faultcode']) || $call == 'addDatastream')) {
           Error_Handler::logError(array(print_r($result,true),Fedora_API::debugInfo($client, true)),
                   __FILE__,__LINE__);
       }
	   return $result;

	}
   /**
	* Passes as Soap call to the NUSOAP engine through to the Fedora Access webservice API-A
	*
	* @access  public
	* @param array $call The name of the fedora web service to call
	* @param array $parms The parameters
	* @return array $result
	*/
	function openSoapCallAccess ($call, $parms) {
	   /********************************************
	   * This is a primary function called by all of
	   * the preceding functions.
	   * $call is the api call to the fedora api-a.
	   ********************************************/
	   $client = new soapclient_internal(APP_FEDORA_ACCESS_API);
	   $client->setCredentials(APP_FEDORA_USERNAME, APP_FEDORA_PWD);
	   $result = $client->call($call, $parms);
	   //comment the return and uncomment the echo and debugInfo
	   //to see debug statements.

		 echo "<pre>".$result;

	 Fedora_API::debugInfo($client);
     echo "</pre>";
	   return $result;

	}

   /**
	* This function provides SOAP debug statements.
	*
	* @access  public
	* @param array $client The soap object
	* @return void (Outputs debug info to screen).
	*/
	function debugInfo($client, $asString=false) {
        $str =
            '<hr /><b>Debug Information</b><br /><br />'
            .'Request: <xmp>'.$client->request.'</xmp>'
            .'Response: <xmp>'.$client->response.'</xmp>'
            .'Debug log: <pre>'.$client->debug_str.'</pre>';
        if ($asString) {
            return $str;
        } else {
            echo $str;
        }
	}
} // end of Fedora_API Class
?>
