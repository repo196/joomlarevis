<?php
/**
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

class AngieViewDatabase extends AView
{
	/** @var int Do we have a flag for large tables? */
	public $large_tables = 0;

	public $substep = '';

	public $number_of_substeps = 0;

	public $db;

	/**
	 * Select list for restoring only specific tables
	 *
	 * @var string
	 */
	public $table_list = '';

	public function onBeforeMain()
	{
		$this->loadHelper('select');

		/** @var AngieModelSteps $stepsModel */
		$stepsModel = AModel::getAnInstance('Steps', 'AngieModel', [], $this->container);
		/** @var AngieModelDatabase $dbModel */
		$dbModel = AModel::getAnInstance('Database', 'AngieModel', [], $this->container);

		$this->substep            = $stepsModel->getActiveSubstep();
		$this->number_of_substeps = $stepsModel->getNumberOfSubsteps();
		$this->db                 = $dbModel->getDatabaseInfo($this->substep);
		$this->large_tables       = $dbModel->largeTablesDetected();

		// Do we have a list of tables? If so let's display them to the user
		$tables = isset($this->db->tables) ? $this->db->tables : '';

		if ($tables)
		{
			$table_data = [];

			foreach ($tables as $table)
			{
				$table_data[] = AngieHelperSelect::option($table, $table);
			}

			$select_attribs = ['data-placeholder' => AText::_('DATABASE_LBL_SPECIFICTABLES_LBL'), 'multiple' => 'true', 'size' => 10, 'style' => 'height: 100px; width: 100%'];
			$this->table_list = AngieHelperSelect::genericlist($table_data, 'specific_tables', $select_attribs, 'value', 'text');
		}

		if ($this->large_tables)
		{
			$this->large_tables = round($this->large_tables / (1024 * 1024), 2);
		}

		return true;
	}
}
