<?php
/**
 * config.php - Centralized Configuration File
 * 
 * This file contains:
 * - Database credentials (single source of truth)
 * - User accounts with hashed passwords
 * - Role definitions
 * - Session settings
 * 
 * SECURITY: This file should never be accessible directly via browser.
 * PHP files are executed server-side, so the source code is never exposed.
 */

// Prevent direct browser access to this file
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    die('Direct access not allowed');
}

// Database Details

define('DB_HOST', 'mydb.itap.purdue.edu');
define('DB_USER', 'g1151919');
define('DB_PASS', 'yM84JeHv');
define('DB_NAME', 'g1151919');

// Session Settings

define('SESSION_LIFETIME', 3600);  // Session expires after 1 hour of inactivity
define('SESSION_NAME', 'GROUP9_SESSION');

// Role Assignment

define('ROLE_ADMIN', 'admin');
define('ROLE_SENIOR_MANAGER', 'senior_manager');
define('ROLE_SUPPLY_CHAIN_MANAGER', 'supply_chain_manager');

// User information 

/**
 * User credentials with bcrypt hashed passwords.
 */
$GLOBALS['USERS'] = array(
    'James' => array(
        'password_hash' => '$2y$10$jHSYPnozPDhXvgnTDCsB.uVYVDjwoCeR8ZSVMQwa3Bt8LGKdmLjDy',
        'role' => ROLE_SENIOR_MANAGER,
        'full_name' => 'James Thompson'
    ),
    'Donald' => array(
        'password_hash' => '$2y$10$BfL5Lw1.Z.d8qu9x6Q8GCOVaoi6T4fM5gq8STZPQ2ZI4nfv50.K1O',
        'role' => ROLE_SUPPLY_CHAIN_MANAGER,
        'full_name' => 'Donald Palacios'
    ),
    'Ferris' => array(
        'password_hash' => '$2y$10$jDjMzh2BD.zhPVybWcGthOqKFLf6KkKhgEcemwwK4kDbv9VT4crEu',
        'role' => ROLE_SUPPLY_CHAIN_MANAGER,
        'full_name' => 'Ferris Bueller'
    ),
    'Edward' => array(
        'password_hash' => '$2y$10$SYV6eB3scjeYC0eS3zdWt.thJMw226FSmZTIy9S/IzdAfZfD3pr9e',
        'role' => ROLE_SENIOR_MANAGER,
        'full_name' => 'Edward Rooney King'
    ),
    'John' => array(
        'password_hash' => '$2y$10$uLyKgNfMVl942kbvUyeDE.mCjFtK2t1o2Og9k2vem/4BcRYmLtaVO',
        'role' => ROLE_SENIOR_MANAGER,
        'full_name' => 'John Bender'
    ),
    'Buster' => array(
        'password_hash' => '$2y$10$4mfkOpkv4vebsLR7vD2fserpACZrIcc.YTqrMdczLhFEstIF3Btku',
        'role' => ROLE_SUPPLY_CHAIN_MANAGER,
        'full_name' => 'Buster White'
    ),
    'Jessie' => array(
        'password_hash' => '$2y$10$.XRkBj7QVV5oDphZeubA.eNRNf5zJEAABmOs696MEp.14FrCCfs36',
        'role' => ROLE_SENIOR_MANAGER,
        'full_name' => 'Jessie Pinkman'
    ),
    'Lebron' => array(
        'password_hash' => '$2y$10$NkaLT654YLwzMPJOJEt0FeEVWwq3Fayu9yog4.a61HxbUv.hx.NdC',
        'role' => ROLE_SUPPLY_CHAIN_MANAGER,
        'full_name' => 'Lebron My Monarch'
    ),
);

// Database connection credentials

/**
 * @return mysqli Database connection object
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            
            // Don't show errors to people
            if (php_sapi_name() === 'cli') {
                die("Database connection failed");
            } else {
                header('Content-Type: application/json');
                http_response_code(500);
                die('{"error": "Database connection failed"}');
            }
        }
        
        $conn->set_charset("utf8");
    }
    
    return $conn;
}


function getUsers() {
    return $GLOBALS['USERS'];
}
