<?php
require_once 'config/config.php';

// Clear session data, remove the session cookie, then destroy the session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

redirect('/hotel/index.php');
