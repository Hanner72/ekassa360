<?php
/**
 * Logout
 * EKassa360
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

logoutUser();

$_SESSION['login_info'] = 'Sie wurden erfolgreich abgemeldet.';

header('Location: login.php');
exit;
