<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Fez - Digital Repository System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2005, 2006, 2007 The University of Queensland,         |
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
include_once("config.inc.php");
//include_once(APP_INC_PATH . "class.db_api.php");
include_once(APP_INC_PATH . "class.auth.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.record_object.php");

if (((($_SERVER["SERVER_PORT"] != 443) && (APP_HTTPS == "ON"))) && APP_REDIRECT_CHECK != 'OFF') { //should be ssl when using basic auth
	header ("Location: https://".APP_HOSTNAME.APP_RELATIVE_URL."basicview.php"."?".$_SERVER['QUERY_STRING']);
	exit;
}
if (!Auth::isValidSession($session)) { // if user not already logged in
	$ipPool = array();
	if (defined('APP_BASIC_AUTH_IP')) {
		$ipPool = Auth::getBasicAuthIPs();
	}

	# Check pool of Basic Auth IP addresses
	if (defined('APP_BASIC_AUTH_IP') && (!in_array($_SERVER['REMOTE_ADDR'], $ipPool))) {
		$record = new RecordObject($_GET['pid']);
		$canView = $record->canView();
		if ($canView) {
			header("Location: https://" . APP_HOSTNAME . APP_RELATIVE_URL . "view/" . $_GET['pid']);
			exit;
		}
	}
}

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="'.APP_HOSTNAME.'"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'You must login to access this service';
    exit;
} else {
	// Check for basic authentication (over ssl) to bypass authorisation and login the user coming directly to eserv.php (and bypass usual login)
	if (!Auth::isValidSession($session)) { // if user not already logged in
		//print_r($_SERVER); exit;
		if (isset($_SERVER['PHP_AUTH_USER'])) { // check to see if there is some basic auth login..
			$username = $_SERVER['PHP_AUTH_USER'];
			$pw = $_SERVER['PHP_AUTH_PW'];
			if (Auth::isCorrectPassword($username, $pw)) {
				Auth::LoginAuthenticatedUser($username, $pw, false);
				header ("Location: https://".APP_HOSTNAME.APP_RELATIVE_URL."view/".$_GET['pid']);
				exit;
			} else {
				header('WWW-Authenticate: Basic realm="'.APP_HOSTNAME.'"');
				header('HTTP/1.0 401 Unauthorized');
				exit;
			}
		}
	}
}
