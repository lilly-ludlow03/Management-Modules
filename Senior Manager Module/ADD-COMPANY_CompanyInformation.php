<?php
/**
 * ADD-COMPANY_CompanyInformation.php - Protected Page
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
 <!-- Link to external stylesheet for overall page styling -->
    <link rel="stylesheet" type="text/css" href="SMstyle3.css">
    <!-- Favicon for the borwser tab -->
    <link rel="icon" type="image/svg+xml" href="favicon(1).svg">
    <!-- Google fonts -->
<link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">

    <style>
        /* Styles for the "Add Company" button */
        #addCompanyBtn {
            background-color: #d4edda; /* Light green */
            color: #155724; /* Dark green text */
            border: 1px solid #155724; /* Matching border */
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        /* Darker background when hovering over the button */
        #addCompanyBtn:hover {
            background-color: #c3e6cb;
        }
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
        }
        
        /* New classes to replace inline styles */
        .hidden {
            display: none !important;
        }
        .confirm-buttons-container {
            margin-top: 20px;
        }
        .confirm-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-right: 20px;
            font-weight: bold;
            cursor: pointer;
        }
        .cancel-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
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
    <script src="SMscript.js"></script>

</head>

<title> Senior Manager Module </title>

<body>
<!-- JavaScript session check - backup protection -->
<script src="session-check.js"></script>

    <div class="content">
        <!-- Top bar with page title and logout -->
        <div class="top">
            <h1>Add Company</h1>
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
        <!-- Left sidebar navigation -->
        <div class="sidebar">
            <!-- Home Icon that takes you back to main Shell page -->
            <div class="home-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='SM Shell.php'">
                    <path
                        d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z">
                    </path>
                </svg>
            </div>
            <!-- Page search wrapper -->
            <div class="search-wrapper">
                <!-- Seach icon in the sidebar -->
                <div class="search-icon" id="page-search">
                    <svg width="60%" height="60%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                            stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <!-- Expanding search panel for page navigation -->
                <div class="search-page-con" id="page-search-bar">
                    <!-- Text input where user types page number -->
                    <input type="text" id="page-search-input" class="search-input" placeholder="Search page...">
                    <!--Dynamic dropdown-->
                    <ul id="pagesuggest"></ul>
                    <!-- Arrow button to go to the selected page -->
                    <div class="search-arrow-con" id="page-search-go">
                        <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="white" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>
             <!-- Sidebar Tab: Company Information -->
            <div class="tab-con">
                <!-- Button that toggles the company information dropdown -->
                <button class="tab" id="btn1" ">Company Information</button>
                <div class="dropdown" id="ddn1">
                    <a href="Most-Critical-Companies_CompanyInformation.php">Most Critical Companies</a>
                    <a href="Top-Distributors_CompanyInformation.php">Top Distributors</a>
                    <a href="Distributor-Delay_CompanyInformation.php">Distributor Delay</a>
                    <a href="ADD-COMPANY_CompanyInformation.php">ADD COMPANY</a>
                </div>
            <!-- Side Tab: Finances -->
            </div>
            <div class="tab-con">
                <button class="tab" id="btn2">Finances</button>
                <div class="dropdown" id="ddn2">
                    <a href="Average-Financial-Health_Finances.php">Average Financial Health</a>
                    <a href="Financials-By-Region_Finances.php">Financials By Region</a>
                </div>
            </div>
            <!-- Sidebar Tab: Disruption Events -->
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
        <!-- Main content area, Add company -->
        <div class="main">
        <!--The form itself will NOT submit normally we handled it via JS/fetch-->
            <form action="add_company.php" method="post">
                <label for="companyType">Company Type:</label>
                <select id="companyType" name="companyType">
                    <option value="" disabled selected>Select Type...</option>
                    <option value="manufacturer">Manufacturer</option>
                    <option value="distributor">Distributor</option>
                    <option value="retailer">Retailer</option>
                </select><br><br>
             <!--Factory Capacity-->
                <div id="factoryCapacityContainer" class="hidden">
                    <label for="factoryCapacity">Factory Capacity:</label>
                    <input type="number" id="factoryCapacity" name="factoryCapacity"><br><br>
                </div>
            <!--Company Name Input-->
                <label for="companyName">Company Name:</label>
                <input type="text" id="companyName" name="companyName"><br><br>

            <!-- Tier Level Dropdown -->
                <label for="tierLevel">Tier Level:</label>
                <select id="tierLevel" name="tierLevel">
                    <option value="1">Tier 1</option>
                    <option value="2">Tier 2</option>
                    <option value="3">Tier 3</option>
                </select><br><br>
            <!-- Radio buttons to decide if the user wants to add a new location or use an existing one -->
                <label>Add a new location?</label><br>
                <input type="radio" id="addLocationYes" name="addLocationChoice" value="yes">
                <label for="addLocationYes">Yes</label>
                <input type="radio" id="addLocationNo" name="addLocationChoice" value="no">
                <label for="addLocationNo">No</label><br><br>

                <!-- Fields for adding a completely new location -->
                <div id="newLocationFields" class="hidden">
                    <h4>Add New Location</h4>
                    <label for="continent">Continent:</label>
                    <!-- This will be populated dynamically -->
                    <select id="continent" name="continent"></select><br><br>
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country"><br><br>
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city"><br><br>
                </div>
            <!--Fields that select an exixting location already stored in DB -->
                <div id="existingLocationFields" class="hidden">
                    <h4>Select Existing Location</h4>

                    <!--Continent selection for existing locations-->
                    <label for="existingContinent">Continent:</label>
                    <select id="existingContinent" name="existingContinent"></select><br><br>

                    <!-- Country selection that depends on the chosen existing continent -->
                    <label for="existingCountry">Country:</label>
                    <select id="existingCountry" name="existingCountry"></select><br><br>

                    <!-- City selection that depends on the chosen existing country -->
                    <label for="existingCity">City:</label>
                    <select id="existingCity" name="existingCity"></select><br><br>
                </div>
                <!-- Button that triggers the confirmation modal -->
                <button type="button" id="addCompanyBtn">Add Company</button>
            </form>
        </div>
        <!-- This "plot" div is re-used -->

        <div class="plot">
        </div>
    </div>

    <script src="page-navigation.js"></script>
    <script src="add-company_companyinformation.js"></script>

</body>

</html>

