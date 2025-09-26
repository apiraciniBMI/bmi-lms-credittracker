<?php
/**
 * logout.php - logout user and session page
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
 * @copyright 2022 
 */

require($_SERVER['DOCUMENT_ROOT'] . '/credit_tracker/initializer.php');

$activity_log_content = [
    'activity_url'      => $_SERVER['REQUEST_URI']
    ,'activity_type'   => EVENT['LOGOUT_ATTEMPT']
    ,'is_error'  => EVENT['SUCCESS']
];

Filer::appendActivityLog($activity_log_content);
$redirect_homepage = '/sponsor/';

// Preserve language setting before destroying session
$currentLanguage = $_SESSION['language'] ?? getDefaultLanguage();

removeSession(SESSION_USERID_KEY);

session_destroy();
session_start();
session_regenerate_id(TRUE);

// Restore language setting
$_SESSION['language'] = $currentLanguage;

redirect($redirect_homepage);
?>