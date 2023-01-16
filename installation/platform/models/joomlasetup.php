<?php

/**
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Encrypt\Aes;
use Joomla\CMS\Encrypt\AES\OpenSSL;

defined('_AKEEBA') or die();

class AngieModelJoomlaSetup extends AngieModelBaseSetup
{
	/** @inheritDoc */
	public function applySettings()
	{
		$jVersion = $this->container->session->get('jversion', '3.6.0');

		// Apply the Super Administrator changes
		$this->applySuperAdminChanges();

		// Apply server config changes
		$this->applyServerconfigchanges();

		// Get the state variables and update the global configuration
		$stateVars = $this->getStateVariables();
		// -- General settings
		$this->configModel->set('sitename', $stateVars->sitename);
		$this->configModel->set('mailfrom', $stateVars->siteemail);
		$this->configModel->set('fromname', $stateVars->emailsender);
		$this->configModel->set('live_site', $stateVars->livesite);
		$this->configModel->set('cookie_domain', $stateVars->cookiedomain);
		$this->configModel->set('cookie_path', $stateVars->cookiepath);
		$this->configModel->set('tmp_path', $stateVars->tmppath);
		$this->configModel->set('log_path', $stateVars->logspath);
		$this->configModel->set('force_ssl', $stateVars->force_ssl);

		if (version_compare($this->container->session->get('jversion'), '3.2', 'ge'))
		{
			$this->configModel->set('mailonline', $stateVars->mailonline);
		}

		// -- FTP settings
		$this->configModel->set('ftp_enable', ($stateVars->ftpenable ? 1 : 0));
		$this->configModel->set('ftp_host', $stateVars->ftphost);
		$this->configModel->set('ftp_port', $stateVars->ftpport);
		$this->configModel->set('ftp_user', $stateVars->ftpuser);
		$this->configModel->set('ftp_pass', $stateVars->ftppass);
		$this->configModel->set('ftp_root', $stateVars->ftpdir);

		// -- Joomla 4 and later does not have FTP settings.
		if (version_compare($jVersion, '3.999.999', 'ge'))
		{
			$this->configModel->remove('ftp_enable');
			$this->configModel->remove('ftp_host');
			$this->configModel->remove('ftp_port');
			$this->configModel->remove('ftp_user');
			$this->configModel->remove('ftp_pass');
			$this->configModel->remove('ftp_root');
		}

		// -- Database settings
		$connectionVars = $this->getDbConnectionVars();
		$this->configModel->set('dbtype', $connectionVars->dbtype);
		$this->configModel->set('host', $connectionVars->dbhost);
		$this->configModel->set('user', $connectionVars->dbuser);
		$this->configModel->set('password', $connectionVars->dbpass);
		$this->configModel->set('db', $connectionVars->dbname);
		$this->configModel->set('dbprefix', $connectionVars->prefix);

		// Let's get the old secret key, since we need it to update encrypted stored data
		$oldsecret = $this->configModel->get('secret', '');
		$newsecret = $this->genRandomPassword(32);

		// -- Replace Two Factor Authentication first
		$this->updateEncryptedData($oldsecret, $newsecret);
		// -- Now replace the secret key
		$this->configModel->set('secret', $newsecret);
		$this->configModel->saveToSession();

		// Get the configuration.php file and try to save it
		$configurationPHP = $this->configModel->getFileContents();
		$filepath         = APATH_SITE . '/configuration.php';

		if (!@file_put_contents($filepath, $configurationPHP))
		{
			if ($this->configModel->get('ftp_enable', 0))
			{
				// Try with FTP
				$ftphost = $this->configModel->get('ftp_host', '');
				$ftpport = $this->configModel->get('ftp_port', '');
				$ftpuser = $this->configModel->get('ftp_user', '');
				$ftppass = $this->configModel->get('ftp_pass', '');
				$ftproot = $this->configModel->get('ftp_root', '');

				try
				{
					$ftp = AFtp::getInstance($ftphost, $ftpport, ['type' => FTP_AUTOASCII], $ftpuser, $ftppass);
					$ftp->chdir($ftproot);
					$ftp->write('configuration.php', $configurationPHP);
					$ftp->chmod('configuration.php', 0644);
				}
				catch (Exception $exc)
				{
					// Fail gracefully
					return false;
				}

				return true;
			}

			return false;
		}

		return true;
	}

	/** @inheritDoc */
	public function getStateVariables()
	{
		// I have to extend the parent method to include FTP params, too
		$params = (array) parent::getStateVariables();

		$params = array_merge($params, $this->getFTPParamsVars());

		return (object) $params;
	}

	/**
	 * Checks if the current site has an .htaccess and an .htpasswd file in its administrator folder
	 *
	 * @return bool
	 */
	public function hasHtpasswd()
	{
		$files = [
			'administrator/.htaccess',
			'administrator/.htpasswd',
		];

		foreach ($files as $file)
		{
			if (file_exists(APATH_ROOT . '/' . $file))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current site has user-defined configuration files (ie php.ini or .user.ini etc etc)
	 *
	 * @return  bool
	 */
	public function hasPhpIni()
	{
		$files = [
			'.user.ini',
			'.user.ini.bak',
			'php.ini',
			'php.ini.bak',
			'administrator/.user.ini',
			'administrator/.user.ini.bak',
			'administrator/php.ini',
			'administrator/php.ini.bak',
		];

		foreach ($files as $file)
		{
			if (file_exists(APATH_ROOT . '/' . $file))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the protocol we're currently using for restoring the site matches with the value stored for the option
	 * Force SSL. If we're using HTTP and we forced any value, it will return false
	 *
	 * @return bool
	 */
	public function protocolMismatch()
	{
		$uri      = AUri::getInstance();
		$protocol = $uri->toString(['scheme']);

		// Restoring under HTTPS, we're always good to go
		if ($protocol == 'https://')
		{
			return false;
		}

		$site_params = $this->getSiteParamsVars();

		// Force SSL not applied, we're good to go
		if ($site_params['force_ssl'] == 0)
		{
			return false;
		}

		// In any other cases, we have a protocol mismatch: we're restoring under HTTP
		// but we set Force SSL to Entire site or Administrator only
		return true;
	}

	/** @inheritDoc */
	protected function getSiteParamsVars()
	{
		$jVersion = $this->container->session->get('jversion', '3.6.0');

		// Default tmp directory: tmp in the root of the site
		$defaultTmpPath = APATH_ROOT . '/tmp';
		// Default logs directory: logs in the administrator directory of the site
		$defaultLogPath = APATH_ADMINISTRATOR . '/logs';

		// If it's a Joomla! 1.x, 2.x or 3.0 to 3.5 site (inclusive) the default log dir is in the site's root
		if (!empty($jVersion) && version_compare($jVersion, '3.5.999', 'le'))
		{
			// I use log instead of logs because "logs" isn't writeable on many hosts.
			$defaultLogPath = APATH_ROOT . '/log';
		}

		$defaultSSL = 2;

		if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')
		{
			$defaultSSL = 0;
		}

		$ret = [
			'sitename'      => $this->getState('sitename', $this->configModel->get('sitename', 'Restored website')),
			'siteemail'     => $this->getState('siteemail', $this->configModel->get('mailfrom', 'no-reply@example.com')),
			'emailsender'   => $this->getState('emailsender', $this->configModel->get('fromname', 'Restored website')),
			'livesite'      => $this->getState('livesite', $this->configModel->get('live_site', '')),
			'cookiedomain'  => $this->getState('cookiedomain', $this->configModel->get('cookie_domain', '')),
			'cookiepath'    => $this->getState('cookiepath', $this->configModel->get('cookie_path', '')),
			'tmppath'       => $this->getState('tmppath', $this->configModel->get('tmp_path', $defaultTmpPath)),
			'logspath'      => $this->getState('logspath', $this->configModel->get('log_path', $defaultLogPath)),
			'force_ssl'     => $this->getState('force_ssl', $this->configModel->get('force_ssl', $defaultSSL)),
			'mailonline'    => $this->getState('mailonline', $this->configModel->get('mailonline', 1)),
			'default_tmp'   => $defaultTmpPath,
			'default_log'   => $defaultLogPath,
			'site_root_dir' => APATH_ROOT,
		];

		// Let's cleanup the live site url
		if (!class_exists('AngieHelperSetup'))
		{
			require_once APATH_INSTALLATION . '/angie/helpers/setup.php';
		}

		$ret['livesite'] = AngieHelperSetup::cleanLiveSite($ret['livesite']);

		// Deal with tmp and logs path
		if (!@is_dir($ret['tmppath']))
		{
			$ret['tmppath'] = $defaultTmpPath;
		}
		elseif (!@is_writable($ret['tmppath']))
		{
			$ret['tmppath'] = $defaultTmpPath;
		}

		if (!@is_dir($ret['logspath']))
		{
			$ret['logspath'] = $defaultLogPath;
		}
		elseif (!@is_writable($ret['logspath']))
		{
			$ret['logspath'] = $defaultLogPath;
		}

		return $ret;
	}

	/** @inheritDoc */
	protected function getSuperUsersVars()
	{
		$ret = [];

		// Connect to the database
		try
		{
			$db = $this->getDatabase();
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Find the Super User groups
		try
		{
			$query = $db->getQuery(true)
				->select($db->qn('rules'))
				->from($db->qn('#__assets'))
				->where($db->qn('parent_id') . ' = ' . $db->q(0));
			$db->setQuery($query, 0, 1);
			$rulesJSON = $db->loadResult();
			$rules     = json_decode($rulesJSON, true);

			$rawGroups = $rules['core.admin'];
			$groups    = [];

			if (empty($rawGroups))
			{
				return $ret;
			}

			foreach ($rawGroups as $g => $enabled)
			{
				if ($enabled)
				{
					$groups[] = $db->q($g);
				}
			}

			if (empty($groups))
			{
				return $ret;
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user IDs of users belonging to the SA groups
		try
		{
			$query = $db->getQuery(true)
				->select($db->qn('user_id'))
				->from($db->qn('#__user_usergroup_map'))
				->where($db->qn('group_id') . ' IN(' . implode(',', $groups) . ')');
			$db->setQuery($query);
			$rawUserIDs = $db->loadColumn(0);

			if (empty($rawUserIDs))
			{
				return $ret;
			}

			$userIDs = [];

			foreach ($rawUserIDs as $id)
			{
				$userIDs[] = $db->q($id);
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user information for the Super Administrator users
		try
		{
			$query = $db->getQuery(true)
				->select([
					$db->qn('id'),
					$db->qn('username'),
					$db->qn('email'),
				])->from($db->qn('#__users'))
				->where($db->qn('id') . ' IN(' . implode(',', $userIDs) . ')');
			$db->setQuery($query);
			$ret['superusers'] = $db->loadObjectList(0);
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		return $ret;
	}

	/**
	 * Replaces the current version of the .htaccess file with the default one provided by Joomla.
	 * The original contents are saved in a backup file named htaccess.bak
	 *
	 * @return bool
	 */
	protected function replaceHtaccess()
	{
		// If I don't have any .htaccess file there's no point on continuing
		if (!$this->hasHtaccess())
		{
			return true;
		}

		// Fetch the latest version from Github
		$downloader = new ADownloadDownload();
		$contents   = false;

		if ($downloader->getAdapterName())
		{
			$contents = $downloader->getFromURL('https://raw.githubusercontent.com/joomla/joomla-cms/staging/htaccess.txt');
		}

		// If a connection error happens or there are no download adapters we'll use our local copy of the file
		if (empty($contents))
		{
			$contents = file_get_contents(__DIR__ . '/serverconfig/htaccess.txt');
		}

		// First of all let's remove any backup file. Then copy the current contents of the .htaccess file in a
		// backup file. Finally delete the .htaccess file and write a new one with the default contents
		// If any of those steps fails we simply stop
		if (!@unlink(APATH_ROOT . '/htaccess.bak'))
		{
			return false;
		}

		$orig = file_get_contents(APATH_ROOT . '/.htaccess');

		if (!empty($orig))
		{
			if (!file_put_contents(APATH_ROOT . '/htaccess.bak', $orig))
			{
				return false;
			}
		}

		if (file_exists(APATH_ROOT . '/.htaccess'))
		{
			if (!@unlink(APATH_ROOT . '/.htaccess'))
			{
				return false;
			}
		}

		if (!file_put_contents(APATH_ROOT . '/.htaccess', $contents))
		{
			return false;
		}

		return true;
	}

	/**
	 * Applies server configuration changes (removing/renaming server configuration files)
	 */
	private function applyServerconfigchanges()
	{
		if ($this->input->get('removephpini'))
		{
			$this->removePhpini();
		}

		if ($this->input->get('replacewebconfig'))
		{
			$this->replaceWebconfig();
		}

		if ($this->input->get('removehtpasswd'))
		{
			$this->removeHtpasswd(APATH_ROOT . '/administrator');
		}

		$htaccessHandling = $this->getState('htaccessHandling', 'none');
		$this->applyHtaccessHandling($htaccessHandling);
	}

	private function applySuperAdminChanges()
	{
		// Get the Super User ID. If it's empty, skip.
		$id = $this->getState('superuserid', 0);

		if (!$id)
		{
			return false;
		}

		// Get the Super User email and password
		$email     = $this->getState('superuseremail', '');
		$password1 = $this->getState('superuserpassword', '');
		$password2 = $this->getState('superuserpasswordrepeat', '');

		// If the email is empty but the passwords are not, fail
		if (empty($email))
		{
			if (empty($password1) && empty($password2))
			{
				return false;
			}

			throw new Exception(AText::_('SETUP_ERR_EMAILEMPTY'));
		}

		// If the passwords are empty, skip
		if (empty($password1) && empty($password2))
		{
			return false;
		}

		// Make sure the passwords match
		if ($password1 != $password2)
		{
			throw new Exception(AText::_('SETUP_ERR_PASSWORDSDONTMATCH'));
		}

		// Let's load the password compatibility file
		require_once APATH_ROOT . '/installation/framework/utils/password.php';

		// Connect to the database
		$db = $this->getDatabase();

		// Create a new salt and encrypted password (legacy method for Joomla! 1.5.0 through 3.2.0)
		$salt      = $this->genRandomPassword(32);
		$crypt     = md5($password1 . $salt);
		$cryptpass = $crypt . ':' . $salt;

		// Get the Joomla! version. If none was detected we assume it's 1.5.0 (so we can use the legacy method)
		$jVersion = $this->container->session->get('jversion', '1.5.0');

		// If we're restoring Joomla! 3.2.2 or later which fully supports bCrypt then we need to get a bCrypt-hashed
		// password.
		if (version_compare($jVersion, '3.2.2', 'ge'))
		{
			// Create a new bCrypt-bashed password. At the time of this writing (July 2015) Joomla! is using a cost of 10
			$cryptpass = password_hash($password1, PASSWORD_BCRYPT, ['cost' => 10]);
		}

		// Update the database record
		$query = $db->getQuery(true)
			->update($db->qn('#__users'))
			->set($db->qn('password') . ' = ' . $db->q($cryptpass))
			->set($db->qn('email') . ' = ' . $db->q($email))
			->where($db->qn('id') . ' = ' . $db->q($id));
		$db->setQuery($query);
		$db->execute();

		return true;
	}

	/**
	 * Tries to decrypt the TFA configuration, using a different method depending on the Joomla version.
	 *
	 * @param   string  $secret           Site's secret key
	 * @param   string  $stringToDecrypt  Base64-encoded and encrypted, JSON-encoded information
	 *
	 * @return  string  Decrypted, but JSON-encoded, information
	 *
	 * @see     https://github.com/joomla/joomla-cms/pull/12497
	 */
	private function decryptTFAString($secret, $stringToDecrypt)
	{
		static $isSupported = null;

		$aesDecryptor    = $this->getAesAdapter($secret);
		$stringToDecrypt = trim($stringToDecrypt, "\0");

		// Do I have unencrypted data?
		if (!is_null(json_decode($stringToDecrypt, true)))
		{
			return $stringToDecrypt;
		}

		if (is_null($isSupported))
		{
			$isSupported = $this->isEncryptionSupportedJ4() || $this->isEncryptionSupportedJ3();
		}

		if (!$isSupported)
		{
			return '';
		}

		$decryptedConfig = $aesDecryptor->decryptString($stringToDecrypt);
		$decryptedConfig = trim($decryptedConfig, "\0");

		if (!is_null(json_decode($decryptedConfig, true)))
		{
			return $decryptedConfig;
		}

		/**
		 * Special case: Joomla 3 with mCrypt supported. We will try to decrypt the data using mCrypt so that it can
		 * then be upgraded to OpenSSL encryption. Otherwise we return an empty string.
		 */
		if (defined('MCRYPT_RIJNDAEL_128') && class_exists('FOFEncryptAesMcrypt', false))
		{
			$mcrypt = new FOFEncryptAes($secret, 256, 'cbc', null, 'mcrypt');

			if (!$mcrypt->isSupported())
			{
				return '';
			}

			$decryptedConfig = $mcrypt->decryptString($stringToDecrypt);
			$decryptedConfig = trim($decryptedConfig, "\0");

			return !is_null(json_decode($decryptedConfig, true)) ? $decryptedConfig : '';
		}

		return '';
	}

	private function encryptTFAString($secret, $data)
	{
		static $isSupported = null;

		if (empty($data))
		{
			return $data;
		}

		// TFA settings are stored unencrypted in Joomla 4
		if (class_exists('\Joomla\CMS\Encrypt\AES\OpenSSL', false))
		{
			return $data;
		}

		// TFA settings are stored unencrypted in Joomla 3.6.4 and later.
		$jVersion = $this->container->session->get('jversion', '3.6.0');

		if (version_compare($jVersion, '3.6.4', 'ge'))
		{
			return $data;
		}

		if (is_null($isSupported))
		{
			$isSupported = $this->isEncryptionSupportedJ4() || $this->isEncryptionSupportedJ3();
		}

		// If encryption is not supported return the unencrypted data
		if (!$isSupported)
		{
			return $data;
		}

		// Earlier versions: encrypt the data again.
		$aes = $this->getAesAdapter($secret);

		return $aes->encryptString($data);
	}

	private function genRandomPassword($length = 8)
	{
		$salt     = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$base      = strlen($salt);
		$makepass = '';

		// Prefer using random_bytes(), either native or the ParagonIE userland implementation
		if (function_exists('random_bytes'))
		{
			/*
			 * Start with a cryptographic strength random string, then convert it to a string with the numeric base of
			 * the salt. Shift the base conversion on each character so the character distribution is even, and
			 * randomize the start shift so it's not predictable.
			 */
			$random = random_bytes($length + 1);
			$shift = \ord($random[0]);

			for ($i = 1; $i <= $length; ++$i)
			{
				$makepass .= $salt[($shift + \ord($random[$i])) % $base];
				$shift += \ord($random[$i]);
			}

			return $makepass;
		}

		// This legacy code should no longer be called.
		$stat = @stat(__FILE__);

		if (empty($stat) || !is_array($stat))
		{
			$stat = [php_uname()];
		}

		mt_srand(crc32(microtime() . implode('|', $stat)));

		for ($i = 0; $i < $length; $i++)
		{
			$makepass .= $salt[mt_rand(0, $base - 1)];
		}

		return $makepass;
	}

	private function getAesAdapter($key)
	{
		// Joomla 4
		if (class_exists('\Joomla\CMS\Encrypt\AES\OpenSSL', false))
		{
			return new Aes($key, 256, 'cbc');
		}

		// Joomla 3
		return new FOFEncryptAes($key, 256, 'cbc');
	}

	/**
	 * Gets the FTP connection parameters
	 *
	 * @return  array
	 */
	private function getFTPParamsVars()
	{
		$ret = [
			'ftpenable' => $this->getState('enableftp', $this->configModel->get('ftp_enable', 0)),
			'ftphost'   => $this->getState('ftphost', $this->configModel->get('ftp_host', '')),
			'ftpport'   => $this->getState('ftpport', $this->configModel->get('ftp_port', 21)),
			'ftpuser'   => $this->getState('ftpuser', $this->configModel->get('ftp_user', '')),
			'ftppass'   => $this->getState('ftppass', $this->configModel->get('ftp_pass', '')),
			'ftpdir'    => $this->getState('ftpdir', $this->configModel->get('ftp_root', '')),
		];

		return $ret;
	}

	/**
	 * Determine if encryption is supported, Joomla 3 version.
	 *
	 * This method goes through Joomla 3's copy of an ancient FOF 2 release.
	 *
	 * Before Joomla 3.6.4 only mCrypt was supported. However, mCrypt is no longer available on PHP 7 and later. On PHP
	 * 8 you will get a fatal error because the FOFEncryptAes class is trying to initialise itself with the constants
	 * from the mCrypt extension which is not present, therefore the constants are not defined. Using undefined
	 * constants is a fatal error in PHP 8, a deprecated warning in PHP 7 and a notice in PHP 5.
	 *
	 * So we need a more thoughtful approach to avoid getting fatal errors.
	 *
	 * @return mixed
	 */
	private function isEncryptionSupportedJ3()
	{
		if (!class_exists('FOFEncryptAes', false))
		{
			return false;
		}

		// Is this Joomla 3.6.2 or earlier?
		$ancientVersion = !class_exists('FOFEncryptAesOpenssl', true);

		// Joomla <= 3.6.2 and no mcrypt? No encryption supported, by definition.
		if ($ancientVersion && !defined('MCRYPT_RIJNDAEL_128'))
		{
			return false;
		}

		// Joomla <= 3.6.2 and mcrypt available? Perform full auto-detection.
		if ($ancientVersion)
		{
			return FOFEncryptAes::isSupported();
		}

		/**
		 * Joomla 3.6.2 and later is SUPPOSED to automatically switch between mcrypt and OpenSSL depending on what is
		 * available. However, due to a bug, it always uses OpenSSL.
		 *
		 * For the same reasons discussed above, if the OpenSSL extension is not available we will end up with a PHP
		 * error. Therefore we need to perform an initial detection first.
		 */
		if (!defined('OPENSSL_RAW_DATA') || !defined('OPENSSL_ZERO_PADDING'))
		{
			return false;
		}

		$adapter = new FOFEncryptAesOpenssl();

		return $adapter->isSupported();
	}

	/**
	 * Determine if encryption is supported, Joomla 4 version.
	 *
	 * This method goes through the Joomla CMS Encrypt package which has replaced the FOF 2.x methods used in Jooml 3.
	 *
	 * The default implementation of Joomla 4's Aes::isSupported() tries to create an mCrypt adapter first. However,
	 * MCrypt is not supported at all on PHP 7 and later. Furthermore, it initialises its class properties with
	 * values from the constants defined in the mCrypt extension. Since the extension is not available neither are
	 * the constants. On PHP 7 this is not a big deal, it just causes some deprecated notices. On PHP 8, however,
	 * this is a fatal error which breaks the restoration.
	 *
	 * Joomla does not suffer from this bug because it ends up always using the OpenSSL adapter. Therefore this is what
	 * we are going to be doing here as well.
	 *
	 * @return mixed
	 */
	private function isEncryptionSupportedJ4()
	{
		if (!class_exists('\Joomla\CMS\Encrypt\AES\OpenSSL', false))
		{
			return false;
		}

		/**
		 * Joomla 4 is always using OpenSSL for encryption.
		 *
		 * For the same reasons discussed above, if the OpenSSL extension is not available we will end up with a PHP
		 * error. Therefore we need to perform an initial detection first.
		 */
		if (!defined('OPENSSL_RAW_DATA') || !defined('OPENSSL_ZERO_PADDING'))
		{
			return false;
		}


		$adapter = new OpenSSL();

		return $adapter->isSupported();
	}

	/**
	 * Removes any user-defined PHP configuration files (.user.ini or php.ini)
	 *
	 * @return  bool
	 */
	private function removePhpini()
	{
		if (!$this->hasPhpIni())
		{
			return true;
		}

		// First of all let's remove any .bak file
		$files = [
			'.user.ini.bak',
			'php.ini.bak',
			'administrator/.user.ini.bak',
			'administrator/php.ini.bak',
		];

		foreach ($files as $file)
		{
			if (file_exists(APATH_ROOT . '/' . $file))
			{
				// If I get any error during the delete, let's stop here
				if (!@unlink(APATH_ROOT . '/' . $file))
				{
					return false;
				}
			}
		}

		$renameFiles = [
			'.user.ini',
			'php.ini',
			'administrator/.user.ini',
			'administrator/php.ini',
		];

		// Let's use the copy-on-write approach to rename those files.
		// Read the contents, create a new file, delete the old one
		foreach ($renameFiles as $file)
		{
			$origPath = APATH_ROOT . '/' . $file;

			if (!file_exists($origPath))
			{
				continue;
			}

			$contents = file_get_contents($origPath);

			// If I can't create the file let's continue with the next one
			if (!file_put_contents($origPath . '.bak', $contents))
			{
				if (!empty($contents))
				{
					continue;
				}
			}

			unlink($origPath);
		}

		return true;
	}

	/**
	 * Replaces the current version of the web.config file with the default one provided by Joomla.
	 * The original contents are saved in a backup file named web.config.bak
	 *
	 * @return bool
	 */
	private function replaceWebconfig()
	{
		// If I don't have any web.config file there's no point on continuing
		if (!$this->hasWebconfig())
		{
			return true;
		}

		// Fetch the latest version from Github
		$downloader = new ADownloadDownload();
		$contents   = $downloader->getFromURL('https://raw.githubusercontent.com/joomla/joomla-cms/staging/web.config.txt');

		// If a connection error happens, let's use the local version of such file
		if ($contents === false)
		{
			$contents = file_get_contents(__DIR__ . '/serverconfig/web.config.txt');
		}

		// First of all let's remove any backup file. Then copy the current contents of the web.config file in a
		// backup file. Finally delete the web.config file and write a new one with the default contents
		// If any of those steps fails we simply stop
		if (!@unlink(APATH_ROOT . '/web.config.bak'))
		{
			return false;
		}

		$orig = file_get_contents(APATH_ROOT . '/web.config');

		if (!file_put_contents(APATH_ROOT . '/web.config.bak', $orig))
		{
			return false;
		}

		if (!@unlink(APATH_ROOT . '/web.config'))
		{
			return false;
		}

		if (!file_put_contents(APATH_ROOT . '/web.config', $contents))
		{
			return false;
		}

		return true;
	}

	/**
	 * This method will update the data encrypted with the old secret key, encrypting it again using
	 * the new secret key
	 *
	 * @param   string  $oldsecret  Old secret key
	 * @param   string  $newsecret  New secret key
	 *
	 * @return  void
	 */
	private function updateEncryptedData($oldsecret, $newsecret)
	{
		$this->updateTFA($oldsecret, $newsecret);
	}

	private function updateTFA($oldsecret, $newsecret)
	{
		$this->container->session->set('tfa_warning', false);

		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select('COUNT(extension_id)')
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('plugin'))
			->where($db->qn('folder') . ' = ' . $db->q('twofactorauth'))
			->where($db->qn('enabled') . ' = ' . $db->q('1'));
		$count = $db->setQuery($query)->loadResult();

		// No enabled TFA plugin, there is no point in continuing
		if (!$count)
		{
			return;
		}

		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__users'))
			->where($db->qn('otpKey') . ' != ' . $db->q(''))
			->where($db->qn('otep') . ' != ' . $db->q(''));

		$users = $db->setQuery($query)->loadObjectList();

		// There are no users with TFA configured, let's stop here
		if (!$users)
		{
			return;
		}

		// Required for Joomla 3. Otherwise I'll get a blank page.
		if (!defined('FOF_INCLUDED'))
		{
			define('FOF_INCLUDED', 1);
		}

		// Required for Joomla 4. Otherwise I'll get a blank page.
		if (!defined('JPATH_PLATFORM'))
		{
			define('JPATH_PLATFORM', APATH_LIBRARIES);
		}

		/**
		 * Joomla 3: I need to partially include the FOF library, at least as much as I need to have the decryption
		 * support for Two Factor Authentication.
		 *
		 * Joomla 4: I need to include the Encrypt package's files
		 */
		foreach ([
			         APATH_LIBRARIES . '/fof/utils/phpfunc/phpfunc.php',
			         APATH_LIBRARIES . '/fof/encrypt/randvalinterface.php',
			         APATH_LIBRARIES . '/fof/encrypt/randval.php',
			         APATH_LIBRARIES . '/fof/encrypt/aes/interface.php',
			         APATH_LIBRARIES . '/fof/encrypt/aes/abstract.php',
			         APATH_LIBRARIES . '/fof/encrypt/aes/mcrypt.php',
			         APATH_LIBRARIES . '/fof/encrypt/aes/openssl.php',
			         APATH_LIBRARIES . '/fof/encrypt/aes.php',
			         APATH_LIBRARIES . '/src/Encrypt/RandValInterface.php',
			         APATH_LIBRARIES . '/src/Encrypt/RandVal.php',
			         APATH_LIBRARIES . '/src/Encrypt/Aes.php',
			         APATH_LIBRARIES . '/src/Encrypt/AES/AesInterface.php',
			         APATH_LIBRARIES . '/src/Encrypt/AES/AbstractAes.php',
			         APATH_LIBRARIES . '/src/Encrypt/AES/OpenSSL.php',
			         APATH_LIBRARIES . '/src/Encrypt/AES/Mcrypt.php',
		         ] as $filePath)
		{
			if (file_exists($filePath))
			{
				include_once $filePath;
			}
		}

		$isSupported = $this->isEncryptionSupportedJ4() || $this->isEncryptionSupportedJ3();

		foreach ($users as $user)
		{
			$update = (object) [
				'id'     => $user->id,
				'otpKey' => '',
				'otep'   => '',
			];

			list($method, $otpKey) = explode(':', $user->otpKey, 2);

			$oldOtpKey = $otpKey;
			$otpKey = $this->decryptTFAString($oldsecret, $otpKey);
			$otep   = $this->decryptTFAString($oldsecret, $user->otep);

			// No change? Skip over this record.
			if (trim($oldOtpKey) === trim($otpKey))
			{
				continue;
			}

			if (!$isSupported && empty($otpKey))
			{
				// Warn the user that TFA has been disabled for some users.
				$this->container->session->set('tfa_warning', true);
			}

			if (!empty($otpKey))
			{
				$update->otpKey = $method . ':' . $this->encryptTFAString($newsecret, $otpKey);
				$update->otep   = $this->encryptTFAString($newsecret, $otep);
			}

			$db->updateObject('#__users', $update, 'id');
		}
	}
}
