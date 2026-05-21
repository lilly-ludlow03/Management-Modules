<?php
/**
 * auth.php - Authentication & Authorization Helper
 * 
 * This file provides all the functions needed for:
 * - Session management (secure cookies, regeneration)
 * - User authentication (login/logout)
 * - Authorization (role-based access control)
 * 
 * USAGE:
 * 
 * To protect a page (redirect to login if not authenticated):
 *   require_once __DIR__ . '/../includes/auth.php';
 *   requireRole(array(ROLE_SENIOR_MANAGER), '../mainpage/index.php');
 */

require_once __DIR__ . '/config.php';

if (!function_exists('password_hash')) {
    function password_hash($password, $algo = 1) {
        $salt = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return crypt($password, '$2y$10$' . $salt);
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        return crypt($password, $hash) === $hash;
    }
}

// Managing Sessions
/**
 * Initialize a secure session with proper cookie settings
 */
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Security settings for session cookies
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        session_name(SESSION_NAME);
        session_start();
        
        // Check for session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                // Session has expired
                logoutUser();
                return;
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
    }
}

// Authentication
/**
 * Check if the current user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    initSecureSession();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

/**
 * Get the current user's role
 * 
 * @return string|null The role name or null if not logged in
 */
function getUserRole() {
    initSecureSession();
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Get the current user's username
 * 
 * @return string|null The username or null if not logged in
 */
function getUsername() {
    initSecureSession();
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

/**
 * Get the current user's full name
 * 
 * @return string|null The full name or null if not logged in
 */
function getFullName() {
    initSecureSession();
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
}

/**
 * Check if the current user has one of the allowed roles
 * Admins automatically have access to everything
 * 
 * @param array|string $allowedRoles Role(s) that are allowed
 * @return bool True if user has permission
 */
function hasRole($allowedRoles) {
    if (!is_array($allowedRoles)) {
        $allowedRoles = array($allowedRoles);
    }
    
    $userRole = getUserRole();
    
    // Admins can access everything
    if ($userRole === ROLE_ADMIN) {
        return true;
    }
    
    return in_array($userRole, $allowedRoles);
}

// Page Protection
/**
 * Set headers to prevent browser caching of protected pages
 * This prevents users from using the back button to view cached pages after logout
 */
function setNoCacheHeaders() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
}

/**
 * Require the user to be logged in
 * Redirects to login page if not authenticated
 * 
 * @param string $redirectUrl URL to redirect to if not authenticated
 */
function requireAuth($redirectUrl = '../mainpage/index.php') {
    // Prevent caching of protected pages
    setNoCacheHeaders();
    
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Require the user to have specific role(s)
 * Redirects to login page if not authorized
 * 
 * @param array|string $allowedRoles Role(s) that can access this page
 * @param string $redirectUrl URL to redirect to if not authorized
 */
function requireRole($allowedRoles, $redirectUrl = '../mainpage/index.php') {
    // Prevent caching of protected pages
    setNoCacheHeaders();
    
    requireAuth($redirectUrl);
    
    if (!hasRole($allowedRoles)) {
        // Log the unauthorized access attempt
        error_log(sprintf(
            "SECURITY: Unauthorized access attempt by '%s' (role: %s) for required roles: %s",
            getUsername(),
            getUserRole(),
            implode(', ', (array)$allowedRoles)
        ));
        
        header("Location: $redirectUrl");
        exit;
    }
}

// API Protection
/**
 * Require authentication for API endpoints
 * Returns JSON error instead of redirecting
 */
function requireAuthAPI() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo '{"error": "Authentication required", "loggedIn": false}';
        exit;
    }
}

/**
 * Require specific role(s) for API endpoints
 * Returns JSON error instead of redirecting
 * 
 * @param array|string $allowedRoles Role(s) that can access this endpoint
 */
function requireRoleAPI($allowedRoles) {
    requireAuthAPI();
    
    if (!hasRole($allowedRoles)) {
        error_log(sprintf(
            "SECURITY: Unauthorized API access by '%s' (role: %s)",
            getUsername(),
            getUserRole()
        ));
        
        header('Content-Type: application/json');
        http_response_code(403);
        echo '{"error": "Access denied - insufficient permissions", "loggedIn": true}';
        exit;
    }
}

// Login and Logout
/**
 * Authenticate a user against the stored credentials
 * 
 * @param string $username The username to check
 * @param string $password The plain-text password to verify
 * @return array|false User data array on success, false on failure
 */
function authenticateUser($username, $password) {
    $users = getUsers();
    
    // Check if user exists (case-sensitive for usernames)
    if (!isset($users[$username])) {
        // Also try lowercase
        $usernameLower = strtolower(trim($username));
        $found = false;
        foreach ($users as $uname => $udata) {
            if (strtolower($uname) === $usernameLower) {
                $username = $uname;
                $found = true;
                break;
            }
        }
        if (!$found) {
            // Use the same delay for non-existent users to prevent timing attacks
            password_verify($password, '$2y$10$dummyhashtopreventtimingattacks');
            return false;
        }
    }
    
    $user = $users[$username];
    
    // Verify password against stored hash
    if (password_verify($password, $user['password_hash'])) {
        return array(
            'username' => $username,
            'role' => $user['role'],
            'full_name' => isset($user['full_name']) ? $user['full_name'] : $username
        );
    }
    
    return false;
}

/**
 * Log in a user and create their session
 * 
 * @param string $username The username
 * @param string $password The plain-text password
 * @return array|false User data on success, false on failure
 */
function loginUser($username, $password) {
    $user = authenticateUser($username, $password);
    
    if ($user) {
        initSecureSession();
        
        // CRITICAL: Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        
        // Store user data in session
        $_SESSION['user_id'] = $user['username'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        return $user;
    }
    
    return false;
}

/**
 * Log out the current user and destroy their session
 */
function logoutUser() {
    initSecureSession();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Redirecting based on the user's role

/**
 * Get the appropriate redirect URL based on user role
 * Used after login to send users to their respective modules
 * 
 * @param string $role The user's role
 * @return string The URL to redirect to
 */
function getRoleRedirectUrl($role) {
    switch ($role) {
        case ROLE_SENIOR_MANAGER:
            return '../Senior Manager Module/SM Shell.php';
            
        case ROLE_SUPPLY_CHAIN_MANAGER:
            return '../Supply Chain Manager Module/SC Manager Shell.php';
            
        case ROLE_ADMIN:
            // Admin defaults to Senior Manager module
            return '../Senior Manager Module/SM Shell.php';
            
        default:
            return 'index.php';
    }
}

/**
 * Get a user-friendly role display name
 * 
 * @param string $role The role constant
 * @return string Human-readable role name
 */
function getRoleDisplayName($role) {
    switch ($role) {
        case ROLE_SENIOR_MANAGER:
            return 'Senior Manager';
        case ROLE_SUPPLY_CHAIN_MANAGER:
            return 'Supply Chain Manager';
        case ROLE_ADMIN:
            return 'Administrator';
        default:
            return 'Unknown';
    }
}
