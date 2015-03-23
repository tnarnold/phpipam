<?php

/**
 *
 * Script to verify userentered input and verify it against database
 *
 * If successfull write values to session and go to main page!
 *
 */


/* functions */
require( dirname(__FILE__) . '/../../functions/functions.php');

# initialize user object
$Database 	= new Database_PDO;
$User 		= new User ($Database);
$Result 	= new Result ();


# Authenticate
if( !empty($_POST['ipamusername']) && !empty($_POST['ipampassword']) )  {

	# initialize array
	$ipampassword = array();

	# verify that there are no invalid characters
	if(strpos($_POST['ipamusername'], " ") >0 ) 	{
		$Result->show("danger", _("Invalid characters in username"), true);
	}

	# check failed table
	$cnt = $User->block_check_ip ();

	# check for failed logins and captcha
	if($User->blocklimit > $cnt) {
		// all good
	}
	# count set, captcha required
	elseif(!isset($_POST['captcha'])) {
		write_log( "Login IP blocked", "Login from IP address $ip was blocked because of 5 minute block after 5 failed attempts", 1);
		$Result->show("danger", _('You have been blocked for 5 minutes due to authentication failures'), true);
	}
	# captcha check
	else {
		# check captcha
		if($_POST['captcha']!=$_SESSION['securimage_code_value']) {
			$Result->show("danger", _("Invalid security code"), true);
		}
	}

	# all good, try to authentucate user
	$User->authenticate ($_POST['ipamusername'], $_POST['ipampassword']);
}
# Username / pass not provided
else {
	$Result->show("danger", _('Please enter your username and password'), true);
}

?>
