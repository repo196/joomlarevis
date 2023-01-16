<?php
/*
 * A plugin that sanitize input on all external request
 * Plugin for Joomla 2.5 & 3.x - Version 1.6
 * License: http://www.gnu.org/copyleft/gpl.html
 * Authors: marco maria leoni
 * Copyright (c) 2010 - 2015 marco maria leoni web consulting - http: www.mmleoni.net
 * Project page at http://www.mmleoni.net/sql-iniection-lfi-protection-plugin-for-joomla
 * *** Last update: Nov 14th, 2015 ***
*/

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

class plgSystemMarcosinterceptorInstallerScript{
	function preflight( $type, $parent ) {
		$j25 = version_compare( JVERSION, '3.0', '<' ); // is pre Joomla 3, no exceptions
		$db = JFactory::getDBO();
		try{
			$db->setQuery('SELECT COUNT(*) FROM `#__extensions` WHERE `element` = \'marcosinterceptor\';' );
			if(!$db->loadResult()){
				if($j25 && $db->getErrorNum()){
					throw new JDatabaseException($db->getErrorMsg(), $db->getErrorNum());
				}					
				// first install, nothing to do
				return;
			}else{
				echo '<ul><li>Updating marcosinterceptor...</li>';
			}
		}catch (Exception $e){
			JFactory::getApplication()->enqueueMessage('Error accessing DB ip protection may not work!!', 'error');
			return;
		}		
		
		if( version_compare( $parent->get("manifest")->version, '1.6', '<=' ) ) {
			try{
				echo '<li>try to update ip table</li>';
				$db->setQuery('ALTER TABLE `#__mi_iptable` CHANGE `ip` `ip` VARCHAR(40) NOT NULL COMMENT \'ip to char\';' );
				$db->query();
				if($j25 && $db->getErrorNum()){
					throw new JDatabaseException($db->getErrorMsg(), $db->getErrorNum());
				}
				echo '<li>ip table updated</li></ul>';
			}catch (Exception $e){
				JFactory::getApplication()->enqueueMessage('Error updating ip table, uninstall plugin and remove #__mi_table from DB or ip protection will not work!!', 'error');
				echo '</ul>';
			}
		}
	}
 
	function install( $parent ) {
	}
 
	function update( $parent ) {
	}
 
	function postflight( $type, $parent ) {
		try{
			$db = JFactory::getDBO();
			$db->setQuery('UPDATE `#__extensions` SET `ordering` = -100 WHERE `element` = \'marcosinterceptor\'' );
			$db->query();
		}catch (Exception $e){
		}
	}
 
	function uninstall( $parent ) {
	}
 
}