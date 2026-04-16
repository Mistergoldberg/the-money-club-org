<?php
/**
 * Copy to smtp-config.local.php on the server (not in git) and fill real values.
 */
return [
    'host' => 'smtp.example.com',
    'port' => 587, // 587 (STARTTLS) or 465 (implicit TLS)
    'username' => 'smtp-username',
    'password' => 'smtp-password',
    'use_tls' => true,
];
