<?php
/**
 * SM Shell.php - Senior Manager Module Main Page
 * 
 * This page is protected and requires:
 * - User to be logged in
 * - User to have 'senior_manager' or 'admin' role
 * 
 * Unauthorized users are redirected to the login page.
 */

// MUST be at the very top - no whitespace before <?php
// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Include authentication helper
require_once __DIR__ . '/../includes/auth.php';

// Double-check: If not logged in, redirect immediately
if (!isLoggedIn()) {
    // Clear any potential stale session data
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    header("Location: ../mainpage/index.php");
    exit;
}

// Check role
if (!hasRole(array(ROLE_SENIOR_MANAGER, ROLE_ADMIN))) {
    header("Location: ../mainpage/index.php");
    exit;
}

// Get current user info for display
$currentUser = getUsername();
$fullName = getFullName();
$userRole = getRoleDisplayName(getUserRole());
?>
<!DOCTYPE html>
<html>
<head>
<!-- NO CACHE meta tags as backup -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<link rel="stylesheet" type="text/css" href="SMstyle3.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="icon" type="image/svg+xml" href="favicon(1).svg"> <!--Icon in the web page bar-->
<link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">

<style>
    .sm-shell-main {
        flex-basis: 100% !important;
        max-width: 100% !important;
        min-height: calc(100vh - 160px);
    }
    .sm-shell-main p {
        padding-bottom: 20px;
    }
    .user-info {
        position: absolute;
        top: 10px;
        right: 120px;
        color: white;
        font-size: 0.9em;
    }
    .user-info strong {
        color: #fff;
    }

	.sm-shell-main p {
            font-family: "Open Sans", sans-serif;
            color: #475569;
            font-size: 15pt;
            line-height: 1.8;
            padding-bottom: 18px;
            background: white;
            padding: 20px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #5fa36f;
        }

        .how-to {
            background: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .page-info-grid {
            display: grid;
            gap: 25px;
            margin-top: 30px;
        }

        .page-info {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #5fa36f;
        }

        .sm-shell-main h3 {
            color: #285733;
            font-size: 18pt;
            font-family: "Montserrat", sans-serif;
            margin-top: 40px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        h4 {
            color: #285733;
            font-size: 16pt;
            margin-bottom: 15px;
            font-family: "Montserrat", sans-serif;
            font-weight: 600;
        }

        .page-info p {
            margin: 0;
            padding: 0;
            box-shadow: none;
            border: none;
            background: transparent;
        }

        .pages-con {
            display: flex;
            gap: 30px;
            margin-top: 30px;
            align-items: flex-start;
			padding-bottom: 40px
        }

        .page-grid-col {
            flex: 1;
            min-width: 30%;
        }
        .logout_con {
    display: flex;
    flex-direction: column;
    position: absolute;
    top: 0%;
    right: 0%;
    padding: 5px;
    border-radius: 2px;
    justify-content: center;
    align-items: center;
    white-space: nowrap;
    width: 10%;
}

.logout_con a, .logout_con p {
    color: white;
    font-family: "Open Sans", sans-serif;
    font-size: 8pt;
    cursor: pointer;
    text-decoration: none;
    margin: 0;
    padding: 0;
}

.logout_con:hover {
    background-color: #397d49;
    border: 1px solid #5fa36f;
    text-decoration: none;
}

.logout_con:hover a{
    text-decoration: underline;
}

.logout_con img {
    max-height: 3vh;
    margin: 0;
}

.logout_row {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 2px;
    justify-content: center;
}
</style>
</head>

<title> Senior Manager Module </title>

<body>
<!-- JavaScript session check - backup protection -->
<script src="session-check.js"></script>

<div class="content">
	<div class="top">
            <h1>Senior Manager Module</h1>
			<div class="logout_con">
                <div class="logout_row">
                <img src="user (1).svg">
                <p><?php echo htmlspecialchars($fullName); ?></p>
                </div>
                <div class="logout_row">
                <img src="logout.svg">
                <a href="../mainpage/logout.php">Log Out</a>
                </div>
            </div>
        </div>
        <!--Ribbon (drop-downs) with home icon, search bar, and tabs-->
        <div class="sidebar">
            <!--Home icon-->
            <div class="home-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                    <path
                        d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z">
                    </path>
                </svg>
            </div>
            <!--Search bar-->
            <div class="search-wrapper">
                <div class="search-icon" id="page-search">
                    <svg width="60%" height="60%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                            stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="search-page-con" id="page-search-bar">
                    <input type="text" id="page-search-input" class="search-input" placeholder="Search page...">
                    <ul id="pagesuggest"></ul>
                    <div class="search-arrow-con" id="page-search-go">
                        <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="white" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>
            <!--All tabs and the links to their respective pages-->
            <div class="tab-con">
                <button class="tab" id="btn1">Company Information</button>
                <!--Dropdown 1: Company information tabs.-->
                <div class="dropdown" id="ddn1">
                    <a href="Most-Critical-Companies_CompanyInformation.php">Most Critical Companies</a>
                    <a href="Top-Distributors_CompanyInformation.php">Top Distributors</a>
                    <a href="Distributor-Delay_CompanyInformation.php">Distributor Delay</a>
                    <a href="ADD-COMPANY_CompanyInformation.php">ADD COMPANY</a>
                </div>
            </div>
            <!--Dropdown 2: Finances tabs.-->
            <div class="tab-con">
                <button class="tab" id="btn2">Finances</button>
                <div class="dropdown" id="ddn2">
                    <a href="Average-Financial-Health_Finances.php">Average Financial Health</a>
                    <a href="Financials-By-Region_Finances.php">Financials By Region</a>
                </div>
            </div>
            <!--Dropdown 3: Disruption events tabs.-->
            <div class="tab-con">
                <button class="tab" id="btn3">Disruption Events</button>
                <div class="dropdown" id="ddn3">
                    <a href="Regional-Disruption_DisruptionEvents.php">Regional Disruption</a>
                    <a href="Disruption-Frequency_DisruptionEvents.php">Disruption Frequency</a>
                    <a href="Disruption-Effects_DisruptionEvents.php">Disruption Effects</a>
                    <a href="Disruption-By-Company_DisruptionEvents.php">Disruption By Company</a>
                </div>
            </div>
        </div>

        <!--Main content of the page-->
        <div class="main sm-shell-main">
            <!--Welcome message-->
            <h2>Welcome to the Senior Manager Module!</h2>
            <div class="how-to">
                <h2>How to use the website:</h2>
                <p>Click the tabs at the top of the page to navigate to our different pages. By clicking on one, you
                    will
                    get a dropdown menu of all pages in that section. To close this dropdown and open another, just
                    click
                    anywhere else on the page and it will close.</p>
                <p>Navigate to a different page by clicking on the title in a dropdown menu or by searching for it. To
                    search, click on the search bar. Type in the name of the page you would like to get to and press the
                    arrow button.</p>
                <p>The options in the dropdown menu will be linked to pages or produce another dropdown menu. Arrows
                    will
                    appear next to options that produce another dropdown.</p>
                <p>There is a filter section on the right of each page where you can filter your results to analyze data
                    in
                    a different way. Opening and closing this menu will cause the other information on the page to
                    expand
                    and shrink accordingly.</p>
                <p>Log out via the button at the top of any page and click the home button on the left of the tabs to
                    return
                    to this page at any time.</p>
            </div>

            <h2>Where do I go to find what I need?</h2>
            <p>See below for each of the tab groups, what pages they contain, and what each respective page displays. You can also search for a specific page via the search bar in the ribbon. Note: In the Disrupton Events tabs, RED TEXT means the disruption event is new/ongoing.</p>
            <div class="pages-con">
                <div class="page-grid-col">
                <h3>Company Information:</h3>
                <div class="page-info-grid">
                <div class="page-info">
                    <h4>Most Critical Companies:</h4>
                    <p>Shows companies and their criticality score, ranking them from
                        most critical to least critical.</p>
                </div>
                <div class="page-info">
                    <h4>Top Distributors:</h4>
                    <p>Shows distributors and their shipment volume, ranking the companies
                        from top distributors to lowest distributors.</p>
                </div>
                <div class="page-info">
                    <h4>Distributor Delay:</h4>
                    <p>Shows distributors and their average delay in days, ranking the
                        distributors from highest delay to shortest delay.</p>
						</div>
				<div class="page-info">
                    <h4>ADD COMPANY:</h4>
                    <p>Add a company to the database.</p>
                </div>
                </div>
            </div>
                <div class="page-grid-col">
                <h3>Finances:</h3>
                <div class="page-info-grid">
                <div class="page-info">
                    <h4>Average Financial Health:</h4>
                    <p>Shows companies and their financial health scores, ranking
                        them from best financial health to worst financial health.</p>
                </div>
                <div class="page-info">
                    <h4>Financials by Region:</h4>
                    <p>Shows the average financial health by region.</p>
                </div>
                </div>
                </div>
                <div class="page-grid-col">
            <h3>Disruption Events:</h3>
            <div class="page-info-grid">
            <div class="page-info">
                <h4>Regional Disruption:</h4>
                <p>Shows total disruptions and total high impact disruptions per
                    region.</p>
            </div>
            <div class="page-info">
                <h4>Disruption Frequency:</h4>
                <p>Shows detailed information regarding disruptions, when they occur,
                    and how their frequency has changed over time.</p>
            </div>
            <div class="page-info">
                <h4>Disruption Effects:</h4>
                <p>Shows companies affected by certain disruption events.</p>
            </div>
            <div class="page-info">
                <h4>Disruption by Company:</h4>
                <p>Shows all disruptions for a specific company.</p>
            </div>
            </div>
            </div>
            </div>
			</div>
</div>

<script src="page-navigation.js"></script>
</body>
</html>
