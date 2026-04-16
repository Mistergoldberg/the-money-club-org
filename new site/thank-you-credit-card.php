<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
unset($_SESSION['tmc_verified_purchase']);
header('Location: etransfer.html');
exit;
?>
