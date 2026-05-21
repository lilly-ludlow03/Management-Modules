<?php
session_start();
// Tell the client that this endpoint returns JSON
header('Content-Type: application/json');

if (empty($_SESSION['UserData']['username'])) {
    // Check if user is logged in
    echo json_encode(['loggedIn' => false]);
} else {
    echo json_encode([
        'loggedIn' => true,
        'username' => $_SESSION['UserData']['username']
    ]);
}
