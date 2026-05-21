<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Data Base Connection
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = new mysqli($servername, $username, $password, $database);

// If connection fails return error
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset("utf8");

// Build the Filters
$whereClauses = [];

// Company Filter
$company_role = isset($_GET['company_role']) ? $conn->real_escape_string($_GET['company_role']) : '';
$company_name = isset($_GET['company_name']) ? $conn->real_escape_string($_GET['company_name']) : '';

if (!empty($company_name)) {
    if ($company_role === 'Sending') {
        $whereClauses[] = "c1.CompanyName = '$company_name'";
    } elseif ($company_role === 'Receiving') {
        $whereClauses[] = "c2.CompanyName = '$company_name'";
    } else { // Both or unspecified
        $whereClauses[] = "(c1.CompanyName = '$company_name' OR c2.CompanyName = '$company_name')";
    }
}

// Date Filter
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

if (!empty($date_from) && !empty($date_to)) {
    $whereClauses[] = "s.ActualDate BETWEEN '$date_from' AND '$date_to'";
}

// Region Filter
$region_type = isset($_GET['region_type']) ? $conn->real_escape_string($_GET['region_type']) : '';
$region = isset($_GET['region']) ? $conn->real_escape_string($_GET['region']) : '';

if (!empty($region_type) && !empty($region)) {
    if ($region_type === 'Continent') {
        $whereClauses[] = "(l1.ContinentName = '$region' OR l2.ContinentName = '$region')";
    } elseif ($region_type === 'Country') {
        $whereClauses[] = "(l1.CountryName = '$region' OR l2.CountryName = '$region')";
    } elseif ($region_type === 'City') {
        $whereClauses[] = "(l1.City = '$region' OR l2.City = '$region')";
    }
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

// 1) Transactions Table Data
$sqlTable = "
    SELECT 
        c1.CompanyName as leaving_company,
        c2.CompanyName as going_to_company,
        s.Quantity as volume,
        CASE 
            WHEN s.ActualDate IS NULL THEN 'Pending'
            WHEN s.ActualDate < s.PromisedDate THEN 'Early'
            WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
            WHEN s.ActualDate > s.PromisedDate THEN 'Delayed'
            ELSE 'Unknown'
        END as status,
        p.ProductName as product
    FROM Shipping s
    JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
    JOIN Location l1 ON c1.LocationID = l1.LocationID
    JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
    JOIN Location l2 ON c2.LocationID = l2.LocationID
    JOIN Product p ON s.ProductID = p.ProductID
    $whereSQL
    ORDER BY s.ActualDate DESC
    LIMIT 200
";

$resTable = $conn->query($sqlTable);
$tableData = [];
if ($resTable) {
    while ($row = $resTable->fetch_assoc()) {
        $tableData[] = $row;
    }
}

// 2) Stacked Bar Chart
$sqlGraph1 = "
    SELECT 
        c1.CompanyName,
        p.ProductName,
        SUM(s.Quantity) as total_volume
    FROM Shipping s
    JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
    JOIN Location l1 ON c1.LocationID = l1.LocationID
    JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID -- Needed for region filter on destination
    JOIN Location l2 ON c2.LocationID = l2.LocationID
    JOIN Product p ON s.ProductID = p.ProductID
    $whereSQL
    GROUP BY c1.CompanyName, p.ProductName
";
$resGraph1 = $conn->query($sqlGraph1);
$graph1Data = [];
if ($resGraph1) {
    while ($row = $resGraph1->fetch_assoc()) {
        $graph1Data[] = $row;
    }
}

// 3) Stacked Bar Chart
$sqlGraph2 = "
    SELECT 
        c1.CompanyName,
        CASE 
            WHEN s.ActualDate IS NULL THEN 'Pending'
            WHEN s.ActualDate < s.PromisedDate THEN 'Early'
            WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
            WHEN s.ActualDate > s.PromisedDate THEN 'Delayed'
            ELSE 'Unknown'
        END as status,
        COUNT(*) as count
    FROM Shipping s
    JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
    JOIN Location l1 ON c1.LocationID = l1.LocationID
    JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
    JOIN Location l2 ON c2.LocationID = l2.LocationID
    $whereSQL
    GROUP BY c1.CompanyName, status
";
$resGraph2 = $conn->query($sqlGraph2);
$graph2Data = [];
if ($resGraph2) {
    while ($row = $resGraph2->fetch_assoc()) {
        $graph2Data[] = $row;
    }
}

// 4) Line Graph: Volume vs Time 
$sqlGraph3 = "
    SELECT 
        DATE_FORMAT(s.ActualDate, '%Y-%m') as date, -- Changed to Month
        CASE 
            WHEN s.ActualDate IS NULL THEN 'Pending'
            WHEN s.ActualDate < s.PromisedDate THEN 'Early'
            WHEN s.ActualDate = s.PromisedDate THEN 'On Time'
            WHEN s.ActualDate > s.PromisedDate THEN 'Delayed'
            ELSE 'Unknown'
        END as status,
        SUM(s.Quantity) as volume
    FROM Shipping s
    JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
    JOIN Location l1 ON c1.LocationID = l1.LocationID
    JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
    JOIN Location l2 ON c2.LocationID = l2.LocationID
    $whereSQL
    AND s.ActualDate IS NOT NULL
    GROUP BY DATE_FORMAT(s.ActualDate, '%Y-%m'), status
    ORDER BY date ASC
";
$resGraph3 = $conn->query($sqlGraph3);
$graph3Data = [];
if ($resGraph3) {
    while ($row = $resGraph3->fetch_assoc()) {
        $graph3Data[] = $row;
    }
}

// 5) Line Graph
$sqlGraph4 = "
    SELECT 
        DATE_FORMAT(s.ActualDate, '%Y-%m') as date, -- Changed to Month
        c1.CompanyName,
        SUM(s.Quantity) as volume
    FROM Shipping s
    JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
    JOIN Location l1 ON c1.LocationID = l1.LocationID
    JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
    JOIN Location l2 ON c2.LocationID = l2.LocationID
    $whereSQL
    AND s.ActualDate IS NOT NULL
    GROUP BY DATE_FORMAT(s.ActualDate, '%Y-%m'), c1.CompanyName
    ORDER BY date ASC
";
$resGraph4 = $conn->query($sqlGraph4);
$graph4Data = [];
if ($resGraph4) {
    while ($row = $resGraph4->fetch_assoc()) {
        $graph4Data[] = $row;
    }
}
// Bundle all data base
$response = [
    'table' => $tableData,
    'graph_vol_product' => $graph1Data,
    'graph_status_company' => $graph2Data,
    'graph_status_trend' => $graph3Data,
    'graph_company_trend' => $graph4Data
];

echo json_encode($response);
$conn->close();
?>
