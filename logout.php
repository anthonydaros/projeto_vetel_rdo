<?php

require_once __DIR__ . '/auth/Auth.php';

use Auth\Auth;

$auth = Auth::getInstance();
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
