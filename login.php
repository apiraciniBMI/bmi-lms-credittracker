<?php
/**
 * login.php - login page for CREDITTRACKER
 * @author    David Piracini <dpiracini@buildingmedia.com>
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
 * @copyright 2022 
 */
define('APP_MODULE', 'credit_tracker');
require($_SERVER['DOCUMENT_ROOT'] . '/CREDITTRACKER/initializer.php');

setSession('LOGIN_PAGE', WEB_ROOT . 'CREDITTRACKER/' . RUNNING_SCRIPT);
if(getSession(SESSION_USERID_KEY)) {
	if(!get('e', '')) redirect(CREDITTRACKER_WEB_ROOT . 'index.php');
}
// CREDITTRACKER GOOGLE, FACEBOOK, TWITTER OAUTH CLIENT SSO SERVICE
require DIR["LIBRARIES"] . 'hybridauth-3.8.2/src/autoload.php';
require DIR["LIBRARIES"] . 'hybridauth-3.8.2/examples/example_06/config.php';

use Hybridauth\Hybridauth;
$hybridauth = new Hybridauth(AUTH_SETTING['OAUTH']['SITE']);
$adapters = $hybridauth->getConnectedAdapters();


Header::setBodyClass('dGrid-dashboard-bg');
Header::setHeaderClass('global_header');
echo $MUSTACHE_ENGINE->render('header.mustache', Header::assembleHeaderInfo());  

/* BEGIN DASHBOARD */
$dashboard = new Dashboarder(true);
$dashboard->startDashboard('dashboard-testing');

/*  BEGIN HEADER */
$dashboard->startHeader('dashboard-navmenu');
switch(getSession('LAST_MODULE')) {
	case MODULE['SITE']:
	default:
		$header_config = Header::prepareMenuItems(SITE_HEADER_CONFIG);
}
unset($header_config['top_nav_bar']);
unset($header_config['search_icon']);
$header_config = Header::prepareMenuItems($header_config);
Header::setActiveMenuItem($header_config['top_nav_bar']);
$header_content = $MUSTACHE_ENGINE->render('dashboard_navmenu_horizontal', $header_config); // build and display header
$dashboard->endHeader($header_content);
/*  END HEADER */

/*  BEGIN BODY */
$dashboard->startBody('dashboard-navmenu', 'dGrid-signup-and-registration-body');
$oGrid = new Gridder();
$oGrid->startGrid('homepage-layout-grid', 12, 12, 'homepage-layout-grid');

// process post
if (postrequest()) {
    $activity_log_content = [
		'activity_url' => $_SERVER['REQUEST_URI']
		,'activity_type' => EVENT['LOGIN_ATTEMPT']
	];

	$password = post('password');
	$email = Form::cleanEmail(post('email'));

	//if (!csrf_check(false)) exit("not allowed"); 
	if ($email && $password) { // check for values

		// is password right? check against db regusers or er::PasswordsEqual
		$login_row = DB::QueryOneRow("SELECT p.user_id, p.first_name, p.last_name, p.email_address, p.password, p.email_address
		FROM people AS p 
		WHERE p.email_address = '{{email}}' AND p.is_active "
		, compact('email')); 

		$matched = false;

		if ($login_row) {
			$db_password = $login_row['password'];

			if (Users::PasswordsEqual($password, $db_password)) {
				$matched = true;
			}else{
				$matched = false;
			}
		}
		
		if ($matched) {
			// get email here as the getSession variable is populated with it as seemingly standard
			$loginUsername  = $login_row['email_address'];
			$loginUID  = $login_row['user_id'];

			// record last login datetime after successful login
			DB::NoSelectQuery("UPDATE people SET last_login = NOW() WHERE user_id = {$loginUID}");

			session_regenerate_id(TRUE);
			$_SESSION['SID'] = session_id();
			$_SESSION[SESSION_USERID_KEY] = $loginUID;	

			// back to previous url, if exists
			$back_url = getSession('login_back_url');
			
			if ($back_url) {
				if (strpos($back_url, "http") === false) {
					if (SERVER_HTTPS)
						$back_url = "https://" . SERVER_NAME . $back_url;
					else
						$back_url = "http://" . SERVER_NAME . $back_url;
				}
			}

			$oUser = Users::loadUserByUserid($loginUID);
			switch (true) {
				case (has_access_module(MODULE['CREDITTRACKER'], [LMS_ROLES['lms_student']], $oUser)):
					$target_redirect = ($back_url && !str_contains($back_url, 'login.php')) ? $back_url : CREDITTRACKER_WEB_ROOT;
					$activity_log_content = [
						'activity_url' => $_SERVER['REQUEST_URI']
						,'activity_type' => EVENT['CREDITTRACKER_SIGN_ON']
						,'user_id' => ($loginUID) ? $loginUID : ''
						,'is_error' => EVENT['SUCCESS']
					];
					Filer::appendActivityLog($activity_log_content);
					removeSession('login_back_url');
					redirect($target_redirect); // redirect on success
					break;
				default:
					$msg_error = "Something went wrong with your account settings. Please contact the LMS Administrator.";
					$activity_log_content = [
						'activity_url' => $_SERVER['REQUEST_URI']
						,'activity_type' => EVENT['CREDITTRACKER_SIGN_ON']
						,'activity_text' => "ERROR! " . basename(__FILE__) . ":" . __LINE__
						,'user_id' => ($loginUID) ? $loginUID : ''
						,'is_error' => EVENT['FAILURE']
					];
					Filer::appendActivityLog($activity_log_content);
					 // do not redirect, stay on login page on error
					break;
			}
		} else {	// no match
			$msg_error = "The combination of email/password you entered is incorrect. Passwords are case sensitive. Please try again.";
		}
	} else {
		$msg_error = "The combination of email/password you entered is incorrect. Passwords are case sensitive. Please try again.";
	}
	if ($msg_error) {
		$activity_log_content['is_error'] = EVENT['FAILURE'];
        Filer::appendActivityLog($activity_log_content);
	}
} else {
    $email = '';
    $password = '';
	$msg_error = getSession('login_msg_error');
	removeSession('login_msg_error');
}

$oForm = new AjaxForm();

/* Common styles variables */
$dashboard_admin_row_class = 'cardRow dGrid-dashboard-admin-form-field';
$container_class = 'formField';
$checkbox_class = 'dGrid-dashboard-admin-checkbox';
$checkbox_container_class = $dashboard_admin_row_class . ' checkbox ' . $container_class;
$datepicker_class = 'datepicker';
$registration_and_login_label_class = ' dGrid-registration-and-login-label';
$input_with_logo_container_class = $container_class . ' dGrid-input-with-logo-container reduced-margins';
$lock_logo_container_class = $input_with_logo_container_class . ' lock';
$user_logo_container_class = $input_with_logo_container_class . ' user';
$mail_logo_container_class = $input_with_logo_container_class . ' mail';
$no_margin_form_field_class = $dashboard_admin_row_class . ' reduced-margins';

$submit_button_html = '
<div class="dGrid-common-login-submit-button-container">
    <p><a href="' . CREDITTRACKER_WEB_ROOT . 'password.php">FORGOT PASSWORD?</a></p>
    <img class="dGrid-image-as-button" src="' . CLIENT_WEB['ROOT'] . 'templates/images/login-button.png" alt="Login" data-onclick="htmlSubmitForm" data-onclick-args=\'' . json_encode(['this']) . '\'>
</div>
';

$hybrid_auth_login_html = "";
// foreach ($hybridauth->getProviders() as $name) { 
//     if (!isset($adapters[$name])) {
//         $hybrid_auth_login_html .= '<a href="' . AUTH_SETTING['OAUTH']['SITE']['callback'] . '?provider=' . $name . '"><img src="' . BNP_WEB_ROOT . '/templates/images/' . strtolower($name) . '-icon.png" alt="Login with ' . $name . '" data-onclick="htmlSubmitForm" data-onclick-args=\'' . json_encode(['this']) . '\'></a>';
//     }
// }

$create_account_and_hybrid_auth_login_html = '
<div class="dGrid-common-create-account-container">
    <p class="dGrid-common-create-account-line-1"><a href="' . CREDITTRACKER_WEB_ROOT . 'registration.php">NO ACCOUNT? CREATE ONE HERE.</a></p>
</div>
<div class="dGrid-common-login-hybrid-auth-container">
    ' . $hybrid_auth_login_html . '
</div>
';

// check if client form field override exists
if(isset($override_form_file) && file_exists($override_form_file)) {
	include($override_form_file); // use this form definition instead of default below
} else {
	/* Form Config */
	$oFormConfig = [ 
		'form' => [
			'container_class' => 'dGrid-dashboard-admin-form-container'
			,'class' => ''
			,'id' => 'CREDITTRACKER-login-form'
			,'name' => ''
			,'enctype' => ''
			,'method' => "POST"
			,'heading' => ''
			,'get_id' => ''
			,'csrf_input_name' => ''
			,'csrf_tokens' => ''
			,'sections' => [
				0 => [
					'section_name' => 'login_inputs'
					,'heading' => ''
					,'section_rows' => []
					,'table_name' => 'people'
					,'table_id_field_name' => 'user_id'
					,'table_id_field_value' => ''
					,'form_fields' => [
						'heading'                   => array('label' => '', 'width' => 8, 'type' => 'html', 'html' => $header_and_logo_html)
						, 'email_address'           => array('label' => '', 'id' => 'email-address', 'width' => 8, 'type' => 'text', 'post_name' => 'email', 'placeholder' => 'Email Address *', 'required' => 1, 'validation' => 'validateExistingEmailAddress', 'show_error_tooltip' => true, 'class' => $dashboard_admin_row_class, 'class' => $no_margin_form_field_class, 'container_class' => $mail_logo_container_class, 'label_class' => $registration_and_login_label_class, 'automatic_value' => $email)
						, 'password'                => array('label' => 'Password', 'id' => 'password', 'width' => 8, 'post_name' => 'password', 'placeholder' => 'Password *', 'type' => 'password', 'required' => 1, 'show_error_tooltip' => true, 'class' => $no_margin_form_field_class, 'container_class' => $lock_logo_container_class, 'label_class' => $registration_and_login_label_class, 'automatic_value' => $password)
						, 'submit_button_html'      => array('label' => '', 'width' => 8, 'type' => 'html', 'html' => $submit_button_html)
					]
				]
			]
		]
	]; 
}

$aRenderedForm = $oForm->renderForm($oFormConfig);
$aRenderedForm['body_class'] = 'dGrid-common-login-body-content';
$aRenderedForm['form']['msg_error'] = $msg_error;
$aRenderedForm['input'] = $create_account_and_hybrid_auth_login_html;
$body_content = $MUSTACHE_ENGINE->render('dashboard_body_content', $aRenderedForm);   

/* START BODY FOOTER AREA */
$oGrid->startArea('dashboard-body-footer-area', 12, 1, 'dashboard-body-footer-area');
$aConfig = SITE_FOOTER_CONFIG;
$html_content = $MUSTACHE_ENGINE->render('partials/dashboard_body_footer', $aConfig);
$oGrid->endArea($html_content);
/* END BODY FOOTER AREA */

$body_content .= $oGrid->endGrid(true);
$dashboard->endBody($body_content);
/*  END BODY */

echo $dashboard->endDashboard(true);
/* END DASHBOARD */

// --- END PAGE
Footer::addStandardJSFile();
Footer::addFooterJS(<<<EOF

let fnApplyPseudoHoverClass = getFunctionWithLimitedCalls(addPseudoHover, 1);

let observer = new MutationObserver(function (mutations) {
    mutations.forEach(function(mutation) {
        let classMutated = (mutation.attributeName === 'class') ? true : false;
        let hasClass = ($(mutation.target).hasClass('dGrid-required-field-unfilled')) ? true : false;
        if (classMutated && hasClass) fnApplyPseudoHoverClass(mutation.target.closest('.dGrid-dashboard-admin-form-row'));
    });
});

observer.observe(document.getElementById('email-address'), { "attributes": true });
observer.observe(document.getElementById('password'), { "attributes": true });

EOF);
Footer::addFooterJSDocReady(<<<EOF
commonOnreadyJQuery();
EOF);

echo $MUSTACHE_ENGINE->render('footer.mustache', Footer::assembleFooterInfo());

?>