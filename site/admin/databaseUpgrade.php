<?php

/*
 *	Script to upgrade database
 **************************************/

/* use required functions */
require_once('../../config.php');
require_once('../../functions/functions.php');

/* get all site settings */
$settings = getAllSettings();

/* display only to admin users */
if(!checkAdmin(false)) {
	die('<div class="error">Admin user required!</div>');
}

/* get version */
$version = $_POST['version'];

/* try to upgrade database */
if(upgradeDatabase($version)) {
	print '<div class="success">Database upgraded successfully!</div>';


	/* update vlans and switches from v 0.4 */
	if($version < "0.5") {
		 updateVLANsFromOldVersions();
		 updateSwitchFromOldVersions();
	}
	/* update VLANS from version 0.5 */
	else if($version < "0.6") {
		 updateVLANsFromOldVersions();
	}
}

?>