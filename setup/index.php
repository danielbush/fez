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
// XXX: try reading $_ENV['HOSTNAME'] and then ask the user if nothing could be found
// XXX: don't use array_map() when possible, but array_walk instead. array_walk() doesn't create a copy
// XXX: dynamically check the email blob and skips the email if it is bigger than 16mb on PHP4 versions

if (isset($_GET)) {
    $HTTP_POST_VARS = $_POST;
    $HTTP_GET_VARS = $_GET;
    $HTTP_SERVER_VARS = $_SERVER;
    $HTTP_ENV_VARS = $_ENV;
    $HTTP_POST_FILES = $_FILES;
    // seems like PHP 4.1.0 didn't implement the $_SESSION auto-global...
    if (isset($_SESSION)) {
        $HTTP_SESSION_VARS = $_SESSION;
    }
    $HTTP_COOKIE_VARS = $_COOKIE;
}

function checkPermissions($file, $desc, $is_directory = FALSE)
{
    clearstatcache();
    if (!file_exists($file)) {
        if (!$is_directory) {
            // try to create the file ourselves then
            $fp = @fopen($file, 'w');
            if (!$fp) {
                return "$desc does not exist. Please create it and try again.";
            }
            @fclose($fp);
        } else {
            if (!@mkdir($file)) {
                return "$desc does not exist. Please create it and try again.";
            }
        }
    }
    clearstatcache();
    if (!is_writable($file)) {
        if (!stristr(PHP_OS, "win")) {
            // let's try to change the permissions ourselves
            @chmod($file, 0777);
            clearstatcache();
            if (!is_writable($file)) {
                return "$desc is not writable";
            }
        } else {
            return "$desc is not writable";
        }
    }
    if (stristr(PHP_OS, "win")) {
        // need to check whether we can really create files in this directory or not
        // since is_writable() is not trustworthy on windows platforms
        if (is_dir($file)) {
            $fp = @fopen($file . '/dummy.txt', 'w');
            if (!$fp) {
                return "$desc is not writable";
            }
            @fwrite($fp, 'test');
            @fclose($fp);
            // clean up after ourselves
            @unlink($file . '/dummy.txt');
        }
    }
    return "";
}


 function parse_mysql_dump($url, $ignoreerrors = false) {
   $file_content = file($url);
   //print_r($file_content);
   $query = "";
   foreach($file_content as $ln => $sql_line) {
	 $sql_line = replace_table_prefix($sql_line);
     $tsl = trim($sql_line);
     if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) {
       $query .= $sql_line;
       if(preg_match("/;\s*$/", $sql_line)) {
         $result = mysql_query($query);
         if (!$result && !$ignoreerrors) die(mysql_error()." Line:$ln\n");
         $query = "";
       }
     }
   }
  }

function checkRequirements()
{
    $errors = array();

    // check for GD support
    ob_start();
    phpinfo();
    $contents = ob_get_contents();
    ob_end_clean();
    if (!preg_match("/GD Support.*<\/td><td.*>enabled/U", $contents)) {
        $errors[] = "The GD extension needs to be enabled in your PHP.INI (for windows) or configured during source compile (Linux) file in order for Fez to work properly.";
    }
    if (!preg_match("/Tidy support.*<\/th><th.*>enabled/U", $contents)) {
        $errors[] = "The Tidy extension needs to be enabled in your PHP.INI (for windows) or configured during source compile (Linux) file in order for Fez to work properly.";
    }
    if (!preg_match("/CURL support.*<\/td><td.*>enabled/i", $contents)) {
        $errors[] = "The CURL extension needs to be enabled in your PHP.INI (for windows) or configured during source compile (Linux) file in order for Fez to work properly.";
    }
    if (!preg_match("/DOM\/XML.*<\/td><td.*>enabled/U", $contents)) {
        $errors[] = "The DOM extension needs to be enabled in your PHP.INI (for windows) or configured during source compile (Linux) file in order for Fez to work properly.";
    }

    // check for MySQL support
    if (!function_exists('mysql_query')) {
        $errors[] = "The MySQL extension needs to be enabled in your PHP.INI (for windows) or configured during source compile (Linux) file in order for Fez to work properly.";
    }

    // check for the file_uploads php.ini directive
    if (ini_get('file_uploads') != "1") {
        $errors[] = "The 'file_uploads' directive needs to be enabled in your PHP.INI file in order for Fez to work properly.";
    }
    if (ini_get('allow_call_time_pass_reference') != "1") {
        $errors[] = "The 'allow_call_time_pass_reference' directive needs to be enabled in your PHP.INI file in order for Fez to work properly.";
    }
    $error = checkPermissions('../templates_c', "Directory 'templates_c'", TRUE);
    if (!empty($error)) {
        $errors[] = $error;
    }
    $error = checkPermissions('../config.inc.php', "File 'config.inc.php'");
    if (!empty($error)) {
        $errors[] = $error;
    }
    $error = checkPermissions('../error_handler.log', "File 'error_handler.log'");
    if (!empty($error)) {
        $errors[] = $error;
    }
    $error = checkPermissions('../setup.conf.php', "File 'setup.conf.php'");
    if (!empty($error)) {
        $errors[] = $error;
    }
    $error = checkPermissions('../include/private_key.php', "File 'include/private_key.php'");
    if (!empty($error)) {
        $errors[] = $error;
    }

    $html = '';
    if (count($errors) > 0) {
        $html = '<html>
<head>
<style type="text/css">
<!--
.default {
  font-family: Verdana, Arial, Helvetica, sans-serif;
  font-style: normal;
  font-weight: normal;
  font-size: 70%;
}
-->
</style>
</head>
<body>

<br /><br />

<table width="500" bgcolor="#003366" border="0" cellspacing="0" cellpadding="1" align="center">
  <tr>
    <td>
      <table bgcolor="#FFFFFF" width="100%" cellspacing="1" cellpadding="2" border="0">
        <tr>
          <td><img src="../images/icons/error.gif" hspace="2" vspace="2" border="0" align="left"></td>
          <td width="100%" class="default"><span style="font-weight: bold; font-size: 160%; color: red;">Configuration Error:</span></td>
        </tr>
        <tr>
          <td colspan="2" class="default">
            <br />
            <b>The following problems regarding file and/or directory permissions were found:</b>
            <br /><br />
            ' . implode("<br />", $errors) . '
            <br /><br />
            <b>Please provide the appropriate permissions to the user that the web server run as to write in the directories and files specified above.</b>
            <br /><br />
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</body>
</html>';
    }
    return $html;
}

$html = checkRequirements();
if (!empty($html)) {
    echo $html;
    exit;
}

ini_set("include_path", '.');
include_once("../include/Smarty/Smarty.class.php");

$tpl = new Smarty();
$tpl->template_dir = '../templates/en';
$tpl->compile_dir = "../templates_c";
$tpl->config_dir = '';

function replace_table_prefix($str)
{
    global $HTTP_POST_VARS;

    return str_replace('%TABLE_PREFIX%', $HTTP_POST_VARS['db_table_prefix'], $str);
}

function getErrorMessage($type, $message)
{
    if (empty($message)) {
        return '';
    } else {
        if (stristr($message, 'Unknown MySQL Server Host')) {
            return 'Could not connect to the MySQL database server with the provided information.';
        } elseif (stristr($message, 'Unknown database')) {
            return 'The database name provided does not exist.';
        } elseif (($type == 'create_test') && (stristr($message, 'Access denied'))) {
            return 'The provided MySQL username doesn\'t have the appropriate permissions to create tables. Please contact your local system administrator for further assistance.';
        } elseif (($type == 'drop_test') && (stristr($message, 'Access denied'))) {
            return 'The provided MySQL username doesn\'t have the appropriate permissions to drop tables. Please contact your local system administrator for further assistance.';
        }
        return $message;
    }
}

function getDatabaseList($conn)
{
    $db_list = mysql_list_dbs($conn);
    $dbs = array();
    while ($row = mysql_fetch_array($db_list)) {
        $dbs[] = $row['Database'];
    }
    return $dbs;
}

function getUserList($conn)
{
    @mysql_select_db('mysql');
    $res = @mysql_query('SELECT DISTINCT User from user');
    $users = array();
    // if the user cannot select from the mysql.user table, then return an empty list
    if (!$res) {
        return $users;
    }
    while ($row = mysql_fetch_row($res)) {
        $users[] = $row[0];
    }
    return $users;
}

function getTableList($conn)
{
    $res = mysql_query('SHOW TABLES', $conn);
    $tables = array();
    while ($row = mysql_fetch_row($res)) {
        $tables[] = $row[0];
    }
    return $tables;
}

function install()
{
    global $HTTP_POST_VARS;

    clearstatcache();
    // check if config.inc.php in the root directory is writable
    if (!is_writable('../config.inc.php')) {
        return "The file 'config.inc.php' in Fez's root directory needs to be writable by the web server user. Please correct this problem and try again.";
    }
    // gotta check and see if the provided installation path really exists...
    if (!file_exists($HTTP_POST_VARS['path'])) {
        return "The provided installation path could not be found. Please review your information and try again.";
    }
    // need to create a random private key variable
    $private_key = '<?php
$private_key = "' . md5(microtime()) . '";
?>';
    if (!is_writable('../include/private_key.php')) {
        return "The file 'include/private_key.php' needs to be writable by the web server user. Please correct this problem and try again.";
    }
    $fp = fopen('../include/private_key.php', 'w');
    if ($fp === FALSE) {
        return "Could not open the file 'include/private_key.php' for writing. The permissions on the file should be set as to allow the user that the web server runs as to open it. Please correct this problem and try again.";
    }
    $res = fwrite($fp, $private_key);
    if ($fp === FALSE) {
        return "Could not write the configuration information to 'include/private_key.php'. The file should be writable by the user that the web server runs as. Please correct this problem and try again.";
    }
    fclose($fp);
    // check if we can connect
    $conn = @mysql_connect($HTTP_POST_VARS['db_hostname'], $HTTP_POST_VARS['db_username'], $HTTP_POST_VARS['db_password']);
    if (!$conn) {
        return getErrorMessage('connect', mysql_error());
    }
    $db_list = getDatabaseList($conn);
    $db_list = array_map('strtolower', $db_list);
    if (@$HTTP_POST_VARS['create_db'] == 'yes') {
        if (!in_array(strtolower($HTTP_POST_VARS['db_name']), $db_list)) {
            if (!mysql_query('CREATE DATABASE ' . $HTTP_POST_VARS['db_name'], $conn)) {
                return getErrorMessage('create_db', mysql_error());
            }
        }
    } else {
        if ((count($db_list) > 0) && (!in_array(strtolower($HTTP_POST_VARS['db_name']), $db_list))) {
            return "The provided database name could not be found. Review your information or specify that the database should be created in the form below.";
        }
    }
    // create the new user, if needed
    if (@$HTTP_POST_VARS["alternate_user"] == 'yes') {
        $user_list = getUserList($conn);
        if (count($user_list) > 0) {
            $user_list = array_map('strtolower', $user_list);
            if (@$HTTP_POST_VARS["create_user"] == 'yes') {
                if (!in_array(strtolower(@$HTTP_POST_VARS['fez_user']), $user_list)) {
                    if ($HTTP_POST_VARS['db_hostname'] == 'localhost') {
                        $stmt = "GRANT SELECT, UPDATE, DELETE, INSERT ON " . $HTTP_POST_VARS['db_name'] . ".* TO '" . $HTTP_POST_VARS["fez_user"] . "'@localhost IDENTIFIED BY '" . $HTTP_POST_VARS["fez_password"] . "'";
                    } else {
                        $stmt = "GRANT SELECT, UPDATE, DELETE, INSERT ON " . $HTTP_POST_VARS['db_name'] . ".* TO '" . $HTTP_POST_VARS["fez_user"] . "'@'%' IDENTIFIED BY '" . $HTTP_POST_VARS["fez_password"] . "'";
                    }
                    if (!mysql_query($stmt, $conn)) {
                        return getErrorMessage('create_user', mysql_error());
                    }
                }
            } else {
                if (!in_array(strtolower(@$HTTP_POST_VARS['fez_user']), $user_list)) {
                    return "The provided MySQL username could not be found. Review your information or specify that the username should be created in the form below.";
                }
            }
        }
    }
    // check if we can use the database
    if (!mysql_select_db($HTTP_POST_VARS['db_name'])) {
        return getErrorMessage('select_db', mysql_error());
    }
    // check the CREATE and DROP privileges by trying to create and drop a test table
    $table_list = getTableList($conn);
    $table_list = array_map('strtolower', $table_list);

    if (!in_array('fez_test', $table_list)) {
        if (!mysql_query('CREATE TABLE fez_test (test char(1))', $conn)) {
            return getErrorMessage('create_test', mysql_error());
        }
    }
    if (!mysql_query('DROP TABLE fez_test', $conn)) {
        return getErrorMessage('drop_test', mysql_error());
    }
	parse_mysql_dump("schema.sql");
/*$contents = file_get_contents("schema.sql");
//    $contents = implode("", file("schema.sql"));
//    $queries = preg_split("/\;$/", $contents); //now with this regex we will no longer need to massage the ;'s out of the sql schemas
//	unset($queries[count($queries)-1]);
    // COMPAT: the next line requires PHP >= 4.0.6
$contents = replace_table_prefix($contents);
$stmt = $contents;
 if (!mysql_query($stmt, $conn)) {
	 return getErrorMessage('create_table', mysql_error());
	 
 }*/
/*    $queries = array_map("trim", $queries);
    $queries = array_map("replace_table_prefix", $queries);

    foreach ($queries as $stmt) {
        if ((stristr($stmt, 'DROP TABLE')) && (@$HTTP_POST_VARS['drop_tables'] != 'yes')) {
            continue;
        }
        // need to check if a CREATE TABLE on an existing table throws an error
        if (!mysql_query($stmt, $conn)) {
            if (stristr($stmt, 'DROP TABLE')) {
                $type = 'drop_table';
            } else {
                $type = 'create_table';
            }
			
            return getErrorMessage($type, mysql_error()."<br/>".$stmt);
        }
    } */
    // substitute the appropriate values in config.inc.php!!!
    if (@$HTTP_POST_VARS['alternate_user'] == 'yes') {
        $HTTP_POST_VARS['db_username'] = $HTTP_POST_VARS['fez_user'];
        $HTTP_POST_VARS['db_password'] = $HTTP_POST_VARS['fez_password'];
    }
    $config_contents = implode("", file("config.inc.php-example"));
    if (@$HTTP_POST_VARS['ldap'] == 'yes') {
    	$config_contents = str_replace("%{LDAP_SWITCH}%", "ON", $config_contents);		
    } else {
    	$config_contents = str_replace("%{LDAP_SWITCH}%", "OFF", $config_contents);		
	}
	$config_contents = str_replace("%{LDAP_ORGANISATION}%", $HTTP_POST_VARS['ldap_org'], $config_contents);
	$config_contents = str_replace("%{LDAP_ROOT_DN}%", $HTTP_POST_VARS['ldap_root_dn'], $config_contents);
	$config_contents = str_replace("%{LDAP_PREFIX}%", $HTTP_POST_VARS['ldap_prefix'], $config_contents);
	$config_contents = str_replace("%{LDAP_SERVER}%", $HTTP_POST_VARS['ldap_server'], $config_contents);		
	$config_contents = str_replace("%{LDAP_PORT}%", $HTTP_POST_VARS['ldap_port'], $config_contents);		
    $config_contents = str_replace("%{APP_FEDORA_LOCATION}%", $HTTP_POST_VARS['fedora_location'], $config_contents);
    $config_contents = str_replace("%{APP_FEDORA_SSL_LOCATION}%", $HTTP_POST_VARS['fedora_ssl_location'], $config_contents);
    $config_contents = str_replace("%{APP_FEDORA_USERNAME}%", $HTTP_POST_VARS['fedora_username'], $config_contents);
    $config_contents = str_replace("%{APP_FEDORA_PWD}%", $HTTP_POST_VARS['fedora_password'], $config_contents);	
    $config_contents = str_replace("%{APP_ORG_NAME}%", $HTTP_POST_VARS['organisation'], $config_contents);
    $config_contents = str_replace("%{APP_SHORT_ORG_NAME}%", $HTTP_POST_VARS['short_org'], $config_contents);
    $config_contents = str_replace("%{APP_NAME}%", $HTTP_POST_VARS['app_name'], $config_contents);		
    $config_contents = str_replace("%{APP_PATH}%", $HTTP_POST_VARS['path'], $config_contents);
    $config_contents = str_replace("%{APP_SQL_DBHOST}%", $HTTP_POST_VARS['db_hostname'], $config_contents);
    $config_contents = str_replace("%{APP_SQL_DBNAME}%", $HTTP_POST_VARS['db_name'], $config_contents);
    $config_contents = str_replace("%{APP_SQL_DBUSER}%", $HTTP_POST_VARS['db_username'], $config_contents);
    $config_contents = str_replace("%{APP_SQL_DBPASS}%", $HTTP_POST_VARS['db_password'], $config_contents);
    $config_contents = str_replace("%{APP_TABLE_PREFIX}%", $HTTP_POST_VARS['db_table_prefix'], $config_contents);
    $config_contents = str_replace("%{APP_HOSTNAME}%", $HTTP_POST_VARS['hostname'], $config_contents);
    $config_contents = str_replace("%{APP_RELATIVE_URL}%", $HTTP_POST_VARS['relative_url'], $config_contents);
    if (@$HTTP_POST_VARS['is_ssl'] == 'yes') {
        $protocol_type = 'https://';
		$app_https = "ON";
    } else {
        $protocol_type = 'http://';
		$app_https = "OFF";		
    }
    $config_contents = str_replace("%{APP_HTTPS}%", $app_https, $config_contents);
    $config_contents = str_replace("%{PROTOCOL_TYPE}%", $protocol_type, $config_contents);
    $config_contents = str_replace("%{APP_FEDORA_SETUP}%", $HTTP_POST_VARS['fedora_setup'], $config_contents);	
	if (@$HTTP_POST_VARS['fedora_setup'] == 'sslall') { 
		$fedora_apim_protocol_type = 'https://';
		$fedora_apia_protocol_type = 'https://';				
	} else {
		if (@$HTTP_POST_VARS['fedora_setup'] == 'sslapim') { 
			$fedora_apim_protocol_type = 'https://';
		} else {
			$fedora_apim_protocol_type = 'http://';
		}
		$fedora_apia_protocol_type = 'http://';				
	}
    $config_contents = str_replace("%{APP_FEDORA_APIM_PROTOCOL_TYPE}%", $fedora_apim_protocol_type, $config_contents);
    $config_contents = str_replace("%{APP_FEDORA_APIA_PROTOCOL_TYPE}%", $fedora_apia_protocol_type, $config_contents);	
    $fp = fopen('../config.inc.php', 'w');
    if ($fp === FALSE) {
        return "Could not open the file 'config.inc.php' for writing. The permissions on the file should be set as to allow the user that the web server runs as to open it. Please correct this problem and try again.";
    }
    $res = fwrite($fp, $config_contents);
    if ($fp === FALSE) {
        return "Could not write the configuration information to 'config.inc.php'. The file should be writable by the user that the web server runs as. Please correct this problem and try again.";
    }
    fclose($fp);
    return 'success';
}

if (@$HTTP_POST_VARS["cat"] == 'install') {
    $res = install();
    $tpl->assign("result", $res);
}

// check if fez has possibly already been configured.
if (is_file('../config.inc.php')) {
	$str = file_get_contents('../config.inc.php');
    // if we can't find the placeholder for the DBHOST
    if (!strstr($str,'@define("APP_SQL_DBHOST", "%{APP_SQL_DBHOST}%");')) {
        // but we can find that it is defined to something
        if (strstr($str,'@define("APP_SQL_DBHOST"')) {
        	$tpl->assign('maybe_configured', true);
        }
    }
}

$full_url = dirname($HTTP_SERVER_VARS['PHP_SELF']);
$pieces = explode("/", $full_url);
$relative_url = array();
$relative_url[] = '';
foreach ($pieces as $piece) {
    if ((!empty($piece)) && ($piece != 'setup')) {
        $relative_url[] = $piece;
    }
}
$relative_url[] = '';
$relative_url = implode("/", $relative_url);

if (substr($HTTP_SERVER_VARS['DOCUMENT_ROOT'], -1) == '/') {
    $HTTP_SERVER_VARS['DOCUMENT_ROOT'] = substr($HTTP_SERVER_VARS['DOCUMENT_ROOT'], 0, -1);
}
$installation_path = $HTTP_SERVER_VARS['DOCUMENT_ROOT'] . $relative_url;

$tpl->assign("rel_url", $relative_url);
$tpl->assign("installation_path", $installation_path);
if (@$HTTP_SERVER_VARS['HTTPS'] == 'on') {
    $ssl_mode = 'enabled';
} else {
    $ssl_mode = 'disabled';
}
$tpl->assign('ssl_mode', $ssl_mode);
$tpl->assign('fedora_setup_options', array(
    'sslall' => 'ssl-authenticate-all',
    'sslapim' => 'ssl-authenticate-apim',
    'nosslall' => 'no-ssl-authenticate-all',
    'nosslapim' => 'no-ssl-authenticate-apim',
    ));

$tpl->display('setup.tpl.html');
?>
