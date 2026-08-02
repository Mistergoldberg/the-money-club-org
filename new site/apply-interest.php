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
    'who-runs-it.html',
    'learn/',
    'learn/index.html',
    'learn/financial-literacy-for-young-entrepreneurs/index.html',
    'learn/financial-literacy-for-young-entrepreneurs/worksheet.html',
    'course-catalogue/',
    'course-catalogue/index.html',
    'course-catalogue/financial-literacy-for-young-entrepreneurs/index.html',
    'course-catalogue/financial-literacy-for-young-entrepreneurs/worksheet.html'
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
        'options' => ['min_range' => 0]
    ]);
    if ($validated_age === false) {
        tmc_redirect_with_error($error_return, 'student-age', 'Please provide a valid child age.');
    }
    $age_value = (string)$validated_age;
}

$session_options = [
    '' => 'August 10th-14th',
    'aug_10_14' => 'August 10th-14th',
    'august' => 'August 10th-14th',
    'either' => 'August 10th-14th'
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

$name_parts = preg_split('/\s+/', trim($parent_name));
$first_name = $name_parts && isset($name_parts[0]) ? $name_parts[0] : '';
$greeting = $first_name !== '' ? 'Hello ' . $first_name . ',' : 'Hello,';

$parent_subject = 'The Money Club.Org | Summer Program';
$parent_message = <<<EMAIL
$greeting

Thank you for expressing interest in The Money Club.Org.

I’m Jared Goldberg, the founder of the program, and I wanted to personally send you an overview of what we are building, who it is for, and how the program will work.

The Money Club.Org is not a traditional summer camp. It is a practical, project-based learning environment where students learn how value is created, measured, tested, explained, and improved.

I designed the program from my own work as a product strategist, systems designer, and educator with 15+ years building and operating real-world business platforms.

I’ve worked across Walmart, Loblaw, and Canadian Tire, and spent more than a decade inside China-based manufacturing ecosystems, where cost, quality, incentives, pricing, execution, and human behaviour collide in real time.

The Money Club.Org takes those ideas and makes them useful for young people.

Students are not just taught vocabulary. They work through the basic mechanics of how real things get built: money, margins, customer needs, research, AI tools, product ideas, presentation, feedback, and improvement.

Based on current demand, we are now focusing on a founder-led August session that I will teach directly.

August Session Details

Dates: August 10–14
Location: UTSU Student Commons
Address: 230 College Street, Toronto
Tuition: $200
Program size: Limited to 30 participants

The best fit is a student who is comfortable working independently on a laptop for basic research, writing, and creative work.

The program is practical and project-based. Students will learn financial literacy, design thinking, AI as a research and creative tool, product-building, and communication by working toward a simple idea of their own.

You can review the curriculum details and one-week schedule here:
https://the-money-club.org/curriculum-details.html

I’ve also started publishing the learning modules here:
https://the-money-club.org/learn/#core-build-modules

You can read more about the mission behind the program here:
https://the-money-club.org/executive-director-letter.html

Open-Book Financials

The Money Club.Org is a nonprofit summer program designed around learning, not upselling.

We publish a simple budget breakdown so families can see how tuition supports materials, venue costs, insurance, and program operations.

You can review the Open-Book Financials here:
https://the-money-club.org/open-book-hook.html

The goal is not to maximize profit. The goal is to run a thoughtful, well-supported program for students in a transparent and responsible way.

Next Steps

If your family is interested in the August session, please complete the parent approval form here:
https://the-money-club.org/parent-approval.html

Once the form is complete, we will send payment instructions by e-transfer to secure the spot.

I’d also be happy to jump on a quick parent call to walk you through the program and answer questions. Simply respond to this email with your availability and phone number.

Warmly,

Jared Goldberg
Founder
The Money Club.Org
EMAIL;

if (!smtp_send_mail([$parent_email], $parent_subject, $parent_message, $from, $from)) {
    $smtp_reason = function_exists('smtp_get_last_error') ? smtp_get_last_error() : 'unknown';
    log_interest_event('parent_confirmation_email_failed reason=' . $smtp_reason . ' source=' . $source_page);
}

tmc_redirect_with_status($return_to, 'sent');
?>
