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

/**
 * Class to handle the business logic related to the administration
 * of XSD Display Attachments in the system.
 *
 * @version 1.0
 * @author Christiaan Kortekaas <c.kortekaas@library.uq.edu.au>
 * @author Matthew Smith <m.smith@library.uq.edu.au>
 */

include_once(APP_INC_PATH . "class.error_handler.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.record.php");
include_once(APP_INC_PATH . "class.user.php");
include_once(APP_INC_PATH . "class.auth.php");



class XSD_Display_Attach
{

	/**
	 * Method used to get the list of XSD Attachments from a given XSD matching field ID.
	 *
	 * @access  public
	 * @param   integer $xsdmf_id
	 * @return  array The list
	 */
	function getListByXSDMF($xsdmf_id)
	{
		$log = FezLog::get();
		$db = DB_API::get();
		
		$stmt = "SELECT
					*
                 FROM
                    " . APP_TABLE_PREFIX . "xsd_display_attach left join
                    " . APP_TABLE_PREFIX . "xsd_display_matchfields on (att_parent_xsdmf_id=".$db->quote($xsdmf_id, 'INTEGER')." and att_child_xsdmf_id = xsdmf_id)
                    inner join " . APP_TABLE_PREFIX . "xsd_display on (xdis_id = xsdmf_xdis_id)
					";
		$stmt .= " ORDER BY att_order ASC";
		try {
			$res = $db->fetchAll($stmt, array(), Zend_Db::FETCH_ASSOC);
		}
		catch(Exception $ex) {
			$log->err($ex);
			return '';
		}
		return $res;
	}

	/**
	 * Method used to get the details of a specific attached display.
	 *
	 * @access  public
	 * @param   integer $att_id The xsd display attachment ID
	 * @return  array The xsd display attachment details
	 */
	function getDetails($att_id)
	{
		$log = FezLog::get();
		$db = DB_API::get();
		
		$stmt = "SELECT
                    *
                 FROM
                    " . APP_TABLE_PREFIX . "xsd_display_attach
                 WHERE
                    att_id=".$db->quote($att_id, 'INTEGER');
		try {
			$res = $db->fetchRow($stmt, array(), Zend_Db::FETCH_ASSOC);
		}
		catch(Exception $ex) {
			$log->err($ex);
			return '';
		}
		return $res;
	}

	/**
	 * Method used to remove a given list of XSD Display Attachments.
	 *
	 * @access  public
	 * @return  boolean
	 */
	function remove()
	{
		$log = FezLog::get();
		$db = DB_API::get();
		
		$items = @implode(", ", $_POST["items"]);

		$stmt = "DELETE FROM
                    " . APP_TABLE_PREFIX . "xsd_display_attach
                 WHERE
                    att_id  IN (" . Misc::arrayToSQLBindStr($_POST["items"]) . ")";
		try {
			$db->query($stmt, $_POST["items"]);
		}
		catch(Exception $ex) {
			$log->err($ex);
			return false;
		}
		return true;
	}


	/**
	 * Method used to add a new XSD Display Attachment to the system.
	 *
	 * @access  public
	 * @return  integer 1 if the insert worked, -1 otherwise
	 */
	function insert()
	{
		$log = FezLog::get();
		$db = DB_API::get();
		
		$stmt = "INSERT INTO
                    " . APP_TABLE_PREFIX . "xsd_display_attach
                 (
                    att_parent_xsdmf_id,
                    att_child_xsdmf_id,
					att_order
                 ) VALUES (
                    " . $db->quote($_POST["att_parent_xsdmf_id"], 'INTEGER') . ",
                    " . $db->quote($_POST["att_child_xsdmf_id"], 'INTEGER') . ",
                    " . $db->quote($_POST["att_order"], 'INTEGER') . "
                 )";
		try {
			$db->query($stmt);
		}
		catch(Exception $ex) {
			$log->err($ex);
			return -1;
		}
	}


	/**
	 * Method used to update a XSD Display Attachment in the system.
	 *
	 * @access  public
	 * @return  integer 1 if the insert worked, -1 otherwise
	 */
	function update()
	{
		$log = FezLog::get();
		$db = DB_API::get();
		
		$stmt = "UPDATE
                    " . APP_TABLE_PREFIX . "xsd_display_attach
                 SET 
                    att_parent_xsdmf_id = " . $db->quote($_POST["att_parent_xsdmf_id"], 'INTEGER') . ",
                    att_child_xsdmf_id = " . $db->quote($_POST["att_child_xsdmf_id"], 'INTEGER') . ",
                    att_order = " . $db->quote($_POST["att_order"], 'INTEGER') . "
                 WHERE att_id = " . $db->quote($_POST["att_id"], 'INTEGER');
		try {
			$db->query($stmt);
		}
		catch(Exception $ex) {
			$log->err($ex);
			return -1;
		}
		return 1;
	}
}
