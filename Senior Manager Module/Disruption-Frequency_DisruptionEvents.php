<?php
/* Disruption-Frequency_DisruptionEvents.php */

// MUST be at the very top 
// Send no-cache headers IMMEDIATELY before there is any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../includes/auth.php';

// Double-check if not logged on
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

<link rel="stylesheet" type="text/css" href="SMstyle3.css"> <!--Link to the style-->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!--Chart functionality-->
<script src = "SMscript.js"></script>
<link rel="icon" type="image/svg+xml" href="favicon(1).svg"> <!--Icon in the web page bar-->
<link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet"> <!--Fonts-->


<style>
    /* Expand or Enlarge Button Styles */
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

    /* Expanded the State Styles */
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
        width: 100vw !important; /* Here we explicity set width for expanded view */
    }
    .expanded-view > .table-container, 
    .expanded-view > .graph-container {
            flex: 1;
            height: 100%;
            max-height: none !important;
            overflow: auto;
    }
    .expanded-view canvas {
        height: 100% !important;
        width: 100% !important;
    }

    /* Container styles for relative positioning */
    
    /* Data display that is container of the layout*/
    .data-display-container {
        display: flex;
        width: 100%;
        gap: 20px;
        margin-top: 20px;
    }
    
    /* Table container layout */
    .table-container {
        flex: 1;
        overflow-y: auto; /* Enable the vertical scrolling */
        border: none;
        height: 100%;
    }
    /* Graph the container layout*/
    .graph-container {
        flex: 1;
        display: flex; /* Flex container for the canvas */
        flex-direction: column;
        padding: 10px;
        background-color: white;
        border: none;
        height: 100%;
        position: relative;
    }
    /* Canvas wrapper layout */
    .canvas-wrapper {
        flex: 1;
        position: relative;
        height: 100%;
    }

    .user-info {
        position: absolute;
        top: 10px;
        right: 120px;
        color: white;
        font-size: 0.85em;
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

    /* Refactored Classes for Structure and Layout */
    .main {
        text-align: center;
    }
    .search-title {
        /* No specific styles needed for h2 based on previous, inherits or default */
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
    .placeholder-message {
        text-align: center;
        margin-top: 50px;
    }
    
    .chart_con, .table_con {
        /* Styles were not explicitly inline but structure suggests they are standard containers */
        /* Adding basic container styles if needed, or relying on SMstyle3.css */
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
        /* flex: 0 0 75%;  Inherited or set via JS/CSS */
        transition: flex-basis 0.3s ease;
    }

    .hidden {
        display: none !important;
    }

    /* Filter Styles */
    .filter-options {
        /* General container for filter options */
    }
    .filter-item {
         /* div class='filter-item' used in JS */
         /* margin-bottom: 5px; or similar if needed */
    }

    /* Table Styling */
    .result-table {
        width: 100%;
        border-collapse: collapse;
    }
    .result-th {
        border: 1px solid #ddd;
        padding: 8px;
        background-color: #f2f2f2;
    }
    .result-td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
    }
    .error-msg {
        color: red;
    }
    
    /* Dynamic JS Elements */
    .filter-label {
        /* Label styles */
    }
    .granularity-label {
        margin-right: 5px;
    }
    .granularity-container {
        margin-top: 15px;
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
<!-- JavaScript session check the backup protection -->
<script src="session-check.js"></script>

<!-- Content layout -->
<div class="content">
    <!-- Top layout -->
	<div class="top">
		<h1>Disruption Frequency Over Time</h1>
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
    <!-- Ribbon layout -->
	<div class="sidebar">
        <!-- Home icon layout -->
		<div class="home-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='SM Shell.php'">
                <path d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z"></path>
            </svg>
        </div>
        <!-- Search bar layout -->
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
        <!-- Tab layout and paths -->
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

    <!-- Filter bar section layout -->
    <div class="page-content">
    <div class="main">
        <!-- Heading layout -->
        <h2>Search</h2>
        <div id="filter-container" class="filter-container">
            <p class="filter-title">Select a date range to analyze:</p>
            <div id="dynamic-filters-container" class="dynamic-filters-container"></div>
        </div>
        <button id="go-button" class="go-button">Go</button>
        <button id="clear-filters" class="clear-button">Clear All Filters</button>
    </div>
    <!-- Main section layout -->
    <div class="plot">
        <!-- Toggle filters button layout -->
        <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
		<h3>Frequency Analysis</h3>
        <!-- Placeholder message layout -->
        <div id="placeholder-message" class="placeholder-message">
            <p>Click "Go" to display results!</p>
            <p>Page may take a few seconds to load</p>
            <p>If page fails to load, refresh browser and try again</p>
        </div>
        <!-- Data display container layout -->
        <div class="data-display-container hidden">
            <!-- Chart container layout -->
            <div class="chart_con">
                <button type="button" class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                <div class="graph-container">
                    <div class="canvas-wrapper">
                        <canvas id="freqChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Table container layout -->
            <div class="table_con">
                <button type="button" class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>
                <div id="table-output" class="table-container">
                     
                </div>
            </div>
        </div>
	</div>
    </div>
</div>

<script src="page-navigation.js"></script>
<script src="disruption-frequency_disruptionevents.js"></script>

</body>
</html>
