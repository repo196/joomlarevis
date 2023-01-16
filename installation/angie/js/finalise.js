/*
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Initialisation of the page
 */
akeeba.System.documentReady(function () {
	akeeba.System.addEventListener(document.getElementById('removeInstallation'), 'click', function (e) {
		finaliseRemoveInstallation();

		return false;
	});
});

/**
 * Try removing the installation directory using an AJAX request
 *
 * @returns void
 */
function finaliseRemoveInstallation()
{
	// Set up the request
	var data = {
		'view':   'finalise',
		'task':   'cleanup',
		'format': 'json'
	};

	// Start the restoration
	akeebaAjax.callJSON(data, finaliseParseMessage, finaliseError);
}

/**
 * Parse the installation directory cleanup message
 *
 * @param    {string|boolean}  msg  The message received from the server
 *
 * @returns void
 */
function finaliseParseMessage(msg)
{
	if (msg === true)
	{
		akeeba.Modal.open({
			inherit: '#success-dialog',
			width:   '80%'
		});
	}
	else
	{
		akeeba.Modal.open({
			inherit: '#error-dialog',
			width:   '80%'
		});
	}
}

/**
 * Handles error messages during the installation directory cleanup
 *
 * @param   {string}  error_message
 *
 * @returns void
 */
function finaliseError(error_message)
{
	akeeba.Modal.open({
		inherit: '#error-dialog',
		width:   '80%'
	});
}
