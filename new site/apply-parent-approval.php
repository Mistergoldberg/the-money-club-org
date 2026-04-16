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
    'options' => ['min_range' => 10, 'max_range' => 16]
]);
if ($age_value === false) {
    redirect_with_error($error_return, 'student-age', 'Student age must be between 10 and 16.');
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
    'two-week-builder-sprint' => [
        'label' => '2-Week Builder Sprint',
        'sessions' => [
            'tw_jul_6_17' => 'July 6 - July 17, 2026',
            'tw_jul_20_31' => 'July 20 - July 31, 2026',
            'tw_aug_4_15' => 'August 4 - August 15, 2026',
            'tw_aug_17_28' => 'August 17 - August 28, 2026'
        ]
    ],
    'four-week-full-program' => [
        'label' => '4-Week Full Program',
        'sessions' => [
            'fw_jul_6_31' => 'July 6 - July 31, 2026',
            'fw_aug_4_28' => 'August 4 - August 28, 2026'
        ]
    ]
];

// Backward compatibility if old values are still posted.
if ($session === 'session1') {
    $session = 'fw_jul_6_31';
} elseif ($session === 'session2') {
    $session = 'fw_aug_4_28';
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

$parent_subject = 'We\'ve received your parent approval form';
$parent_lines = [];
$parent_lines[] = 'Hi,';
$parent_lines[] = '';
$parent_lines[] = 'Thank you - we\'ve received your parent approval form for The Money Club.Org.';
$parent_lines[] = '';
$parent_lines[] = 'We\'re grateful for your interest and excited that your family wants to be part of this.';
$parent_lines[] = '';
$parent_lines[] = 'I also want to be direct about where things stand.';
$parent_lines[] = '';
$parent_lines[] = 'The Money Club.Org is a community-first program built for learning, not profit. It is designed to teach financial literacy by example, which is why we operate with open-book financials so families can see how money moves through a real system.';
$parent_lines[] = '';
$parent_lines[] = 'This is not a traditional camp. It is a startup-style summer program where kids learn how money works by building real products through guided sprints. Each student receives a $50 build budget to help research, build, price, and test ideas in the real world.';
$parent_lines[] = '';
$parent_lines[] = 'This is the model we are trying to prove:';
$parent_lines[] = '';
$parent_lines[] = 'kids learn financial literacy through radical transparency';
$parent_lines[] = 'University of Toronto student mentors get meaningful paid work';
$parent_lines[] = 'families help kickstart a local feedback loop of learning, demand, and community value';
$parent_lines[] = '';
$parent_lines[] = 'Right now, we are working toward the threshold needed to run the program this summer.';
$parent_lines[] = '';
$parent_lines[] = 'To launch, we need to secure $61,000 in paid parent participation by June 1. That works out to some mix of:';
$parent_lines[] = '';
$parent_lines[] = '60 four-week registrations, or';
$parent_lines[] = '120 two-week sprints';
$parent_lines[] = '';
$parent_lines[] = 'I am personally contributing $15,000 toward that goal to help get the program off the ground.';
$parent_lines[] = '';
$parent_lines[] = 'Because we have not yet reached that threshold, we are not sending payment links just yet. Your approval form lets us know your family is seriously interested, and we are holding that in our planning as we work toward launch.';
$parent_lines[] = '';
$parent_lines[] = 'If we hit the threshold, we will follow up with payment details, final next steps, and program logistics.';
$parent_lines[] = '';
$parent_lines[] = 'If this opportunity resonates with you, I would be very grateful if you shared it with friends or family who may know a young person who would be a strong fit. At this stage, thoughtful word of mouth can make a real difference.';
$parent_lines[] = '';
$parent_lines[] = 'Over the coming weeks, we\'ll continue to share updates, including:';
$parent_lines[] = '';
$parent_lines[] = 'curriculum details';
$parent_lines[] = 'what students will be working on';
$parent_lines[] = 'mentor introductions';
$parent_lines[] = 'and launch progress';
$parent_lines[] = '';
$parent_lines[] = 'Location:';
$parent_lines[] = 'UTSU Student Commons';
$parent_lines[] = 'University of Toronto, downtown Toronto';
$parent_lines[] = '';
$parent_lines[] = 'Daily schedule:';
$parent_lines[] = '9:00 AM-5:00 PM';
$parent_lines[] = 'Core instruction: 9:30 AM-3:30 PM';
$parent_lines[] = '';
$parent_lines[] = 'Thank you again for your interest, your trust, and your willingness to consider something early.';
$parent_lines[] = '';
$parent_lines[] = 'Warmly,';
$parent_lines[] = 'Jared Goldberg';
$parent_lines[] = 'The Money Club.Org';
$parent_message = implode("\n", $parent_lines);

try {
    $parent_email_sent = smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from);
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
