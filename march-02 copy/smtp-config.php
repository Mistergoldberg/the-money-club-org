<?php
$local_path = __DIR__ . '/smtp-config.local.php';
if (is_file($local_path)) {
    $local = require $local_path;
    if (!is_array($local)) {
        throw new RuntimeException('smtp-config.local.php must return an array.');
    }
    return $local;
}

$host = trim((string) getenv('SMTP_HOST'));
$port_raw = getenv('SMTP_PORT');
$port = is_string($port_raw) && trim($port_raw) !== '' ? (int) $port_raw : 2525;
$username = trim((string) getenv('SMTP_USERNAME'));
$password = (string) getenv('SMTP_PASSWORD');
$use_tls_raw = getenv('SMTP_USE_TLS');
$use_tls = $use_tls_raw === false || $use_tls_raw === ''
    ? true
    : filter_var($use_tls_raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

if ($use_tls === null) {
    throw new RuntimeException('Invalid SMTP_USE_TLS value. Use true/false or 1/0.');
}

$missing = [];
if ($host === '') { $missing[] = 'SMTP_HOST'; }
if ($username === '') { $missing[] = 'SMTP_USERNAME'; }
if ($password === '') { $missing[] = 'SMTP_PASSWORD'; }
if ($port < 1 || $port > 65535) {
    throw new RuntimeException('SMTP_PORT must be between 1 and 65535.');
}

if ($missing) {
    throw new RuntimeException(
        'Missing required SMTP config: ' . implode(', ', $missing) .
        '. Set env vars or provide smtp-config.local.php.'
    );
}

return [
    'host' => $host,
    'port' => $port,
    'username' => $username,
    'password' => $password,
    'use_tls' => $use_tls,
];
