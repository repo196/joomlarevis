<?php
/**
 * @package SP Page Builder
 * @author JoomShaper http://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2021 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/
//no direct accees
defined ('_JEXEC') or die ('Restricted access');

use \Joomla\CMS\Router\Route;

abstract class SppagebuilderHelperRoute
{

	public static function buildRoute($link)
	{
		// sh404sef
		if( defined('SH404SEF_IS_RUNNING') )
		{
			return JURI::root() . $link;
		}

		return \Joomla\CMS\Router\Route::link('site', $link, $xhtml = true, $ssl = null);
	}

	// Get page route
	public static function getPageRoute($id, $language = 0, $layout = null)
	{
		// Create the link
		$link = 'index.php?option=com_sppagebuilder&view=page&id=' . $id;

		if ($language && $language !== '*' && JLanguageMultilang::isEnabled())
		{		
			$link .= '&lang=' . $language;
		}

		if ($layout)
		{
			$link .= '&layout=' . $layout;
		}

		if ($Itemid = self::getMenuItemId($id))
		{
			$link .= '&Itemid=' . $Itemid;
		}

		return self::buildRoute($link);
	}

	// Get form route
	public static function getFormRoute($id, $language = 0, $Itemid = 0)
	{
		$link = 'index.php?option=com_sppagebuilder&view=form&id=' . (int) $id;

		if ($language && $language !== '*' && JLanguageMultilang::isEnabled())
		{
			$link .= '&lang=' . $language;
		}

		if ($Itemid != 0)
		{
			$link .= '&Itemid=' . $Itemid;
		} else {
			if (self::getMenuItemId($id))
			{
				$link .= '&Itemid=' . self::getMenuItemId($id);
			}
		}

		$link .= '&layout=edit&tmpl=component';

		return self::buildRoute($link);
	}

	// get menu ID
	private static function getMenuItemId($id)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName(array('id')));
		$query->from($db->quoteName('#__menu'));
		$query->where($db->quoteName('link') . ' LIKE '. $db->quote('%option=com_sppagebuilder&view=page&id='. (int) $id .'%'));
		$query->where($db->quoteName('published') . ' = '. $db->quote('1'));
		$db->setQuery($query);
		$result = $db->loadResult();

		if($result) {
			return $result;
		}

		return;
	}
}