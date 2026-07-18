<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

function log_survey_event($message) {
    $log_path = tmc_get_data_dir() . '/apply-survey.log';
    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents($log_path, $line, FILE_APPEND);
}

function survey_array_post($key, $allowed, $max_count, $error_return, $field_label) {
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        $raw = [$raw];
    }

    $values = [];
    foreach ($raw as $value) {
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }
        if (!array_key_exists($value, $allowed)) {
            tmc_redirect_with_error($error_return, $key, 'Please choose valid options for ' . $field_label . '.');
        }
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    if (count($values) > $max_count) {
        tmc_redirect_with_error($error_return, $key, 'Please choose no more than ' . $max_count . ' options.');
    }

    return $values;
}

function survey_labels($values, $allowed) {
    $labels = [];
    foreach ($values as $value) {
        if (isset($allowed[$value])) {
            $labels[] = $allowed[$value];
        }
    }
    return implode('; ', $labels);
}

function survey_trim_text($key, $max_length = 4000) {
    return tmc_trim_post($key, $max_length);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: survey.html');
    exit;
}

$allowed_returns = ['survey.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'survey.html', $allowed_returns, 'survey.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'survey.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-survey', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-survey', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-survey', 8, 900);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-survey', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait a few minutes and try again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-survey', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$student_name = survey_trim_text('student-name', 160);
$student_age = survey_trim_text('student-age', 3);

if ($student_name === '') {
    tmc_redirect_with_error($error_return, 'student-name', 'Student name is required.');
}

$validated_age = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 5, 'max_range' => 18]
]);
if ($validated_age === false) {
    tmc_redirect_with_error($error_return, 'student-age', 'Please provide a valid student age.');
}
$age_value = (string)$validated_age;

$activity_options = [
    'talking_questions' => 'Talking to people and asking questions',
    'finding_information' => 'Finding information',
    'numbers_prices' => 'Working with numbers and prices',
    'drawing_designing' => 'Drawing and designing',
    'writing_marketing' => 'Writing stories, names, slogans, or advertisements',
    'photo_video' => 'Taking photographs or making videos',
    'computers_websites' => 'Using computers or building websites',
    'presenting_selling' => 'Presenting or selling an idea',
    'organizing_planning' => 'Organizing people and making plans',
    'not_sure' => 'I am not sure yet'
];

$try_options = [
    'customer_interviewing' => 'Interviewing customers',
    'costs_prices_profits' => 'Calculating costs, prices, and profits',
    'product_design' => 'Designing a product',
    'logo_packaging' => 'Creating a logo and packaging',
    'building_website' => 'Building a website',
    'ads_photo_video' => 'Making advertisements, photographs, or videos',
    'story_sales_pitch' => 'Writing a product story or sales pitch',
    'presenting_selling' => 'Presenting and selling an idea',
    'organizing_team' => 'Organizing the team'
];

$work_options = [
    'one_or_two_people' => 'I like working with one or two other people.',
    'larger_group' => 'I like working with a larger group.',
    'one_clear_job' => 'I like having one clear job.',
    'many_jobs' => 'I like helping with many different jobs.',
    'group_decisions' => 'I like helping the group make decisions.',
    'not_sure' => 'I am not sure yet.'
];

$product_options = [
    'food_snacks_drinks' => 'Food, snacks, or drinks',
    'sports_fitness_outdoors' => 'Sports, fitness, or outdoor activities',
    'fashion_personal_care' => 'Fashion, accessories, or personal care',
    'art_entertainment_games' => 'Art, entertainment, games, or hobbies',
    'school_student_life' => 'School or student life',
    'technology_apps_gaming' => 'Technology, apps, websites, or gaming',
    'household_products' => 'Household products',
    'pets_animals' => 'Pets or animals',
    'environment_waste' => 'The environment or reducing waste',
    'families_communities' => 'Helping families or communities',
    'something_else' => 'Something else'
];

$activities = survey_array_post('activities', $activity_options, 3, $error_return, 'activities');
$try_during = survey_array_post('try-during', $try_options, 2, $error_return, 'what you would like to try');
$work_preference = survey_array_post('work-preference', $work_options, 2, $error_return, 'work preferences');
$product_interest = survey_array_post('product-interest', $product_options, 3, $error_return, 'product interests');

$product_interest_other = survey_trim_text('product-interest-other', 500);
$everyday_problem = survey_trim_text('everyday-problem');
$best_work_supports = survey_trim_text('best-work-supports');

$submitted_at = gmdate('c');
$source_page = $return_to;
$from = 'info@the-money-club.org';

$data_dir = tmc_get_data_dir();
$csv_path = $data_dir . '/student-survey-submissions.csv';
$csv_headers = [
    'submitted_at',
    'student_name',
    'student_age',
    'activities',
    'try_during',
    'work_preference',
    'product_interest',
    'product_interest_other',
    'everyday_problem',
    'best_work_supports',
    'source'
];
$csv_row = [
    $submitted_at,
    $student_name,
    $age_value,
    survey_labels($activities, $activity_options),
    survey_labels($try_during, $try_options),
    survey_labels($work_preference, $work_options),
    survey_labels($product_interest, $product_options),
    $product_interest_other,
    $everyday_problem,
    $best_work_supports,
    $source_page
];

$csv_written = false;
$handle = @fopen($csv_path, 'a+');
if ($handle) {
    if (flock($handle, LOCK_EX)) {
        $is_empty = (filesize($csv_path) === 0);
        if ($is_empty) {
            fputcsv($handle, $csv_headers);
        }
        $csv_written = fputcsv($handle, $csv_row) !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

if (!$csv_written) {
    log_survey_event('csv_write_failed source=' . $source_page);
    tmc_redirect_with_error($error_return, 'form', 'Unable to save this survey right now. Please try again shortly.');
}

$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Student Survey: ' . $student_name;

$internal_lines = [];
$internal_lines[] = 'Student Name: ' . $student_name;
$internal_lines[] = 'Age: ' . $age_value;
$internal_lines[] = 'Good At / Enjoys: ' . (survey_labels($activities, $activity_options) !== '' ? survey_labels($activities, $activity_options) : '(none selected)');
$internal_lines[] = 'Would Like To Try: ' . (survey_labels($try_during, $try_options) !== '' ? survey_labels($try_during, $try_options) : '(none selected)');
$internal_lines[] = 'Work Preference: ' . (survey_labels($work_preference, $work_options) !== '' ? survey_labels($work_preference, $work_options) : '(none selected)');
$internal_lines[] = 'Product Interests: ' . (survey_labels($product_interest, $product_options) !== '' ? survey_labels($product_interest, $product_options) : '(none selected)');
$internal_lines[] = 'Product Interests - Other: ' . ($product_interest_other !== '' ? $product_interest_other : '(blank)');
$internal_lines[] = 'Everyday Problem: ' . ($everyday_problem !== '' ? $everyday_problem : '(blank)');
$internal_lines[] = 'Best Work Supports: ' . ($best_work_supports !== '' ? $best_work_supports : '(blank)');
$internal_lines[] = 'Source: ' . $source_page;
$internal_lines[] = 'Submitted At: ' . $submitted_at;
$internal_message = implode("\n", $internal_lines);

if (!smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $from)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_survey_event('internal_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
}

tmc_redirect_with_status($return_to, 'sent');
?>
