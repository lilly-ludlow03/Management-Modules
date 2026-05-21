<?php
/**
 * Average-Financial-Health_Finances.php - Protected Page
 * 
 * Requires: senior_manager or admin role
 */

// MUST be at the very top - no whitespace before <?php
// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../includes/auth.php';

// Double-check: If not logged in, redirect immediately
if (!isLoggedIn()) {
    header("Location: ../mainpage/index.php");
    exit;
}

// Check role
if (!hasRole(array(ROLE_SENIOR_MANAGER, ROLE_ADMIN))) {
    header("Location: ../mainpage/index.php");
    exit;
}

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

    <link rel="stylesheet" type="text/css" href="SMstyle3.css"> <!--Link to SM stylesheet for styling the page-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!--Link to Chart.js for generating charts-->
    <script src="SMscript.js"></script> <!--Link to SMscript.js for additional functionality-->
    <link rel="icon" type="image/svg+xml" href="favicon(1).svg">
    <!--Link to favicon for the page (the icon in the web browser of the page)-->
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">
    <!--Link to Montserrat and Open Sans fonts for the page. Imports them from Google Fonts-->

    <style>
        /* Holds the table and graphs side by side */
        .data-display-container {
            display: flex; /* Changed from inline style to class, hidden by default via class toggle or JS logic */
            width: 100%;
            gap: 20px;
            /*Gap between the table and graph sections*/
            margin-top: 20px;
            /*Margin top for the container*/
        }
        
        .hidden {
            display: none !important;
        }

        /* Expand/Enlarge Button Styles */
        .expand-btn {
            position: absolute;
            /*Position the button absolutely. Stays in the top left of the container its in (table or graph)*/
            top: 5px;
            /*Button stays in the top*/
            left: 5px;
            /*Button stays in the left*/
            background: rgba(255, 255, 255, 0.8);
            /*Background color for the button. This is a slightly transparent white.*/
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1.2em;
            cursor: pointer;
            /*Cursor is a pointer when hovering over the button*/
            color: #1e3a5f;
            /* Dark blue text color for the button*/
            z-index: 20;
            /*Z-index is the stack order of the button. Ensures that the bottom remains above everything else inside the container.*/
            padding: 2px 6px;
            /*The space between the text and the border of the button.*/
            line-height: 1;
        }

        .expand-btn:hover {
            background-color: #f0f0f0;
            /*Background color is light gray when hovering over the button*/
        }

        /* Expanded Fullscreen Styles */
        .expanded-view {
            position: fixed !important;
            /*Locks it to screen instead of normal layout. This overrides the normal layout.*/
            top: 0;
            left: 0;
            width: 100vw !important;
            /*Makes it the full width of the screen*/
            height: 100vh !important;
            /*Makes it the full height of the screen*/
            z-index: 9999;
            /*Stays above the page content.*/
            background: white !important;
            /*Background color is white.*/
            padding: 40px;
            box-sizing: border-box;
            /*Ensures that the padding is included in the width and height calculations.*/
            margin: 0 !important;
            max-height: none !important;
            display: flex;
            /*Allows table and chart to stack vertically*/
            flex-direction: column;
            width: 100vw !important;
            /* Explicitly set width for expanded view */
        }

        /*Ensures the table and chart expand properly in exapanded mode*/
        .expanded-view>#table-container,
        .expanded-view>#graph-container {
            flex: 1;
            /*Ensures it takes up equal vertical space*/
            height: 100%;
            /*Ensures it takes up the full height of the container*/
            max-height: none !important;
            overflow: auto;
            /*Allows scrolling if the content is too large*/
        }

        /* Makes the chart always stretch inside fullscreen */
        .expanded-view canvas {
            height: 100% !important;
            width: 100% !important;
        }

        /* Split view styles for the table and chart */
        .table_con,
        .chart_con {
            position: relative;
            /*Allows the table and chart to be positioned relative to the container*/
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            /*A subtle shadow for the table and chart*/
            flex: 1;
            /* Ensures the tavle and graph take up the same amount of space*/
            min-width: 0;
            /* Allow shrinking without there being overflow */
            /*height: 700px;*/
            display: flex;
            flex-direction: column;
        }
        .table_con{
            height: fit-content;
            max-height: 70vh;
        }

        .chart_con{
            height: 70vh;
        }

        /*Table styles*/
        #table-container {
            flex: 1;
            overflow-y: auto;
            /*height: 100%;
            /* Fill parent */
        }

        /*Chart styles*/
        #graph-container {
            flex: 1;
            height: 80vh;
            /* Fill parent */
            /* Allow shrinking */
            position: relative;
            /* For Chart.js */
        }
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }
        
        /* New Classes from Refactoring */
        .filter-container {
            text-align: center;
        }
        .filter-title {
            text-align: center;
            font-family: 'Open Sans', sans-serif;
            font-size: 14pt;
            color: #475569;
            margin-bottom: 10px;
        }
        .dynamic-filters-container {
            margin-top: 10px;
        }
        .go-button {
            margin-top: 20px;
        }
        .placeholder-message {
            text-align: center;
            margin-top: 50px;
        }
        .company-filter-box {
            margin-bottom: 15px;
        }
        .multi-select-container {
            display: inline-block;
            position: relative;
            width: calc(100% - 90px);
        }
        .selected-companies-box {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 5px;
            padding: 5px;
            border: 1px solid #ccc;
            min-height: 30px;
        }
        .suggestions-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid #ccc;
            position: absolute;
            width: 100%;
            background-color: white;
            z-index: 1001;
            max-height: 150px;
            overflow-y: auto;
        }
        .suggestion-item {
            padding: 8px;
            cursor: pointer;
        }
        .suggestion-item:hover {
            background-color: #f0f0f0;
        }
        .company-tag {
            background-color: #e0e0e0;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 5px;
            font-size: 0.8em;
        }
        .remove-tag-btn {
            border: none;
            background-color: transparent;
            color: #888;
            cursor: pointer;
            margin-left: 4px;
        }
        .date-filter-box {
            margin-top: 10px;
        }
        .type-filter-box {
            margin-top: 10px;
        }
        .label-margin-right {
            margin-right: 5px;
        }
        .label-margin-left {
            margin-left: 10px;
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
            z-index: 100;
        }
        .plot {
            position: relative;
            flex: 0 0 70%;
            height: 100vh;
            overflow-y: auto;
            transition: flex-basis 0.3s ease;
        }
        .no-scroll {
            overflow: hidden;
        }
        
        .filter-options {
            display: flex;
            flex-wrap: nowrap;
            gap: 5px;
            align-items: center;
            justify-content: center;
            margin: 15px 0; 
        }
        .filter-options label {
            font-size: 0.9em;
            font-family: "Open Sans", sans-serif;
            color: #285733;
            margin-right: 5px;
            font-weight: normal;
        }
        .filter-options input[type="checkbox"] {
            margin-right: 5px;
        }
        
        /* JS Generated Elements Styles */
        .chart-limit-container {
            position: absolute;
            top: 5px;
            right: 5px;
            z-index: 25;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 2px 5px;
            border-radius: 4px;
        }
        .chart-limit-label {
            font-size: 0.8em;
            margin-right: 5px;
        }
        .chart-limit-select {
            font-size: 0.8em;
        }

        /* Table Styling */
        .result-table {
            width: 100%;
            border-collapse: collapse;
        }
        .result-th {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            background-color: #f2f2f2;
        }
        .result-td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
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
        <!-- Top section of the page-->
        <div class="top">
            <h1>Average Financial Health by Company</h1>
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
        <!-- Dropdown ribbon section of the page-->
        <div class="sidebar">
            <div class="home-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='SM Shell.php'">
                    <path
                        d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z">
                    </path>
                </svg>
            </div>
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
            <div class="tab-con">
                <button class="tab" id="btn1">Company Information</button>
                <div class="dropdown" id="ddn1">
                    <a href="Most-Critical-Companies_CompanyInformation.php">Most Critical Companies</a>
                    <a href="Top-Distributors_CompanyInformation.php">Top Distributors</a>
                    <a href="Distributor-Delay_CompanyInformation.php">Distributor Delay</a>
                    <a href="ADD-COMPANY_CompanyInformation.php">ADD COMPANY</a>
                </div>
            </div>
            <div class="tab-con">
                <button class="tab" id="btn2">Finances</button>
                <div class="dropdown" id="ddn2">
                    <a href="Average-Financial-Health_Finances.php">Average Financial Health</a>
                    <a href="Financials-By-Region_Finances.php">Financials By Region</a>
                </div>
            </div>
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
        <div class="page-content">
            <!-- Sidebar section of the page -->
            <div class="main">
                <h2>Search</h2>
                <div id="filter-container" class="filter-container">
                    <p class="filter-title">
                        Select filters:</p>
                    <div class="filter-options">
                        <div class="filter-item">
                            <input type="checkbox" id="companyFilter" name="filterOptions" value="Company">
                            <label for="companyFilter">Company</label>
                        </div>
                        <div class="filter-item">
                            <input type="checkbox" id="dateFilter" name="filterOptions" value="Date">
                            <label for="dateFilter">Date</label>
                        </div>
                        <div class="filter-item">
                            <input type="checkbox" id="typeFilter" name="filterOptions" value="Type">
                            <label for="typeFilter">Company Type</label>
                        </div>
                    </div>
                    <div id="dynamic-filters-container" class="dynamic-filters-container"></div>
                </div>
                <button id="go-button" class="go-button">Go</button>
                <button id="clear-filters" class="clear-button">Clear All Filters</button>
            </div>
            <!-- Main section of the page. Tables and graphs are displayed here -->
            <div class="plot">
                <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>

                <h3>Financial Health Score Analysis</h3>
                <div id="placeholder-message" class="placeholder-message">
                    <p>Click "Go" to display results!</p>
                    <p>Page may take a few seconds to load</p>
                    <p>If page fails to load, refresh browser and try again</p>
                </div>
                <div class="data-display-container hidden">
                    <div class="table_con">
                        <button type="button" class="expand-btn" title="Enlarge Table"
                            onclick="toggleExpand(this)">&#x2922;</button>
                        <div id="table-container">
                        </div>
                    </div>
                    <div class="chart_con">
                        <button type="button" class="expand-btn" title="Enlarge Graph"
                            onclick="toggleExpand(this)">&#x2922;</button>
                        <div id="graph-container">
                            <!-- Chart canvas will be appended here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="page-navigation.js"></script>
    <script src="Average-Financial-Heath_Finances.js"></script>

</body>

</html>

