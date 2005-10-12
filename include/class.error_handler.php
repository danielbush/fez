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


/**
 * Class to manage all tasks related to error conditions of the site, such as
 * logging facilities or alert notifications to the site administrators.
 *
 * @version 1.0
 * @author Jo�o Prado Maia <jpm@mysql.com>
 */

include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.setup.php");

@define("REPORT_ERROR_FILE", true);

class Error_Handler
{
    /**
     * Logs the specified error
     *
     * @access public
     * @param  string $error_code The error code
     * @param  string $error_msg The error message
     * @param  string $script The script name where the error happened
     * @param  int $line The line number where the error happened
     */
    function logError($error_msg = "", $script = "", $line = "")
    {
        if (APP_DEBUG) {
            $txt = print_r($error_msg, true);
            echo "ERROR: $txt $script $line";
        }
        if (REPORT_ERROR_FILE) {
            Error_Handler::_logToFile($error_msg, $script, $line);
        }
        $setup = Setup::load();
        if (@$setup['email_error']['status'] == 'enabled') {
            Error_Handler::_notify($error_msg, $script, $line);
        }
    }


    /**
     * Notifies site administrators of the error condition
     *
     * @access public
     * @param  string $error_msg The error message
     * @param  string $script The script name where the error happened
     * @param  int $line The line number where the error happened
     */
    function _notify($error_msg = "unknown", $script = "unknown", $line = "unknown")
    {
        global $HTTP_SERVER_VARS;

        $setup = Setup::load();
        $notify_list = trim($setup['email_error']['addresses']);
        if (empty($notify_list)) {
            return false;
        }
        $notify_list = str_replace(';', ',', $notify_list);
        $notify_list = explode(',', $notify_list);

        $subject = APP_SITE_NAME . " - Error found! - " . date("m/d/Y H:i:s");
        $msg = "Hello,\n\n";
        $msg .= "An error was found at " . date("m/d/Y H:i:s") . " (" . time() . ") on line '" . $line . "' of script " . "'$script'.\n\n";
        $msg .= "The error message passed to us was:\n\n";
        if ((is_array($error_msg)) && (count($error_msg) > 1)) {
            $msg .= "'" . $error_msg[0] . "'\n\n";
            $msg .= "A more detailed error message follows:\n\n";
            $msg .= "'" . $error_msg[1] . "'\n\n";
        } else {
            $msg .= "'$error_msg'\n\n";
        }
        $msg .= "That happened on page '" . $HTTP_SERVER_VARS["PHP_SELF"] . "' from IP Address '" . getenv("REMOTE_ADDR") . "' coming from the page (referrer) '" . getenv("HTTP_REFERER") . "'.\n\n";
        $msg .= "Sincerely yours,\nAutomated Error_Handler Class";
        foreach ($notify_list as $notify_email) {
     //       $mail = new Mail_API;
     //       $mail->setTextBody($msg);
     //       $mail->send($setup['smtp']['from'], $notify_email, $subject);
        }
    }


    /**
     * Logs the error condition to a specific file
     *
     * @access public
     * @param  string $error_msg The error message
     * @param  string $script The script name where the error happened
     * @param  int $line The line number where the error happened
     */
    function _logToFile($error_msg = "unknown", $script = "unknown", $line = "unknown")
    {
        global $HTTP_SERVER_VARS;

        if (is_array($error_msg)) {
            $msg = "[" . date("D M d H:i:s Y") . "] Found error '" . $error_msg[0] . "/" . $error_msg[1] . "' on line '" . $line . "' of script '" . $script . "' on page '" . $HTTP_SERVER_VARS["PHP_SELF"] . "'.\n";
        } else {
            $msg = "[" . date("D M d H:i:s Y") . "] Found error '" . $error_msg . "' on line '" . $line . "' of script '" . $script . "' on page '" . $HTTP_SERVER_VARS["PHP_SELF"] . "'.\n";
        }
        $fp = @fopen(APP_ERROR_LOG, "a");
        @fwrite($fp, $msg);
        @fclose($fp);
    }
}

// benchmarking the included file (aka setup time)
if (APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Error_Handler Class');
}
?>
