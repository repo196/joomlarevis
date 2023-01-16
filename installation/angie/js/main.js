/*
 * ANGIE - The site restoration script for backup archives created by Akeeba Backup and Akeeba Solo
 *
 * @package   angie
 * @copyright Copyright (c)2009-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Initialization. Runs as soon as the DOM is ready.
 */
akeeba.System.documentReady(function ()
{
    // Disable the mobile navigation
    document.getElementById('mobileMenuToggler').setAttribute('disabled', 'disabled');

    // Start the AJAX calls in a way which doesn't make some older browsers choke
    setTimeout(mainStart, 500);
});

/**
 * Ask the server to detect the installed version of the script/CMS we are restoring.
 */
function mainStart() {
    request_data = {
        'view': 'main',
        'task': 'detectversion',
        'format': 'json'
    };
    akeebaAjax.callJSON(request_data, mainGotVersion, mainGotVersion);
}

/**
 * Handler for receiving information about the installed script/CMS version. Also starts the next AJAX query, loading
 * the script/CMS configuration information to the session.
 *
 * @param data
 * @param textStatus
 * @param errorThrown
 */
function mainGotVersion(data, textStatus, errorThrown) {
    setTimeout(mainGetConfig, 1000);
}

/**
 * Ask the server to make an attempt at reading the installed script/CMS configuration and stash it to the session.
 */
function mainGetConfig() {
    request_data = {
        'view': 'main',
        'task': 'getconfig',
        'format': 'json'
    };
    akeebaAjax.callJSON(request_data, mainGotConfig, mainGotConfig);
}

/**
 * The server finished loading the configuration information. Proceed to getting the main page content.
 *
 * @param data
 */
function mainGotConfig(data) {
    setTimeout(mainGetPage, 1000);
}

/**
 * Ask the server to render and send the main page area content.
 */
function mainGetPage() {
    request_data = {
        'view': 'main',
        'task': 'main',
        'layout': 'init',
        'format': 'raw'
    };
    akeebaAjax.callRaw(request_data, mainGotPage, mainGotPage);
}

/**
 * Got the rendered main page area. Let's show it to the user and activate the UI.
 *
 * @param html
 */
function mainGotPage(html) {
    // Put the main content on the page
    $('#mainContent').html(html);

    // Re-enable the mobile navigation (we had disabled it at the top)
    document.getElementById('mobileMenuToggler').removeAttribute('disabled');
}

/**
 * Open a modal dialog to display the README.html file
 */
function mainOpenReadme()
{
    akeeba.System.modalDialog = akeeba.Modal.open({
        iframe: 'README.html',
        width:   '80%',
        height:  '320'
    });
}