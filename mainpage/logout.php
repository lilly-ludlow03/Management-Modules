<?php
/**
 * logout.php - Secure Logout Handler
 * Properly destroys the user session and clears all session data
 */

// Set no-cache headers FIRST
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Include the authentication helper
require_once __DIR__ . '/../includes/auth.php';

// Log the logout 
if (isLoggedIn()) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    error_log(sprintf(
        "User '%s' logged out from IP %s",
        getUsername(),
        $ip
    ));
}

// Aggressive Session Destruction 
// Step 1) Unset all session variables
$_SESSION = array();

// Step 2) Delete the session cookie with CORRECT name
$sessionName = session_name(); // Should be GROUP9_SESSION
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    
    // Delete the session cookie
    setcookie(
        $sessionName,
        '',
        time() - 3600,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
    
    
    setcookie($sessionName, '', time() - 3600, '/');
    
    
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

// Step 3) Destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Step 4) Start a fresh session and immediately destroy it
session_name('GROUP9_SESSION');
session_start();
$_SESSION = array();
session_destroy();

// Redirect to login page (index.php)
header("Location: index.php");
exit;
