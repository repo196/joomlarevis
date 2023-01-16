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
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );
jimport('joomla.log.log');

class plgSystemMarcosinterceptor extends JPlugin{
	function plgSystemMarcosinterceptor( &$subject, $config ){
		parent::__construct( $subject, $config );
	}

	function onAfterInitialise(){

		$app = JFactory::getApplication();
		$this->p_dbprefix = $app->getCfg('dbprefix');
		$this->p_raiseError = $this->params->get('raiseerror', 1);
		$this->p_errorCode = intval($this->params->get('errorcode', 500));
		$this->p_errorMsg = $this->params->get('errormsg', 'Internal Server Error');
		$this->p_strictLFI = $this->params->get('strictlfi', 1);
		$this->p_levelLFI = intval($this->params->get('levellfi', 1));
		$this->p_frontEndOnly = $this->params->get('frontendonly', 1);
		$this->p_ignoredExts = $this->params->get('ignoredexts','');
		$this->p_sendNotification = $this->params->get('sendnotification',0);
		$this->p_nameSpaces = $this->params->get('namespaces','GET,POST');
		
		$this->p_ipBlock  = $this->params->get('ipblock', 0);
		$this->p_ipBlockTime  = intval($this->params->get('ipblocktime', 300));
		$this->p_ipBlockCount  = intval($this->params->get('ipblockcount', 3));
		$this->p_ipBlockCount = ($this->p_ipBlockCount < 1 ? 1 : $this->p_ipBlockCount);
		$this->p_debug = intval($this->params->get('debugplugin', 0));
		$this->j25 = version_compare( JVERSION, '3.0', '<' ); // is pre Joomla 3, no exceptions
		if($this->p_debug){
			JLog::addLogger(array('text_file' => 'marcosinterceptor.php'));
		}		
		
		$remoteIP = $_SERVER['REMOTE_ADDR'];
		if (($this->p_frontEndOnly) AND (strpos($_SERVER['REQUEST_URI'], '/administrator') === 0)) return;
		
		if($this->p_ipBlock){
			$db = JFactory::getDBO();
			try{
				// delete expired entries
				$sql = "DELETE FROM `#__mi_iptable` WHERE DATE_ADD(`lasthacktime`, INTERVAL {$this->p_ipBlockTime} SECOND) < NOW() AND `autodelete`=1;";
				$db->setQuery( $sql );
				$db->execute();
				if($this->j25 && $db->getErrorNum()){
					throw new JDatabaseException($db->getErrorMsg(), $db->getErrorNum());
				}

				// verify previous logs
				$sql = "SELECT COUNT(*) from `#__mi_iptable` WHERE ip = '{$remoteIP}' AND `hackcount` >= {$this->p_ipBlockCount}" ;
				$db->setQuery( $sql );
				$db->execute( $sql );
				if($this->j25 && $db->getErrorNum()){
					throw new JDatabaseException($db->getErrorMsg(), $db->getErrorNum());
				}
				if($db->loadResult()){
					// unceremoniously shut down connection
					ob_end_clean();
					header('HTTP/1.0 403 Forbidden');
					header('Status: 403 Forbidden');
					header('Content-Length: 0',true);
					header('Connection: Close');
					exit;
				}
			}catch (Exception $e){
				$msg = 'IP PROTECTION NOT ENABLED! Unexpected mysql error ' . $e->getCode() . ' accessing iptable';
				if($this->p_debug){
					$msg .= ':' . $e->getMessage();
					JLog::add($msg);
				}
				if($this->j25){
					JError::raiseError($e->getCode(), $msg);
					return false;
				}else{
					throw new Exception( $msg, $e->getCode() );
				}
			}
		}
		
				
		$this->p_ignoredExts = explode(',', preg_replace('/\s*/', '', $this->p_ignoredExts));
		if (isset($_REQUEST['option']) AND in_array($_REQUEST['option'], $this->p_ignoredExts)) return;

		$wr=array();
		$path=array();
		foreach(explode(',', $this->p_nameSpaces) as $nameSpace){
			$this->recurseArray($nameSpace, $path, $wr);
		} //namespaces

		
		if($this->p_debug){
			$err = "** begin marco's interceptor\r\n";
			$err .= "REQUEST\r\n";
			$err .= print_r($_REQUEST, true);
			$err .= "GET\r\n";
			$err .= print_r($_GET, true);
			$err .= "ERRORS\r\n";
			$err .= implode("\r\n", $wr);
			$err .= "\r\n** end";
			JLog::add($err);
		}
		
		if(($this->p_ipBlock) AND ($wr)){
			try{
				$db = JFactory::getDBO();
				$sql = "INSERT INTO `#__mi_iptable` (`ip`, `firsthacktime`, `lasthacktime` ) VALUES ('{$remoteIP}', NOW(), NOW()) ON DUPLICATE KEY UPDATE `lasthacktime` = NOW(), `hackcount` = `hackcount` + 1;";
				$db->setQuery( $sql );
				$db->execute( $sql );
				if($this->j25 && $db->getErrorNum()){
					throw new JDatabaseException($db->getErrorMsg(), $db->getErrorNum());
				}
			}catch (Exception $e){
				$msg = 'IP PROTECTION NOT ENABLED! Unexpected mysql error ' . $e->getCode() . ' accessing iptable';
				if($this->p_debug){
					$msg .= ':' . $e->getMessage();
					JLog::add($msg);
				}
				if($this->j25){
					JError::raiseError($e->getCode(), $msg);
					return false;
				}else{
					throw new Exception($msg, $e->getCode());
				}
			}
		}
		
		if(($this->p_sendNotification) AND ($wr)) $this->sendNotification($wr);

		if($this->p_debug){
			// there is not a removeLogger ...
			JLog::addLogger(array());
		}		
		
		if(($this->p_raiseError) AND ($wr)){
			if($this->j25){
				JError::raiseError($this->p_errorCode, $this->p_errorMsg);
				return false;
			}else{
				throw new Exception( $this->p_errorMsg, $this->p_errorCode );
			}
			
		}
		
	}


	
	function recurseArray($namespace, &$path, &$wr){
		static $i=0;$i++;
		$xpath = '_' . $namespace;
		$nsp ='$_' . $namespace;
		global ${$xpath};
		$ref = &${$xpath};
		foreach($path as $p){
			// it looks foolish? yeah, but we have no pointers...
			$ref = &$ref[$p];
		}		
		
		if(count($path)){
			$nsp .="['" . implode("']['", $path) . "']";
		}
		
		if($this->p_debug){
			$err = "** begin marco's interceptor\r\n";
			$err .= "Recursion pass: {$i}: namespace :{$nsp}\r\n";
			$err .= "object:\r\n";
			$err .= print_r($ref, true);
			$err .= "\r\n** end";
			JLog::add($err);
		}
		
		foreach($ref as $k => $v){
			if(is_numeric($v)) continue;
			if(is_array($v)){
				$path[]=$k;
				$this->recurseArray($namespace, $path, $wr);
			}else{	
				/* SQL injection */
				// strip /* comments */
				$a = preg_replace('!/\*.*?\*/!s', ' ', $v); 
				/* union select ... #__users */
				if (preg_match('/UNION(?:\s+ALL)?\s+SELECT/i', $a)){
					$wr[] = "* Union Select {$nsp}['{$k}'] => {$v}"; 
					if(!$this->p_raiseError){
						$v = preg_replace('/UNION(?:\s+ALL)?\s+SELECT/i', '--', $a);
						$ref[$k]=$v;
					}
				}

				/* table name */
				$ta = array ('/(\s+|\.|,)`?(#__)/', '/(\s+|\.|,)`?(jos_)/i', "/(\s+|\.|,)`?({$this->p_dbprefix})/i");
				foreach ($ta as $t){
					if (preg_match($t, $v)){
						if($this->p_debug){
							JLog::add("Match: $v");
						}
						$wr[] = "* Table name in url {$nsp}['{$k}'] => {$v}";
						if(!$this->p_raiseError){
							$v = preg_replace($t, ' --$1', $v);
							$ref[$k]=$v;
						}
					}
				}
				
				/* LFI */
				if ($this->p_strictLFI){
					if (!in_array($k, array('controller', 'view', 'model', 'tmpl', 'layout'))) continue;
				}
				$recurse = str_repeat('\.\.\/', $this->p_levelLFI+1);
				$i=0;
				while (preg_match("/$recurse/", $v)){
					if(!$i) $wr[] = "* Local File Inclusion {$nsp}['{$k}'] => {$v}";
					if(!$this->p_raiseError){
						$v = preg_replace('/\.\.\//', '', $v);
						$ref[$k]=$v;
					}else{
						break;
					}
					$i++;
				}
			}
		}
		array_pop($path);
	}
	
	
	function sendNotification($warnings){
		$app = JFactory::getApplication();
		$p_sendTo = $this->params->get('sendto','');
		if(!$p_sendTo) $p_sendTo = $app->getCfg('mailfrom');
		
		$warning = "** PATTERNS MATCHED (possible hack attempts)\r\n\r\n";
		$warning .= implode("\r\n", $warnings);
		$warning .= "\r\n\r\n\r\n";

		$warning .= "** PAGE / SERVER INFO\r\n";
		$warning .= "\r\n";
		foreach(explode(',', 'REMOTE_ADDR,HTTP_USER_AGENT,REQUEST_METHOD,QUERY_STRING,HTTP_REFERER') as $sg){
			if(!isset($_SERVER[$sg])) continue;
			$warning .= "*{$sg} : {$_SERVER[$sg]}\r\n";
		}
		$warning .= "\r\n\r\n";
		
		$warning .= "** SUPERGLOBALS DUMP (sanitized)\r\n\r\n";
		
		$warning .= '*$_GET DUMP:';
		$warning .= "\r\n";
		$warning .= print_r($_GET, true);

		$warning .= "\r\n\r\n";
		$warning .= '*$_POST DUMP:';
		$warning .= "\r\n";
		$warning .= print_r($_POST, true);

		$warning .= "\r\n\r\n";
		$warning .= '*$_COOKIE DUMP:';
		$warning .= "\r\n";
		$warning .= print_r($_COOKIE, true);

		$warning .= "\r\n\r\n";
		$warning .= '*$_REQUEST DUMP:';
		$warning .= "\r\n";
		$warning .= print_r($_REQUEST, true);
		
		
		$mail = JFactory::getMailer();
		$cfg = JFactory::getConfig();
		$mail->setsender($cfg->get('mailfrom'));
		$mail->addRecipient($p_sendTo);
		$mail->setSubject($cfg->get('sitename') . ' Marco\'s interceptor warning ' );
		$mail->setbody($warning);
		$mail->send();		
	}
}