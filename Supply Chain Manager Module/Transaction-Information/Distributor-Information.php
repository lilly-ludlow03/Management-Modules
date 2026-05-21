<?php
// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../../includes/auth.php';

// Double-check If user is logged in
if (!isLoggedIn()) {
    header("Location: ../../mainpage/index.php");
    exit;
}

// Check role
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

    <link rel="icon" type="image/svg+xml" href="../favicon(3).svg">
    <link href="../SC-Manager-Style-V2.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
            overflow-y: auto; /* Allow internal scrolling for the sidebar */
            padding-right: 10px;
            height: calc(100vh - 60px); 
        }
        .plot {
            flex: 0 0 70%;
            overflow-y: auto; /* Allow scrolling for the results */
            padding: 20px;
            height: calc(100vh - 60px); 
            box-sizing: border-box;
            padding-bottom: 200px; 
            position: relative; 
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

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 15px 0;
            justify-content: center;
        }
        .filter-options label {
            margin-right: 5px;
            font-size: 0.9em;
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

        .graphs-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .graph-section {
            flex: 1 1 45%;
            min-width: 300px;
        }

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
        width: 100vw !important;
    }
    .expanded-view > div,
    .expanded-view canvas {
        height: 100% !important;
        width: 100% !important;
    }

    .result-section {
        margin-bottom: 30px;
        border: 1px solid #ddd;
        padding: 5px;
        border-radius: 5px;
        height: fit-content; /* Ensure it grows with the content */
        position: relative; /* For expand button */
        background: white;
        height: 400px;
    }
    .table-scroll-container {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* Styles */
        #company-multi-search {
            width: 100%;
            padding: 5px;
        }
        #company-suggestions li:hover {
            background-color: #f0f0f0;
        }
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }
        .divider-major {
            border: 1px solid #ccc;
            margin-top: 20px;
        }
        .divider-minor {
            border: 1px solid #ccc;
            margin-top: 5px;
            margin-bottom: 20px;
        }
        /* Table Styles */
        .table-scroll-container {
            overflow-x: auto;
            max-height: 100%;
            overflow-y: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            max-height: 100%;
        }
        .table-header-row {
            background-color: #f2f2f2;
        }
        .table-th, .table-td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .table td {
            border: 1px solid #ddd;
            padding: 8px;
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
<!-- JavaScript session check for backup protection -->
<script src="../session-check.js"></script>
    <div class="content">
        <div class="top">
            <h1>Distributor Information</h1>
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
                <a href="General-Transactions.php">General Transactions</a>
                <button class="subtab" id="subtab3">Distributor Transactions</button>
                <div class="subtabdropdown" id="subddn3">
                    <a href="Shipment-Information.php">Shipment Information</a>
                    <a href="Distributor-Information.php">Distributor Information</a>
                </div>
            </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="main">
                <h2 class="text-center font-bold text-18pt">Search Distributors</h2>
            
            <p class="text-center">Select filters:</p>
            
            <div class="filter-options">
                <input type="checkbox" id="companyFilter" value="yes">
                <label for="companyFilter">Company</label>

                <input type="checkbox" id="dateFilter" value="yes">
                <label for="dateFilter">Date</label>
                
                <input type="checkbox" id="regionFilter" value="yes">
                <label for="regionFilter">Region</label>
            </div>
            
            <div id="dynamic-filters-container"></div>
            <div id="dynamic-dividers"></div>

            <button id="go-button-filters" class="go-button">Go</button>
            <button id="clear-filters" class="clear-button">Clear All Filters</button>
        </div>

        <!-- Results Section -->
        <div class="plot">
            <button id="filterToggle" class="toggle-btn" title="Toggle Filters">&#9664;</button>
            
            <div class="subheading">
                <p class="text-center font-bold text-18pt">Distributor Results</p>
            </div>
            
            <div id="results-container" class="hidden">
                <!-- Subset Selection -->
                <div class="flex justify-end mb-15">
                    <div class="flex items-center gap-10">
                        <label for="subsetSelect" class="font-bold">Select Subset:</label>
                        <select id="subsetSelect" class="padding-5">
                            <option value="all">All Distributors</option>
                            <option value="top_5">Top 5 by Delivery Rate</option>
                            <option value="top_10">Top 10 by Delivery Rate</option>
                            <option value="middle_10">Middle 10 by Delivery Rate</option>
                            <option value="bottom_10">Bottom 10 by Delivery Rate</option>
                        </select>
                    </div>
                </div>

                <!-- Graphs Grid -->
                <div class="graphs-grid">
                    <div class="result-section graph-section">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <canvas id="rateVsExposureChart"></canvas>
                    </div>
                    <div class="result-section graph-section">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <canvas id="rateTimeChart"></canvas>
                    </div>
                    <div class="result-section graph-section">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <canvas id="exposureTimeChart"></canvas>
                    </div>
                    <div class="result-section graph-section">
                        <button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>
                        <canvas id="avgRateChart"></canvas>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="result-section">
                    <button class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>
                    <h3>Detailed Distributor Transactions</h3>
                    <div class="table-scroll-container">
                        <table id="transactions-table" class="data-table">
                            <thead>
                                <tr class="table-header-row">
                                    <th class="table-th">Company Name</th>
                                    <th class="table-th">Delivery Rate</th>
                                    <th class="table-th">Disruption Exposure</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="placeholder-message" class="placeholder-message">
                <p>Select filters and click "Go" to view results.</p>
                <p>Page may take a few seconds to load</p>
                <p>If page fails to load, refresh browser and try again</p>
            </div>
        </div>
    </div>

    <!-- Sidebar Scripts -->
    <script src="../page-navigation"></script>
    <script src="distributor-information.js"></script>
</body>
</html>