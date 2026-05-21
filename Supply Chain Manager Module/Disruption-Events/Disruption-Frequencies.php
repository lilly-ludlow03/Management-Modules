<?php
/**
 * Disruption-Frequencies.php - Protected Page
 * 
 * Requires: supply_chain_manager or admin role
 */

// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../../includes/auth.php';

// Double-check if not logged in, redirect immediately
if (!isLoggedIn()) {
    header("Location: ../../mainpage/index.php");
    exit;
}

// Check role
if (!hasRole(array(ROLE_SUPPLY_CHAIN_MANAGER, ROLE_ADMIN))) {
    header("Location: ../../mainpage/index.php");
    exit;
}
// Info used in header 
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

    <link href="../SC-Manager-Style-V2.css" rel="stylesheet" type="text/css">
    <link rel="icon" type="image/svg+xml" href="../favicon(3).svg">
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevent the page-level scrolling */
            height: 100vh;
        }
        .content {
            height: 100vh; /* Ensure the content takes full viewport height */
            display: flex;
            flex-wrap: wrap;
        }
        .main {
            flex: 0 0 30%;
            overflow-y: auto; /* Allows internal scrolling for the sidebar */
            padding-right: 10px;
            height: calc(100vh - 60px); /* Leave room for the top header */
            transition: all 0.3s ease; /* Smooth collapse/expand */
        }
        .plot {
            flex: 0 0 70%;
            overflow-y: auto; /* Allow scrolling to the plot area */
            height: calc(100vh - 60px);
            transition: flex-basis 0.3s ease; /*  For smooth transition */
            position: relative;
        }
        /* Collapsed State of the page */
        .content.filters-hidden .main {
            display: none;
        }
        .content.filters-hidden .plot {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .data-display-container {
            display: flex;
            width: 100%;
            margin-top: 20px;
        }
        .table-container {
            flex: 0 0 40%;
            padding-right: 10px;
            max-height: 600px;
            overflow-y: auto;
        }
        .graph-container {
            flex: 0 0 60%;
            padding-left: 10px;
            height: 600px; /* Match the increased height */
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        canvas#recoveryChart {
            max-height: 100% !important;
            width: 100% !important;
        }
        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 15px 0;
        }
        /* Adjust label styling for the side bar */
        .filter-options label {
            margin-right: 5px;
            font-size: 0.9em;
        }
        .dynamic-filter-item {
            margin-bottom: 15px;
        }
        
        /*.go-button {
            display: block;
            background-color: green;
            color: white;
            width: 60px;
            text-align: center;
            padding: 6px 20px;
            border: none;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            margin: 20px auto 0;
        }
        .go-button:hover {
            background-color: darkgreen;
        }*/

        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .table th {
            background-color: #f2f2f2;
        }

        /*  The auto-suggest styles */
        #company-multi-search {
            width: 100%;
            padding: 5px;
        }
        .clear-button {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: underline;
            margin-top: 15px;
            align-self: flex-start;
            padding-left: 0;
            font-family: inherit;
        }
        .clear-button:hover {
            color: #333;
        }
        
        /* Full-screen toggle button sits inside each container */
        .table-container, .graph-container {
            position: relative;
        }
        .expand-btn {
            position: absolute;
            top: 5px;
            left: 15px; /* Shifted to align better */
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1.2em;
            cursor: pointer;
            color: #1e3a5f;
            z-index: 20;
            padding: 2px 6px;
            line-height: 1;
        }
        .graph-container .expand-btn {
            left: 25px; /* Extra shift for graph container padding */
        }
        
        /* Expanded State Styles */
        .expanded-view {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999;
            background: white !important;
            padding: 40px;
            box-sizing: border-box;
            margin: 0 !important;
            max-height: none !important;
            display: flex;
            flex-direction: column;
        }
        .expanded-view canvas {
            height: 90% !important;
            width: 100% !important;
        }
        .toggle-btn:hover {
            background-color: #e0e0e0;
        }
        /* Toggle Button Styles */
        .toggle-btn {
            position: absolute;
            left: 0;
            top: 20px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-left: none;
            padding: 8px 4px;
            cursor: pointer;
            border-radius: 0 4px 4px 0;
            color: #333;
            font-size: 14px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        /* Collapsed State */
        .content.filters-hidden .main {
            display: none;
        }
        .content.filters-hidden .plot {
            flex: 0 0 100%;
            max-width: 100%;
        }
        /* Custom Alert to Modal Styles */
        #custom-alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        #custom-alert-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        #custom-alert-title {
            color: red;
            font-size: 1.5em;
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: bold;
        }

        #custom-alert-message {
            margin-bottom: 20px;
            color: #333;
            line-height: 1.5;
        }

        #custom-alert-close {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }

        #custom-alert-close:hover {
            background-color: #c82333;
        }
        /* User info next to logout */
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }

        /* Helper Classes for Clean HTML */
        .center-text {
            text-align: center;
        }
        .filter-options-centered {
            justify-content: center;
        }
        .placeholder-message {
            text-align: center; 
            margin-top: 50px;
        }
        .hidden {
            display: none;
        }
        .subheading-text {
            text-align: center; 
            font-weight: bold; 
            font-size: 18pt;
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
    padding: 0 0 0 0!important;
}

.logout_con:hover {
    background-color: #2c5282;
    border: 1px solid #3667a2;
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

<body>
    <script src = "../session-check.js"></script>
    <!-- Custom Alert Modal -->
    <div id="custom-alert-overlay">
        <div id="custom-alert-box">
            <h2 id="custom-alert-title">Alert</h2>
            <div id="custom-alert-message"></div>
            <button id="custom-alert-close">Close</button>
        </div>
    </div>
    <div class="content">
        <div class="top">
            <h1>Disruption Frequencies</h1>
            <div class="logout_con">
                <div class="logout_row">
                <img src="../user (1).svg">
                <p><?php echo htmlspecialchars($fullName); ?></p>
                </div>
                <div class="logout_row">
                <img src="../logout.svg">
                <a href="../../mainpage/logout.php">Log Out</a>
                </div>
            </div>
        </div>
        <div class="sidebar">
            <div class="home-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='../SC Manager Shell.php'">
                <path d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z"></path>
            </svg>
            </div>
            <div class="search-wrapper">
                <div class="search-icon" id="page-search">
                    <svg width="60%" height="60%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="search-page-con" id="page-search-bar">
                    <input type="text" id="page-search-input" class="search-input"
                        placeholder="Search page...">
                    <ul id="pagesuggest"></ul>
                    <div class="search-arrow-con" id="page-search-go">
                        <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path  d="M5 12H19M19 12L12 5M19 12L12 19" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="tab-con">
            <button class="tab" id="btn1">Company Details</button>
            <div class="dropdown" id="ddn1">
                <a href="../Company-Details/Company-Information.php">Company Information</a>
                <a href="../Company-Details/Key-Performance-Indicators.php">Key Performance Indicators</a>
                <a href="../Company-Details/Update-Company-Information.php">Update Company Information</a>
            </div>
            </div>
            <div class="tab-con">
            <button class="tab" id="btn2">Disruption Events</button>
            <div class="dropdown" id="ddn2">
                <a href="Disruption-Frequencies.php">Disruption Frequencies</a>
                <a href="Average-Recovery-Time.php">Average Recovery Time</a>
                <a href="High-Impact-Disruption-Rate.php">High Impact Disruption Rate</a>
                <a href="Total-Downtime.php">Total Downtime</a>
                <a href="Regional-Risk-Concentration.php">Regional Risk Concentration</a>
                <a href="Disruption-Severity-Distribution.php">Disruption Severity Distribution</a>
            </div>
            </div>
            <div class="tab-con">
            <button class="tab" id="btn3">Transaction Information</button>
            <div class="dropdown" id="ddn3">
                <a href="../Transaction-Information/General-Transactions.php">General Transactions</a>
                <button class="subtab" id="subtab3">Distributor Transactions</button>
                <div class="subtabdropdown" id="subddn3">
                    <a href="../Transaction-Information/Shipment-Information.php">Shipment Information</a>
                    <a href="../Transaction-Information/Distributor-Information.php">Distributor Information</a>
                </div>
            </div>
            </div>
        </div>
        
        <!-- Filter controls -->
        <div class="main">
            <h2>Disruption Frequency Per Company</h2>
            
            <p class="center-text">Select filters:</p>
            
            <div class="filter-options filter-options-centered">
                <input type="checkbox" id="tierFilter" name="filterOptions" value="Tier">
                <label for="tierFilter">Tier</label>

                <input type="checkbox" id="companyFilter" name="filterOptions" value="Company">
                <label for="companyFilter">Company</label>

                <input type="checkbox" id="regionFilter" name="filterOptions" value="Region">
                <label for="regionFilter">Region</label>

                <input type="checkbox" id="dateFilter" name="filterOptions" value="Date">
                <label for="dateFilter">Date</label>
            </div>
            
            <div id="dynamic-filters-container"></div>
            <div id="dynamic-dividers"></div>
            
            <button id="go-button-filters" class="go-button">Go</button>
            <button id="clear-filters" class="clear-button">Clear All Filters</button>
        </div>

        <!-- Modified Plot Section for Results -->
        <div class="plot">
            <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
            
            <div id="placeholder-message" class="placeholder-message">
                <p>Click "Go" to display results!</p>
                <p>Page may take a few seconds to load</p>
                <p>If page fails to load, refresh browser and try again</p>
            </div>

            <div id="results-area" class="hidden">
                <div class="subheading">
                    <p class="subheading-text">Total Disruption Frequency</p>
                </div>
                
                <div class="data-display-container">
                    <div class="table-container">
                        <button class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>
                        <!-- Table will appear here -->
                    </div>
                    <div class="graph-container">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <!-- Graph will appear here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../page-navigation.js"></script>
    <script src="disruption-frequencies.js"></script>
</body>
</html>