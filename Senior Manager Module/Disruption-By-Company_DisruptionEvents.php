<?php
/**
 * Disruption-By-Company_DisruptionEvents.php - Protected Page
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

    <link rel="stylesheet" type="text/css" href="SMstyle3.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="SMscript.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon(1).svg">
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">


    <style>
        /* Expand/Enlarge Button Styles */
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
            /* Pull it out of normal layout */
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
            width: 100vw !important;
            /* Explicitly set width for expanded view */
        }

        /* Make sure the inner containers actually use the full height when going in expended mode */
        .expanded-view>.graph-inner-container,
        .expanded-view>#table-container {
            flex: 1;
            height: 100%;
            max-height: none !important;
            overflow: auto;
        }

        /* When expanded left chart streches with the window */
        .expanded-view canvas {
            height: 100% !important;
            width: 100% !important;
        }

        /* Container styles for tables and graphs */
        .table_con,
        .chart_con {
            position: relative;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            /* For chart containers in the side-by-side view */
            display: flex;
            flex-direction: column;
        }
        
        /* Flex child behavior for charts in row */
        .chart_con {
             flex: 1;
             min-width: 0;
             /* width: 0; Removed as it collapses the table/chart in some contexts */
             height: 400px; /* Set height here to match previous inline logic */
        }

        .table_con {
            width: 100%;
            height: 400px;
            margin-top: 20px;
        }

        /* Row that contains both graphs height is already fixed so they dont get to tall */
        .graph-row {
            display: flex;
            gap: 20px;
            height: 400px; /* Updated from 300px to match inline style being replaced */
            min-height: 300px;
            margin-bottom: 20px;
            width: 100%;
        }

        /* Inner wrapper for the chart so the canvas fills the card height */
        .graph-inner-container {
            flex: 1;
            position: relative;
            height: 100%;
        }

        /* Ensure table output container resets properly */
        #table-output-container {
            flex: 1;
            overflow-y: auto;
            height: 100%;
        }
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }

        /* Refactored Classes for Structure and Layout */
        .main {
            text-align: center;
            /* Flex styles needed to match original layout */
            flex: 0 0 25%;
            padding-right: 10px;
        }
        .filter-container {
            margin-top: 20px; 
            text-align: center;
        }
        .filter-title {
            text-align: center; 
            font-family: 'Open Sans', sans-serif; 
            font-size: 14pt; 
            color: #475569;
        }
        .dynamic-filters-container {
             margin-top: 10px; 
        }
        .go-button {
            margin-top: 20px;
        }
        
        .total-disruptions-header {
            margin-top: 0; 
            margin-bottom: 20px; 
            font-weight: normal;
        }
        .total-count-value {
            color: #d9534f; 
            font-weight: bold;
        }
        .data-display-container {
             width: 100%;
             display: flex;
             flex-direction: column;
             gap: 20px;
        }
        
        .placeholder-message {
            text-align: center; 
            margin-top: 50px;
        }
        
        .hidden {
            display: none;
        }
        
        /* Plot section styles */
        .plot {
            position: relative !important;
            flex: 0 0 70%;
            height: 100vh;
            overflow-y: auto;
            transition: flex-basis 0.3s ease;
        }

        /* Collapsed State Styles (Restored) */
        .content.filters-hidden .main {
            display: none;
        }

        .content.filters-hidden .plot {
            flex: 0 0 100%;
            max-width: 100%;
        }

        /* Clear button */
        .clear-button {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: underline;
            margin-top: 15px;
            align-self: flex-start; /* Aligned to the left */
            padding-left: 0;
            font-family: inherit;
        }
        .clear-button:hover {
            color: #333;
        }

        /* Toggle Button Styles */
        .toggle-btn {
            position: absolute;
            left: 0 !important;
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

        /* Filter Options */
        .filter-options {
            /* General container for filter options */
        }
        
        /* JS Dynamic Classes */
        .company-filter-box {
            margin-bottom: 15px;
        }
        .company-label {
            margin-right: 5px;
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
        .company-search-input {
            width: 100%;
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
        .optional-filter-box {
            margin-top: 15px;
        }
        .type-label {
            display: block;
            text-align: left;
            margin-bottom: 5px;
        }
        .type-search-container {
            position: relative;
        }
        .type-search-input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .error-row {
            color: red;
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
<script src="session-check.js"></script>

    <div class="content">
        <div class="top">
            <h1>Disruptions by Company</h1>
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
        <!-- Left sidebar for home, search bar, and navigation tabs -->

        <div class="sidebar">
            <div class="home-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='SM Shell.php'">
                    <path
                        d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z">
                    </path>
                </svg>
            </div>
            <!-- Global search that navigates between the pages in the module -->
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
            <!-- Sidebar tabs -->
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

        <!-- Left filter panel for this page -->
        <div class="page-content">
            <div class="main">
                <h2>Search</h2>
                <!-- Where JS will build dynamic filters -->
                <div id="filter-container" class="filter-container">
                    <p class="filter-title">
                        Select companies to analyze:</p>
                    <div id="dynamic-filters-container" class="dynamic-filters-container"></div>
                </div>

                <!-- Trigger to query backend -->
                <button id="go-button" class="go-button">Go</button>
                <button id="clear-filters" class="clear-button">Clear All Filters</button>
            </div>
            <div class="plot">
                <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
                <h3>Disruption Analysis</h3>

                <!-- Total distruption summary hidden until we have some type of data -->
                <h4 id="total-disruptions-header" class="hidden total-disruptions-header">Total Disruptions:
                    <span id="total-count" class="total-count-value">0</span></h4>
                <div class="data-display-container hidden" id="results-area">

                    <!-- Graphs Container -->
                    <div class="graph-row">
                        <div class="chart_con">
                            <button type="button" class="expand-btn" title="Enlarge Graph"
                                onclick="toggleExpand(this)">&#x2922;</button>
                            <div class="graph-inner-container">
                                <canvas id="lineChart"></canvas>
                            </div>
                        </div>
                        <!-- Bar chart container -->
                        <div class="chart_con">
                            <button type="button" class="expand-btn" title="Enlarge Graph"
                                onclick="toggleExpand(this)">&#x2922;</button>
                            <div class="graph-inner-container">
                                <canvas id="barChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Table container -->
                    <div class="table_con">
                        <button type="button" class="expand-btn" title="Enlarge Table"
                            onclick="toggleExpand(this)">&#x2922;</button>
                        <div id="table-output-container">
                            <div id="table-output"></div>
                        </div>
                    </div>
                </div>
                <!-- Place Holder that tells user what do to on first load -->
                <div id="table-container-placeholder" class="placeholder-message">
                    <p>Click "Go" to display results!</p>
                    <p>Page may take a few seconds to load</p>
                    <p>If page fails to load, refresh browser and try again</p>
                </div>
            </div>
        </div>
    </div>

        <script src="page-navigation.js?v=4"></script>
        <script src="Disruption-By-Company_DisruptionEvents.js?v=4"></script>

</body>
</html>
