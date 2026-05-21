<?php
/**
 * hash_passwords.php - Password Hash Generator
 * 
 * This file is for setting up the users with their encrypted passwords.
 * After the passwords are encrypted, delete the file.
 * 
 * HOW TO USE:
 * 1. Edit the $users array below with your desired usernames and passwords
 * 2. Open this file in your browser: https://web.ics.purdue.edu/~g1151919/332-A3/setup/hash_passwords.php
 * 3. Copy the generated hashes into includes/config.php
 * 4. DELETE THIS FILE
 */

// Enable error reporting to see any issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('password_hash')) {
    // Polyfill for password_hash() - uses bcrypt via crypt()
    function password_hash($password, $algo = 1) {
        // Generate a random salt for bcrypt
        $salt = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        // Bcrypt format: $2y$10$ + 22 char salt
        $hash = crypt($password, '$2y$10$' . $salt);
        return $hash;
    }
}

if (!function_exists('password_verify')) {
    // Polyfill for password_verify()
    function password_verify($password, $hash) {
        return crypt($password, $hash) === $hash;
    }
}

//Users and passwords
$users = array(
    'James' => array(
        'password' => 'James123',
        'role' => 'senior_manager',
        'full_name' => 'James Thompson'
    ),
    'Donald' => array(
        'password' => 'Donald321',
        'role' => 'supply_chain_manager',
        'full_name' => 'Donald Palacios'
    ),
    'Ferris' => array(
        'password' => 'unicornsparkle98',
        'role' => 'supply_chain_manager',
        'full_name' => 'Ferris Bueller'
    ),
    'Edward' => array(
        'password' => 'Roonyourlife!',
        'role' => 'senior_manager',
        'full_name' => 'Edward Rooney King'
    ),
    'John' => array(
        'password' => 'Eatmyshorts!',
        'role' => 'senior_manager',
        'full_name' => 'John Bender'
    ),
    'Buster' => array(
        'password' => 'Xy348rR-zqy',
        'role' => 'supply_chain_manager',
        'full_name' => 'Buster White'
    ),
    'Jessie' => array(
        'password' => 'YoyoyoMrWhite!',
        'role' => 'senior_manager',
        'full_name' => 'Jessie Pinkman'
    ), 
    'Lebron' => array(
        'password' => 'YouAreMySunshine!',
        'role' => 'supply_chain_manager',
        'full_name' => 'Lebron My Monarch'
    ), 
);
// Pre-generate all the hashes (do this once, not in the loop)
$hashes = array();
foreach ($users as $username => $userData) {
    $hashes[$username] = password_hash($userData['password'], 1);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Hash Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
            min-height: 100vh;
            padding: 40px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #a89fca;
            margin-bottom: 10px;
        }
        .warning {
            background: rgba(255, 107, 107, 0.2);
            border: 2px solid #ff6b6b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning h2 {
            color: #ff6b6b;
            margin-bottom: 10px;
        }
        .user-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #a89fca;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .user-card h3 {
            color: #a89fca;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .field {
            margin: 10px 0;
        }
        .field label {
            color: #888;
            font-size: 0.9em;
        }
        .field code {
            display: block;
            background: #0f3460;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 5px;
            word-break: break-all;
            font-size: 0.85em;
        }
        .copy-section {
            background: #0f3460;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .copy-section h2 {
            color: #a89fca;
            margin-bottom: 15px;
        }
        pre {
            background: #1a1a2e;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.85em;
            line-height: 1.6;
        }
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .role-senior_manager { background: #2e7d32; }
        .role-supply_chain_manager { background: #1565c0; }
        .role-admin { background: #c62828; }
        .php-version {
            background: #333;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .success {
            background: rgba(46, 125, 50, 0.2);
            border: 2px solid #2e7d32;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #8bc34a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Hash Generator</h1>
        <p>Group 9 - Security Setup Tool</p>

        <div class="Description">
            <p> This page is designed for creating the hashes for the passwords using the password_hash() function. After copying over the hashed to the config.php file, delete the hash_passwords.php file.</p>
        </div>
        

        <div class="success">
            Successfully generated <?php echo count($users); ?> password hashes
        </div>
        
        
        <h2 style="margin-top: 30px; color: #a89fca;">Generated Password Hashes</h2>
        
        <?php 
        foreach ($users as $username => $userData) {
            $hash = $hashes[$username];
        ?>
        <div class="user-card">
            <h3>
                <?php echo htmlspecialchars($username); ?>
                <span class="role-badge role-<?php echo htmlspecialchars($userData['role']); ?>">
                    <?php echo htmlspecialchars($userData['role']); ?>
                </span>
            </h3>
            
            <div class="field">
                <label>Password :</label>
                <code><?php echo htmlspecialchars($userData['password']); ?></code>
            </div>
            
            <div class="field">
                <label>Password Hash (copy this to config.php):</label>
                <code><?php echo htmlspecialchars($hash); ?></code>
            </div>
            
            <div class="field">
                <label>Full Name:</label>
                <code><?php echo htmlspecialchars($userData['full_name']); ?></code>
            </div>
        </div>
        <?php } ?>
        
        <div class="copy-section">
            <h2>Copy This to config.php</h2>
            <p style="color: #888; margin-bottom: 15px;">Replace the USERS constant in includes/config.php with this:</p>
            
            <pre>define('USERS', array(
<?php 
foreach ($users as $username => $userData) {
    $hash = $hashes[$username];
    $roleConst = 'ROLE_' . strtoupper($userData['role']);
    echo "    '" . $username . "' => array(\n";
    echo "        'password_hash' => '" . $hash . "',\n";
    echo "        'role' => " . $roleConst . ",\n";
    echo "        'full_name' => '" . $userData['full_name'] . "'\n";
    echo "    ),\n";
}
?>));</pre>
        </div>
    </div>
</body>
</html>
