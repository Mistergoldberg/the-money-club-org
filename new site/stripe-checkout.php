<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: etransfer.html');
    exit;
}

header('Location: etransfer.html?status=payment-pending');
exit;
?>
