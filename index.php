<?php
/**
 * index.php - credit tracker page for all users
 * @author    David Piracini <dpiracini@buildingmedia.com>
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
*/

require_once($_SERVER['DOCUMENT_ROOT'] . '/core/credit_tracker/initializer.php');

$user_id = getSession(SESSION_USERID_KEY);

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
Header::setActiveMenuItem($header_config['top_nav_bar']);
$header_content = $MUSTACHE_ENGINE->render('dashboard_navmenu_horizontal', $header_config); // build and display header
$dashboard->endHeader($header_content);
/*  END HEADER */

switch(APP_MODULE){
    case MODULE['CREDITTRACKER']:
		$module_web_root = CREDITTRACKER_WEB_ROOT;
		$visibility_values = getSession('SESSION_VISIBILITY_VALUES');
        break;
    default:
        exit("Module " . APP_MODULE . " is not supported in search yet.");
}

/*  BEGIN BODY */
$dashboard->startBody('dashboard-navmenu');

// load the Credit Tracker Dashboard class
$__autoload_class_credit_tracker = array( 	
	'creditTracker' => CREDITTRACKER_DIR['DASHBOARD'] . 'credit_tracker.php',
);
spl_autoload_register('autoload_class_credit_tracker');
function autoload_class_credit_tracker($classname) {
	global $__autoload_class_credit_tracker;
	if (isset($__autoload_class_credit_tracker[$classname])) {
		require($__autoload_class_credit_tracker[$classname]);
	}
}

// $report is used by the include files
$report = new Reporter();

// // GET DATA
include(CREDITTRACKER_DIR['DASHBOARD'] . 'get_data.php'); // get data for all

include(CREDITTRACKER_DIR['DASHBOARD'] . 'credittracker_pagelayout.php'); // page layout for course type

// ASSEMBLE BODY
$body_content = 
'<div class="dGrid-dashboard-body-content"><div class="dGrid-dashboard-report-container"><div class="dGrid-dashboard-report-body">'
. implode(' ', creditTracker::renderCreditTracker($oUser)) . 
 '</div></div></div>';

$oGrid = new Gridder();
$oGrid->startGrid('homepage-layout-grid', 12, 12, 'homepage-layout-grid');
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

Footer::addStandardJSFile();

echo "<script nonce=\"" . NONCE_MODULE . "\">

let searchDBLunchandlearn = '{$searchDBLunchandlearn}';
if(searchDBLunchandlearn) $('#ll_search').val(searchDBLunchandlearn);
</script>";

Footer::addFooterJSDocReady(<<<EOF
commonOnreadyJQuery();
initSimpleCards();

EOF);

echo $MUSTACHE_ENGINE->render('footer.mustache', Footer::assembleFooterInfo());

?>