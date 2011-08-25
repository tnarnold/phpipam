<?php session_start();?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">

<?php 

/* require functions */
require_once('../functions/loginFunctions.php'); 

/* site config */
require_once('../config.php');

/* get all site details */
$settings = getAllSettings();

/* if title is missing set it to install */
if(!$settings['siteTitle']) {
	$settings['siteTitle'] = "phpipam IP management installation";
}

/* php debugging on/off - ignore notices */
if ($debugging == 0) { ini_set('display_errors', 0); }
else { ini_set('display_errors', 1); }

/* destroy session */
if (isset($_SESSION['ipamusername'])) {
    $logout = true;
    updateLogTable ('User '. $_SESSION['ipamusername'] .' has logged out', 0);
}
session_destroy();

?>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
	<meta name="Description" content=""> 
	<meta name="title" content="<?php print $settings['siteTitle']; ?> | login"> 
	
	<meta name="robots" content="noindex, nofollow"> 
	<meta name="Description" content="IPv4/v6 address management. Please login"> 
  
	<!-- title -->
	<title><?php print $settings['siteTitle']; ?> | login</title>
	
	<!-- css -->
	<link rel="stylesheet" type="text/css" href="../css/style.css" />
	<link rel="shortcut icon" href="../css/images/favicon.ico">
	
	<!-- js -->
	<script type="text/javascript" src="../js/jquery-1.6.2.min.js"></script>
	<script type="text/javascript" src="../js/login.js"></script>
		
</head>


<body>

	<?php
	/* check if database needs upgrade */
	include('../functions/dbInstallCheck.php');

	/* check for support for PHP modules and database connection  */
	$locationPrefix = "../";		//prefix for ccs in case of login page checkPhpBuild.php fails
	include('../functions/checkPhpBuild.php');
	?>

    <!-- jQuery error -->
    <div class="jqueryError"><br><br><br><br><br><br><br>jQuery error!</div>

    <!-- title -->
    <div class="header"><?php print $settings['siteTitle']; ?> | login page</div>

    <!-- loader -->
    <div class="loading">Loading...<br><img src="../css/images/ajax-loader.gif"></div>
    
	<!-- login form -->
	<div id="login"></div>
	
     <!-- contact -->
     <div id="contact"><a href="mailto:<?php print $settings['siteAdminMail']; ?>?subject=Login_problem">Contact</a></div>

    <!-- login response -->
    <div id="loginCheck"><?php if (isset($logout)) print '<div class="success">You have logged out.</div>'; ?></div>


</body>

</html>
