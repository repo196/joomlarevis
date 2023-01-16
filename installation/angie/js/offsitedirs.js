/*
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

function offsitedirsRunRestoration(key)
{
	// Prime the request data
	var data = {
		'view':   'offsitedirs',
		'task':   'move',
		'format': 'json',
		'key':    key,
		'info':   {}
	};

	// Get the form data and add them to the dbinfo request array
	data.info.target = document.getElementById('target_folder').value;

	// Set up the modal dialog
	document.getElementById('restoration-progress-bar').style.width = '0%';
	document.getElementById('restoration-progress').style.display   = 'block';
	document.getElementById('restoration-success').style.display    = 'none';
	document.getElementById('restoration-error').style.display      = 'none';

	// Open the restoration's modal dialog
	akeeba.Modal.open({
		inherit: '#restoration-dialog',
		width:   '80%',
		lock:    true
	});

	// Start the restoration
	setTimeout(function () {
		akeebaAjax.callJSON(data, databaseParseRestoration, databaseErrorRestoration);
	}, 1000);
}

/**
 * Handles a restoration error message
 */
function databaseErrorRestoration(error_message)
{
	document.getElementById('restoration-progress').style.display  = 'none';
	document.getElementById('restoration-success').style.display   = 'none';
	document.getElementById('restoration-error').style.display     = 'block';
	document.getElementById('restoration-lbl-error').innerHTML     = error_message;
	document.getElementById('akeeba-modal-close').style.visibility = 'visible';
}

/**
 * Parses the restoration result message, updates the restoration progress bar
 * and steps through the restoration as necessary.
 */
function databaseParseRestoration(msg)
{
	if (msg.error !== '')
	{
		// An error occurred
		databaseErrorRestoration(msg.error);

		return;
	}

	if (msg.done == 1)
	{
		// The restoration is complete
		document.getElementById('restoration-progress-bar').style.width = '100%';

		setTimeout(function () {
			document.getElementById('restoration-progress').style.display           = 'none';
			document.getElementById('restoration-success').style.display            = 'block';
			document.getElementById('restoration-error').style.display              = 'none';
		}, 500);
	}
}

function databaseBtnSuccessClick(e)
{
	window.location = document.getElementById('btnSkip').href;
}
