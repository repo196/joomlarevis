<?php
/**
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

class AngieViewFinalise extends AView
{
	public function onBeforeMain()
	{
        $this->container->application->getDocument()->addScriptDeclaration(<<<ENDSRIPT
var akeebaAjax = null;
$(document).ready(function(){
    akeebaAjax = new akeebaAjaxConnector('index.php');

    akeebaAjax.callJSON({
        'view'   : 'runscripts',
        'format' : 'raw'
    });
});
ENDSRIPT
);
		$model = $this->getModel();

		$this->showconfig = $model->getState('showconfig', 0);

		if ($this->showconfig)
		{
			$this->configuration = AModel::getAnInstance('Configuration', 'AngieModel', array(), $this->container)->getFileContents();
		}

        if($this->container->session->get('tfa_warning', false))
        {
            $this->extra_warning  = '<div class="alert alert-block alert-error">';
            $this->extra_warning .=     '<h4 class="alert-heading">'.AText::_('FINALISE_TFA_DISABLED_TITLE').'</h4>';
            $this->extra_warning .=     '<p>'.AText::_('FINALISE_TFA_DISABLED_BODY').'</p>';
            $this->extra_warning .= '</div>';
        }

		return true;
	}
}
