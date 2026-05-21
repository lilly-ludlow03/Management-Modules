<?php
/**
 * Distributor-Delay_CompanyInformation.php - Protected Page
 * 
 * Requires: senior_manager or admin role
 */

// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../includes/auth.php';

// Double-check if not logged in
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

<link rel="stylesheet" type="text/css" href="SMstyle3.css"> <!-- Link to style -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart functionality -->
<script src = "SMscript.js"></script>
<link rel="icon" type="image/svg+xml" href="favicon(1).svg"> <!-- Icon in the web page bar -->
<link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet"> <!-- Fonts -->

<style>
    /*.main {
        flex: 0 0 25%; /*Filter bar takes up 25% of the page
        padding-right: 10px;
    }
    .plot {
        flex: 0 0 75%; /*Main section takes up 75% of the page
        position: relative;
    }*/
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
    
    /* Expanded state styles. Allows the user to expand the chart and table to the full width of the page. */
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
        width: 100vw !important; /* Explicitly set width for expanded view */
    }
    .expanded-view > #table-container, 
    .expanded-view > #graph-container {
            flex: 1;
            height: 100%;
            max-height: none !important;
            overflow: auto;
    }
    .expanded-view canvas {
        height: 100% !important;
        width: 100% !important;
    }
    
    /* Container styles for relative positioning of tables and graphs. */
    .table_con, .chart_con {
        position: relative;
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        flex: 1;
        min-width: 0;
        height: 700px;
        display: flex;
        flex-direction: column;
        max-height: 70vh;
    }
    #table-container {
        flex: 1;
        overflow-y: auto;
        height: 100%; /* Fill parent */
    }
    #graph-container {
        flex: 1;
        height: 100%; /* Fill parent */
        min-height: 0; /* Allow shrinking */
        position: relative; 
    }
    .user-info {
        position: absolute;
        top: 10px;
        right: 120px;
        color: white;
        font-size: 0.85em;
    }

    /* Refactored Inline Styles */
    .filter-container {
        margin-top: 20px;
        text-align: center;
    }
    .filter-title {
        text-align: center;
        font-family: 'Open Sans', sans-serif;
        font-size: 14pt;
        color: #475569;
        margin-bottom: 10px;
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
    .filter-options-wrapper {
        margin-bottom: 15px;
    }
    .dynamic-filters-mt {
        margin-top: 10px;
    }
    .go-button {
        margin-top: 20px;
    }
    .placeholder-msg {
        text-align: center;
        margin-top: 50px;
    }
    .data-display-container {
        display: flex; /* Flex layout, visibility controlled by .hidden */
        width: 100%;
        gap: 20px;
        margin-top: 20px;
        flex: 1;
    }
    .hidden {
        display: none !important;
    }
    .chart-con-limit {
        max-height: 70vh;
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

    /* Company Filter Styles */
    .company-filter-box {
        margin-bottom: 15px;
        text-align: center;
    }
    .label-margin-right {
        margin-right: 5px;
    }
    .multi-select-container {
        display: inline-block;
        position: relative;
        width: calc(100% - 100px);
        align-items: center;
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

    /* Date Filter Styles */
    .date-filter-grid {
        margin-top: 10px;
        display: grid;
        gap: 8px;
        grid-template-columns: auto 1fr;
        align-items: center;
    }
    .date-input-full {
        width: 100%;
    }

    /* Region Filter Styles */
    .region-filter-box {
        margin-top: 10px;
    }
    .region-value-box {
        margin-top: 10px;
    }

    /* Table Styles */
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
    .error-text {
        color: red;
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
<!-- JavaScript session check for backup proteccion -->
<script src="session-check.js"></script>

<div class="content">
    <!-- Top section of the page -->
	<div class="top">
		<h1>Distributors by Average Delay</h1>
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
    <!-- Ribbon section of the page -->
	<div class="sidebar">
        <!-- Home icon -->
		<div class="home-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='SM Shell.php'">
                <path d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z"></path>
            </svg>
        </div>
        <!-- Search bar -->
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
        <!-- Tabs and paths to the correct pages. The user can click on the tab to navigate to the correct page -->
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

    <!-- Filter bar section of the page -->
    <div class="page-content">
    <div class="main">
        <!-- Heading -->
            <h2>Search</h2>
        <div id="filter-container" class="filter-container">
            <p class="filter-title">Select filters:</p>
            
            <!-- Checkbox filters that allows the user to select the filters they want to apply to the data -->
            <div class="filter-options filter-options-wrapper">
                <div class="filter-item">
                <input type="checkbox" id="companyFilter" name="filterOptions" value="Company">
                <label for="companyFilter">Distributors</label>
                </div>
                <div class="filter-item">
                <input type="checkbox" id="dateFilter" name="filterOptions" value="Date">
                <label for="dateFilter">Date</label>
                </div>
                <div class="filter-item">
                <input type="checkbox" id="regionFilter" name="filterOptions" value="Region">
                <label for="regionFilter">Region</label>
                </div>
            </div>

            <div id="dynamic-filters-container" class="dynamic-filters-mt"></div>
        </div>
        <button id="go-button" class="go-button">Go</button>
        <button id="clear-filters" class="clear-button">Clear All Filters</button>
    </div>
    <!-- Main section of the page -->
    <div class="plot">
        <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
		<h3>Delay Analysis</h3>
        <div id="placeholder-message" class="placeholder-msg">
            <p>Click "Go" to display results!</p>
            <p>Page may take a few seconds to load</p>
            <p>If page fails to load, refresh browser and try again</p>
        </div>
        <div class="data-display-container hidden">
            <div class="table_con">
                <button type="button" class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>
                <div id="table-container">
                </div>
            </div>
            <div class="chart_con">
                <button type="button" class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                <div id="graph-container">
                    <canvas id="delayChart"></canvas>
                </div>
            </div>
        </div>
	</div>
    </div>
</div>

<script src="page-navigation.js"></script>
<script src="distributor-delay_companyinformation.js"></script>

</body>
</html>

