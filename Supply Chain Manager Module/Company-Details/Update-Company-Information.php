<?php
/**
 * Update-Company-Information.php - Protected Page
 * 
 * Requires: supply_chain_manager or admin role
 */

// MUST be at the very top - no whitespace before <?php
// Send no-cache headers IMMEDIATELY before any output
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../../includes/auth.php';

// Double-check: If not logged in, redirect immediately
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
<!--Prevents browser from caching to ensure it is always using fresh data -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

    <!--Reference to the CSS file for general website styling-->
    <link href="../SC-Manager-Style-V2.css" rel="stylesheet" type="text/css">
    <!--Favicon for the browser tab at the top of the browser-->
    <link rel="icon" type="image/svg+xml" href="../favicon(3).svg">
    <!--Font family used across style elements in the page-->
    <link href="https://fonts.googleapis.com/css?family=Montserrat&family=Open Sans" rel="stylesheet">
    
    <style>
        /* wrapper for search inputs that include dynamic dropdowns */
        .input-wrapper {
            position: relative;
        }

        /* style for dropdown suggestion list in autocomplete/dynamic dropdown fields */
        .suggest-list {
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

        /* individual suggestion item within suggest-list */
        .suggest-list li {
            padding: 8px;
            cursor: pointer;
        }

        /* style to appear when hovering over a suggestion item in the dropdown */
        .suggest-list li:hover {
            background-color: #f0f0f0;
            color: black;
        }

        /* styling for confirm button that appears with confirmation pop-up on submit */
        .confirm-btn {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #155724;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* styling for cancel button that appears with confrimation message */
        .cancel-btn {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #721c24;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }

        /* plot container alignment */
        #pltcon {
            text-align: center;
        }

        /* Styling for multi-select search feature */
        .multi-select-container {
            position: relative;
            width: 100%;
        }

        /* Container for selected options as part of multi-select search feature */
        .selected-options {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 5px;
            border: 1px solid #ccc;
            padding: 5px;
            border-radius: 4px;
            min-height: 34px;
        }

        /* Individual selection tag from multi-select search feature */
        .option-tag {
            background-color: #e0e0e0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
        }

        /* tag removal button (small x icon) */
        .option-tag .remove-btn {
            border: none;
            background-color: transparent;
            color: #888;
            cursor: pointer;
            margin-left: 4px;
            font-weight: bold;
        }

        /* imput box for filtering multi-select */
        .multi-select-container input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        /* dropdown that populates with multi-select suggestions */
        .suggestions-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid #ccc;
            border-top: none;
            position: absolute;
            width: 100%;
            background-color: white;
            z-index: 1001;
            max-height: 150px;
            overflow-y: auto;
        }

        /* items inside multi-select suggestions */
        .suggestions-list li {
            padding: 8px;
            cursor: pointer;
        }

        /* altered styling for hovering over item in multi-select */
        .suggestions-list li:hover {
            background-color: #f0f0f0;
        }

        /* layout container for page content */
        .page-content {
            display: flex;
            flex-direction: row;
            width: 100vw;
        }

        /* displays the user's name and role */
        .user-info {
            position: absolute;
            top: 10px;
            right: 120px;
            color: white;
            font-size: 0.85em;
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

        /* Utility classes for relocating inline styles */
        .hidden { display: none; }
        .block { display: block; }
        .mt-10 { margin-top: 10px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-5 { margin-bottom: 5px; }
        .mt-15 { margin-top: 15px; }
        .mt-20 { margin-top: 20px; }
        .text-small { font-size: small; }
        .text-color-555 { color: #555; }
        .text-color-black { color: black; }
        .overflow-x-hidden { overflow-x: hidden; }

    </style>
</head>

<body>
<!-- JavaScript session check; redirecting user to login page if not logged in -->
<script src="../session-check.js">
</script>
    <div class="content">
        <div class="top">
            <!-- Page title -->
            <h1>Update Company Information</h1>
            <!-- user identification -->
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

        <!-- tab navigation menu at top of pagecontaining links to all other pages -->
        <div class="sidebar">

            <!-- home icon that returns user to dashboard when clicked -->
            <div class="home-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" onclick="location.href='../SC Manager Shell.php'">
                <path d="M 16 2.59375 L 15.28125 3.28125 L 2.28125 16.28125 L 3.71875 17.71875 L 5 16.4375 L 5 28 L 14 28 L 14 18 L 18 18 L 18 28 L 27 28 L 27 16.4375 L 28.28125 17.71875 L 29.71875 16.28125 L 16.71875 3.28125 Z M 16 5.4375 L 25 14.4375 L 25 26 L 20 26 L 20 16 L 12 16 L 12 26 L 7 26 L 7 14.4375 Z"></path>
            </svg>
            </div>

            <!-- search bar to navigate to other pages -->
            <div class="search-wrapper">
                <!-- search icon -->
                <div class="search-icon" id="page-search">
                    <svg width="60%" height="60%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <!-- search dropdown -->
                <div class="search-page-con" id="page-search-bar">
                    <input type="text" id="page-search-input" class="search-input"
                        placeholder="Search page...">
                    <ul id="pagesuggest"></ul>
                    <!-- button to initiate search -->
                    <div class="search-arrow-con" id="page-search-go">
                        <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path  d="M5 12H19M19 12L12 5M19 12L12 19" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Top of page tab: Company Details -->
            <div class="tab-con">
            <button class="tab" id="btn1">Company Details</button>
            <div class="dropdown" id="ddn1">
                <a href="Company-Information.php">Company Information</a>
                <a href="Key-Performance-Indicators.php">Key Performance Indicators</a>
                <a href="Update-Company-Information.php">Update Company Information</a>
            </div>

            <!-- Top of page tab: Disruption Events -->
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

            <!-- Top of page tab: Transaction Information -->
            </div>
            <div class="tab-con">
            <button class="tab" id="btn3">Transaction Information</button>
            <div class="dropdown" id="ddn3">
                <a href="../Transaction-Information/General-Transactions.php">General Transactions</a>

                <!-- nested dropdown tabs for distributor transactions -->
                <button class="subtab" id="subtab3">Distributor Transactions</button>
                <div class="subtabdropdown" id="subddn3">
                    <a href="../Transaction-Information/Shipment-Information.php">Shipment Information</a>
                    <a href="../Transaction-Information/Distributor-Information.php">Distributor Information</a>
                </div>
            </div>
            </div>
        </div>

        <!-- main page content container -->
        <div class="page-content overflow-x-hidden">
        <div class="main">
            <h2>Search</h2>

            <!-- dropdown for user to select what they want to update/change -->
            <div class="update_con">
            <label>What would you like to update?</label>
            <select name="update" id="update" onchange="if(this.value) this.style.color='black'">
                <option value="" disabled selected hidden>Click on an option to update</option>

                <!-- Company Information options -->
                <optgroup label="Company Information" class="text-color-black">
                    <option value="Type">Company Type</option>
                    <option value="Tier">Company Tier</option>
                </optgroup>
                <!-- Distributor options -->
                <optgroup label="Distributors" class="text-color-black">
                    <option value="addroute">Add Route</option>
                    <option value="removeroute">Remove Route</option>
                </optgroup>
                <!-- Manufacturer options -->
                <optgroup label="Manufacturers" class="text-color-black">
                    <option value="capacity">Capacity</option>
                    <option value="addproduct">Add Product</option>
                    <option value="removeproduct">Remove Product</option>
                    <option value="updateprice">Update Price</option>
                </optgroup>
                <!-- Dependency options -->
                <optgroup label="Dependencies" class="text-color-black">
                    <option value="dependants">Add Dependency</option>
                    <option value="depends">Remove Dependency</option>
                </optgroup>
                <!-- Transaction Information options -->
                <optgroup label="Transaction Information" class="text-color-black">
                    <option value="existing">Existing Transaction</option>
                    <option value="new">New Transaction</option>
                </optgroup>
            </select>
            </div>

            <!-- company search bar that appears when the user selects an option from the Company Information group -->
            <div id="company-search-container" class="hidden">
                <div class="search-bar">
                    <label for="companySearch">Search Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="companySearch" name="companySearch"
                            placeholder="Enter and Click On Company Name">
                        <ul id="suggest" class="suggest-list"></ul>
                    </div>
                </div>
            </div>

            <!-- pop-up container for forms that appear depending on selected update option -->
            <div class="pop_con" id="pop_con">
                <br>

                <!-- update company type form with an input section for capacity that remains hidden unless the company type selected is manufacturer -->
                <form id="uptype">
                    <label>Company Type: <select name="type"></select></label>
                    <div id="type-capacity-container" class="hidden mt-10">
                        <label>Capacity: <input type="number" id="type-capacity-input" step="0.01" name="capacity" placeholder="Enter factory capacity"></label>
                    </div>
                    <button type="submit">Submit</button>
                </form>

                <!-- update company tier form -->
                <form id="uptier">
                    <label>Company Tier: <select name="tier"></select></label>
                    <button type="submit">Submit</button>
                </form>

                <!-- add route form -->
                <form id="addroute" class="hidden">
                    <label for="addroute-distributor">Distributor:</label>
                    <div class="input-wrapper">
                        <input type="text" id="addroute-distributor" name="addroute-distributor" placeholder="Enter Distributor Name">
                        <ul id="addroute-distributor-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="addroute-from" class="mt-10 block">From Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="addroute-from" name="addroute-from" placeholder="Enter From Company">
                        <ul id="addroute-from-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="addroute-to" class="mt-10 block">To Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="addroute-to" name="addroute-to" placeholder="Enter To Company">
                        <ul id="addroute-to-suggest" class="suggest-list"></ul>
                    </div>
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- remove route form -->
                <form id="removeroute" class="hidden">
                    <label for="removeroute-distributor">Distributor:</label>
                    <div class="input-wrapper">
                        <input type="text" id="removeroute-distributor" name="removeroute-distributor" placeholder="Enter Distributor Name">
                        <ul id="removeroute-distributor-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="removeroute-from" class="mt-10 block">From Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="removeroute-from" name="removeroute-from" placeholder="Enter From Company">
                        <ul id="removeroute-from-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="removeroute-to" class="mt-10 block">To Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="removeroute-to" name="removeroute-to" placeholder="Enter To Company">
                        <ul id="removeroute-to-suggest" class="suggest-list"></ul>
                    </div>
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- add product form -->
                <form id="addproduct" class="hidden">
                    <label for="addproduct-manufacturer">Manufacturer:</label>
                    <div class="input-wrapper">
                        <input type="text" id="addproduct-manufacturer" name="addproduct-manufacturer" placeholder="Enter Manufacturer Name">
                        <ul id="addproduct-manufacturer-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="add-product-search" class="mt-10 block">Product:</label>
                    <div class="input-wrapper">
                        <input type="text" id="add-product-search" placeholder="Search for a product...">
                        <ul id="add-product-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="add-product-price" class="mt-10 block">Price:</label>
                    <input type="number" id="add-product-price" name="price" step="0.01" placeholder="Enter price">
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- remove product form -->
                <form id="removeproduct" class="hidden">
                    <label for="removeproduct-manufacturer">Manufacturer:</label>
                    <div class="input-wrapper">
                        <input type="text" id="removeproduct-manufacturer" name="removeproduct-manufacturer" placeholder="Enter Manufacturer Name">
                        <ul id="removeproduct-manufacturer-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="remove-product-search" class="mt-10 block">Product:</label>
                    <div class="input-wrapper">
                        <input type="text" id="remove-product-search" placeholder="Search for a product...">
                        <ul id="remove-product-suggest" class="suggest-list"></ul>
                    </div>
                     <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- update product price form -->
                <form id="updateprice" class="hidden">
                    <label for="updateprice-manufacturer">Manufacturer:</label>
                    <div class="input-wrapper">
                        <input type="text" id="updateprice-manufacturer" name="updateprice-manufacturer" placeholder="Enter Manufacturer Name">
                        <ul id="updateprice-manufacturer-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="updateprice-product" class="mt-10 block">Product:</label>
                    <div class="input-wrapper">
                        <input type="text" id="updateprice-product" name="updateprice-product" placeholder="Search for a product...">
                        <ul id="updateprice-product-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="updateprice-price" class="mt-10 block">New Price:</label>
                    <input type="number" id="updateprice-price" name="price" step="0.01" placeholder="Enter new price">
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- add dependency form -->
                <form id="updep">
                    <label for="updep-upstream">Upstream Company:<br></label>
                    <div class="input-wrapper">
                        <input type="text" id="updep-upstream" name="updep-upstream" placeholder="Upstream Company (Supplier)">
                        <ul id="updep-upstream-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="updep-downstream" class="mt-10 block">Downstream Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="updep-downstream" name="updep-downstream" placeholder="Downstream Company (Dependent)">
                        <ul id="updep-downstream-suggest" class="suggest-list"></ul>
                    </div>
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- remove dependency form -->
                <form id="updepon">
                    <label for="updepon-upstream">Upstream Company:<br></label>
                    <div class="input-wrapper">
                        <input type="text" id="updepon-upstream" name="updepon-upstream" placeholder="Upstream Company (Supplier)">
                        <ul id="updepon-upstream-suggest" class="suggest-list"></ul>
                    </div>
                    <label for="updepon-downstream" class="mt-10 block">Downstream Company:</label>
                    <div class="input-wrapper">
                        <input type="text" id="updepon-downstream" name="updepon-downstream" placeholder="Downstream Company (Dependent)">
                        <ul id="updepon-downstream-suggest" class="suggest-list"></ul>
                    </div>
                    <div class="mt-10">
                        <button type="submit">Submit</button>
                    </div>
                </form>

                <!-- update capacity form -->
                <form id="upcap">
                    <label>Capacity: <input type="number" step="0.01" name="name"></label>
                    <button type="submit">Submit</button>
                </form>

                <!-- transaction creation and update forms -->
                <form id="uptrans">
                    <!-- transaction type dropdown -->
                    <label for="trans_type">Choose a type of transaction:</label>
                    <select id="trans_type" name="trans_type" onchange="if(this.value) this.style.color='black'">
                        <option value="" disabled selected hidden>Click on a transaction type</option>
                        <option value="ship" class="text-color-black">Shipping</option>
                        <option value="rec" class="text-color-black">Receiving</option>
                        <option value="adj" class="text-color-black">Adjustment</option>
                    </select>
                    <!-- shipping company input, populates with manufacturers and retailers -->
                    <div id="shipping-company-container" class="hidden mt-10">
                        <label for="ship-company-search">Shipping Company:</label>
                        <div class="input-wrapper">
                            <input type="text" id="ship-company-search" name="ship-company-search" placeholder="Enter Shipping Company">
                            <ul id="ship-company-suggest" class="suggest-list"></ul>
                        </div>
                    </div>
                    <!-- distributor input, only populates with distributors -->
                    <div id="distributor-company-container" class="hidden mt-10">
                        <label for="dist-company-search">Distributor:</label>
                        <div class="input-wrapper">
                            <input type="text" id="dist-company-search" name="dist-company-search" placeholder="Enter Distributor Company">
                            <ul id="dist-company-suggest" class="suggest-list"></ul>
                        </div>
                    </div>
                    <!-- receiving company input, only populates with manufacturers and retailers -->
                    <div id="receiving-company-container" class="hidden mt-10">
                        <label for="rec-company-search">Receiving Company:</label>
                        <div class="input-wrapper">
                            <input type="text" id="rec-company-search" name="rec-company-search" placeholder="Enter Receiving Company">
                            <ul id="rec-company-suggest" class="suggest-list"></ul>
                        </div>
                    </div>
                    <!-- adjustment company input, general company dropdown with full company list -->
                    <div id="adjustment-company-container" class="hidden mt-10">
                        <label for="adj-company-search">Company Name:</label>
                        <div class="input-wrapper">
                            <input type="text" id="adj-company-search" name="adj-company-search" placeholder="Enter Company Name">
                            <ul id="adj-company-suggest" class="suggest-list"></ul>
                        </div>
                    </div>
                    <!-- transaction id input, designed to accept TransactionID, not ShipmentID -->
                    <div id="transaction-id-container" class="hidden mt-10">
                        <p class="text-small mb-5 text-color-555">Transaction ID can be looked up under General Transactions</p>
                        <label for="trans-id-input">Transaction ID of Shipment:</label>
                        <input type="number" id="trans-id-input" name="trans-id-input" placeholder="Enter Transaction ID of Shipment">
                    </div>
                    <!-- multi-select field for selecting what attributes of a shipment transaction to update -->
                    <div id="existing-shipping-update-container" class="hidden mt-10">
                        <label for="shipping-update-search">What would you like to update?</label>
                        <div class="multi-select-container">
                            <div class="selected-options" id="shipping-update-selected"></div>
                            <input type="text" id="shipping-update-search" placeholder="Select options...">
                            <ul class="suggestions-list" id="shipping-update-suggestions"></ul>
                        </div>
                    </div>
                    <!-- multi-select field for selecting what attributes of a receiving transaction to update -->
                    <div id="existing-receiving-update-container" class="hidden mt-10">
                        <label for="receiving-update-search">What would you like to update?</label>
                        <div class="multi-select-container">
                            <div class="selected-options" id="receiving-update-selected"></div>
                            <input type="text" id="receiving-update-search" placeholder="Select options...">
                            <ul class="suggestions-list" id="receiving-update-suggestions"></ul>
                        </div>
                    </div>

                    <!-- Container for dynamically shown update fields (multi-select options that populate as you select them from the dropdown) -->
                    <div id="existing-transaction-update-fields" class="mt-15">
                        
                        <!-- Shipping Fields -->
                        <!-- new distributor input, only populates with distributors -->
                        <div id="update-distributor-container" class="hidden mb-10">
                            <label for="update-distributor-search">New Distributor:</label>
                            <div class="input-wrapper">
                                <input type="text" id="update-distributor-search" placeholder="Enter new distributor...">
                                <ul id="update-distributor-suggest" class="suggest-list"></ul>
                            </div>
                        </div>
                        <!-- new shipping company input -->
                        <div id="update-shipping-company-container" class="hidden mb-10">
                            <label for="update-shipping-company-search">New Shipping Company:</label>
                            <div class="input-wrapper">
                                <input type="text" id="update-shipping-company-search" placeholder="Enter new shipping company...">
                                <ul id="update-shipping-company-suggest" class="suggest-list"></ul>
                            </div>
                        </div>
                        <!-- update promised delivery date input -->
                         <div id="update-promised-date-container" class="hidden mb-10">
                            <label for="update-promised-date">New Promised Date:</label>
                            <input type="date" id="update-promised-date">
                        </div>
                        <!-- actual delivery date input -->
                        <div id="update-actual-date-container" class="hidden mb-10">
                            <label for="update-actual-date">New Actual Delivery Date:</label>
                            <input type="date" id="update-actual-date">
                        </div>

                        <!-- Receiving Fields -->
                        <!-- update date received -->
                         <div id="update-date-received-container" class="hidden mb-10">
                            <label for="update-date-received">New Date Received:</label>
                            <input type="date" id="update-date-received">
                        </div>

                        <!-- Common Fields -->
                        <!-- update receiving company -->
                        <div id="update-receiving-company-container" class="hidden mb-10">
                            <label for="update-receiving-company-search">New Receiving Company:</label>
                            <div class="input-wrapper">
                                <input type="text" id="update-receiving-company-search" placeholder="Enter new receiving company...">
                                <ul id="update-receiving-company-suggest" class="suggest-list"></ul>
                            </div>
                        </div>
                        <!-- input to update product type involved in transaction -->
                        <div id="update-product-container" class="hidden mb-10">
                            <label for="update-product-search">New Product:</label>
                            <div class="input-wrapper">
                                <input type="text" id="update-product-search" placeholder="Enter new product...">
                                <ul id="update-product-suggest" class="suggest-list"></ul>
                            </div>
                        </div>
                        <!-- input to update quantity of transaction -->
                        <div id="update-quantity-container" class="hidden mb-10">
                            <label for="update-quantity">New Quantity:</label>
                            <input type="number" id="update-quantity" step="0.01" placeholder="Enter new quantity">
                        </div>
                    </div>

                    <br>
                    <br>

                    <!-- date input for new transaction creation -->
                    <label id="trans-date-label">Date: <input type="date" id="trans-date" name="name"></label>
                    <div id="actual-date-container" class="hidden">
                        <label for="trans-actual-date">Actual Date: <input type="date" id="trans-actual-date" name="actual-date"></label>
                    </div>
                    <!-- product input for new transaction creation -->
                    <label for="product-search">Product:</label>
                    <div class="input-wrapper">
                        <input type="text" id="product-search" name="product-search" placeholder="Enter Product Name">
                        <ul id="product-suggest" class="suggest-list"></ul>
                    </div>
                    <!-- quantity input for new transaction creation -->
                    <label>Quantity: <input type="number" id="trans-quantity" name="name"></label>
                    <div id="reason-container" class="hidden mt-10">
                        <label for="trans-reason">Reason:</label>
                        <input type="text" id="trans-reason" name="trans-reason" placeholder="Enter reason for adjustment">
                    </div>
                    <button type="submit">Submit</button>
                </form>
            </div>
        </div>
        <div class="plot">
            <div class="plot_content" id="pltcon">
                <!-- header to display current company information whene relevant -->
                <h2 id="current-company-info-header">Current Company Information</h2>
                <div class="sup_comp" id="info">
                    <!-- Content will be dynamically injected here -->
                </div>
            </div>
        </div>
        </div>
    </div>
    <script src="../page-navigation.js"></script>
    <script src="update-company-information.js"></script>
</body>

</html>
