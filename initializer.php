<?php
/**
 * initializer.php - (CREDITTRACKER) inter-module functions
 *  includes all required files - ex. config, helpers and class loader
 *  ensures access to pages - ex. insecure pages like login or perhaps index (depends on configuration)
 *  enforces security features, - two factor, password expired
 *  established single sign on
 *  redirects where necessary - ex. login page
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
 */
// Config file with Globals & Configurations 
define('APP_MODULE', 'bmidemo');
$config_path = $_SERVER['DOCUMENT_ROOT'] . '/core/config/';
// echo "INITIALIZER: config = " . $config_path . 'config.php' . "<br>"; exit(); 
require($config_path . 'config.php'); 
$module_web_root = SITE_WEB['ROOT'];
$module_app = MODULE['SITE'];

// Loads all required Classes & Helpers 
$initializers = array( 	// NOTE: Please put this list in alphabetical order
 'Class Loader'			=>  DIR['CORE'] . 'class_loader.php',
 'Helpers'			    =>  DIR['CORE'] . 'helpers.php'
);

foreach($initializers as $initializer => $value){
	require_once($value);
}

// Check if website is in maintenance mode or not
MaintenanceMode::checkMaintenanceMode();

// Additional initializing functions
Mustache_Autoloader::register(); // required for Mustache library

// Initialize i18n
$i18n = I18n::getInstance();
$languageSwitcher = new LanguageSwitcher($i18n);


// Instances of classes that can be shared globally
$MUSTACHE_ENGINE = new Mustache_Engine(array(
	'loader' => new Mustache_Loader_FilesystemLoader(CREDITTRACKER_DIR['TEMPLATES']),
	'partials_loader' => new Mustache_Loader_FilesystemLoader(CREDITTRACKER_DIR['PARTIALS']),
	'helpers' => array(
		't' => function($key) { return t($key); }
	)
));

// load session variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nonce = new Nonce();
$nonce->generateNonce(24, APP_MODULE, 30); // nonce is set for 24 chars long and for a half hour
define('NONCE_MODULE', $nonce->getNonceFromSession(APP_MODULE));

// if running overnight scripts, run as LMS Admin and set values for script security (also no redirects)
$is_script = false;
if (in_array(RUNNING_SCRIPT, array("run_overnight_scripts.php", "overnight_scripts.php"))) {
	$is_script = true;
	setSession('script_url', SCRIPT_WEB . RUNNING_SCRIPT);
	setSession(SESSION_USERID_KEY, SCRIPT_USER);
}

// User Session
$oUser = Users::loadUserByUserid(getSession(SESSION_USERID_KEY, '0'));
if ($oUser !== false && $oUser::$user_id !== '0') {
	setSession(SESSION_USERID_KEY, $oUser::$user_id);
	setSession('SESSION_VISIBILITY_VALUES', $oUser::$visibility_values);
} else {
	setSession(SESSION_USERID_KEY, '0');
	setSession('SESSION_VISIBILITY_VALUES', VISIBILITY_VALUES_DEFAULT);
}

switch(getSession('LAST_MODULE')) {
	case $module_app:
	default:
		$app_module_redirect = $module_app;
}

//Filer::logActivity($ips, $_SESSION['SESSION_USERID_KEY']);
if(!$is_script) {
	if ($oUser === false) {
		redirect_secure_pages(CREDITTRACKER_GUEST_PAGES, CREDITTRACKER_WEB_ROOT . 'login.php');
	} else {
		if(RUNNING_SCRIPT != 'login.php') {
			redirect_no_access_module($app_module_redirect, [LMS_ROLES['lms_specialist'],LMS_ROLES['lms_proctor'],LMS_ROLES['lms_distributor'],LMS_ROLES['lms_team_leader'],LMS_ROLES['lms_teacher'],LMS_ROLES['lms_editor'],LMS_ROLES['lms_regional_mgr'],LMS_ROLES['lms_grader'],LMS_ROLES['lms_student']], $oUser, $module_web_root . 'index.php');
			if(RUNNING_SCRIPT != 'login_code.php') redirect_two_factor(AUTH_SETTING['TWO_FACTOR']['CREDITTRACKER'], $oUser, CREDITTRACKER_WEB_ROOT . 'login_code.php');
			if(RUNNING_SCRIPT != 'change_password.php') redirect_password_expired(AUTH_SETTING['PASSWORD_EXPIRED']['CREDITTRACKER'], $oUser, AUTH_SETTING['PASSWORD_EXPIRED']['CREDITTRACKER'], CREDITTRACKER_WEB_ROOT . 'change_password.php?e=1');
		}
	}
} 

// handle spinner
Header::handleSpinner(CREDITTRACKER_SPINNER_PAGES);
Footer::handleBeforeUnloadSpinner(CREDITTRACKER_SPINNER_PAGES);

// echo "DEBUGGING: initialized ended!!!<br>".RUNNING_SCRIPT. "<br>". var_export($oUser);
?>
