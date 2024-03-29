<?php
/*
 * @package   akeebabackup
 * @copyright Copyright (c)2006-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\AkeebaBackup\Administrator\Controller\Mixin;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use RuntimeException;

trait CustomACL
{
	protected function onBeforeExecute(&$task)
	{
		$this->akeebaBackupACLCheck($this->getName(), $this->task);
	}

	/**
	 * Checks if the currently logged in user has the required ACL privileges to access the current view. If not, a
	 * RuntimeException is thrown.
	 *
	 * @return  void
	 */
	protected function akeebaBackupACLCheck($view, $task)
	{
		// Akeeba Backup-specific ACL checks. All views not listed here are limited by the akeeba.configure privilege.
		$viewACLMap = [
			'controlpanel'       => 'core.manage',
			'backup'             => 'akeeba.backup',
			'manage'             => 'core.manage',
			'manage.download'    => 'akeeba.download',
			'manage.remove'      => 'akeeba.download',
			'manage.deletefiles' => 'akeeba.download',
			'manage.showcomment' => 'akeeba.backup',
			'manage.save'        => 'akeeba.download',
			'manage.restore'     => 'akeeba.configure',
			'manage.cancel'      => 'akeeba.backup',
			'upload'             => 'akeeba.backup',
			'remotefiles'        => 'akeeba.download',
			'transfer'           => 'akeeba.download',
		];

		$view = strtolower($view ?? 'controlpanel');
		$task = strtolower($task ?? 'main');

		// Default
		$privilege = 'akeeba.configure';

		// Just the view was found
		if (array_key_exists($view, $viewACLMap))
		{
			$privilege = $viewACLMap[$view];
		}

		// The view AND task was found
		if (array_key_exists($view . '.' . $task, $viewACLMap))
		{
			$privilege = $viewACLMap[$view . '.' . $task];
		}

		// If an empty privilege is defined do not perform any ACL checks
		if (empty($privilege))
		{
			return;
		}

		$user = Factory::getApplication()->getIdentity() ?? (new User());

		if (!$user->authorise($privilege, 'com_akeebabackup'))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

}