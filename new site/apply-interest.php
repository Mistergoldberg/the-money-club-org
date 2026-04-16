<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

function log_interest_event($message) {
    $log_path = tmc_get_data_dir() . '/apply-interest.log';
    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents($log_path, $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$allowed_returns = [
    'index.html',
    'index-03-26.html',
    'how-it-works.html',
    'schedule-pricing.html',
    'pricing.html',
    'faq.html',
    'open-book-hook.html',
    'reserve-a-spot.html',
    'curriculum-details.html',
    'executive-director-letter.html',
    'who-runs-it.html'
];

$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'index.html', $allowed_returns, 'index.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? $return_to, $allowed_returns, 'index.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-interest', 'csrf_cookie_failed');
    tmc_redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-interest', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-interest', 8, 600);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-interest', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    tmc_redirect_with_error($error_return, 'form', 'Too many submissions. Please wait a few minutes and try again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-interest', 'csrf_failed', ['reason' => $csrf_reason]);
    tmc_redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

$parent_name = tmc_trim_post('parent-name', 160);
$parent_email = tmc_trim_post('parent-email', 254);
$student_age = tmc_trim_post('student-age', 3);
$interested_session = tmc_trim_post('interested-session', 20);

if ($parent_name === '') {
    tmc_redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if (!tmc_is_valid_email($parent_email)) {
    tmc_redirect_with_error($error_return, 'parent-email', 'Please provide a valid email.');
}

$age_value = '';
if ($student_age !== '') {
    $validated_age = filter_var($student_age, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 10, 'max_range' => 16]
    ]);
    if ($validated_age === false) {
        tmc_redirect_with_error($error_return, 'student-age', 'Child\'s age must be between 10 and 16.');
    }
    $age_value = (string)$validated_age;
}

$session_options = [
    '' => 'Either session',
    'july' => 'July session',
    'august' => 'August session',
    'either' => 'Either session'
];

if (!array_key_exists($interested_session, $session_options)) {
    tmc_redirect_with_error($error_return, 'interested-session', 'Please choose a valid session option.');
}

$submitted_at = gmdate('c');
$source_page = $return_to;
$from = 'info@the-money-club.org';

$data_dir = tmc_get_data_dir();
$csv_path = $data_dir . '/apply-interest-submissions.csv';
$csv_headers = ['submitted_at', 'parent_name', 'parent_email', 'student_age', 'interested_session', 'source'];
$csv_row = [$submitted_at, $parent_name, $parent_email, $age_value, $session_options[$interested_session], $source_page];

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
    log_interest_event('csv_write_failed source=' . $source_page);
}

$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Interest List Lead: The Money Club.Org';
$internal_lines = [];
$internal_lines[] = 'Parent/Guardian Name: ' . $parent_name;
$internal_lines[] = 'Email: ' . $parent_email;
$internal_lines[] = 'Child Age: ' . ($age_value !== '' ? $age_value : '(not provided)');
$internal_lines[] = 'Interested In: ' . $session_options[$interested_session];
$internal_lines[] = 'Source: ' . $source_page;
$internal_lines[] = 'Submitted At: ' . $submitted_at;
$internal_message = implode("\n", $internal_lines);

if (!smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $parent_email)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_interest_event('internal_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
}

$first_name = '';
if ($parent_name !== '') {
    $parts = preg_split('/\s+/', $parent_name);
    $first_name = $parts ? trim((string)$parts[0]) : '';
}

$greeting = $first_name !== '' ? 'Hi ' . $first_name . ',' : 'Hi there,';
$parent_subject = 'You’re in — April 18 Info Session';
$parent_lines = [];
$parent_lines[] = $greeting;
$parent_lines[] = '';
$parent_lines[] = 'We’ll send the virtual call link and session details shortly.';
$parent_lines[] = '';
$parent_lines[] = 'You’re confirmed for the April 18 Info Session.';
$parent_lines[] = '';
$parent_lines[] = 'This is a short virtual session (about 30 minutes) where we’ll walk through how The Money Club.Org works and what students actually experience day to day.';
$parent_lines[] = '';
$parent_lines[] = 'We’ll cover:';
$parent_lines[] = 'what students do each day';
$parent_lines[] = 'how the program is structured';
$parent_lines[] = 'how instruction and supervision work';
$parent_lines[] = 'how registration works';
$parent_lines[] = '';
$parent_lines[] = 'There will also be time for live Q&A.';
$parent_lines[] = '';
$parent_lines[] = 'We’ll send the virtual call link and full session details shortly before the event.';
$parent_lines[] = '';
$parent_lines[] = 'If you can’t attend live, we’ll send a recording afterward.';
$parent_lines[] = '';
$parent_lines[] = 'Talk soon,';
$parent_lines[] = 'The Money Club.Org';

if ($interested_session !== '') {
    $parent_lines[] = '';
    $parent_lines[] = 'Preferred session: ' . $session_options[$interested_session];
}
if ($age_value !== '') {
    $parent_lines[] = 'Submitted child age: ' . $age_value;
}

$parent_message = implode("\n", $parent_lines);

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_interest_event('parent_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
}

tmc_redirect_with_status($return_to, 'sent');
?>
