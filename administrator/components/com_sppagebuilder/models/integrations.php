<?php
/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2021 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/
//no direct accees
defined ('_JEXEC') or die ('Restricted access');

use Joomla\CMS\Plugin\PluginHelper;

JLoader::register('SppagebuilderHelperIntegrations', __DIR__ . '/helpers/integrations.php');

class SppagebuilderModelIntegrations extends JModelLegacy
{

	public function toggle($group = '', $name = '')
	{

		$db = JFactory::getDbo();
		$integrations = SppagebuilderHelperIntegrations::integrations();

		if(isset($integrations[$group]))
		{
			$enabled = PluginHelper::isEnabled($group, $name);
			$status = $enabled ? 0 : 1;
			
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$fields = array($db->quoteName('enabled') . ' = ' . $status);

			$conditions = array(
				$db->quoteName('type') . ' = ' . $db->quote('plugin'),
				$db->quoteName('element') . ' = ' . $db->quote($name),
				$db->quoteName('folder') . ' = ' . $db->quote($group)
			);

			$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
			$db->setQuery($query);
			$db->execute();

			return $status;
		}
		else
		{
			return false;
		}
	}
}