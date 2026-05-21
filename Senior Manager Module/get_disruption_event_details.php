<?php
header('Content-Type: application/json');

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';

if ($search_term === '') {
    echo json_encode(["error" => "Please enter a search term"]);
    exit;
}

// --- Build Filter Clauses ---
$filterSql = "";

// Region Filter
if (isset($_GET['region_type']) && isset($_GET['region']) && $_GET['region'] !== '') {
    $rType = $_GET['region_type'];
    $rVal = $conn->real_escape_string($_GET['region']);
    
    if ($rType === 'Continent') {
        $filterSql .= " AND l.ContinentName = '$rVal'";
    } elseif ($rType === 'Country') {
        $filterSql .= " AND l.CountryName = '$rVal'";
    } elseif ($rType === 'City') {
        $filterSql .= " AND l.City = '$rVal'";
    }
}

// Date Filter
if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
    $dFrom = $conn->real_escape_string($_GET['date_from']);
    $filterSql .= " AND e.EventDate >= '$dFrom'";
}
if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
    $dTo = $conn->real_escape_string($_GET['date_to']);
    $filterSql .= " AND e.EventDate <= '$dTo'";
}

// Tier Filter
if (isset($_GET['tier']) && $_GET['tier'] !== '') {
    $tier = $conn->real_escape_string($_GET['tier']);
    $filterSql .= " AND c.TierLevel = '$tier'";
}

// Search Term Logic (Safe comparison)
// If input is numeric, match EventID OR CategoryName
// If input is text, match CategoryName
$escaped_term = $conn->real_escape_string($search_term);
if (is_numeric($search_term)) {
    $searchClause = "(e.EventID = '$escaped_term' OR dc.CategoryName LIKE '%$escaped_term%')";
} else {
    $searchClause = "(dc.CategoryName LIKE '%$escaped_term%')";
}


// 1. Get Details of Affected Companies
$sqlDetails = "
    SELECT 
        e.EventDate,
        e.EventID,
        dc.CategoryName,
        c.CompanyName, 
        c.TierLevel, 
        l.ContinentName,
        l.CountryName,
        ic.ImpactLevel,
        e.EventRecoveryDate
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE $searchClause
    $filterSql
    ORDER BY 
        e.EventDate DESC,
        CASE ic.ImpactLevel 
            WHEN 'High' THEN 1 
            WHEN 'Medium' THEN 2 
            WHEN 'Low' THEN 3 
            ELSE 4 
        END,
        c.CompanyName ASC
";

$resultDetails = $conn->query($sqlDetails);
$companies = [];
if ($resultDetails) {
    while ($row = $resultDetails->fetch_assoc()) {
        $companies[] = $row;
    }
}

// 2. Get Impact Counts for Bar Chart
$sqlCounts = "
    SELECT 
        ic.ImpactLevel, 
        COUNT(*) as count 
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE $searchClause
    $filterSql
    GROUP BY ic.ImpactLevel
";

$resultCounts = $conn->query($sqlCounts);
$impactCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
if ($resultCounts) {
    while ($row = $resultCounts->fetch_assoc()) {
        $impactCounts[$row['ImpactLevel']] = (int)$row['count'];
    }
}

// 3. Get Timeline Data for Line Chart
// Group by EventDate
$sqlTimeline = "
    SELECT 
        e.EventDate as date,
        COUNT(*) as count 
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE $searchClause
    $filterSql
    GROUP BY e.EventDate
    ORDER BY e.EventDate ASC
";

$resultTimeline = $conn->query($sqlTimeline);
$timelineData = [];
if ($resultTimeline) {
    while ($row = $resultTimeline->fetch_assoc()) {
        $timelineData[] = $row;
    }
}

$response = [
    "search_term" => $search_term,
    "total_affected" => count($companies),
    "impact_counts" => $impactCounts,
    "timeline_data" => $timelineData,
    "companies" => $companies
];

echo json_encode($response);
$conn->close();
?>