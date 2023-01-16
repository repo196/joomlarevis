<?php
/**
* @package SP Page Builder
* @author JoomShaper http://www.joomshaper.com
* @copyright Copyright (c) 2010 - 2021 JoomShaper
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or later
*/
//no direct accees
defined ('_JEXEC') or die ('restricted aceess');

$sppb_helper_path = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/helpers/sppagebuilder.php';

if (!file_exists($sppb_helper_path))
{
 return;
}

if(!class_exists('SppagebuilderHelper'))
{
	require_once $sppb_helper_path;
}

// Initiate class to hold plugin events
class plgSpsimpleportfolioSppagebuilder extends JPlugin
{
	// Some params
	var $pluginName = 'sppagebuilder';
	var $pluginNameHumanReadable = 'SP Simple Portfolio - SP Page Builder';

	function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);
	}

	function onSPPortfolioPrepareContent($content, &$item, &$params, $limitstart)
	{
		$input = JFactory::getApplication()->input;
		$option = $input->get('option', '', 'STRING');
		$view = $input->get('view', '', 'STRING');

		$isSppagebuilderEnabled = $this->isSppagebuilderEnabled();
		if ( $isSppagebuilderEnabled )
		{
			if (SppagebuilderHelper::onIntegrationPrepareContent($item->description, $option, $view, $item->id))
			{
				$item->description = SppagebuilderHelper::onIntegrationPrepareContent($item->description, $option, $view, $item->id);
			}
		}
	}

	private function isSppagebuilderEnabled(){
		$db = JFactory::getDbo();
		$db->setQuery("SELECT enabled FROM #__extensions WHERE element = 'com_sppagebuilder' AND type = 'component'");
		return $is_enabled = $db->loadResult();
	}

}
