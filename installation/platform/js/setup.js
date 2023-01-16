/*
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

var setupSuperUsers     = {};
var setupDefaultTmpDir  = '';
var setupDefaultLogsDir = '';

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
 * Initialisation of the page
 */
akeeba.System.documentReady(function () {
	// Hook for the Next button
	akeeba.System.addEventListener('btnNext', 'click', function (e) {
		document.forms.setupForm.submit();
		return false;
	});

	// Hook for the “Override tmp and log paths” checkbox
	akeeba.System.addEventListener('usesitedirs', 'click', function (e) {
		setupOverrideDirectories();
	});

	// Hook for the Enable FTP Layer button
	akeeba.System.addEventListener('showFtpOptions', 'click', function () {
		document.getElementById('showFtpOptions').style.display = 'none';
		document.getElementById('hideFtpOptions').style.display = 'block';
		document.getElementById('ftpLayerHolder').style.display = 'block';
		document.getElementById('enableftp').value              = 1;
	});

	// Hook for the Disable FTP Layer button
	akeeba.System.addEventListener('hideFtpOptions', 'click', function () {
		document.getElementById('showFtpOptions').style.display = 'block';
		document.getElementById('hideFtpOptions').style.display = 'none';
		document.getElementById('ftpLayerHolder').style.display = 'none';
		document.getElementById('enableftp').value              = 0;
	});
});

/**
 * Runs whenever the Super User selection changes, displaying the correct SU's parameters on the page
 *
 * @param e
 */
function setupSuperUserChange(e)
{
	var saID   = document.getElementById('superuserid').value;
	var params = {};

	for (var idx = 0; idx < setupSuperUsers.length; idx++)
	{
		var sa = setupSuperUsers[idx];

		if (sa.id === saID)
		{
			params = sa;

			break;
		}
	}

	document.getElementById('superuseremail').value          = '';
	document.getElementById('superuserpassword').value       = '';
	document.getElementById('superuserpasswordrepeat').value = '';
	document.getElementById('superuseremail').value          = params.email;
}

function openFTPBrowser()
{
	var hostname  = document.getElementById('ftphost').value;
	var port      = document.getElementById('ftpport').value;
	var username  = document.getElementById('ftpuser').value;
	var password  = document.getElementById('ftppass').value;
	var directory = document.getElementById('fptdir').value;

	if ((port <= 0) || (port >= 65536))
	{
		port = 21;
	}

	var url = 'index.php?view=ftpbrowser&tmpl=component'
		+ '&hostname=' + encodeURIComponent(hostname)
		+ '&port=' + encodeURIComponent(port)
		+ '&username=' + encodeURIComponent(username)
		+ '&password=' + encodeURIComponent(password)
		+ '&directory=' + encodeURIComponent(directory);

	document.getElementById('browseFrame').src = url;

	akeeba.System.data.set(document.getElementById('browseModal'), 'modal', akeeba.Modal.open({
		inherit: '#browseModal',
		width:   '80%'
	}));
}

function useFTPDirectory(path)
{
	document.getElementById('ftpdir').value = path;

	akeeba.System.data.get(document.getElementById('browseModal'), 'modal').close();
}

function setupOverrideDirectories()
{
	document.getElementById('tmppath').value  = setupDefaultTmpDir;
	document.getElementById('logspath').value = setupDefaultLogsDir;
}
