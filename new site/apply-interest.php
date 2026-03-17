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

$to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$subject = 'Interest List Lead: The Money Club.Org';
$from = 'info@the-money-club.org';

$lines = [];
$lines[] = 'Parent/Guardian Name: ' . $parent_name;
$lines[] = 'Email: ' . $parent_email;
$lines[] = 'Child Age: ' . ($age_value !== '' ? $age_value : '(not provided)');
$lines[] = 'Interested In: ' . $session_options[$interested_session];
$lines[] = 'Source: Homepage Stage 1 Interest Form';
$lines[] = 'Submitted At: ' . gmdate('c');

$message = implode("\n", $lines);

if (!smtp_send_mail($to, $subject, $message, $from, $parent_email)) {
    redirect_with_error($error_return, 'parent-email', 'Unable to submit right now. Please try again.');
}

$data_dir = get_data_dir();
$csv_path = $data_dir . '/apply-interest-submissions.csv';
$csv_headers = ['submitted_at', 'parent_name', 'parent_email', 'student_age', 'interested_session', 'source'];
$csv_row = [gmdate('c'), $parent_name, $parent_email, $age_value, $session_options[$interested_session], 'homepage_stage1_interest'];

$handle = @fopen($csv_path, 'a');
if ($handle) {
    if (filesize($csv_path) === 0) {
        fputcsv($handle, $csv_headers);
    }
    fputcsv($handle, $csv_row);
    fclose($handle);
}

$separator = (strpos($return_to, '?') === false) ? '?' : '&';
header('Location: ' . $return_to . $separator . 'status=sent');
exit;
?>
