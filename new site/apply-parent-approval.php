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

function log_parent_approval_event($message) {
    $log_path = get_data_dir() . '/apply-parent-approval.log';
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

function get_parent_approval_hash_secret($data_dir) {
    $env_secret = getenv('PARENT_APPROVAL_HASH_KEY');
    if (is_string($env_secret) && strlen(trim($env_secret)) >= 16) {
        return trim($env_secret);
    }

    $key_path = $data_dir . '/parent-approval-hash.key';
    if (is_file($key_path) && is_readable($key_path)) {
        $existing_key = trim((string)@file_get_contents($key_path));
        if (strlen($existing_key) >= 16) {
            return $existing_key;
        }
    }

    try {
        $new_key = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $new_key = hash('sha256', __FILE__ . php_uname('n'));
    }

    @file_put_contents($key_path, $new_key, LOCK_EX);
    @chmod($key_path, 0600);
    return $new_key;
}

function get_previous_parent_approval_hash($chain_path) {
    if (!is_file($chain_path) || !is_readable($chain_path)) {
        return str_repeat('0', 64);
    }

    $lines = @file($chain_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return str_repeat('0', 64);
    }

    $last_line = trim((string)end($lines));
    $parts = explode(',', $last_line);
    $candidate = isset($parts[1]) ? trim($parts[1]) : '';
    if (preg_match('/^[a-f0-9]{64}$/', $candidate)) {
        return $candidate;
    }

    return str_repeat('0', 64);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: parent-approval.html');
    exit;
}

$student_name = isset($_POST['student-name']) ? trim($_POST['student-name']) : '';
$student_age = isset($_POST['student-age']) ? trim($_POST['student-age']) : '';
$parent_name = isset($_POST['parent-name']) ? trim($_POST['parent-name']) : '';
$parent_email = isset($_POST['parent-email']) ? trim($_POST['parent-email']) : '';
$parent_phone = isset($_POST['parent-phone']) ? trim($_POST['parent-phone']) : '';
$session = isset($_POST['session']) ? trim($_POST['session']) : '';
$emergency_contact_name = isset($_POST['emergency-contact-name']) ? trim($_POST['emergency-contact-name']) : '';
$emergency_contact_phone = isset($_POST['emergency-contact-phone']) ? trim($_POST['emergency-contact-phone']) : '';
$medical_notes = isset($_POST['medical-notes']) ? trim($_POST['medical-notes']) : '';
$photo_consent = isset($_POST['photo-consent']) ? trim($_POST['photo-consent']) : '';
$parent_signature_name = isset($_POST['parent-signature-name']) ? trim($_POST['parent-signature-name']) : '';
$consent_agree = isset($_POST['consent-agree']) ? trim($_POST['consent-agree']) : '';

$return_to = isset($_POST['return-to']) ? trim($_POST['return-to']) : 'parent-approval.html';
$error_return = isset($_POST['return-error']) ? trim($_POST['return-error']) : 'parent-approval.html';
$allowed_returns = ['parent-approval.html'];

if (!in_array($return_to, $allowed_returns, true)) {
    $return_to = 'parent-approval.html';
}
if (!in_array($error_return, $allowed_returns, true)) {
    $error_return = 'parent-approval.html';
}

if ($student_name === '') {
    redirect_with_error($error_return, 'student-name', 'Student name is required.');
}

$age_value = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 10, 'max_range' => 16]
]);
if ($age_value === false) {
    redirect_with_error($error_return, 'student-age', 'Student age must be between 10 and 16.');
}

if ($parent_name === '') {
    redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if ($parent_email === '' || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error($error_return, 'parent-email', 'Please provide a valid parent email.');
}

$parent_phone_digits = preg_replace('/\D+/', '', $parent_phone);
if ($parent_phone === '' || $parent_phone_digits === '' || strlen($parent_phone_digits) < 10 || strlen($parent_phone_digits) > 15) {
    redirect_with_error($error_return, 'parent-phone', 'Please provide a valid parent phone number.');
}

$session_map = [
    'session1' => 'July 6 - July 31, 2026',
    'session2' => 'August 4 - August 28, 2026'
];
if (!array_key_exists($session, $session_map)) {
    redirect_with_error($error_return, 'session', 'Please select a valid session.');
}

if ($emergency_contact_name === '') {
    redirect_with_error($error_return, 'emergency-contact-name', 'Emergency contact name is required.');
}

$emergency_phone_digits = preg_replace('/\D+/', '', $emergency_contact_phone);
if ($emergency_contact_phone === '' || $emergency_phone_digits === '' || strlen($emergency_phone_digits) < 10 || strlen($emergency_phone_digits) > 15) {
    redirect_with_error($error_return, 'emergency-contact-phone', 'Please provide a valid emergency contact phone number.');
}

if ($photo_consent !== '' && !in_array($photo_consent, ['yes', 'no'], true)) {
    redirect_with_error($error_return, 'photo-consent', 'Please choose Yes or No for photo/media consent.');
}

if ($parent_signature_name === '') {
    redirect_with_error($error_return, 'parent-signature-name', 'Typed parent/guardian full name is required.');
}

if ($consent_agree === '') {
    redirect_with_error($error_return, 'consent-agree', 'Please confirm parent/guardian approval.');
}

if (strlen($medical_notes) > 4000) {
    $medical_notes = substr($medical_notes, 0, 4000);
}

$submitted_at_utc = gmdate('c');
$submitted_at_local = date('Y-m-d H:i:s T');
$ip_address = '';

if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip_address = trim($forwarded[0]);
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $ip_address = trim($_SERVER['REMOTE_ADDR']);
}

$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '';
if (strlen($user_agent) > 500) {
    $user_agent = substr($user_agent, 0, 500);
}

$source_page = $return_to;
$photo_consent_label = $photo_consent === '' ? 'Not provided' : strtoupper($photo_consent);

$data_dir = get_data_dir();
$hash_secret = get_parent_approval_hash_secret($data_dir);
$hash_chain_path = $data_dir . '/parent-approval-hash-chain.log';

$previous_hash = get_previous_parent_approval_hash($hash_chain_path);
$hash_payload = [
    'submitted_at_utc' => $submitted_at_utc,
    'student_name' => $student_name,
    'student_age' => (string)$age_value,
    'parent_name' => $parent_name,
    'parent_email' => $parent_email,
    'parent_phone' => $parent_phone,
    'session' => $session_map[$session],
    'emergency_contact_name' => $emergency_contact_name,
    'emergency_contact_phone' => $emergency_contact_phone,
    'medical_notes' => $medical_notes,
    'photo_consent' => $photo_consent_label,
    'typed_signature_name' => $parent_signature_name,
    'consent_confirmed' => 'Yes',
    'ip_address' => $ip_address,
    'user_agent' => $user_agent,
    'source' => $source_page,
    'previous_hash' => $previous_hash
];
$hash_input = json_encode($hash_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$submission_hash = hash_hmac('sha256', (string)$hash_input, $hash_secret);

$chain_line = $submitted_at_utc . ',' . $submission_hash . ',' . $previous_hash . "\n";
if (@file_put_contents($hash_chain_path, $chain_line, FILE_APPEND | LOCK_EX) === false) {
    log_parent_approval_event('hash_chain_write_failed email=' . $parent_email);
}

$csv_path = $data_dir . '/parent-approval-submissions.csv';
$csv_headers = [
    'submitted_at_utc',
    'submitted_at_local',
    'student_name',
    'student_age',
    'parent_name',
    'parent_email',
    'parent_phone',
    'session',
    'emergency_contact_name',
    'emergency_contact_phone',
    'medical_notes',
    'photo_consent',
    'typed_signature_name',
    'consent_confirmed',
    'ip_address',
    'user_agent',
    'source',
    'previous_hash',
    'submission_hash'
];
$csv_row = [
    $submitted_at_utc,
    $submitted_at_local,
    $student_name,
    (string)$age_value,
    $parent_name,
    $parent_email,
    $parent_phone,
    $session_map[$session],
    $emergency_contact_name,
    $emergency_contact_phone,
    $medical_notes,
    $photo_consent_label,
    $parent_signature_name,
    'Yes',
    $ip_address,
    $user_agent,
    $source_page,
    $previous_hash,
    $submission_hash
];

$handle = @fopen($csv_path, 'a');
if ($handle) {
    $needs_headers = !file_exists($csv_path) || filesize($csv_path) === 0;
    if ($needs_headers) {
        fputcsv($handle, $csv_headers);
    }
    fputcsv($handle, $csv_row);
    fclose($handle);
} else {
    log_parent_approval_event('csv_write_failed email=' . $parent_email);
}

$from = 'info@the-money-club.org';
$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Parent Approval Form: The Money Club.Org';
$internal_lines = [];
$internal_lines[] = 'Student Name: ' . $student_name;
$internal_lines[] = 'Student Age: ' . (string)$age_value;
$internal_lines[] = 'Parent/Guardian Name: ' . $parent_name;
$internal_lines[] = 'Parent Email: ' . $parent_email;
$internal_lines[] = 'Parent Phone: ' . $parent_phone;
$internal_lines[] = 'Session: ' . $session_map[$session];
$internal_lines[] = 'Emergency Contact Name: ' . $emergency_contact_name;
$internal_lines[] = 'Emergency Contact Phone: ' . $emergency_contact_phone;
$internal_lines[] = 'Medical / Safety Notes: ' . ($medical_notes !== '' ? $medical_notes : '(none)');
$internal_lines[] = 'Photo / Media Consent: ' . $photo_consent_label;
$internal_lines[] = 'Typed Parent Signature: ' . $parent_signature_name;
$internal_lines[] = 'Consent Confirmed: Yes';
$internal_lines[] = 'Submitted At (UTC): ' . $submitted_at_utc;
$internal_lines[] = 'Submitted At (Server Local): ' . $submitted_at_local;
$internal_lines[] = 'IP Address: ' . ($ip_address !== '' ? $ip_address : '(unavailable)');
$internal_lines[] = 'User Agent: ' . ($user_agent !== '' ? $user_agent : '(unavailable)');
$internal_lines[] = 'Source: ' . $source_page;
$internal_lines[] = 'Previous Hash: ' . $previous_hash;
$internal_lines[] = 'Submission Hash: ' . $submission_hash;
$internal_message = implode("\n", $internal_lines);

if (!smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $parent_email)) {
    log_parent_approval_event('internal_email_failed email=' . $parent_email);
}

$parent_subject = 'You’re all set — see you this summer';
$greeting = $parent_name !== '' ? 'Hi ' . $parent_name . ',' : 'Hi there,';
$parent_lines = [];
$parent_lines[] = $greeting;
$parent_lines[] = '';
$parent_lines[] = 'We’ve received your parent approval form.';
$parent_lines[] = '';
$parent_lines[] = 'Everything is now complete — your child is fully registered for The Money Club.Org.';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = '📅 What happens next';
$parent_lines[] = '';
$parent_lines[] = 'Closer to the program start, we’ll send:';
$parent_lines[] = '- first-day details';
$parent_lines[] = '- what to bring';
$parent_lines[] = '- program schedule';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = '📍 Location';
$parent_lines[] = '';
$parent_lines[] = 'UTSU Student Commons';
$parent_lines[] = 'University of Toronto (downtown)';
$parent_lines[] = '';
$parent_lines[] = 'Daily 9–5';
$parent_lines[] = '';
$parent_lines[] = '---';
$parent_lines[] = '';
$parent_lines[] = 'If anything changes or you have questions, feel free to reply anytime.';
$parent_lines[] = '';
$parent_lines[] = 'Looking forward to having your child in the program.';
$parent_lines[] = '';
$parent_lines[] = '— The Money Club.Org';
$parent_message = implode("\n", $parent_lines);

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    log_parent_approval_event('parent_email_failed email=' . $parent_email);
}

$separator = (strpos($return_to, '?') === false) ? '?' : '&';
header('Location: ' . $return_to . $separator . 'status=sent');
exit;
?>
