<?php
/**
 * index.php - credit tracker page for all users
 * @author    David Piracini <dpiracini@buildingmedia.com>
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
*/

require_once($_SERVER['DOCUMENT_ROOT'] . '/credit_tracker/initializer.php');

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

// include(CREDITTRACKER_DIR['SHARED'] . 'page_components/preferences.php');

$dashboard->endBody($body_content);
/*  END BODY */

echo $dashboard->endDashboard(true);
/* END DASHBOARD */

Footer::addStandardJSFile();

Footer::addFooterJSDocReady(<<<EOF
commonOnreadyJQuery();
initSimpleCards();

EOF);

echo $MUSTACHE_ENGINE->render('footer.mustache', Footer::assembleFooterInfo());

?>