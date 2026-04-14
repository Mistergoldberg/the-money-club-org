<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: parent-approval.html');
    exit;
}

function clip_value($value, $max_length) {
    $value = trim((string)$value);
    if (strlen($value) > $max_length) {
        return substr($value, 0, $max_length);
    }
    return $value;
}

$parent_name = clip_value($_POST['parent-name'] ?? '', 160);
$parent_email = clip_value($_POST['parent-email'] ?? '', 254);
$parent_phone = clip_value($_POST['parent-phone'] ?? '', 40);
$student_name = clip_value($_POST['student-name'] ?? '', 160);
$student_age = clip_value($_POST['student-age'] ?? '', 3);
$program_track = clip_value($_POST['program-track'] ?? '', 80);
$preferred_session = clip_value($_POST['preferred-session'] ?? '', 80);
$terms_agree = clip_value($_POST['terms-agree'] ?? '', 10);

$valid_programs = ['two-week-builder-sprint', 'four-week-full-program'];
$valid_age = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 10, 'max_range' => 16]
]);

$phone_digits = preg_replace('/\D+/', '', $parent_phone);
$has_required_identity =
    $parent_name !== '' &&
    $student_name !== '' &&
    filter_var($parent_email, FILTER_VALIDATE_EMAIL) &&
    $phone_digits !== '' &&
    strlen($phone_digits) >= 10 &&
    strlen($phone_digits) <= 15 &&
    $valid_age !== false &&
    in_array($program_track, $valid_programs, true) &&
    $preferred_session !== '' &&
    $terms_agree !== '';

if (!$has_required_identity) {
    http_response_code(400);
    exit('Unable to continue registration safely. Please go back and complete all required fields.');
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

header('Location: parent-approval.html');
exit;
?>
