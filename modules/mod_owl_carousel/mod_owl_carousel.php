<?php 
// no direct access
defined('_JEXEC') or die;
error_reporting(E_ALL & ~E_NOTICE);

$document 					= JFactory::getDocument();

$document->addStyleSheet(JURI::base() . 'modules/mod_owl_carousel/assets/css/owl.carousel.css');
$document->addStyleSheet(JURI::base() . 'modules/mod_owl_carousel/assets/css/owl.theme.css');
$document->addStyleSheet(JURI::base() . 'modules/mod_owl_carousel/assets/css/owl.transitions.css');
$document->addStyleSheet(JURI::base() . 'modules/mod_owl_carousel/assets/magnific-popup/magnific-popup.css');

$moduleclass_sfx			= $params->get('moduleclass_sfx');
$owl_id 					= "mod_".$module->id;
$owl_jquery_ver				= $params->get('owl_jquery_ver', '1.10.2');
$owl_load_jquery			= (int)$params->get('owl_load_jquery', 1);
$owl_load_base				= (int)$params->get('owl_load_base', 1);


// Options Owl Carousel http://www.owlgraphic.com/owlcarousel/#customizing
//---------------------------------------------------------------------

//basic:
$owl_width_block			= (int)$params->get('owl_width_block', 600);
$owl_items 					= (int)$params->get('owl_items', 1);
$owl_navigation				= $params->get('owl_navigation', 'true');
$owl_pagination				= $params->get('owl_pagination', 'true');
$owl_paginationnumbers		= $params->get('owl_paginationnumbers', 'false');



	
// Load jQuery
//---------------------------------------------------------------------

$owl_script = <<<SCRIPT


var jQowlImg = false;
function initJQ() {
	if (typeof(jQuery) == 'undefined') {
		if (!jQowlImg) {
			jQowlImg = true;
			document.write('<scr' + 'ipt type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/$owl_jquery_ver/jquery.min.js"></scr' + 'ipt>');
		}
		setTimeout('initJQ()', 500);
	}
}
initJQ(); 
 
 if (jQuery) jQuery.noConflict();    
  
  
 

SCRIPT;

if ($owl_load_jquery  > 0) {
	$document->addScriptDeclaration($owl_script);		
}
if ($owl_load_base  > 0) { 
	$document->addCustomTag('<script type = "text/javascript" src = "'.JURI::root().'modules/mod_owl_carousel/assets/js/myjs.js"></script>'); 	
	
	$document->addCustomTag('<script type = "text/javascript" src = "'.JURI::root().'modules/mod_owl_carousel/assets/js/owl.carousel.min.js"></script>'); 	
	
	$document->addCustomTag('<script type = "text/javascript" src = "'.JURI::root().'modules/mod_owl_carousel/assets/magnific-popup/jquery.magnific-popup.js"></script>');
	
	$document->addCustomTag('<script type = "text/javascript" src = "'.JURI::root().'modules/mod_owl_carousel/assets/magnific-popup/jquery.magnific-popup.min.js"></script>');

}

// Load img params
//---------------------------------------------------------------------

$names = array('img', 'alt', 'url', 'target', 'html');
$max = 10;
foreach($names as $name) {
    ${$name} = array();
    for($i = 1; $i <= $max; ++$i)
        ${$name}[] = $params->get($name . $i);
}	
require JModuleHelper::getLayoutPath('mod_owl_carousel', $params->get('layout', 'default'));
?>