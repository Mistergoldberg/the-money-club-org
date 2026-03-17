<?php
require_once __DIR__ . '/smtp-send.php';

function get_data_dir() {
    $outside = dirname(__DIR__) . '/data';
    if (is_dir($outside) && is_writable($outside)) {
        return $outside;
    }
    $inside = __DIR__ . '/data';
    if (is_dir($inside) && is_writable($inside)) {
        return $inside;
    }
    return $inside;
}

function log_interest_event($message) {
    $log_path = get_data_dir() . '/apply-interest.log';
    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents($log_path, $line, FILE_APPEND);
}

function redirect_with_error($return_to, $field, $message) {
    $params = [
        'status' => 'error',
        'field' => $field,
        'message' => $message
    ];
    $separator = (strpos($return_to, '?') === false) ? '?' : '&';
    header('Location: ' . $return_to . $separator . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$parent_name = isset($_POST['parent-name']) ? trim($_POST['parent-name']) : '';
$parent_email = isset($_POST['parent-email']) ? trim($_POST['parent-email']) : '';
$student_age = isset($_POST['student-age']) ? trim($_POST['student-age']) : '';
$interested_session = isset($_POST['interested-session']) ? trim($_POST['interested-session']) : '';

$return_to = isset($_POST['return-to']) ? trim($_POST['return-to']) : 'index.html';
$error_return = isset($_POST['return-error']) ? trim($_POST['return-error']) : 'index.html';
$allowed_returns = ['index.html', 'how-it-works.html', 'schedule-pricing.html'];

if (!in_array($return_to, $allowed_returns, true)) {
    $return_to = 'index.html';
}
if (!in_array($error_return, $allowed_returns, true)) {
    $error_return = 'index.html';
}

if ($parent_name === '') {
    redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if ($parent_email === '' || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error($error_return, 'parent-email', 'Please provide a valid email.');
}

$age_value = '';
if ($student_age !== '') {
    $validated_age = filter_var($student_age, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 10, 'max_range' => 16]
    ]);
    if ($validated_age === false) {
        redirect_with_error($error_return, 'student-age', 'Child\'s age must be between 10 and 16.');
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
    redirect_with_error($error_return, 'interested-session', 'Please choose a valid session option.');
}

$submitted_at = gmdate('c');
$source_page = $return_to;
$from = 'info@the-money-club.org';

$data_dir = get_data_dir();
$csv_path = $data_dir . '/apply-interest-submissions.csv';
$csv_headers = ['submitted_at', 'parent_name', 'parent_email', 'student_age', 'interested_session', 'source'];
$csv_row = [$submitted_at, $parent_name, $parent_email, $age_value, $session_options[$interested_session], $source_page];

$handle = @fopen($csv_path, 'a');
if ($handle) {
    if (filesize($csv_path) === 0) {
        fputcsv($handle, $csv_headers);
    }
    fputcsv($handle, $csv_row);
    fclose($handle);
} else {
    log_interest_event('csv_write_failed email=' . $parent_email . ' source=' . $source_page);
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
    log_interest_event('internal_email_failed email=' . $parent_email . ' source=' . $source_page);
}

$greeting = $parent_name !== '' ? 'Hi ' . $parent_name . ',' : 'Hi there,';
$parent_subject = 'Money Club.Org - Program Details & Next Steps';
$parent_lines = [];
$parent_lines[] = $greeting;
$parent_lines[] = '';
$parent_lines[] = 'Thanks for your interest in The Money Club.Org.';
$parent_lines[] = '';
$parent_lines[] = 'How the program works';
$parent_lines[] = '- 4-week summer program';
$parent_lines[] = '- Daily 9-5';
$parent_lines[] = '- Ages 10-16';
$parent_lines[] = '- UTSU Student Commons (230 College St.)';
$parent_lines[] = '- Students build real products and learn how money works';
$parent_lines[] = '';
$parent_lines[] = 'Session Dates';
$parent_lines[] = 'Session 1: July 6-31, 2026';
$parent_lines[] = 'Session 2: August 4-28, 2026';
$parent_lines[] = '';
$parent_lines[] = 'Small groups: 10 students to 1 instructor.';
$parent_lines[] = '';
$parent_lines[] = 'Program Tuition';
$parent_lines[] = '$1,500 per student (+HST)';
$parent_lines[] = '(Nonprofit program - open-book structure)';
$parent_lines[] = '';
$parent_lines[] = 'Refunds available until June 1, 2026.';
$parent_lines[] = '';
$parent_lines[] = 'Next Step: Complete Registration';
$parent_lines[] = '';
$parent_lines[] = 'To confirm a spot, select your session and complete registration:';
$parent_lines[] = '';
$parent_lines[] = 'https://the-money-club.org/reserve-a-spot.html';
$parent_lines[] = '';
$parent_lines[] = 'Spots are limited to keep group sizes small.';
$parent_lines[] = '';
$parent_lines[] = 'If you have any questions, just reply to this email.';
$parent_lines[] = '';
$parent_lines[] = '- The Money Club.Org';

if ($age_value !== '') {
    $parent_lines[] = '';
    $parent_lines[] = 'Submitted child age: ' . $age_value;
}
if ($interested_session !== '') {
    $parent_lines[] = 'Preferred session: ' . $session_options[$interested_session];
}

$parent_message = implode("\n", $parent_lines);

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    log_interest_event('parent_email_failed email=' . $parent_email . ' source=' . $source_page);
}

$separator = (strpos($return_to, '?') === false) ? '?' : '&';
header('Location: ' . $return_to . $separator . 'status=sent');
exit;
?>
