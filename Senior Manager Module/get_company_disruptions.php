<?php

// This endpoint returns disruption data for one or more companies
header('Content-Type: application/json');

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

// Opens a connection to MySQL using mysqli
// If this fails nothing else will run
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    // Returns generic error 
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}
 // Reads and validates the `companies` parameter
$companies = isset($_GET['companies']) ? $_GET['companies'] : '';

if ($companies === '') {
    // The frontend should never call this without companies
    echo json_encode(["error" => "Please select at least one company"]);
    exit;
}

// Handle multiple companies separated by pipe '|'
$companyList = explode('|', $companies);
$safeList = [];
foreach ($companyList as $comp) {
    $comp = trim($comp);
    if ($comp !== '') {
        $safeList[] = "'" . $conn->real_escape_string($comp) . "'";
    }
}

if (empty($safeList)) {
    // If we end up here the user passed something weird
    echo json_encode(["error" => "Invalid company selection"]);
    exit;
}

$companyInClause = implode(',', $safeList);

// Optional Disruption Type Filter
$typeFilter = "";
if (isset($_GET['disruption_type']) && $_GET['disruption_type'] !== '') {
    $dType = $conn->real_escape_string($_GET['disruption_type']);
    $typeFilter = " AND dc.CategoryName = '$dType'";
}

// 1. Get Table Data: Event, Impact, Date
// Join Company, ImpactsCompany, DisruptionEvent, DisruptionCategory
$sqlTable = "
    SELECT 
        c.CompanyName,
        dc.CategoryName AS event,
        ic.ImpactLevel AS impact,
        e.EventDate AS date,
        e.EventRecoveryDate
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE c.CompanyName IN ($companyInClause)
    $typeFilter
    ORDER BY e.EventDate DESC
";

$resultTable = $conn->query($sqlTable);
$tableData = [];
if ($resultTable) {
    while ($row = $resultTable->fetch_assoc()) {
        $tableData[] = $row;
    }
}

// 2. Get Line Chart Data: Number of disruption events over time
// Group by Month (YYYY-MM) for cleaner line chart
$sqlLine = "
    SELECT 
        DATE_FORMAT(e.EventDate, '%Y-%m') as period,
        COUNT(*) as count
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE c.CompanyName IN ($companyInClause)
    $typeFilter
    GROUP BY period
    ORDER BY period ASC
";

$resultLine = $conn->query($sqlLine);
$lineChartData = [];
if ($resultLine) {
    while ($row = $resultLine->fetch_assoc()) {
        $lineChartData[] = $row;
    }
}

// 3. Get Bar Chart Data: Occurrences of each type of disruption event
$sqlBar = "
    SELECT 
        dc.CategoryName,
        COUNT(*) as count
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE c.CompanyName IN ($companyInClause)
    $typeFilter
    GROUP BY dc.CategoryName
    ORDER BY count DESC
";

$resultBar = $conn->query($sqlBar);
$barChartData = [];
if ($resultBar) {
    while ($row = $resultBar->fetch_assoc()) {
        $barChartData[] = $row;
    }
}

// The frontend expects one JSON payload

$response = [
    "total_disruptions" => count($tableData),
    "table_data" => $tableData,
    "line_chart_data" => $lineChartData,
    "bar_chart_data" => $barChartData
];

echo json_encode($response);
$conn->close();
?>
