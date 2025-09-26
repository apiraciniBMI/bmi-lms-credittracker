<?php
/**
 * save_selfreported_credits.php - saves self reported user credit rows
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
 */

require($_SERVER['DOCUMENT_ROOT'] . '/credit_tracker/initializer.php');

/* STEP 1: Get parameters */
$user_credit_id = post('user_credit_id');
$date_issued    = date(DEFAULT_DATE_SAVE_FORMAT, strtotime(post('date_issued')));
$course_title   = post('course_title');
$credit_org_id  = post('credit_org_id');
$credits        = post('credits');
$user_id        = post('user_id');

if(!$date_issued || !$course_title || !$credit_org_id || !$credits) {
    $data = ['error' => true, 'message' => 'There was missing data. Please check your entries and try again.'];
    exit (json_encode($data));
}

if($user_credit_id) {
    $sql = "UPDATE user_credits_selfreported 
        SET user_id = {$user_id} 
        ,credit_org_id = {$credit_org_id} 
        ,date_issued = '{$date_issued}' 
        ,course_title = '{$course_title}'
        ,credits = {$credits} 
        WHERE user_credit_id = {$user_credit_id} 
    ";
} else {
    $sql = "INSERT INTO user_credits_selfreported
    (user_id, credit_org_id, date_issued, course_title, credits)
    VALUES
    ({$user_id}, {$credit_org_id}, '{$date_issued}', '{$course_title}', {$credits})
    ";
}
DB::NoSelectQuery($sql);  

// SUCCESS
$data = ['success' => true, 'message' => 'The item was successfully saved.'];
exit (json_encode($data));

?>