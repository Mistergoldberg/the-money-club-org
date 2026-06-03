<?php
require_once __DIR__ . '/smtp-send.php';
require_once __DIR__ . '/form-security.php';

function base_url() {
    $scheme = 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'the-money-club.org';
    return $scheme . '://' . $host;
}

function log_parent_approval_event($message) {
    $log_path = tmc_get_data_dir() . '/apply-parent-approval.log';
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
        throw new RuntimeException(
            'Unable to generate parent approval hash key securely. ' .
            'Set PARENT_APPROVAL_HASH_KEY or provide writable key storage.'
        );
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

$student_name = tmc_trim_post('student-name', 160);
$student_age = tmc_trim_post('student-age', 3);
$parent_name = tmc_trim_post('parent-name', 160);
$parent_email = tmc_trim_post('parent-email', 254);
$parent_phone = tmc_trim_post('parent-phone', 40);
$program_track = tmc_trim_post('program-track', 80);
$session = tmc_trim_post('session', 80);
$emergency_contact_name = tmc_trim_post('emergency-contact-name', 160);
$emergency_contact_phone = tmc_trim_post('emergency-contact-phone', 40);
$authorized_pickup_name_1 = tmc_trim_post('authorized-pickup-name-1', 160);
$authorized_pickup_phone_1 = tmc_trim_post('authorized-pickup-phone-1', 40);
$authorized_pickup_name_2 = tmc_trim_post('authorized-pickup-name-2', 160);
$authorized_pickup_phone_2 = tmc_trim_post('authorized-pickup-phone-2', 40);
$medical_allergies = tmc_trim_post('medical-allergies', 20);
$medical_medications = tmc_trim_post('medical-medications', 20);
$medical_accommodations = tmc_trim_post('medical-accommodations', 20);
$medical_details = tmc_trim_post('medical-details', 4000);
$legacy_medical_notes = tmc_trim_post('medical-notes', 4000);
$photo_consent = tmc_trim_post('photo-consent', 20);
$parent_signature_name = tmc_trim_post('parent-signature-name', 160);
$consent_agree = tmc_trim_post('consent-agree', 10);

$allowed_returns = ['parent-approval.html', 'etransfer.html', 'thank-you.html'];
$return_to = tmc_resolve_return_target($_POST['return-to'] ?? 'parent-approval.html', $allowed_returns, 'parent-approval.html');
$error_return = tmc_resolve_return_target($_POST['return-error'] ?? 'parent-approval.html', $allowed_returns, 'parent-approval.html');

try {
    tmc_issue_csrf_cookie();
} catch (RuntimeException $e) {
    tmc_log_form_security_event('apply-parent-approval', 'csrf_cookie_failed');
    redirect_with_error($error_return, 'form', 'Unable to validate this form securely. Please refresh and try again.');
}

if (tmc_honeypot_triggered()) {
    tmc_log_form_security_event('apply-parent-approval', 'honeypot_tripped', ['return_to' => $return_to]);
    tmc_redirect_with_status($return_to, 'sent');
}

$rate_limit = tmc_rate_limit_check('apply-parent-approval', 6, 1800);
if (!$rate_limit['allowed']) {
    tmc_log_form_security_event('apply-parent-approval', 'rate_limited', ['retry_after' => (string)$rate_limit['retry_after']]);
    redirect_with_error($error_return, 'form', 'Too many submissions. Please wait before trying again.');
}

$csrf_reason = '';
if (!tmc_verify_csrf_token(true, $csrf_reason)) {
    tmc_log_form_security_event('apply-parent-approval', 'csrf_failed', ['reason' => $csrf_reason]);
    redirect_with_error($error_return, 'form', 'Your form session expired. Please refresh and try again.');
}

if ($student_name === '') {
    redirect_with_error($error_return, 'student-name', 'Student name is required.');
}

$age_value = filter_var($student_age, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0]
]);
if ($age_value === false) {
    redirect_with_error($error_return, 'student-age', 'Please provide a valid student age.');
}

if ($parent_name === '') {
    redirect_with_error($error_return, 'parent-name', 'Parent/guardian name is required.');
}

if (!tmc_is_valid_email($parent_email)) {
    redirect_with_error($error_return, 'parent-email', 'Please provide a valid parent email.');
}

$parent_phone_digits = tmc_phone_digits($parent_phone);
if ($parent_phone === '' || $parent_phone_digits === '' || strlen($parent_phone_digits) < 10 || strlen($parent_phone_digits) > 15) {
    redirect_with_error($error_return, 'parent-phone', 'Please provide a valid parent phone number.');
}

$program_options = [
    'money-club-program' => [
        'label' => 'The Money Club.Org Program',
        'sessions' => [
            'aug_10_14' => 'August 10th-14th, 2026'
        ]
    ]
];

// Backward compatibility if old values are still posted.
if ($session === 'session1' || $session === 'session2' || strpos($session, 'aug') === 0) {
    $session = 'aug_10_14';
}
if ($program_track === '') {
    $program_track = 'money-club-program';
}

if (!array_key_exists($program_track, $program_options)) {
    foreach ($program_options as $track_key => $track_data) {
        if (isset($track_data['sessions'][$session])) {
            $program_track = $track_key;
            break;
        }
    }
}

if (!array_key_exists($program_track, $program_options)) {
    redirect_with_error($error_return, 'session', 'Please select a valid session.');
}

if (!array_key_exists($session, $program_options[$program_track]['sessions'])) {
    $matched_track = '';
    foreach ($program_options as $track_key => $track_data) {
        if (isset($track_data['sessions'][$session])) {
            $matched_track = $track_key;
            break;
        }
    }
    if ($matched_track !== '') {
        $program_track = $matched_track;
    } else {
        redirect_with_error($error_return, 'session', 'Please select a valid session.');
    }
}

$program_label = $program_options[$program_track]['label'];
$session_label = $program_options[$program_track]['sessions'][$session];

$emergency_phone_digits = preg_replace('/\D+/', '', $emergency_contact_phone);
if ($emergency_contact_phone === '' || $emergency_phone_digits === '' || strlen($emergency_phone_digits) < 10 || strlen($emergency_phone_digits) > 15) {
    redirect_with_error($error_return, 'emergency-contact-phone', 'Please provide a valid emergency contact phone number.');
}

$authorized_pickup_1_phone_digits = preg_replace('/\D+/', '', $authorized_pickup_phone_1);
if (($authorized_pickup_name_1 !== '' && $authorized_pickup_phone_1 === '')) {
    redirect_with_error($error_return, 'authorized-pickup-phone-1', 'Please provide a phone number for Authorized Pickup Name 1.');
}
if (($authorized_pickup_name_1 === '' && $authorized_pickup_phone_1 !== '')) {
    redirect_with_error($error_return, 'authorized-pickup-name-1', 'Please provide a name for Authorized Pickup Phone 1.');
}
if ($authorized_pickup_phone_1 !== '' && ($authorized_pickup_1_phone_digits === '' || strlen($authorized_pickup_1_phone_digits) < 10 || strlen($authorized_pickup_1_phone_digits) > 15)) {
    redirect_with_error($error_return, 'authorized-pickup-phone-1', 'Please provide a valid authorized pickup phone number.');
}

$authorized_pickup_2_phone_digits = preg_replace('/\D+/', '', $authorized_pickup_phone_2);
if (($authorized_pickup_name_2 !== '' && $authorized_pickup_phone_2 === '')) {
    redirect_with_error($error_return, 'authorized-pickup-phone-2', 'Please provide a phone number for Authorized Pickup Name 2.');
}
if (($authorized_pickup_name_2 === '' && $authorized_pickup_phone_2 !== '')) {
    redirect_with_error($error_return, 'authorized-pickup-name-2', 'Please provide a name for Authorized Pickup Phone 2.');
}
if ($authorized_pickup_phone_2 !== '' && ($authorized_pickup_2_phone_digits === '' || strlen($authorized_pickup_2_phone_digits) < 10 || strlen($authorized_pickup_2_phone_digits) > 15)) {
    redirect_with_error($error_return, 'authorized-pickup-phone-2', 'Please provide a valid authorized pickup phone number.');
}

if (!in_array($medical_allergies, ['yes', 'no'], true)) {
    redirect_with_error($error_return, 'medical-allergies', 'Please choose Yes or No for allergies.');
}
if (!in_array($medical_medications, ['yes', 'no'], true)) {
    redirect_with_error($error_return, 'medical-medications', 'Please choose Yes or No for medications or health concerns.');
}
if (!in_array($medical_accommodations, ['yes', 'no'], true)) {
    redirect_with_error($error_return, 'medical-accommodations', 'Please choose Yes or No for accommodations.');
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

if ($medical_details === '' && $legacy_medical_notes !== '') {
    $medical_details = $legacy_medical_notes;
}
if (strlen($medical_details) > 4000) {
    $medical_details = substr($medical_details, 0, 4000);
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

$data_dir = tmc_get_data_dir();
try {
    $hash_secret = get_parent_approval_hash_secret($data_dir);
} catch (Throwable $e) {
    log_parent_approval_event('hash_secret_error: ' . $e->getMessage());
    redirect_with_error($error_return, 'form', 'Unable to process this form securely at this time. Please try again.');
}
$hash_chain_path = $data_dir . '/parent-approval-hash-chain.log';

$previous_hash = get_previous_parent_approval_hash($hash_chain_path);
$hash_payload = [
    'submitted_at_utc' => $submitted_at_utc,
    'student_name' => $student_name,
    'student_age' => (string)$age_value,
    'parent_name' => $parent_name,
    'parent_email' => $parent_email,
    'parent_phone' => $parent_phone,
    'program_track' => $program_label,
    'session' => $session_label,
    'emergency_contact_name' => $emergency_contact_name,
    'emergency_contact_phone' => $emergency_contact_phone,
    'authorized_pickup_name_1' => $authorized_pickup_name_1,
    'authorized_pickup_phone_1' => $authorized_pickup_phone_1,
    'authorized_pickup_name_2' => $authorized_pickup_name_2,
    'authorized_pickup_phone_2' => $authorized_pickup_phone_2,
    'medical_allergies' => strtoupper($medical_allergies),
    'medical_medications_or_health_concerns' => strtoupper($medical_medications),
    'medical_accommodations' => strtoupper($medical_accommodations),
    'medical_details' => $medical_details,
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
    log_parent_approval_event('hash_chain_write_failed');
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
    'program_track',
    'session',
    'emergency_contact_name',
    'emergency_contact_phone',
    'authorized_pickup_name_1',
    'authorized_pickup_phone_1',
    'authorized_pickup_name_2',
    'authorized_pickup_phone_2',
    'medical_allergies',
    'medical_medications',
    'medical_accommodations',
    'medical_details',
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
    $program_label,
    $session_label,
    $emergency_contact_name,
    $emergency_contact_phone,
    $authorized_pickup_name_1,
    $authorized_pickup_phone_1,
    $authorized_pickup_name_2,
    $authorized_pickup_phone_2,
    strtoupper($medical_allergies),
    strtoupper($medical_medications),
    strtoupper($medical_accommodations),
    $medical_details,
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
    log_parent_approval_event('csv_write_failed');
}

$from = 'info@the-money-club.org';
$internal_to = ['info@the-money-club.org', 'alex@the-money-club.org', 'sarah@the-money-club.org'];
$internal_subject = 'Parent Approval & Consent Form: The Money Club.Org';
$internal_lines = [];
$internal_lines[] = 'Student Name: ' . $student_name;
$internal_lines[] = 'Student Age: ' . (string)$age_value;
$internal_lines[] = 'Parent/Guardian Name: ' . $parent_name;
$internal_lines[] = 'Parent Email: ' . $parent_email;
$internal_lines[] = 'Parent Phone: ' . $parent_phone;
$internal_lines[] = 'Program: ' . $program_label;
$internal_lines[] = 'Session: ' . $session_label;
$internal_lines[] = 'Emergency Contact Name: ' . $emergency_contact_name;
$internal_lines[] = 'Emergency Contact Phone: ' . $emergency_contact_phone;
$internal_lines[] = 'Authorized Pickup Name 1: ' . $authorized_pickup_name_1;
$internal_lines[] = 'Authorized Pickup Phone 1: ' . $authorized_pickup_phone_1;
$internal_lines[] = 'Authorized Pickup Name 2: ' . ($authorized_pickup_name_2 !== '' ? $authorized_pickup_name_2 : '(none)');
$internal_lines[] = 'Authorized Pickup Phone 2: ' . ($authorized_pickup_phone_2 !== '' ? $authorized_pickup_phone_2 : '(none)');
$internal_lines[] = 'Medical - Allergies: ' . strtoupper($medical_allergies);
$internal_lines[] = 'Medical - Medications/Health Concerns: ' . strtoupper($medical_medications);
$internal_lines[] = 'Medical - Accommodations: ' . strtoupper($medical_accommodations);
$internal_lines[] = 'Medical - Details: ' . ($medical_details !== '' ? $medical_details : '(none)');
$internal_lines[] = 'Photo / Media Consent: ' . $photo_consent_label;
$internal_lines[] = 'Typed Parent Signature: ' . $parent_signature_name;
$internal_lines[] = 'Consent Confirmed: Yes';
$internal_lines[] = 'Submitted At (UTC): ' . $submitted_at_utc;
$internal_lines[] = 'Submitted At (Server Local): ' . $submitted_at_local;
$internal_lines[] = 'IP Address: ' . ($ip_address !== '' ? $ip_address : '(unavailable)');
$internal_lines[] = 'User Agent: ' . ($user_agent !== '' ? $user_agent : '(unavailable)');
$internal_lines[] = 'Source: ' . $source_page;
$internal_lines[] = 'Payment Status: Pending e-Transfer (manual confirmation required)';
$internal_lines[] = 'Previous Hash: ' . $previous_hash;
$internal_lines[] = 'Submission Hash: ' . $submission_hash;
$internal_message = implode("\n", $internal_lines);

try {
    $internal_email_sent = smtp_send_mail($internal_to, $internal_subject, $internal_message, $from, $parent_email);
} catch (Throwable $e) {
    error_log('[apply-parent-approval] Internal email exception: ' . $e->getMessage());
    $internal_email_sent = false;
}

if ($internal_email_sent) {
    log_parent_approval_event('internal_email_sent');
} else {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    if ($smtp_reason === '') {
        $smtp_reason = 'unknown';
    }
    log_parent_approval_event('internal_email_failed reason=' . $smtp_reason);
}

$parent_subject = 'Thank you — we received your Money Club parent approval form';
$parent_greeting_name = trim(preg_replace('/\s+/', ' ', $parent_name));
$curriculum_link = base_url() . '/curriculum-details.html';
$safe_parent_greeting_name = htmlspecialchars($parent_greeting_name, ENT_QUOTES, 'UTF-8');
$safe_curriculum_link = htmlspecialchars($curriculum_link, ENT_QUOTES, 'UTF-8');
$greeting = $safe_parent_greeting_name !== '' ? 'Hi ' . $safe_parent_greeting_name . ',' : 'Hi,';
$parent_message = '<!doctype html>
<html>
  <body style="margin:0;padding:0;background:#ffffff;color:#111111;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.55;">
    <div style="max-width:680px;margin:0 auto;padding:24px;">
      <p style="margin:0 0 18px;">' . $greeting . '</p>

      <p style="margin:0 0 18px;">Thank you — we&rsquo;ve received your parent approval form for The Money Club.Org.</p>

      <p style="margin:0 0 24px;">Below are the confirmed program details and payment instructions for the August session.</p>

      <p style="margin:0 0 8px;"><strong>Program Details</strong></p>
      <p style="margin:0 0 24px;">
        <strong>Dates:</strong> August 10–14<br>
        <strong>Location:</strong> UTSU Student Commons<br>
        <strong>Address:</strong> 230 College Street, Toronto<br>
        <strong>Tuition:</strong> $200<br>
        <strong>Program size:</strong> Limited to 30 students
      </p>

      <p style="margin:0 0 8px;"><strong>Daily Schedule</strong></p>
      <p style="margin:0 0 24px;">
        <strong>Drop-off:</strong> 9:00 AM<br>
        <strong>Instruction begins:</strong> 9:30 AM<br>
        <strong>Lunch:</strong> 12:00–1:00 PM<br>
        <strong>Pick-up window:</strong> 3:30–5:00 PM
      </p>

      <p style="margin:0 0 18px;">Students will learn financial literacy, design thinking, AI as a research and creative tool, product-building, and communication through practical, project-based work.</p>

      <p style="margin:0 0 24px;">
        You can review the curriculum and one-week schedule here:<br>
        <a href="' . $safe_curriculum_link . '" style="color:#0f6f78;text-decoration:underline;">' . $safe_curriculum_link . '</a>
      </p>

      <p style="margin:0 0 8px;"><strong>Payment</strong></p>
      <p style="margin:0 0 18px;">To secure your child&rsquo;s spot, please send the $200 tuition payment by e-transfer to:</p>

      <p style="margin:0 0 18px;"><a href="mailto:info@the-money-club.org" style="color:#0f6f78;text-decoration:underline;">info@the-money-club.org</a></p>

      <p style="margin:0 0 18px;">Please include your child&rsquo;s name in the payment note.</p>

      <p style="margin:0 0 24px;">Once payment is received, I&rsquo;ll send a short confirmation that your child&rsquo;s spot has been secured.</p>

      <p style="margin:0 0 18px;">Thank you again for your interest and your trust.</p>

      <p style="margin:0;">Warmly,<br>
      Jared Goldberg<br>
      Executive Director<br>
      The Money Club.Org</p>
    </div>
  </body>
</html>';

try {
    $parent_email_sent = smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from, 'The Money Club.Org', true);
} catch (Throwable $e) {
    error_log('[apply-parent-approval] Parent confirmation email exception: ' . $e->getMessage());
    $parent_email_sent = false;
}

if ($parent_email_sent) {
    log_parent_approval_event('parent_email_sent');
} else {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    if ($smtp_reason === '') {
        $smtp_reason = 'unknown';
    }
    log_parent_approval_event('parent_email_failed reason=' . $smtp_reason);
}

header('Location: thank-you.html');
exit;
?>
