<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/form-security.php';
require_once __DIR__ . '/smtp-send.php';

function log_parent_approval_start_event($message) {
    $log_path = tmc_get_data_dir() . '/start-parent-approval.log';
    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents($log_path, $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: parent-approval.html');
    exit;
}

$allowed_returns = ['parent-approval.html'];
$allowed_error_returns = ['reserve-a-spot.html', 'pricing.html', 'parent-approval.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'parent-approval.html', $allowed_returns, 'parent-approval.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? 'reserve-a-spot.html', $allowed_error_returns, 'reserve-a-spot.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('start-parent-approval', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('start-parent-approval', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('start-parent-approval', 10, 600);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('start-parent-approval', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait a few minutes and try again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('start-parent-approval', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$parent_name = tmc_trim_post('parent-name', 160);
$parent_email = tmc_trim_post('parent-email', 254);
$parent_phone = tmc_trim_post('parent-phone', 40);
$student_name = tmc_trim_post('student-name', 160);
$student_age = tmc_trim_post('student-age', 3);
$program_track = tmc_trim_post('program-track', 80);
$preferred_session = tmc_trim_post('preferred-session', 80);
$terms_agree = tmc_trim_post('terms-agree', 10);

if ($parent_name === '') {
    tmc_redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}
if ($student_name === '') {
    tmc_redirect_with_error($error_return, 'student-name', 'Child\'s name is required.');
}
if (!tmc_is_valid_email($parent_email)) {
    tmc_redirect_with_error($error_return, 'parent-email', 'Please provide a valid parent email.');
}

$phone_digits = tmc_phone_digits($parent_phone);
if ($phone_digits === '' || strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
    tmc_redirect_with_error($error_return, 'parent-phone', 'Please provide a valid parent phone number.');
}

$valid_age = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0]
]);
if ($valid_age === false) {
    tmc_redirect_with_error($error_return, 'student-age', 'Please provide a valid child age.');
}

$valid_programs = ['money-club-program'];
if (!in_array($program_track, $valid_programs, true)) {
    tmc_redirect_with_error($error_return, 'program-track', 'Please select a valid program.');
}
if ($preferred_session === '') {
    tmc_redirect_with_error($error_return, 'preferred-session', 'Please select a session.');
}
$session_labels = [
    'aug_10_14' => 'August 10th-14th, 2026'
];
if (!array_key_exists($preferred_session, $session_labels)) {
    tmc_redirect_with_error($error_return, 'preferred-session', 'Please select a valid session.');
}
if ($terms_agree === '') {
    tmc_redirect_with_error($error_return, 'terms-agree', 'Please agree to the Terms & Payment Policy.');
}

$_SESSION['tmc_parent_approval_prefill'] = [
    'parent_name' => $parent_name,
    'parent_email' => $parent_email,
    'parent_phone' => $parent_phone,
    'student_name' => $student_name,
    'student_age' => (string)$valid_age,
    'session' => $preferred_session,
    'program_track' => $program_track
];
$_SESSION['tmc_parent_approval_prefill_set_at'] = time();

$program_labels = [
    'money-club-program' => 'The Money Club.Org Program'
];

$from = 'info@the-money-club.org';
$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Reserve a Spot Started: The Money Club.Org';
$internal_lines = [];
$internal_lines[] = 'Parent/Guardian Name: ' . $parent_name;
$internal_lines[] = 'Parent Email: ' . $parent_email;
$internal_lines[] = 'Parent Phone: ' . $parent_phone;
$internal_lines[] = 'Student Name: ' . $student_name;
$internal_lines[] = 'Student Age: ' . (string)$valid_age;
$internal_lines[] = 'Program: ' . ($program_labels[$program_track] ?? $program_track);
$internal_lines[] = 'Session: ' . ($session_labels[$preferred_session] ?? $preferred_session);
$internal_lines[] = 'Terms agreed: Yes';
$internal_lines[] = 'Next step: Parent approval form';
$internal_lines[] = 'Source: ' . $error_return;
$internal_lines[] = 'Submitted At: ' . gmdate('c');
$internal_message = implode("\n", $internal_lines);

if (smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $parent_email)) {
    log_parent_approval_start_event('internal_email_sent source=' . $error_return);
} else {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    if ($smtp_reason === '') {
        $smtp_reason = 'unknown';
    }
    log_parent_approval_start_event('internal_email_failed reason=' . $smtp_reason . ' source=' . $error_return);
}

header('Location: ' . $return_to);
exit;
?>
