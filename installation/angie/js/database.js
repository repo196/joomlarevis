/*
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

var databaseKey             = null;
var databaseThrottle        = 100;
var databasePasswordMessage = '';
var databasePrefixMessage   = '';
var databaseHasWarnings     = false;
var databaseLogFile         = '';
var databaseResumeRetry		= 0;
var databaseResumeTimer		= null;

/**
 * Toggles the help text on the page.
 *
 * By default we hide the help text underneath each field because it makes the page look busy. When the user clicks on
 * the Show / hide help we make it appear. Click again, it disappears again.
 */
function toggleHelp()
{
	var elHelpTextAll = document.querySelectorAll('.akeeba-help-text');

	for (var i = 0; i < elHelpTextAll.length; i++)
	{
		var elHelp = elHelpTextAll[i];

		if (elHelp.style.display === 'none')
		{
			elHelp.style.display = 'block';

			continue;
		}

		elHelp.style.display = 'none';
	}
}

/**
 * Begins the database restoration using the filled-in form data
 */
function databaseRunRestoration(key)
{
	// Store the database key (it's used later to step through the restoration)
	databaseKey = key;

	// Prime the request data
	var data = {
		'view':   'dbrestore',
		'task':   'start',
		'format': 'json',
		'key':    databaseKey,
		'dbinfo': {}
	};

	// Get the form data and add them to the dbinfo request array
	data.dbinfo.dbtype                 = document.getElementById('dbtype').value;
	data.dbinfo.dbhost                 = document.getElementById('dbhost').value;
	data.dbinfo.dbuser                 = document.getElementById('dbuser').value;
	data.dbinfo.dbpass                 = document.getElementById('dbpass').value;
	data.dbinfo.dbname                 = document.getElementById('dbname').value;
	data.dbinfo.prefix                 = document.getElementById('prefix').value;
	data.dbinfo.existing               = document.getElementById('existing').value;
	data.dbinfo.foreignkey             = +document.getElementById('foreignkey').checked;
	data.dbinfo.noautovalue            = +document.getElementById('noautovalue').checked;
	data.dbinfo.replace                = +document.getElementById('replace').checked;
	data.dbinfo.utf8db                 = +document.getElementById('utf8db').checked;
	data.dbinfo.utf8tables             = +document.getElementById('utf8tables').checked;
	data.dbinfo.utf8mb4                = +document.getElementById('utf8mb4').checked;
	data.dbinfo.break_on_failed_create = +document.getElementById('break_on_failed_create').checked;
	data.dbinfo.break_on_failed_insert = +document.getElementById('break_on_failed_insert').checked;
	data.dbinfo.maxexectime            = document.getElementById('maxexectime').value;
	data.dbinfo.throttle               = document.getElementById('throttle').value;

	// Now pass the selected tables
	var elSpecificTables = document.getElementById('specific_tables');

	if (elSpecificTables){
		var specificTables   = [];

		for (var i = 0; i < elSpecificTables.length; i++)
		{
			if (elSpecificTables.options[i].selected)
			{
				specificTables.push(elSpecificTables.options[i].value);
			}
		}

		data.specific_tables = specificTables;
	}

	// Apply bounds to the throttle (wait time in msec)
	var databaseThrottle = data.dbinfo.throttle;
	databaseThrottle     = Math.min(databaseThrottle, 100);
	databaseThrottle     = Math.max(databaseThrottle, 60000);

	// Check whether the prefix contains uppercase characters and show a warning
	if (databasePrefixMessage.length && (/[A-Z]{1,}/.test(data.dbinfo.prefix) !== false))
	{
		if (!window.confirm(databasePrefixMessage))
		{
			return;
		}
	}

	// Check whether the password contains non-ASCII characters and show a warning
	if (databasePasswordMessage.length && (/^[a-zA-Z0-9- ]*$/.test(data.dbinfo.dbpass) === false))
	{
		if (!window.confirm(databasePasswordMessage))
		{
			return;
		}
	}

	// Set up the modal dialog
	document.getElementById('restoration-progress-bar').style.width    = '0%';
	document.getElementById('restoration-progress-bar-text').innerText = '0%';
	document.getElementById('restoration-lbl-restored').innerText      = '';
	document.getElementById('restoration-lbl-total').innerText         = '';
	document.getElementById('restoration-lbl-eta').innerText           = '';
	document.getElementById('restoration-progress').style.display      = 'block';
	document.getElementById('restoration-success').style.display       = 'none';
	document.getElementById('restoration-error').style.display         = 'none';
	document.getElementById('restoration-retry').style.display         = 'none';

	// Open the restoration's modal dialog
	akeeba.Modal.open({
		inherit: '#restoration-dialog',
		width:   '80%',
		lock:    true
	});

	// Reset the warnings status and the resume counter
	databaseResumeRetry 									 	  = 0;
	databaseHasWarnings                                           = false;
	databaseLogFile                                               = '';
	document.getElementById('restoration-warnings').style.display = 'none';

	// Start the restoration
	akeebaAjax.callJSON(data, databaseParseRestoration, databaseEndWithErrorRestoration);
}

/**
 * Parses the restoration result message, updates the restoration progress bar
 * and steps through the restoration as necessary.
 */
function databaseParseRestoration(msg)
{
	if (msg.error != '')
	{
		// An error occurred
		databaseErrorRestoration(msg.error);

		return;
	}

	if (msg.done == 1)
	{
		// The restoration is complete
		document.getElementById('restoration-progress').style.display           = 'none';
		document.getElementById('restoration-success').style.display            = 'block';
		document.getElementById('restoration-error').style.display              = 'none';
		document.getElementById('restoration-retry').style.display              = 'none';
		document.getElementById('restoration-success-nowarnings').style.display = 'block';
		document.getElementById('restoration-success-warnings').style.display   = 'none';

		// Display a message if there were any warnings during the restoration
		if (databaseHasWarnings)
		{
			document.getElementById('restoration-success-nowarnings').style.display = 'hide';
			document.getElementById('restoration-success-warnings').style.display   = 'show';
			document.getElementById('restoration-sql-log').innerText                = databaseLogFile;
		}

		return;
	}

	// Step through the restoration
	document.getElementById('restoration-progress').style.display      = 'block';
	document.getElementById('restoration-success').style.display       = 'none';
	document.getElementById('restoration-error').style.display         = 'none';
	document.getElementById('restoration-progress-bar').style.width    = msg.percent + '%';
	document.getElementById('restoration-progress-bar-text').innerText = msg.percent + '%';
	document.getElementById('restoration-lbl-restored').innerText      = msg.restored;
	document.getElementById('restoration-lbl-total').innerText         = msg.total;
	document.getElementById('restoration-lbl-eta').innerText           = msg.eta;

	// Display warning box if necessary (restoration)
	if (!databaseHasWarnings && (msg.errorcount > 0))
	{
		databaseHasWarnings                                             = true;
		databaseLogFile                                                 = msg.errorlog;
		document.getElementById('restoration-warnings').style.display   = 'block';
		document.getElementById('restoration-inprogress-log').innerText = databaseLogFile;
	}

	setTimeout(databaseStepRestoration, databaseThrottle);
}

/**
 * Runs one more restoration step via AJAX
 */
function databaseStepRestoration()
{
	var data = {
		'view':   'dbrestore',
		'task':   'step',
		'format': 'json',
		'key':    databaseKey
	};

	akeebaAjax.callJSON(data, databaseParseRestoration, databaseErrorRestoration);
}

function databaseErrorRestoration(error_message)
{
	if (databaseResumeRetry > 3){
		databaseEndWithErrorRestoration(error_message);

		return;
	}

	databaseResumeRetry++;

	resetRetryTimeoutBar()

	// Hide progress and warnings
	document.getElementById("restoration-progress").style.display  = "none";
	document.getElementById("restoration-success").style.display   = "none";
	document.getElementById("restoration-error").style.display     = "none";

	// Setup and show the retry pane
	document.getElementById("restoration-error-message-retry").textContent = error_message;
	document.getElementById("restoration-retry").style.display             = "block";

	// Start the countdown
	startRetryTimeoutBar();
}

/**
 * Handles a restoration error message
 */
function databaseEndWithErrorRestoration(error_message)
{
	document.getElementById('restoration-progress').style.display  = 'none';
	document.getElementById('restoration-success').style.display   = 'none';
	document.getElementById('restoration-error').style.display     = 'block';
	document.getElementById('restoration-retry').style.display     = 'none';
	document.getElementById('restoration-lbl-error').innerHTML     = error_message;
	document.getElementById('akeeba-modal-close').style.visibility = 'visible';
}

function databaseBtnSuccessClick(e)
{
	window.location = document.getElementById('btnSkip').href;
}

/**
 * Cancel the automatic resumption of a restoration attempt after an AJAX error has occurred
 */
function cancelResume()
{
	// Make sure the timer is stopped
	resetRetryTimeoutBar();

	// Kill the backup
	var errorMessage = document.getElementById("restoration-error-message-retry").innerHTML;
	databaseEndWithErrorRestoration(errorMessage);

	return false;
}

function resumeRestoration()
{
	// Make sure the timer is stopped
	resetRetryTimeoutBar();

	// Hide error and retry panels
	document.getElementById("restoration-error").style.display = "none";
	document.getElementById("restoration-retry").style.display = "none";

	// Show progress and warnings
	document.getElementById("restoration-progress").style.display = "block";

	// Restart the restoration
	databaseStepRestoration()

	return false;
}

function resetRetryTimeoutBar()
{
	clearInterval(databaseResumeTimer);

	document.getElementById("akeeba-retry-timeout").textContent = "30";
}

function startRetryTimeoutBar()
{
	var remainingSeconds = 30;

	databaseResumeTimer = setInterval(function ()
	{
		remainingSeconds--;
		document.getElementById(
			"akeeba-retry-timeout").textContent = remainingSeconds.toFixed(0);

		if (remainingSeconds == 0)
		{
			clearInterval(databaseResumeTimer);
			resumeRestoration();
		}
	}, 1000);
}

function toggleSpecificTables(){
	var specificTablesCbx = document.getElementById('specific_tables_cbx');

	if (!specificTablesCbx)
	{
		return;
	}

	specificTablesCbx.addEventListener('click', function(){
		var elTables = document.getElementById('specific_tables');

		if (this.checked){
			$('#specific_tables_holder').show();
			$('#specific_tables').chosen();
		}
		else{
			var elements = elTables.options;

			for(var i = 0; i < elements.length; i++){
				elements[i].selected = false;
			}

			$('#specific_tables_holder').hide();
			$('#specific_tables').chosen('destroy');
		}
	});

	var select_all = document.getElementById('specific_tables_addall');
	var clear_all = document.getElementById('specific_tables_clearall');

	select_all.addEventListener('click', function(){
		$('#specific_tables option').prop('selected', true);
		$('#specific_tables').chosen().trigger('chosen:updated');
	});

	clear_all.addEventListener('click', function(){
		$('#specific_tables option:selected').removeAttr('selected');
		$('#specific_tables').chosen().trigger('chosen:updated');
	});

}

akeeba.System.documentReady(function (){
	toggleSpecificTables();

	document.getElementById("restoration-cancel-resume").addEventListener("click", cancelResume);
	document.getElementById("restoration-resume").addEventListener("click", resumeRestoration);
});
