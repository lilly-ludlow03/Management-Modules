<?php
/**
 * Key-Performance-Indicators.php - Protected Page
 * 
 * Requires: supply_chain_manager or admin role
 */
// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../../includes/auth.php';

// Double-check if users are logged in
if (!isLoggedIn()) {
    header("Location: ../../mainpage/index.php");
    exit;
}

// Check role of the user
if (!hasRole(array(ROLE_SUPPLY_CHAIN_MANAGER, ROLE_ADMIN))) {
    header("Location: ../../mainpage/index.php");
    exit;
}

$currentUser = getUsername();
$fullName = getFullName();
$userRole = getRoleDisplayName(getUserRole());
?>
<!DOCTYPE html>
<html>
<head>
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
            overflow: hidden; /* Prevent page-level scrolling */
            height: 100vh;
        }
        .content {
            height: 100vh; /* Ensure content takes full viewport height */
            display: flex;
            flex-wrap: wrap;
        }
        .main {
            flex: 0 0 30%;
            overflow-y: auto; /* Allow internal scrolling for sidebar */
            padding-right: 10px;
            height: calc(100vh - 60px); /* Adjust height accounting for top bar if needed */
        }
        .plot {
            flex: 0 0 70%;
            overflow-y: auto;
            padding: 20px;
            height: calc(100vh - 60px); /* Reset to standard viewport minus top bar */
            padding-bottom: 200px; /* Large padding to ensure scroll space */
            position: relative;
        }
        .search-bar {
            margin-bottom: 15px;
        }
        .input-wrapper {
            position: relative;
        }
        #companySearch {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        #suggest {
            position: absolute;
            width: 100%;
            background: white;
            border: 1px solid #ccc;
            z-index: 1000;
            max-height: 150px;
            overflow-y: auto;
            list-style: none;
            padding: 0;
            margin: 0;
            display: none;
        }
        #suggest li {
            padding: 8px;
            cursor: pointer;
        }
        #suggest li:hover {
            background-color: #f0f0f0;
            color: black;
        }
        .results-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap; 
            padding-bottom: 50px; /* Add padding inside the container */
        }
        #pop_con {
            flex: 1 1 30%; 
            min-width: 300px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: fit-content;
        }
        #pop_con h2 {
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-top: 0;
            font-size: 1.2em;
        }
        #pop_con p {
            margin: 10px 0;
            line-height: 1.5;
        }
        #pltcon {
            flex: 2 1 60%; /* Take up remaining space */
            min-width: 400px;
        }
        .table_con {
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th, .table td {
            border: 1px solid #eee;
            padding: 12px;
            text-align: left;
        }
        .table th {
            background-color: #f8f8f8;
            font-weight: 600;
        }
        .chart_con {
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: 400px; /* Height Constrain */
            position: relative;
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
            z-index: 100;
            border-radius: 0 4px 4px 0;
            color: #333;
            font-size: 14px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .toggle-btn:hover {
            background-color: #e0e0e0;
        }
        .plot {
            position: relative; /* Context for the button */
            transition: flex-basis 0.3s ease;
        }
        
        /* Collapsed State */
        .content.filters-hidden .main {
            display: none;
        }
        .content.filters-hidden .plot {
            flex: 0 0 100%;
            max-width: 100%;
        }

        /* Enlarge The Button Styles */
        .table_con, .chart_con {
            position: relative;
        }
        .expand-btn {
            position: absolute;
            top: 5px;
            left: 5px;
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
        .expand-btn:hover {
            background-color: #f0f0f0;
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
        .expanded-view h3 {
            margin-top: 0;
        }
        .expanded-view canvas {
            height: 90% !important;
            width: 100% !important;
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
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }
        
        /* Classes for removed inline styles */
        .placeholder-message {
            text-align: center; 
            margin-top: 50px;
        }
        .hidden {
            display: none;
        }
        .kpi-health-details {
            margin-top: 5px; 
            font-size: 0.9em;
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

<body class="kpi-page">
<!-- JavaScript session check for backup protection -->
<script src="../session-check.js"></script>
    <div class="content">
        <div class="top">
            <h1>Key Performance Indicators</h1>
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
                <a href="Company-Information.php">Company Information</a>
                <a href="Key-Performance-Indicators.php">Key Performance Indicators</a>
                <a href="Update-Company-Information.php">Update Company Information</a>
            </div>
            </div>
            <div class="tab-con">
            <button class="tab" id="btn2">Disruption Events</button>
            <div class="dropdown" id="ddn2">
                <a href="../Disruption-Events/Disruption-Frequencies.php">Disruption Frequencies</a>
                <a href="../Disruption-Events/Average-Recovery-Time.php">Average Recovery Time</a>
                <a href="../Disruption-Events/High-Impact-Disruption-Rate.php">High Impact Disruption Rate</a>
                <a href="../Disruption-Events/Total-Downtime.php">Total Downtime</a>
                <a href="../Disruption-Events/Regional-Risk-Concentration.php">Regional Risk Concentration</a>
                <a href="../Disruption-Events/Disruption-Severity-Distribution.php">Disruption Severity Distribution</a>
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
        <div class="main">
            <h2>Search</h2>
            <div class="search-bar">
                <label for="companySearch">Search Company:</label>
                <div class="input-wrapper">
                    <input type="text" id="companySearch" name="companySearch"
                        placeholder="Search..." autocomplete="off">
                    <ul id="suggest"></ul>
                </div>

            </div>

            <div class="date-container">

                <span>From</span>
                <input type="date" id="dateFrom" name="dateFrom">
                <span>to</span>
                <input type="date" id="dateTo" name="dateTo">
            </div>
            <button class="go-button" id="go-button">Go</button>
            <button id="clear-filters" class="clear-button">Clear All Filters</button>
        </div>
        <div class="plot">
            <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
            <div id="placeholder-message" class="placeholder-message">
                <p>Click "Go" to display results!</p>
                <p>Page may take a few seconds to load</p>
                <p>If page fails to load, refresh browser and try again</p>
            </div>
            <div id="results-area" class="results-container hidden">
                <!-- Company KPIs List -->
                <div id="pop_con">
                    <h2>Company Statistics</h2>
                    <p><strong>Company Name:</strong> <span id="kpi-name"></span></p>
                    <p><strong>On Time Delivery Rate:</strong> <span id="kpi-rate"></span></p>
                    <p><strong>Average Delay:</strong> <span id="kpi-avg-delay"></span></p>
                    <p><strong>Standard Deviation of Delay:</strong> <span id="kpi-std-dev"></span></p>
                    <p><strong>Financial Health Status:</strong> <span id="kpi-health"></span></p>
                    <p><strong>Distribution of Disruption Events:</strong> <span id="kpi-dist"></span></p>
                </div>
                
                <!-- Table and Graph -->
                <div id="pltcon">
                    <div class="table_con">
                        <button class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>
                        <h3>Disruption Events</h3>
                        <table id="events_table" class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Disruption Event ID</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="chart_con">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <h3>Disruption Events Over Time</h3>
                        <canvas id="eventsLineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../page-navigation.js"></script>
    <script src="key-performance-indicators.js"></script>
</body>

</html>