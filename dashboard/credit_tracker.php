<?php
/**
 * credit_tracker.php - Sponsor Dashboard clases
 * @author    Anthony Piracini <apiracini@buildingmedia.com>
 * @author    David Piracini <dpiracini@buildingmedia.com>
 */

abstract class creditTracker {
    private static $cards;  // not sure what this is yet
    private static $has_data = false;

    // Current User
    public  static $user_id;

    private static $display_headerFilter = false;
    // private static $display_completed = true; // always show, always true
    private static $display_incompleted = true;
    private static $display_selfreported = false; // @TODO - customers decided not to do this, also not done
    private static $display_progress = false;

    // Course
    private static $default_course_id = 'all_courses';  
    private static $course_id;
    private static $course_title;
    private static $courses_dropdown;

    // Default credit org
    private static $default_credit_org_id = "all_credit_orgs";
    private static $credit_org_AIA = 1; // AIA 
    private static $credit_org_GBCI = 2; // GBCI 
    private static function getWelcomeMessage() {
        return '<h1>' . t('credit_tracker.my_credits') . '</h1>';
    }
    private static $course_type_all_courses = ['article', 'podcast', 'multimedia', 'webinar'];
    private static $course_type_all_online = ['article', 'podcast', 'multimedia'];
    private static $course_type_all_webinars = ['webinar'];
    private static $course_type_all_lunchandlearns = ['lunchandlearn'];
    private static $course_type_all_live_events = ['lunchandlearn', 'webinar'];
    private static $credit_orgs_progress = ['AIA', 'HSW'];
    // Credit Orgs
    private static $credit_org_id;
    private static $credit_orgs_dropdown;
    private static $credit_org_title;

    // User Credits
    private static $all_user_courses_complete;
    private static $user_courses_complete;
    private static $all_user_courses_incomplete;
    private static $user_courses_incomplete;

    private static $all_selfreported;
    private static $selfreported;
    private static $user_credits;
    private static $user_credits_year;

    // Timeframe 
    private static $default_timeframe = 'all_time';  
    private static $timeframe;
    private static $timeframe_title;
    private static $timeframes_dropdown;
    private static $start_date_formatted;
    private static $end_date_formatted;
    private static $start_year_formatted;
    private static $end_year_formatted;
    private static $start_date; // offset start date to UTC/GMT for sql query
    private static $end_date; // offset end date to UTC/GMT for sql query
    private static $start_year;
    private static $end_year;
    private static $current_year; // current year (for current year progress to goals)

    // Classes 
    //      Grid
    private static $grid_form_field_class = 'dGrid-form-field-for-grid';
    private static $container_class = 'formField';
    private static $checkbox_class = 'dGrid-dashboard-admin-checkbox';
    private static $datepicker_class = 'datepicker dGrid-dashboard-admin-datepicker';
    private static $checkbox_container_class = 'dGrid-form-field-for-grid checkbox formField';
    private static $first_td_class = 'dGrid-first-td-in-row';
    private static $last_td_class = 'dGrid-last-td-in-row';
    private static $dashboard_admin_row_class = 'cardRow dGrid-dashboard-admin-form-field';
    //      Cards
    private static $aCardClasses = ["card" => "dGrid-admin-report-card"];
    private static $strTitleClass = "dGrid-simple-card-title";
    private static $strCardClass = "dGrid-simple-card";
    private static $strIconClass = "dGrid-simple-card-icon";
    private static $strValueClass = "dGrid-simple-card-value";

    // URLs
    private static $url_certificate = "/pdf_prnt_cert.php";
    public static $module_web_root = SITE_WEB['ROOT'];

    public static function renderCreditTracker($oUser) {
        $_return = [];
        // GET DATA
        self::$user_id = getSession(SESSION_USERID_KEY);
        if(!self::$user_id) redirect(self::$module_web_root . 'login.php'); // no user session, go to login

        $cid = get('cid', false); // self reported user credit id (user_credit_id)
        if (get('action') == "delete") {
            Form::delete('user_credits_selfreported', 'user_credit_id', $cid);
            redirect(preg_replace("/&cid=[0-9]*&action=delete/", '', COMPLETE_URL));
        }
        self::getTimeframe();
        self::getDataCourse();
        self::getCreditOrgs();

        self::sqlCreditOrgs();
        $_return = self::layoutCredits();

        return $_return;
    }

    private static function fontawesomeSingleNumber($num) {
        return ((int)$num > 9) ? "fa-plus" : "fa-" . $num;
    }

    // Centralized message methods
    private static function getNoDataMessage() {
        return '<h4 class="pt-3 dGrid-dashboard-admin-report-title">' . t('credit_tracker.no_data_title') . '</h4><h5 class="pl-3">' . t('credit_tracker.no_data_message') . '</h5><br>';
    }

    private static function getNoDataDisplayCreditsMessage() {
        return '<h5 class="pl-3">' . t('credit_tracker.no_data_review_settings') . '</h5><br>';
    }

    private static function getNoDataTabsMessage() {
        return t('credit_tracker.no_data_review_settings');
    }

    private static function displayCardsCredits() {
        $report = new Reporter();
        $numof_cards = 0;

        foreach(self::$user_credits AS $user_credit) {
            if($numof_cards === 0) {
                $report->addSimpleCard('total-results-card', t('credit_tracker.total_credits') . ' (' . t('timeframe.all_time') . ')', $user_credit['total_user_credits_all'], NULL, NULL, NULL, ["icon"=>"fa " . self::fontawesomeSingleNumber($user_credit['total_user_credits_all']) . " dGrid-simple-card-icon-1", 'title' => self::$strTitleClass, 'card' => self::$strCardClass, 'value' => self::$strValueClass], 'info');
                $numof_cards++;    
            }
            $report->addSimpleCard('total-results-card', $user_credit['display_name'] . " " . t('credit_tracker.credits') . " (" . self::$timeframe_title . ")", $user_credit['total_user_credits'], NULL, NULL, NULL, ["icon"=>"fa " . self::fontawesomeSingleNumber($user_credit['total_user_credits']) . " dGrid-simple-card-icon-1", 'title' => self::$strTitleClass, 'card' => self::$strCardClass, 'value' => self::$strValueClass], 'info');
            $numof_cards++;
        }
    
        $html_cards = DashboardWidgets::simpleCards($report, self::$cards);

        // CREATE GRID AREAS FOR EACH CARD
        // total course attempts
        $oCardsGrid = new Gridder();
        $oCardsGrid->startGridAdvanced('dashboard-cards-grid', 12, 1, 'dGrid-admin-cards-section');
        for($i=0;$i<$numof_cards;$i++) {
            $oCardsGrid->startArea('total-pageviews-area', (12/$numof_cards), 1, 'dGrid-admin-card-line');
            $oCardsGrid->endArea($html_cards[$i]['html']);    
        }
        return $oCardsGrid->endGrid(true);
    }

    private static function layoutCredits() {
        $html_rows = [];
        $html_rows[] = self::headerGrid();
        $html_rows[] = self::displayCardsCredits();

        if(!self::$has_data) {
            $html_rows[] = self::getNoDataMessage();
        } else {
            $html_rows[] = self::displayTabsCredits();
        }
        return $html_rows;
    }

    private static function headerWelcome() {
        return self::displayWelcome(); // WELCOME - $html_welcome
    }
    private static function headerFilter() {
        return self::$display_headerFilter ? self::displayFilter() : null; // FILTER - $html_filter
    }
    private static function headerGrid() {
        // Filter & Update
        $oGridRow1 = new Gridder();
        $oGridRow1->startGridAdvanced('dashboard-filter-grid', 12, 1, 'dGrid-survey-donut-charts-grid mb-4', '10px', '10px');
        $oGridRow1->startArea('area-row1-column-filter', 12, 1, '');
        $oGridRow1->endArea(self::headerWelcome() . self::headerFilter());
        return $oGridRow1->endGrid(true);
    }

    public static function getTimeframe() {
        // YEAR and DATES
        self::$timeframe = get('timeframe', self::$default_timeframe); // default to all years 
        self::$timeframe_title = self::getFilterTimeframeTitle(self::$timeframe);

        // dropdown timeframes options 
        self::$timeframes_dropdown = [];
        // foreach (range(date('Y'), date("Y",strtotime(SYSTEM_LIVEDATE))) as $key => $option_timeframe) {
        //     self::$timeframes_dropdown[] = ['id' => $option_timeframe, 'text' => $option_timeframe];
        //     if(self::$timeframe && $option_timeframe == self::$timeframe) {
        //         self::$timeframes_dropdown[$key] = array_merge(self::$timeframes_dropdown[$key], ['selected' => 'selected']);
        //     }
        // }

        // add timeframes
        self::$timeframes_dropdown = array_merge([
            ['id' => '', 'text' => '(Select One)']
            ,['id' => 'current_year', 'text' => 'Current Year', 'selected' => (self::$timeframe === 'current_year')] 
            ,['id' => 'last_year', 'text' => 'Last Year', 'selected' => (self::$timeframe === 'last_year')]
            ,['id' => 'all_time', 'text' => 'All Time', 'selected' => (self::$timeframe === 'all_time')]
            // ,['id' => 'past_year', 'text' => 'Past Year', 'selected' => (self::$timeframe === 'past_year')] 
            // ,['id' => 'past_quarter', 'text' => 'Past 90 Days', 'selected' => (self::$timeframe === 'past_quarter')]
            // ,['id' => 'past_week', 'text' => 'Past Week', 'selected' => (self::$timeframe === 'past_week')]
            // ,['id' => 'past_5years', 'text' => 'Past 5 Years', 'selected' => (self::$timeframe === 'past_5years')]
        ], self::$timeframes_dropdown );

        if(in_array(self::$timeframe, ['current_year', 'last_year', 'all_time'])){
            switch(self::$timeframe) {
                case 'current_year':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime('first day of january this year'));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime('first day of january next year -1 second'));
                    break;
                case 'last_year':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime('first day of january last year'));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime('first day of january this year -1 second'));
                    break;
                case 'all_time':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime(SYSTEM_LIVEDATE));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime('first day of january next year -1 second'));
                    break;
                case 'past_year':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now -1 year"));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now +1 day"));
                    break;
                case 'past_quarter':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now -90 days"));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now +1 day"));
                    break;
                case 'past_week':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now -7 days"));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now +1 day"));
                    break;
                case 'past_5years':
                    self::$start_date = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now -5 years"));
                    self::$end_date   = date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now +1 day"));
                    break;
                default:
            }
        } else {
            self::$start_date = (self::$timeframe ? date(DEFAULT_DATE_SAVE_FORMAT, strtotime(self::$timeframe . "-01-01")) : date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now -1 year")));
            self::$end_date = (self::$timeframe ? date(DEFAULT_DATE_SAVE_FORMAT, strtotime(self::$timeframe . "-12-31")) : date(DEFAULT_DATETIME_SAVE_FORMAT, strtotime("now +1 day")));    
        }
        self::$start_date_formatted = date(DEFAULT_DATE_SAVE_FORMAT, strtotime(self::$start_date));
        self::$end_date_formatted = date(DEFAULT_DATE_SAVE_FORMAT, strtotime(self::$end_date));
        self::$start_year_formatted = date('Y', strtotime(self::$start_date));
        self::$end_year_formatted = date('Y', strtotime(self::$end_date));
        self::$start_date = serverDateToGMTDate(self::$start_date, "00:00:00"); // offset start date to UTC/GMT for sql query
        self::$end_date = serverDateToGMTDate(self::$end_date, "23:59:59"); // offset end date to UTC/GMT for sql query
        self::$start_year = date("Y", strtotime(self::$start_date));
        self::$end_year = date("Y", strtotime(self::$end_date));
        self::$current_year = date("Y");
    }

    public static function getDataCourse() {
        // COURSE TYPES
        self::$course_id = get('course_type', self::$default_course_id); 
        self::$courses_dropdown = [
            ['id' => '', 'text' => '(Select One)']
            ,['id' => 'all_courses', 'text' => 'All Courses', 'selected' => (self::$course_id === 'all_courses')]
            ,['id' => 'all_online', 'text' => 'All Online Courses', 'selected' => (self::$course_id === 'all_online')]
            ,['id' => 'all_webinars', 'text' => 'All Webinars', 'selected' => (self::$course_id === 'all_webinars')]
            ,['id' => 'all_lunchandlearns', 'text' => 'All Lunch & Learns', 'selected' => (self::$course_id === 'all_lunchandlearns')]
            ,['id' => 'all_live_events', 'text' => 'All Live Events', 'selected' => (self::$course_id === 'all_live_events')]
        ];
        $course_index = array_search(true, array_column(self::$courses_dropdown, 'selected')); 
        self:: $course_title = ($course_index === false ? '' : self::$courses_dropdown[$course_index+1]['text']);
    }

    public static function getCreditOrgs() {

        // CREDIT ORGS(s) 
        self::$credit_org_id = get('credit_org', self::$default_credit_org_id); // default credit org,  1 AIA

        // get dropdown credit orgs - ie. all credit orgs (any type) that this user has rights to (per sponsor manager or rep role)
        self::$credit_orgs_dropdown = Credits::getCreditOrgsDropdown(self::$credit_org_id);

        switch(self::$credit_org_id) { 
            case '': // nothing selected - default to all
            case "all_credit_orgs": // article, multimedia, and podcast
                self::$credit_org_id = 'all_credit_orgs';
                self::$credit_org_title = "All Accreditation Types";
                break;
            default: // specific course selected
                // find selected course from dropdown
                $selected_num = array_search('selected', array_column(self::$credit_orgs_dropdown, 'selected')); // subscript number
                $credit_org_type = self::$credit_orgs_dropdown[$selected_num]['credit_org_type'];
                self::$credit_org_title = self::$credit_orgs_dropdown[$selected_num]['text']; // chosen course title
                // filter to chosen row
                $credit_orgs[0] = self::$credit_orgs_dropdown[$selected_num];
        }
        self::$credit_orgs_dropdown = array_merge(
            [['id' => '', 'text' => '(Select One)']
            ,['id' => 'all_credit_orgs', 'text' => 'All Accreditations', 'selected' => (self::$credit_org_id === 'all_credit_orgs')]
            ], self::$credit_orgs_dropdown
        );
    }

    public static function sqlCreditOrgs() {
        $credit_org_AIA = self::$credit_org_AIA;
        $credit_org_GBCI = self::$credit_org_GBCI;

        // ** TOTALS **
        $sql_params = [];
        $sql_params['DISTINCT'] = true;

        switch(self::$course_id) {
            case 'all_courses':
                $sql_params_course_types = self::$course_type_all_courses ? " AND c.course_type IN ('" . implode("','", self::$course_type_all_courses) . "')" : "";
                break;
            case 'all_online':
                $sql_params_course_types = self::$course_type_all_online ? " AND c.course_type IN ('" . implode("','", self::$course_type_all_online) . "')" : "";
                break;
            case 'all_webinars':
                $sql_params_course_types = self::$course_type_all_webinars ? " AND c.course_type IN ('" . implode("','", self::$course_type_all_webinars) . "')" : "";
                break;
            case 'all_lunchandlearns':
                $sql_params_course_types = self::$course_type_all_lunchandlearns ? " AND c.course_type IN ('" . implode("','", self::$course_type_all_lunchandlearns) . "')" : "";
                break;
            case 'all_live_events':
                $sql_params_course_types = self::$course_type_all_live_events ? " AND c.course_type IN ('" . implode("','", self::$course_type_all_live_events) . "')" : "";
                break;
            case '':
                break;
            default:
                break;
        }
        $sql_params['JOINS'] = "LEFT JOIN credit_orgs AS co ON co.credit_org_id = uc.credit_org_id ";
        $sql_params['WHERE'] = $sql_params_course_types . " AND co.is_active = 1 AND uc.date_issued BETWEEN '" . self::$start_date_formatted . "' AND '" . self::$end_date_formatted . "'";
        $sql_params['GROUP BY'] = " GROUP BY uc.user_id, uc.credit_org_id ";
        $sql_params['COLUMNS'] = ", (SELECT SUM(uc.credits) FROM user_credits AS uc " . $sql_params['JOINS'] . " JOIN courses AS c ON c.course_id = uc.course_id WHERE uc.user_id IN (" . self::$user_id . ") " . $sql_params['WHERE'] . " )  AS total_user_credits_all
            , co.abbrev, co.full_title, co.display_name 
        ";
        self::$user_credits = Credits::getUserCredits([self::$user_id], $sql_params);
        // set has data
        self::$has_data = (self::$user_credits);

        // ** COMPLETE **
        $sql_params['COLUMNS'] = ""; // reset columns (we don't need more columns anymore)
        $sql_params['WHERE'] = $sql_params_course_types . " AND uc.date_issued BETWEEN '" . self::$start_date_formatted . "' AND '" . self::$end_date_formatted . "' ";
        $sql_params['GROUP BY'] = " GROUP BY uc.user_id, uc.course_id ";
        $sql_params['ORDER BY'] = " ORDER BY uc.date_issued DESC ";

        $yes_text = I18n::getInstance()->t('common.yes');
        $sql = "SELECT " . (array_key_exists('DISTINCT', $sql_params) && $sql_params['DISTINCT'] ? " DISTINCT " : "") . "
                IF(c.allow_certificate = 1, CONCAT_WS('', '?u=', uc.user_id, '&id=', uc.course_id, '&id2=', uc.course_schedule_id), '') AS cert_querystring
                , uc.course_id, uc.course_schedule_id AS course_schedule_id
                , CONCAT(c.title, ' (', c.course_type, ')') AS course_title, uc.credit_org_id
                , GROUP_CONCAT(DISTINCT uc.credit_org_id SEPARATOR ',') AS credit_ids
                , GROUP_CONCAT(DISTINCT CONCAT(co.display_name, '(', uc.credits, ')') SEPARATOR ',') AS credit_org_and_credits
                , DATE_FORMAT(uc.date_issued, '%Y-%m-%d') AS date_issued
                , uc.date_issued AS date_issued_sort
                " . $sql_params['COLUMNS'] . "
                , IF(c.course_type IN ('lunchandlearn', 'webinar'), '" . DB::Escape($yes_text) . "', '') AS attended_live
            FROM user_credits AS uc 
            LEFT JOIN courses AS c ON c.course_id = uc.course_id 
            LEFT JOIN course_credits AS cc ON cc.course_id = uc.course_id 
                " . $sql_params['JOINS'] . " 
            WHERE uc.user_id IN (" . self::$user_id . ") 
                " . $sql_params['WHERE'] . "
                " . $sql_params['GROUP BY'] . "
                " . $sql_params['ORDER BY'] . "
        ";
        self::$all_user_courses_complete = DB::Query($sql);

        $credit_org_id = self::$credit_org_id;

        self::$user_courses_complete = array_values(array_filter(self::$all_user_courses_complete, function($element) use($credit_org_id){
            if($credit_org_id == 'all_credit_orgs' || $element['credit_org_id'] == $credit_org_id) return $element;
        }));

        // Translate course titles for completed courses
        if (self::$user_courses_complete) {
            // Map course_title to title for translation
            foreach (self::$user_courses_complete as &$course) {
                $course['title'] = $course['course_title'];
            }
            TranslatedContent::translateCourseFields(self::$user_courses_complete, ['title']);
            // Map back to course_title
            foreach (self::$user_courses_complete as &$course) {
                $course['course_title'] = $course['title'];
            }
        }

        $user_courses_complete_course_ids = (self::$user_courses_complete) ? array_values(array_unique(array_column(self::$user_courses_complete, 'course_id'))) : [];
        $sql_params_complete_course_ids = ($user_courses_complete_course_ids) ? " AND cat.course_id NOT IN (" . implode(', ', $user_courses_complete_course_ids) . ") " : '';
        // ** INCOMPLETE **
        $sql_params['COLUMNS'] = ""; // reset columns (we don't need more columns anymore)
        $sql_params['JOINS'] = ""; // reset joins 
        $sql_params['WHERE'] = $sql_params_course_types; // reset where 
        $sql_params['GROUP BY'] = " GROUP BY cat.user_id, cat.course_id ";
        $sql_params['ORDER BY'] = " ORDER BY IF(cat.start_date IS NOT NULL, cat.start_date, cat.registration_date) DESC ";

        $sql = "SELECT " . (array_key_exists('DISTINCT', $sql_params) && $sql_params['DISTINCT'] ? " DISTINCT " : "") . "
            cat.course_attempt_id, c.course_id, c.title, c.course_type
            , DATE_FORMAT(cat.start_date, '%Y-%m-%d') AS start_date 
            , cat.start_date AS start_date_sort
            , co.credit_org_id
            , GROUP_CONCAT(DISTINCT CONCAT(co.display_name, '(', cc.credit_amt, ')') SEPARATOR ',') AS credit_org_and_credits
            , cs.building_name AS location
            , IF(csd.date IS NOT NULL, DATE_FORMAT(csd.date, '%Y-%m-%d'), NULL) AS event_date
            , csd.date AS event_date_sort
            " . $sql_params['COLUMNS'] . "
            FROM course_attempts AS cat 
            JOIN courses AS c ON c.course_id = cat.course_id 
            LEFT JOIN course_credits AS cc ON cc.course_id = cat.course_id 
            LEFT JOIN credit_orgs AS co ON co.credit_org_id = cc.credit_org_id 
                " . $sql_params['JOINS'] . " 
            LEFT JOIN course_schedule AS cs ON cs.course_schedule_id = cat.course_schedule_id
            LEFT JOIN course_schedule_day AS csd ON csd.course_schedule_id = cs.course_schedule_id
            LEFT JOIN user_credits AS uc ON uc.course_id = cat.course_id AND uc.course_schedule_id = cat.course_schedule_id AND uc.user_id = cat.user_id
            WHERE cat.user_id IN (" . self::$user_id . ") 
                {$sql_params_complete_course_ids}
                AND (co.is_active = 1 OR co.is_active IS NULL) 
                AND uc.user_credit_id IS NULL 
                AND ((cat.completion_date IS NULL AND cat.start_date BETWEEN '" . self::$start_date_formatted . "' AND '" . self::$end_date_formatted . "' ) OR 
                    cat.attendance_date IS NULL AND cat.registration_date BETWEEN '" . self::$start_date_formatted . "' AND '" . self::$end_date_formatted . "' )
                " . $sql_params['WHERE'] . "
                " . $sql_params['GROUP BY'] . "
                " . $sql_params['ORDER BY'] . " 
        ";
        self::$all_user_courses_incomplete = self::$display_incompleted ? DB::Query($sql) : [];
        self::$user_courses_incomplete = array_values(array_filter(self::$all_user_courses_incomplete, function($element) use($credit_org_id){
            if($credit_org_id == 'all_credit_orgs' || $element['credit_org_id'] == $credit_org_id) return $element;
        }));

        // Translate course titles for incomplete courses
        if (self::$user_courses_incomplete) {
            TranslatedContent::translateCourseFields(self::$user_courses_incomplete, ['title']);
            // Create course_title with translated title and course_type
            foreach (self::$user_courses_incomplete as &$course) {
                $course['course_title'] = $course['title'] . ' (' . $course['course_type'] . ')';
            }
        }

        // ** SELF REPORTED **
        $sql = "SELECT " . (array_key_exists('DISTINCT', $sql_params) && $sql_params['DISTINCT'] ? " DISTINCT " : "") . "
            ucs.user_credit_id, ucs.course_title, ucs.user_id
            , ucs.credit_org_id, co.abbrev AS credit_org_abbrev
            , DATE_FORMAT(ucs.date_issued,'%Y-%m-%d') AS date_issued
            , ucs.date_issued AS date_issued_sort
            , ucs.credits
            , " . self::$user_id . " AS user_id, 1 AS delete_button 
        FROM user_credits_selfreported AS ucs
        JOIN credit_orgs AS co ON co.credit_org_id = ucs.credit_org_id
        WHERE ucs.user_id = " . self::$user_id . " 
        AND ucs.date_issued BETWEEN '" . self::$start_date_formatted . "' AND '" . self::$end_date_formatted . "' ;";
        self::$all_selfreported = self::$display_selfreported ? DB::Query($sql) : [];
        self::$selfreported = self::$all_selfreported;

        self::$credit_orgs_dropdown = Credits::getCreditOrgsDropdown(self::$credit_org_id);
        self::$credit_orgs_dropdown = array_merge(
            [['id' => '', 'text' => '(Select One)']
            ,['id' => 'all_credit_orgs', 'text' => 'All Accreditations', 'selected' => (self::$credit_org_id === 'all_credit_orgs')]
            ], self::$credit_orgs_dropdown
        );

        // ** PROGRESS (for current year) **
        $sql = "SELECT " . (array_key_exists('DISTINCT', $sql_params) && $sql_params['DISTINCT'] ? " DISTINCT " : "") . "
            co.abbrev, co.display_name, co.credit_target_yearly AS target_value
            , SUM(IF(c.course_id IS NULL, 0, COALESCE(uc.credits, 0))) AS current_value
            , ROUND(SUM(IF(c.course_id IS NULL, 0, COALESCE(uc.credits, 0))) / co.credit_target_yearly, 2) AS percent_complete
        FROM credit_orgs AS co 
        LEFT JOIN user_credits AS uc ON uc.credit_org_id = co.credit_org_id AND uc.user_id IN (" . self::$user_id . ") 
            AND YEAR(uc.date_issued) = " . self::$current_year . " 
        LEFT JOIN courses AS c ON c.course_id = uc.course_id
        WHERE co.abbrev IN (" . self::$credit_orgs_progress . ") 
        GROUP BY co.abbrev";
        self::$user_credits_year = self::$display_progress ? DB::Query($sql) : []; 
    }

    private static function displayFilter(){
		global $MUSTACHE_ENGINE; 

        $reportdashboard_form = new Gridder(true);
        $reportdashboard_form->startGridAdvanced('reportdashboard_form-form-grid', 3, 1, '', '25px', '0px');
        
        // FORM FIELDS
        $reportdashboard_form->startArea('reportdashboard_form-form-gridarea', 1, 1, '');
        // dropdown credit orgs ()
        $form_field = $MUSTACHE_ENGINE->render('partials/ajaxForm/form_field_dropdown', [
            'label' => 'Accreditation'
            , 'type' => 'dropdown'
            , 'id' => 'credit_org'
            , 'name' => 'credit_org'
            , 'ignore' => false
            , 'onchange' => 'controlHiddenInputDropdown', 'onchange_args' =>  json_encode(['hidden_credit_org', 'this'])
            , 'class' => self::$grid_form_field_class
            , 'container_class' => self::$container_class
            , 'value' => self::$credit_org_id
            , 'options' => self::$credit_orgs_dropdown
        ]);
        $reportdashboard_form->endArea($form_field);
        
        // dropdown timeframe ()
        $reportdashboard_form->startArea('reportdashboard_form-form-gridarea', 1, 1, '');
        $form_field = $MUSTACHE_ENGINE->render('partials/ajaxForm/form_field_dropdown', [
            'label' => 'Timeframe'
            , 'type' => 'dropdown'
            , 'id' => 'timeframe'
            , 'name' => 'timeframe'
            , 'ignore' => false
            , 'onchange' => 'controlHiddenInputDropdown', 'onchange_args' =>  json_encode(['hidden_timeframe', 'this'])
            , 'class' => self::$grid_form_field_class
            , 'container_class' => self::$container_class
            , 'value' => self::$timeframe
            , 'options' => self::$timeframes_dropdown
        ]);
        $reportdashboard_form->endArea($form_field);
        
        $reportdashboard_form->startArea('reportdashboard_form-form-gridarea', 1, 1, '');
        // dropdown course types ()
        $form_field = $MUSTACHE_ENGINE->render('partials/ajaxForm/form_field_dropdown', [
            'label' => 'Course Types'
            , 'type' => 'dropdown'
            , 'id' => 'course_type'
            , 'name' => 'course_type'
            , 'ignore' => false
            , 'onchange' => 'controlHiddenInputDropdown', 'onchange_args' =>  json_encode(['hidden_course_type', 'this'])
            , 'class' => self::$grid_form_field_class
            , 'container_class' => self::$container_class
            , 'value' => self::$course_id
            , 'options' => self::$courses_dropdown
        ]);
        $reportdashboard_form->endArea($form_field);
        
        $reportdashboard_form->startArea('reportdashboard_form-form-gridarea', 3, 1, '');
        // Apply Filters button
        $form_field = $MUSTACHE_ENGINE->render('partials/button', [
            'class' => "button-primary float-right-important dGrid-dashboard-admin-save-button"
            ,'type' => "button"
            ,'text' => "Apply Filters"
            ,'ignore' => false
            ,'onclick' => 'submitAllInputsToGet'
        ]);
        $reportdashboard_form->endArea($form_field);
        
        $form_fields = $reportdashboard_form->endGrid(true);
        self::$timeframe_title = self::getFilterTimeframeTitle(self::$timeframe);

        $sFilters = (self::$credit_org_title ? " <strong>Accreditation Type:</strong> " . self::$credit_org_title : "")  . (self::$timeframe_title ?  " | <strong>Timeframe:</strong>  " . self::$timeframe_title : "") . (self::$course_title ? " | <strong>Course Type:</strong>  " . self::$course_title : "");
        $sFilters = substr_replace($sFilters, '', strrpos($sFilters, ','), 1);
        
        $aAccordionData = [];
        $aAccordionData['accordion'][0] = [
            'id' => "report-dashboartd-0"
            ,'accordion_selected' => "0"
            ,'show' => ''
            ,'headers' => [['header_text' => "<strong>Credit Tracker Filters</strong> " . ($sFilters ? " - " . $sFilters : "") ]]
            ,'body' => $form_fields
            ,'accordion_class' => ''
            ,'header_container_class' => ''
            ,'body_class' => 'dGrid-dashboard-admin-form-row'
            ,'body_container_class' => 'dGrid-dashboard-learning-plan-accordion-body-container'
        ];
        $oAccordions = new stdClass();
        $oAccordions->accordions = $aAccordionData;
        
        // $html_filter = $MUSTACHE_ENGINE->render('area_content', (array)$oAccordions); 
        return $MUSTACHE_ENGINE->render('area_content', (array)$oAccordions); 

    }

    private static function getFilterTimeframeTitle($timeframe) {
        $i18n = I18n::getInstance();
        switch($timeframe) {
            case 'current_year':
                $timeframe_title = $i18n->t('timeframe.current_year');
                break;
            case 'last_year':
                $timeframe_title = $i18n->t('timeframe.last_year');
                break;
            case 'all_time':
                $timeframe_title = $i18n->t('timeframe.all_time');
                break;
            case 'past_year':
                $timeframe_title = $i18n->t('timeframe.past_year');
                break;
            case 'past_quarter':
                $timeframe_title = $i18n->t('timeframe.past_quarter');
                break;
            case 'past_week':
                $timeframe_title = $i18n->t('timeframe.past_week');
                break;
            case 'past_5years':
                $timeframe_title = $i18n->t('timeframe.past_5years');
                break;
            default:
                $timeframe_title = $timeframe;
        }
        return $timeframe_title;
    }

    private static function displayWelcome() {
        return self::getWelcomeMessage();
    }

    private static function displayTabsCredits() {
        global $MUSTACHE_ENGINE;
        $report = new Reporter();

        $credit_org_AIA = self::$credit_org_AIA;

        $widget_title = "Course Status"; 
        if(!self::$user_courses_complete && !self::$user_courses_incomplete && !self::$selfreported) { 
            $dashboard_widget[] = '<h4 class="pt-3 dGrid-dashboard-admin-report-title">' . $widget_title . '</h4>' . self::getNoDataDisplayCreditsMessage();
        } else {
            // show if AIA and past year 
            if(self::$display_progress) {
                $html_user_courses_progress = []; // abbrev, display_name, credit_target_yearly, sum_currentyear_credits
                $i = 0;
                foreach(self::$user_credits_year AS $row_progress) {
                    $credit_org_display_name = $row_progress['display_name'] . " Credits for " . (self::$end_year_formatted > date("Y") ? date("Y") : self::$end_year_formatted);
                    $value = $row_progress['current_value'];
                    $baseValue = $row_progress['target_value'];
                    $infoText = ($row_progress['percent_complete'] > 1 ? "Congratulations! You reached the yearly target of credits (" . $row_progress['target_value'] . ")." : "You are " . ($row_progress['percent_complete'] * 100) . "% towards your target number of credits (" . $row_progress['target_value'] . ") for the year.");
                    switch($i) {
                        case 1:
                            $class_card = "card-progress-lightgreen";
                            break;
                        case 2:
                            $class_card = 'card-progress-lightred';
                            break;
                        default:
                            $class_card = "card-progress-lightblue";
                    }
                    $i++;

                    $html_user_courses_progress[] = $report->renderProgressCard(
                        [
                            "title" => $credit_org_display_name
                            ,"id" => 'card-' . $row_progress['abbrev']
                            ,"infoText" => $infoText
                            ,"value" => $value
                            ,"baseValue" => $baseValue
                            ,"format" => [
                                "indicator" => [
                                    "decimals"=>0
                                ]
                            ]
                            ,"cssClass" => [
                                "card" => $class_card
                            ]
                        ]
                    );
                    
                }
                $oGridRow1 = new Gridder();
                $oGridRow1->startGridAdvanced('dashboard-filter-grid', 2, 1, 'dGrid-survey-donut-charts-grid', '10px', '10px');
                $oGridRow1->startArea('area-row-column1-filter', 1, 1, '');
                $oGridRow1->endArea($html_user_courses_progress[0]);
                $oGridRow1->startArea('area-row-column2-filter', 1, 1, '');
                $oGridRow1->endArea($html_user_courses_progress[1]);
                $html_user_courses_progress = $oGridRow1->endGrid(true);
            }
                        
            $aConfig_complete = [
                "name"=>"tbl_complete"
                ,"dataSource" => self::$user_courses_complete
                ,"columns" => [
                    'course_title' => [
                        'label' => t('credit_tracker.course_title')
                    ]
                    ,'credit_org_and_credits' => [
                        'label' => t('credit_tracker.accreditation_and_credits')
                    ]
                    ,'date_issued' => [
                        'label' => t('credit_tracker.date_issued')
                        ,'type' => 'date'
                        ,"data-order" => "date_issued_sort"
                    ]
                    ,'date_issued_sort' => [
                        'label' => 'Date Issued Sort'
                        ,'type' => 'date'
                        ,"format" => "Y-m-d H:i:s"
                        ,'visible' => false
                    ]
                    ,'attended_live' => [
                        'label' => t('credit_tracker.live')
                    ]
                    ,'cert_querystring' => [
                        'label' => t('credit_tracker.certificate')
                        ,'formatValue' => function($value, $row, $cKey) use ($MUSTACHE_ENGINE) {
                            return $MUSTACHE_ENGINE->render('datagrid_buttons', [
                                'buttons' => [
                                    [
                                        'class' => 'button-secondary add'  
                                        ,'onclick' => 'openInNewWindow'
                                        ,'onclick_args' => json_encode([self::$url_certificate . "{$row['cert_querystring']}"])
                                        ,'icon' => ['text' => t('credit_tracker.certificate'), 'class' => 'button-icon']
                                    ]
                                ]
                            ]);
                        }
                    ]
                ]
                ,"cssClass" => [
                    "td" => function($row, $colName) { 
                        switch($colName) {
                            case 'cert_querystring':
                                $_class = '';
                                break;
                            case 'course_title':
                                $_class = 'max-width-400 white-space-unset';
                                break;
                            case 'credit_org_and_credits':
                                $_class = 'max-width-200 white-space-unset';
                                break;
                            default:
                        }
                        return "dGrid-dashboard-admin-report-table-td {$_class} "; 
                    }
                    ,"tr" => 'dGrid-dashboard-admin-report-table-tr showCursor'
                    ,"thead" => 'dGrid-dashboard-admin-report-table-tr'
                    ,"table" => "table table-striped dGrid-dashboard-admin-report-table dGrid-datagrid-responsive"
                ]
                ,"themeBase" => "bs4"
                ,"plugins" => ["Buttons", "Select", "SearchPanes"]
                ,"options" => [
                    // "fixedHeader" => true
                    "colReorder" => true
                    ,"responsive" => true	
                    ,"searching" => true
                    ,"paging" => true
                    ,"pageLength" => 10
                    ,"select" => false
                    ,"dom" => 'Bfrtip'
                    ,"language" => json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/i18n/datatables/' . I18n::getCurrentLanguage() . '.json'), true)
                    ,"order" => [
                        [3, 'desc']
                    ]
                    ,"buttons" => [
                        "csv"
                        ,"excel"
                    ]
                ]
            ];
            $html_user_courses_complete = $report->renderDataGrid($aConfig_complete, '');	
        
            if(self::$display_incompleted) {
                $aConfig_incomplete = [
                    "name"=>"tbl_incomplete"
                    ,"dataSource" => self::$user_courses_incomplete
                    ,"columns" => [
                        'course_title' => [
                            'label' => t('credit_tracker.course_title')
                        ]
                        ,'credit_org_and_credits' => [
                            'label' => t('credit_tracker.credits')
                        ]
                        ,'start_date' => [
                            'label' => t('credit_tracker.start_date')
                            ,'type' => 'date'
                            ,'data-order' => 'start_date_sort'
                        ]
                        ,'start_date_sort' => [
                            'label' => 'Start Date Sort'
                            ,'type' => 'date'
                            ,"format" => "Y-m-d H:i:s"
                            ,'visible' => false
                        ]
                        ,'event_date' => [
                            'label' => t('credit_tracker.event_date')
                            ,'data-order' => 'event_date_sort'
                        ]
                        ,'event_date_sort' => [
                            'label' => 'Event Date Sort'
                            ,'type' => 'date'
                            ,"format" => "Y-m-d H:i:s"
                            ,'visible' => false
                        ]
                        ,'overall_date_sort' => [
                            'label' => 'Overall Date Sort'
                            ,'type' => 'date'
                            ,"format" => "Y-m-d H:i:s"
                            ,'visible' => false
                            , 'formatValue' => function($value, $row, $cKey) {
                                return (getValueByKey($row, 'start_date_sort')) ? $row['start_date_sort'] : getValueByKey($row, 'event_date_sort');
                            }
                        ]
                    ]
                    ,"cssClass" => [
                        "td" => function($row, $colName) { 
                            switch($colName) {
                                case 'cert_querystring':
                                    $_class = '';
                                    break;
                                case 'course_title':
                                    $_class = 'max-width-400 white-space-unset';
                                    break;
                                case 'credit_org_and_credits':
                                    $_class = 'max-width-200 white-space-unset';
                                    break;
                                default:
                            }
                            return "dGrid-dashboard-admin-report-table-td {$_class} "; 
                        }    
                        ,"tr" => 'dGrid-dashboard-admin-report-table-tr showCursor'
                        ,"thead" => 'dGrid-dashboard-admin-report-table-tr'
                        ,"table" => "table table-striped dGrid-dashboard-admin-report-table dGrid-datagrid-responsive"
                    ]
                    ,"themeBase" => "bs4"
                    ,"fastRender" => true
                    ,"plugins" => ["Buttons", "Select", "SearchPanes"]
                    ,"options" => [
                        // "fixedHeader" => true
                        "colReorder" => true
                        ,"searching" => true
                        ,"paging" => true
                        ,"pageLength" => 10
                        ,"select" => true
                        ,"dom" => 'Bfrtip'
                        ,"language" => json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/i18n/datatables/' . I18n::getCurrentLanguage() . '.json'), true)
                        ,"order" => [
                            [6, 'desc']
                        ]
                        ,"buttons" => [
                            "csv"
                            ,"excel"
                        ]
                    ]
                ];
                $html_user_courses_incomplete = $report->renderDataGrid($aConfig_incomplete, '');	
            }
        
            if(self::$all_selfreported) {
                $aConfig_selfreported = [
                    "name"=>"tbl_selfreported" // user_credit_id, user_id, credit_org_id, credit_org_abbrev, date_issued, credits
                    ,"dataSource" => self::$selfreported
                    ,"columns" => [
                        'date_issued' => [
                            'label' => 'Completed Date'
                            ,'type' => 'date'
                            ,'data-order' => 'date_issued_sort'
                        ]
                        ,'date_issued_sort' => [
                            'label' => 'Date Issued Sort'
                            ,'type' => 'date'
                            ,"format" => "Y-m-d H:i:s"
                            ,'visible' => false
                        ]
                        ,'course_title' => [
                            'label' => "Course Title"
                        ]
                        ,'credit_org_abbrev' => [
                            'label' => 'Accreditation'
                        ]
                        ,'credit_org_id' => [
                            'visible' => false
                        ]
                        ,'credits' => [
                            'label' => 'Credits'
                        ]
                        ,'user_id' => [
                            'visible' => false
                        ]
                        ,'user_credit_id' => [
                            'visible' => false
                        ]
                        ,'delete_button' => [
                            'label' => ''
                            ,'formatValue' => function($value, $row, $cKey) use ($MUSTACHE_ENGINE) {
                                return $MUSTACHE_ENGINE->render('partials/button', [
                                    'class' => 'button-secondary delete'  
                                    ,'link' => RUNNING_SCRIPT . QUERY_STRING . "&tab=selfreported&cid=" . $row['user_credit_id'] . "&action=delete"
                                    ,'icon' => ['text' => 'Delete', 'class' => 'button-icon']
                                ]);
                            }
                        ]
                    ]
                    ,"cssClass" => [
                        "td" => function($row, $colName) { 
                            switch($colName) {
                                case 'cert_querystring':
                                    $_class = '';
                                    break;
                                case 'course_title':
                                    $_class = 'max-width-400 white-space-unset';
                                    break;
                                case 'credit_org_and_credits':
                                    $_class = 'max-width-200 white-space-unset';
                                    break;
                                default:
                            }
                            return "dGrid-dashboard-admin-report-table-td {$_class} "; 
                        }    
                        ,"tr" => 'dGrid-dashboard-admin-report-table-tr showCursor'
                        ,"thead" => 'dGrid-dashboard-admin-report-table-tr'
                        ,"table" => "table table-striped dGrid-dashboard-admin-report-table dGrid-datagrid-responsive"
                    ]
                    ,"themeBase" => "bs4"
                    ,"fastRender" => true
                    ,"plugins" => ["Buttons", "Select", "SearchPanes"]
                    ,"options" => [
                        // "fixedHeader" => true
                        "colReorder" => true
                        ,"searching" => true
                        ,"paging" => true
                        ,"pageLength" => 10
                        ,"select" => true
                        ,"dom" => 'Bfrtip'
                        ,"order" => [
                            [1, 'desc']
                        ]
                        ,"buttons" => [
                            "csv"
                            ,"excel"
                        ]
                    ]
                ];
            
                /* Modal Popup */
                $modal_name = "formAddSelfCredit";
                $modal_formname = 'add-selfreported-credits-form';
                self::setJSModalFunctions(self::$credit_org_id, $modal_formname);
                
                $oModal = new stdClass();
                $oModal->id = $modal_name;
                $oModal->title = "Self-reported Credit";
                $oModal_redirect = (!str_contains(QUERY_STRING, 'tab=selfreported') ? (str_contains(QUERY_STRING, '?') ? RUNNING_SCRIPT . QUERY_STRING . '&tab=selfreported' : RUNNING_SCRIPT . '?tab=selfreported') : RUNNING_SCRIPT . QUERY_STRING);
                /* Add Credit Button */
                $button_add_credit = "
                    <div id='tbl_textresponses_wrapper' class='dataTables_wrapper container-fluid dt-bootstrap4 no-footer'>
                        <div class='dt-buttons'>
                            <button data-onclick=\"addSelfReported\" data-onclick-args='" . json_encode([$modal_formname, $modal_name]) . "' class='mr-2 dt-button buttons-excel buttons-html5' class='btn btn-primary'>Add Credit</button>
                        </div>
                    </div>
                ";
            
                /* Form Config */
                $oForm = new AjaxForm();
                $oFormConfig = [ 
                    'form' => [
                        'container_class' => 'dGrid-dashboard-admin-form-container'
                        ,'class' => 'ml-40'
                        ,'id' => 'add-selfreported-credits-form'
                        ,'name' => ''
                        ,'enctype' => ''
                        ,'method' => "POST"
                        ,'heading' => ''
                        ,'get_id' => ''
                        ,'csrf_input_name' => ''
                        ,'csrf_tokens' => ''
                        ,'sections' => [
                            0 => [
                                'section_name' => 'selfreported-credits'
                                ,'section_class' => 'mt-8'
                                ,'heading' => ''
                                ,'section_rows' => []
                                ,'table_name' => 'user_credits_selfreported'
                                ,'table_id_field_name' => 'user_credit_id'
                                ,'table_id_field_value' => ''
                                ,'form_fields' => [
                                    'date_issued' 			=> ['label' => 'Completed Date', 'width' => 11, 'id' => 'date_issued',  'type' => 'date', 'always_post' => true, 'required' => 1, 'class' => self::$dashboard_admin_row_class . " " . self::$datepicker_class, 'container_class' => self::$container_class]
                                    ,'course_title' 		=> ['label' => 'Title', 'id' => 'course_title', 'width' => 12, 'id' => 'course_title', 'name' => 'course_title',  'datatype' => '', 'type' => 'text', 'always_post' => true, 'required' => 1, 'class' => self::$dashboard_admin_row_class, 'container_class' => self::$container_class, 'onchange' => 'setFriendlyUrlName', 'onchange_args' => json_encode(['this'])]
                                    ,'credit_org_dropdown'	=> ['label' => 'Accreditation', 'width' => 11, 'id' => 'credit_org_dropdown', 'type' => 'dropdown', 'always_post' => true, 'required' => 1, 'ignore' => true, 'onchange' => 'controlHiddenInputDropdown', 'onchange_args' =>  json_encode(['hidden_credit_org_dropdown', 'this']), 'class' => self::$grid_form_field_class, 'container_class' => self::$container_class, 'value' => self::$credit_org_id, 'options' => self::$credit_orgs_dropdown]
                                    ,'credits' 				=> ['label' => 'Number of Credits', 'id' => 'credits', 'width' => 12, 'id' => 'credits', 'datatype' => '', 'type' => 'text', 'always_post' => true, 'required' => 1, 'class' => self::$dashboard_admin_row_class, 'container_class' => self::$container_class, 'onchange' => 'setFriendlyUrlName', 'onchange_args' => json_encode(['this'])]
                                    ,'user_id' 				=> ['label' => 'user_id', 'width' => 12, 'id' => 'user_id', 'type' => 'hidden', 'always_post' => true, 'automatic_value' => self::$user_id]
                                    ,'credit_org_id' 		=> ['label' => 'credit_org_id', 'width' => 12, 'id' => 'credit_org_id', 'type' => 'hidden', 'always_post' => true, 'id' => 'hidden_credit_org_id', 'name' => 'hidden_credit_org_id', 'default_value' => self::$credit_org_id]
                                    ,'user_credit_id' 		=> ['label' => 'user_credit_id', 'width' => 12, 'id' => 'user_credit_id', 'type' => 'hidden', 'always_post' => true, 'id' => 'user_credit_id', 'name' => 'user_credit_id', 'default_value' => self::$credit_org_id]
                                ]
                            ]
                        ]
                        ,'buttons' => [
                            'class' => 'content-center'
                            ,'button' => [
                                // save button
                                0 => [
                                    'class' => "button-primary dGrid-dashboard-admin-save-button"
                                    ,'id' => 'submit-button'
                                    ,'type' => "button"
                                    ,'value' => "Submit"
                                    ,'onclick' => 'fnSubmitSelfreportedCredit'
                                    ,'onclick_args' => json_encode(['this', $oModal_redirect])
                                ]
                            ]
                        ]
                    ]
                ]; 
            
                $oModal->dashboard_form = $oForm->renderForm($oFormConfig);
                $modal_content = $MUSTACHE_ENGINE->render('partials/modal_form.mustache', $oModal);
            
                $html_selfreported = $report->renderDataGrid($aConfig_selfreported, '', $button_add_credit . $modal_content);		
            }
        
            // place library sections into tabbed content
            $aConfig_tabs = [];
            $aConfig_tabs['tabs'][] = [
                'id' => "credittracker-complete-tab"
                ,'heading' => t('credit_tracker.completed')
                ,'is_open' => (get('tab') == 'complete' || get('tab') == '' ? true : false)
                ,'content' => $html_user_courses_complete // requests table and hidden request table
            ];
            if(self::$display_incompleted) {
                $aConfig_tabs['tabs'][] = [
                    'id' => "credittracker-incomplete-tab"
                    ,'heading' => t('credit_tracker.in_progress')
                    ,'is_open' => (get('tab') == 'incomplete' ? true : false)
                    ,'content' => $html_user_courses_incomplete // attended table and hidden attended table
                ];
            }
            if(self::$display_selfreported) {
                $aConfig_tabs['tabs'][] = [
                    'id' => "credittracker-selfreported-tab"
                    ,'heading' => 'Self-Reported'
                    ,'is_open' => (get('tab') == 'selfreported' ? true : false)
                    ,'content' => $html_selfreported 
                ];
            }
            if(self::$display_progress) {
                if($html_user_courses_progress) {
                    $aConfig_tabs['tabs'][] = [
                        'id' => "credittracker-progress-tab"
                        ,'heading' => 'Progress'
                        ,'is_open' => (get('tab') == 'progress' ? true : false)
                        ,'content' => $html_user_courses_progress // requests table and hidden request table
                    ];
                }
            }
            if (!getValueByKey($aConfig_tabs, 'tabs')) {
                $aConfig_tabs = [
                    'no_data' => true
                    ,'no_data_id' => 'credittracker-no-data'
                    ,'no_data_msg' => self::getNoDataTabsMessage()
                ];
            }
            return $MUSTACHE_ENGINE->render('partials/tabbed_content.mustache', $aConfig_tabs);
        }        
    }

private static function setJSModalFunctions($credit_org_id, $modal_formname) {
Footer::addFooterJS(<<<EOF
function addSelfReported(form_id, modal_id) {
    requiredFieldsClear(form_id);
    $('#date_issued').val('');
    $('#course_title').val('');
    $('#credit_org_dropdown select').val('{$credit_org_id}');
    $('#hidden_credit_org_dropdown').val('{$credit_org_id}');
    $('#credit_org_id').val('{$credit_org_id}');
    $('#credits').val('');
    $('#user_credit_id').val('');
    modalFormPopup(modal_id);
}

function fnSubmitSelfreportedCredit(submit_button, redirectURL) {
    var form = submit_button.closest('FORM');
    // validate form fields
    if (requiredFieldsValidation(form)) {
        var params = [];
        params[0] = {'id' : 'date_issued' 		, 'value' : $('#date_issued').val()};
        params[1] = {'id' : 'course_title' 		, 'value' : $('#course_title').val()};
        params[2] = {'id' : 'credit_org_id' 	, 'value' : $('#hidden_credit_org_dropdown').val()};
        params[3] = {'id' : 'credits' 			, 'value' : $('#credits').val()};
        params[4] = {'id' : 'user_id' 			, 'value' : $('#user_id').val()};
        params[5] = {'id' : 'user_credit_id' 	, 'value' : $('#user_credit_id').val()};
    
        fnAjaxPOSTParams('/credit_tracker/ajax/save_selfreported_credits.php'
            , params
            , redirectURL
            , submit_button
        );

    }
}

// loads form from table rows
function loadSelfReported(rowData) {
    requiredFieldsClear('{$modal_formname}');

    $('#date_issued').val(rowData[0]);
    $('#course_title').val(rowData[1]);
    $('#credit_org_dropdown select').val(rowData[3]);
    $('#hidden_credit_org_dropdown').val(rowData[3]);
    $('#credit_org_id').val(rowData[3]);
    $('#credits').val(rowData[4]);
    $('#user_id').val(rowData[5]);
    $('#user_credit_id').val(rowData[6]);
}

EOF);

Footer::addFooterJS(<<<EOF
function showTooltipDownloadCourses(){
    $("#tooltip-text-course-funnel").css('opacity', '0');
    $("#tooltip-text-course-funnel").css("visibility", "hidden");
    $('#tooltip-temp-text-course-download').css('opacity', '1');
    $("#tooltip-temp-text-course-download").css("visibility", "visible");

    setTimeout(
    function() {
        $('#tooltip-temp-text-course-download').css('opacity', '0');
        $("#tooltip-temp-text-course-download").css("visibility", "hidden");
    }, 5000);
}
function showTooltipDownloadWebinars(){
    $("#tooltip-text-webinar-funnel").css('opacity', '0');
    $("#tooltip-text-webinar-funnel").css("visibility", "hidden");
    $('#tooltip-temp-text-webinar-download').css('opacity', '1');
    $("#tooltip-temp-text-webinar-download").css("visibility", "visible");

    setTimeout(
    function() {
        $('#tooltip-temp-text-webinar-download').css('opacity', '0');
        $("#tooltip-temp-text-webinar-download").css("visibility", "hidden");
    }, 5000);
}

$("#tbl_request_totals").on("click", "tr", function() {

	if($(this).html() == '') return; // no button, return
	let data = tbl_request_totals.row(this).data();
	var course_id = data[0]; 

	if(course_id > 0) {
		$("#table-request-details").removeClass('display-none-important');
		$([document.documentElement, document.body]).animate({
			scrollTop: $("#table-request-details").offset().top
		}, 2000);
		$('#tbl_requests').DataTable().columns(0).search(course_id).draw();
		scrollToIDOffset('table-request-details', 50)
	}
});

$("#tbl_attended_totals").on("click", "tr", function() {

	if($(this).html() == '') return; // no button, return
	let data = tbl_attended_totals.row(this).data();
	var course_schedule_id = data[0]; 

	if(course_schedule_id > 0) {
		$("#table-attended-details").removeClass('display-none-important');
		$([document.documentElement, document.body]).animate({
			scrollTop: $("#table-attended-details").offset().top
		}, 2000);
		$('#tbl_attended').DataTable().columns(0).search(course_schedule_id).draw();
		scrollToIDOffset('table-attended-details', 50)
	}
});

$(window).bind("load", function () {
	$('.dt-button').each(function() {
		$(this).addClass('float-left');
		$(this).addClass('button-secondary');
	});
});

EOF);

Footer::addFooterJSDocReady(<<<EOF
$("#tbl_complete").on("draw.dt", function (e, dt, type, indexes) {
    initOnclickListeners('tbl_complete');
});
EOF);

}

}


?>