<?php
/**
 * index.php - Login Page with Auto-Logout
 * 
 * This page automatically logs out any existing user session when accessed,
 * then displays the login form and handles authentication.
 */
// Set no-cache headers 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Include the authentication helper
require_once __DIR__ . '/../includes/auth.php';

// Auto-Logout
if (session_status() === PHP_SESSION_NONE) {
    session_name('GROUP9_SESSION');
    session_start();
}

// Check if user is logged in 
if (isLoggedIn()) {
    // Log the auto-logout 
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    error_log(sprintf(
        "AUTO-LOGOUT: User '%s' was automatically logged out when accessing login page from IP %s",
        getUsername(),
        $ip
    ));
    
    // Perform aggressive session destruction
    $_SESSION = array();
    
    // Delete the session cookie
    $sessionName = session_name();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
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
    
    // Destroy current session if still active
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Start a new fresh session
    session_name('GROUP9_SESSION');
    session_start();
    $_SESSION = array();
    session_destroy();
}

// Initialize the error message
$error = null;

// Process login 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit-btn'])) {
    
    // Ensure a Fresh Start
    if (session_status() === PHP_SESSION_NONE) {
        session_name('GROUP9_SESSION');
        session_start();
    }
    
    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Attempt to log in the user
        $user = loginUser($username, $password);
        
        if ($user) {
            // Login successful redirect to the page based on role
            $redirectUrl = getRoleRedirectUrl($user['role']);
            header("Location: $redirectUrl");
            exit;
        } else {
            // If Login failed give generic message
            $error = "Invalid username or password.";
            
            // Log failed login attempt 
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            error_log(sprintf(
                "SECURITY: Failed login attempt for username '%s' from IP %s",
                htmlspecialchars($username),
                $ip
            ));
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="login.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #e8e9ed;
        }

        /* Main content container with team images */
        .content {
            width: 80%;
            margin: 0 auto;
            margin-top: 3%;
            aspect-ratio: 3/2;
            background-color: #f4f4f6;
            border-radius: 8px;
            box-shadow: 0 4px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .headerdiv {
            background-color: #a89fca;
            text-align: center;
            color: white;
            height: 20%;
            font-family: Montserrat;
            font-size: 2vw;
            padding: 30px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            height: 80%;
            align-content: center;
            row-gap: 7%;
            padding-left: 5%;
            padding-right: 5%;
        }

        .person {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            justify-content: center;
        }

        .dim {
            width: 60%;
            aspect-ratio: 1/1;
            overflow: hidden;
            border: 2px solid #e0e0e0;
        }

        .dim img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .person p {
            font-family: "Open Sans", sans-serif;
            font-size: 1.5vw;
            color: #545454;
            margin-top: 3%;
        }

        .loginform {
            width: 50%;
            margin: 0 auto;
            background-color: #f4f4f6;
            padding: 1%;
            border-radius: 8px;
            box-shadow: 0 4px 4px rgba(0, 0, 0, 0.1);
            margin-top: 3%;
            margin-bottom: 3%;
        }

        .loginform h1 {
            color: #545454;
            text-align: center;
            margin-bottom: 20px;
            font-size: 2.5vw;
            font-family: "Montserrat", sans-serif;
            padding-top: 3%;
            padding-bottom: 3%;
        }

        .loginform form {
            display: flex;
            flex-direction: column;
            margin-top: 5%;
            width: 80%;
            margin: 5% auto 0 auto;
        }

        .loginform input[type="text"],
        .loginform input[type="password"] {
            padding: 20px;
            border: 2px solid #e8e9ed;
            border-radius: 8px;
            font-size: 1.5vw;
            font-family: "Open Sans", sans-serif;
        }

        .loginform input[type="text"]:focus,
        .loginform input[type="password"]:focus {
            outline: none;
            border-color: #a89fca;
        }

        .loginform input[type="submit"] {
            padding: 12px;
            background-color: #a89fca;
            color: white;
            border: 1px #928aaf;
            border-radius: 4px;
            font-size: 2vw;
            font-family: 'Montserrat', sans-serif;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .loginform input[type="submit"]:hover {
            background-color: #928aaf;
        }

        .error {
            color: #c62828;
            font-size: 1.1em;
            margin-bottom: 10px;
            text-align: center;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
        }
    </style>
</head>

<body>
     <!-- Team info and Welcome Section -->
    <div class="content">
        <div class="headerdiv">
            <h1>Welcome to Group 9's Webpage!</h1>
        </div>
        <div class="row">
            <div class="person">
                <div class="dim">
                    <img src="MargaretBrink.jpg">
                </div>
                <p>Margaret Brink</p>
            </div>
            <div class="person">
                <div class="dim">
                    <img src="ConnorKirkendall1.jpg" alt="Connor Kirkendall">
                </div>
                <p>Connor Kirkendall</p>
            </div>
            <div class="person">
                <div class="dim">
                    <img src="AlaynaSkidmore.JPG" alt="Alayna Skidmore">
                </div>
                <p>Alayna Skidmore</p>
            </div>
            <div class="person">
                <div class="dim">
                    <img src="LillianLudlow.jpg" alt="Lillian Ludlow">
                </div>
                <p>Lillian Ludlow</p>
            </div>
            <div class="person">
                <div class="dim">
                    <img src="EmilioMuzquiz.jpg" alt="Emilio Muzquiz">
                </div>
                <p>Emilio Muzquiz</p>
            </div>
            <div class="person">
                <div class="dim">
                    <img src="AlexanderMujica.JPG" alt="Alexander Mujica">
                </div>
                <p>Alexander Mujica</p>
            </div>
        </div>
    </div>

    <div class="loginform">
        <h1>Login</h1>

        <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="index.php">
            <input type="text" name="username" placeholder="Enter Username" required><br><br>
            <input type="password" name="password" placeholder="Enter Password" required><br><br>
            <input type="submit" name="submit-btn" value="Login">
        </form>
    </div>

    <script>
        // Aggressive back button prevention and cache clearing
        (function() {
            // Clear any cached pages f
            if (window.history && window.history.pushState) {
                // Replace current entry
                window.history.replaceState(null, null, window.location.href);
                
                // Prevent back button
                window.history.pushState(null, null, window.location.href);
                window.addEventListener('popstate', function() {
                    window.history.pushState(null, null, window.location.href);
                });
            }
            
            // Force reload if this page was loaded from cache 
            if (window.performance && window.performance.navigation) {
                if (window.performance.navigation.type === 2) {
                    window.location.reload(true);
                }
            }
            
            // Also check with PerformanceNavigationTiming 
            if (window.performance && window.performance.getEntriesByType) {
                var navEntries = window.performance.getEntriesByType('navigation');
                if (navEntries.length > 0 && navEntries[0].type === 'back_forward') {
                    window.location.reload(true);
                }
            }
        })();
    </script>
</body>

</html>

