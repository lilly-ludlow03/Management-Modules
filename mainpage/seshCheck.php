<?php
/**
 * seshCheck.php - Session Status API
 * 
 * Returns JSON with current authentication status
 */

// Include the authentication helper
require_once __DIR__ . '/../includes/auth.php';

// Set JSON content type
header('Content-Type: application/json');

// Check login status and return appropriate response
if (isLoggedIn()) {
    $response = array(
        'loggedIn' => true,
        'username' => getUsername(),
        'role' => getUserRole(),
        'fullName' => getFullName(),
        'roleDisplayName' => getRoleDisplayName(getUserRole())
    );
    echo json_encode($response);
} else {
    echo '{"loggedIn": false}';
}
