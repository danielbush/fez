<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004 MySQL AB                                    |
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
// | Authors: Jo�o Prado Maia <jpm@mysql.com>                             |
// +----------------------------------------------------------------------+
//
// @(#) $Id: s.class.prefs.php 1.18 03/12/31 17:29:01-00:00 jpradomaia $
//


/**
 * Class to handle the business logic related to the user preferences
 * available in the application.
 *
 * @version 1.0
 * @author Jo�o Prado Maia <jpm@mysql.com>
 */

include_once(APP_INC_PATH . "class.error_handler.php");
include_once(APP_INC_PATH . "class.user.php");
include_once(APP_INC_PATH . "class.date.php");

class Prefs
{
    /**
     * Method used to get the system-wide default preferences.
     *
     * @access  public
     * @return  string The serialized array of the default preferences
     */
    function getDefaults()
    {
        return serialize(array(
            'updated'                 => 0,
            'closed'                  => 0,
            'emails'                  => 1, // @@@ CK - changed so default notifications is 'emails are associated'
            'files'                   => 0,
            'close_popup_windows'     => 1, //@@@ CK added so default user popup is date
            'receive_assigned_emails' => 1,
            'receive_new_emails'      => 0,
            'timezone'                => 'Australia/Brisbane', //@@@ CK changed to Australia/Brisbane as default
//            'timezone'                => Date_API::getDefaultTimezone(),
            'list_refresh_rate'       => APP_DEFAULT_REFRESH_RATE,
            'emails_refresh_rate'     => APP_DEFAULT_REFRESH_RATE,
            'email_signature'         => '',
            'auto_append_sig'         => 'no'
        ));
    }


    /**
     * Method used to get the preferences set by a specific user.
     *
     * @access  public
     * @param   integer $usr_id The user ID
     * @return  array The preferences
     */
    function get($usr_id)
    {
        static $returns;

        if (!empty($returns[$usr_id])) {
            return $returns[$usr_id];
        }

        $stmt = "SELECT
                    usr_preferences
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "user
                 WHERE
                    usr_id=$usr_id";
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            $res = @unserialize($res);
            // check for the refresh rate variables, and use the default values if appropriate
            if (empty($res['list_refresh_rate'])) {
                $res['list_refresh_rate'] = APP_DEFAULT_REFRESH_RATE;
            }
            if (empty($res['emails_refresh_rate'])) {
                $res['emails_refresh_rate'] = APP_DEFAULT_REFRESH_RATE;
            }
            $returns[$usr_id] = $res;
            return $returns[$usr_id];
        }
    }


    /**
     * Method used to get the email notification related preferences
     * for a specific user.
     *
     * @access  public
     * @param   integer $usr_id The user ID
     * @return  array The preferences
     */
    function getNotification($usr_id)
    {
        $prefs = Prefs::get($usr_id);
        $info = User::getNameEmail($usr_id);
        $prefs["sub_email"] = $info["usr_email"];
        return $prefs;
    }


    /**
     * Method used to set the preferences for a specific user.
     *
     * @access  public
     * @param   integer $usr_id The user ID
     * @return  integer 1 if the update worked, -1 otherwise
     */
    function set($usr_id)
    {
        global $HTTP_POST_VARS, $HTTP_POST_FILES;

        // if the user is trying to upload a new signature, override any changes to the textarea
        if (!empty($HTTP_POST_FILES["file_signature"]["name"])) {
            $HTTP_POST_VARS['signature'] = Misc::getFileContents($HTTP_POST_FILES["file_signature"]["tmp_name"]);
        }

        $data = serialize(array(
            'updated'                 => @$HTTP_POST_VARS['updated'],
            'closed'                  => @$HTTP_POST_VARS['closed'],
            'emails'                  => @$HTTP_POST_VARS['emails'],
            'files'                   => @$HTTP_POST_VARS['files'],
            'close_popup_windows'     => $HTTP_POST_VARS['close_popup_windows'],
            'receive_assigned_emails' => $HTTP_POST_VARS['receive_assigned_emails'],
            'receive_new_emails'      => $HTTP_POST_VARS['receive_new_emails'],
            'timezone'                => $HTTP_POST_VARS['timezone'],
            'list_refresh_rate'       => $HTTP_POST_VARS['list_refresh_rate'],
            'emails_refresh_rate'     => $HTTP_POST_VARS['emails_refresh_rate'],
            'email_signature'         => @$HTTP_POST_VARS['signature'],
            'auto_append_sig'         => @$HTTP_POST_VARS['auto_append_sig']
        ));
        $stmt = "UPDATE
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "user
                 SET
                    usr_preferences='" . Misc::escapeString($data) . "'
                 WHERE
                    usr_id=$usr_id";
        $res = $GLOBALS["db_api"]->dbh->query($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return -1;
        } else {
            return 1;
        }
    }
}

// benchmarking the included file (aka setup time)
if (APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Prefs Class');
}
?>