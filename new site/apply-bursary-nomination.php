<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

function log_bursary_nomination_event($message) {
    $log_path = tmc_get_data_dir() . '/apply-bursary-nomination.log';
    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents($log_path, $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bursary-nomination.html');
    exit;
}

$allowed_returns = ['bursary-nomination.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'bursary-nomination.html', $allowed_returns, 'bursary-nomination.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'bursary-nomination.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-bursary-nomination', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-bursary-nomination', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-bursary-nomination', 6, 900);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-bursary-nomination', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait a few minutes and try again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-bursary-nomination', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$parent_name = tmc_trim_post('parent-name', 160);
$parent_email = tmc_trim_post('parent-email', 254);
$parent_phone = tmc_trim_post('parent-phone', 40);
$student_first_name = tmc_trim_post('student-first-name', 80);
$student_age = tmc_trim_post('student-age', 3);
$laptop_access = tmc_trim_post('laptop-access', 20);
$student_benefit = tmc_trim_post('student-benefit', 4000);
$student_curiosity = tmc_trim_post('student-curiosity', 4000);
$learning_style = tmc_trim_post('learning-style', 4000);
$learning_needs = tmc_trim_post('learning-needs', 4000);
$independent_ready = tmc_trim_post('independent-ready', 20);

if ($parent_name === '') {
    tmc_redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if (!tmc_is_valid_email($parent_email)) {
    tmc_redirect_with_error($error_return, 'parent-email', 'Please provide a valid email.');
}

$phone_digits = tmc_phone_digits($parent_phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    tmc_redirect_with_error($error_return, 'parent-phone', 'Please provide a valid phone number.');
}

if ($student_first_name === '') {
    tmc_redirect_with_error($error_return, 'student-first-name', 'Student first name is required.');
}

$validated_age = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 5, 'max_range' => 18]
]);
if ($validated_age === false) {
    tmc_redirect_with_error($error_return, 'student-age', 'Please provide a valid student age.');
}
$age_value = (string)$validated_age;

$laptop_options = [
    'yes' => 'Yes',
    'no' => 'No',
    'not_sure' => 'Not sure'
];
if (!array_key_exists($laptop_access, $laptop_options)) {
    tmc_redirect_with_error($error_return, 'laptop-access', 'Please choose a laptop access option.');
}

if ($student_benefit === '') {
    tmc_redirect_with_error($error_return, 'student-benefit', 'Please share why the student would benefit.');
}

if ($student_curiosity === '') {
    tmc_redirect_with_error($error_return, 'student-curiosity', 'Please share what the student is curious about.');
}

if ($learning_style === '') {
    tmc_redirect_with_error($error_return, 'learning-style', 'Please describe the student learning style.');
}

$ready_options = [
    'yes' => 'Yes',
    'mostly' => 'Mostly',
    'not_sure' => 'Not sure'
];
if (!array_key_exists($independent_ready, $ready_options)) {
    tmc_redirect_with_error($error_return, 'independent-ready', 'Please choose a readiness option.');
}

$submitted_at = gmdate('c');
$source_page = $return_to;
$from = 'info@the-money-club.org';

$data_dir = tmc_get_data_dir();
$csv_path = $data_dir . '/apply-bursary-nomination-submissions.csv';
$csv_headers = [
    'submitted_at',
    'parent_name',
    'parent_email',
    'parent_phone',
    'student_first_name',
    'student_age',
    'laptop_access',
    'student_benefit',
    'student_curiosity',
    'learning_style',
    'learning_needs',
    'independent_ready',
    'source'
];
$csv_row = [
    $submitted_at,
    $parent_name,
    $parent_email,
    $parent_phone,
    $student_first_name,
    $age_value,
    $laptop_options[$laptop_access],
    $student_benefit,
    $student_curiosity,
    $learning_style,
    $learning_needs,
    $ready_options[$independent_ready],
    $source_page
];

$handle = @fopen($csv_path, 'a+');
if ($handle) {
    if (flock($handle, LOCK_EX)) {
        $is_empty = (filesize($csv_path) === 0);
        if ($is_empty) {
            fputcsv($handle, $csv_headers);
        }
        fputcsv($handle, $csv_row);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
} else {
    log_bursary_nomination_event('csv_write_failed source=' . $source_page);
}

$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Founding Student Bursary Nomination: The Money Club.Org';
$internal_lines = [];
$internal_lines[] = 'Parent/Guardian Name: ' . $parent_name;
$internal_lines[] = 'Parent/Guardian Email: ' . $parent_email;
$internal_lines[] = 'Parent/Guardian Phone: ' . $parent_phone;
$internal_lines[] = 'Student First Name: ' . $student_first_name;
$internal_lines[] = 'Student Age: ' . $age_value;
$internal_lines[] = 'Laptop Access: ' . $laptop_options[$laptop_access];
$internal_lines[] = 'Why This Student Would Benefit: ' . $student_benefit;
$internal_lines[] = 'Student Curiosity: ' . $student_curiosity;
$internal_lines[] = 'Learning Style: ' . $learning_style;
$internal_lines[] = 'Learning Needs or Accommodations: ' . ($learning_needs !== '' ? $learning_needs : '(none provided)');
$internal_lines[] = 'Ready to Work Independently With Guidance: ' . $ready_options[$independent_ready];
$internal_lines[] = 'Source: ' . $source_page;
$internal_lines[] = 'Submitted At: ' . $submitted_at;
$internal_message = implode("\n", $internal_lines);

if (!smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $parent_email)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_bursary_nomination_event('internal_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
    tmc_redirect_with_error($error_return, 'form', 'Unable to submit right now. Please try again shortly.');
}

$name_parts = preg_split('/\s+/', trim($parent_name));
$first_name = $name_parts && isset($name_parts[0]) ? $name_parts[0] : '';
$greeting = $first_name !== '' ? 'Hello ' . $first_name . ',' : 'Hello,';

$parent_subject = 'The Money Club.Org | Bursary Nomination Received';
$parent_message = <<<EMAIL
$greeting

Thank you for nominating a student for The Money Club Founding Student Bursary. We will review nominations based on fit, curiosity, access need, and readiness for the program. Selected families will be contacted directly.

You can review how The Money Club.Org works here:
https://the-money-club.org/curriculum-details.html

Warmly,

The Money Club.Org
EMAIL;

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_bursary_nomination_event('parent_confirmation_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
}

tmc_redirect_with_status($return_to, 'sent');
?>
