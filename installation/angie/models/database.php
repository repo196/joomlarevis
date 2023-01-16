<?php
/**
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

class AngieModelDatabase extends AModel
{
	/**
	 * The databases.json contents
	 *
	 * @var array
	 */
	private $dbjson = array();

	/**
	 * Returns the cached databases.json information, parsing the databases.json
	 * file if necessary.
	 *
	 * @return array
	 */
	public function getDatabasesJson()
	{
		if (empty($this->dbjson))
		{
			$this->dbjson = $this->container->session->get('databases.dbjson', array());

			if (empty($this->dbjson))
			{
				$filename = APATH_INSTALLATION . '/sql/databases.json';

				if (file_exists($filename))
				{
					$raw_data     = file_get_contents($filename);
					$this->dbjson = json_decode($raw_data, true);
				}

				if (!empty($this->dbjson))
				{
					// Add the custom options
					$temp    = array();
					$siteSQL = null;

					foreach ($this->dbjson as $key => $data)
					{
						if (!array_key_exists('dbtech', $data))
						{
							$data['dbtech'] = null;
						}

						// Skip section that have the db tech set to none (flat-file CMS)
						if (strtolower($data['dbtech']) == 'none')
						{
							continue;
						}

						if (!array_key_exists('existing', $data))
						{
							$data['existing'] = 'drop';
						}

						if (!array_key_exists('prefix', $data))
						{
							$data['prefix'] = 'jos_';
						}

						if (!array_key_exists('foreignkey', $data))
						{
							$data['foreignkey'] = true;
						}

						if (!array_key_exists('noautovalue', $data))
						{
							$data['noautovalue'] = true;
						}

						if (!array_key_exists('replace', $data))
						{
							$data['replace'] = false;
						}

						if (!array_key_exists('utf8db', $data))
						{
							$data['utf8db'] = false;
						}

						if (!array_key_exists('utf8tables', $data))
						{
							$data['utf8tables'] = false;
						}

						if (!array_key_exists('utf8mb4', $data))
						{
							$data['utf8mb4'] = defined('ANGIE_ALLOW_UTF8MB4_DEFAULT') ? ANGIE_ALLOW_UTF8MB4_DEFAULT : true;
						}

						if (!array_key_exists('maxexectime', $data))
						{
							$data['maxexectime'] = 5;
						}

						if (!array_key_exists('throttle', $data))
						{
							$data['throttle'] = 250;
						}

						if (!array_key_exists('break_on_failed_create', $data))
						{
							$data['break_on_failed_create'] = true;
						}

						if (!array_key_exists('break_on_failed_insert', $data))
						{
							$data['break_on_failed_insert'] = true;
						}

						// If we are using SQLite, let's replace any token we found inside the dbname index
						if ($data['dbtype'] == 'sqlite')
						{
							$data['dbname'] = str_replace('#SITEROOT#', APATH_ROOT, $data['dbname']);
						}

						if ($key == 'site.sql')
						{
							$siteSQL = $data;
						}
						else
						{
							$temp[ $key ] = $data;
						}
					}

					// Add the site db definition only if it was defined
					if ($siteSQL)
					{
						$temp = array_merge(array('site.sql' => $siteSQL), $temp);
					}

					$this->dbjson = $temp;
				}

                $this->container->session->set('databases.dbjson', $this->dbjson);
			}
		}

		return $this->dbjson;
	}

	/**
	 * Saves the (modified) databases information to the session
	 */
	public function saveDatabasesJson()
	{
        $this->container->session->set('databases.dbjson', $this->dbjson);
	}

	/**
	 * Returns the keys of all available database definitions
	 *
	 * @return array
	 */
	public function getDatabaseNames()
	{
		$dbjson = $this->getDatabasesJson();

		return array_keys($dbjson);
	}

	/**
	 * Returns an object with a database's connection information
	 *
	 * @param   string $key The database's key (name of SQL file)
	 *
	 * @return  null|stdClass
	 */
	public function getDatabaseInfo($key)
	{
		$dbjson = $this->getDatabasesJson();

		if (array_key_exists($key, $dbjson))
		{
			return (object) $dbjson[ $key ];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Sets a database's connection information
	 *
	 * @param   string $key  The database's key (name of SQL file)
	 * @param   mixed  $data The database's data (stdObject or array)
	 */
	public function setDatabaseInfo($key, $data)
	{
		$dbjson = $this->getDatabasesJson();

		$this->dbjson[ $key ] = (array) $data;

		$this->saveDatabasesJson();
	}

	/**
	 * Detects if we have a flag file for large columns; if so it returns its contents (longest query we will have to run)
	 *
	 * @return  int
	 */
	public function largeTablesDetected()
	{
		$file = APATH_INSTALLATION.'/large_tables_detected';

		if (!file_exists($file))
		{
			return 0;
		}

		$bytes  = (int) file_get_contents($file);

		return $bytes;
	}
}
