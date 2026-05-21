<?php
// Use output buffering to catch any unwanted output at the start
ob_start();

// We dont want raw PHP erros mixed with out JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {

    // Attempt to connect to the database
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Make sure all strings are handled at utf8
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Get parameters
    $region = isset($_GET['region']) ? $_GET['region'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $impact = isset($_GET['impact']) ? $_GET['impact'] : '';

    // Clean
    $region = $conn->real_escape_string($region);
    $region_type = $conn->real_escape_string($region_type);
    $date_from = $conn->real_escape_string($date_from);
    $date_to = $conn->real_escape_string($date_to);
    $impact = $conn->real_escape_string($impact);

    // We use the same filters for both the map and the table queries
    $whereClause = "WHERE 1=1";
    if ($date_from !== '') {
        $whereClause .= " AND e.EventDate >= '$date_from'";
    }

    // Data range filters 
    if ($date_to !== '') {
        $whereClause .= " AND e.EventDate <= '$date_to'";
    }

    // Region filters 
    if ($region_type && $region && strtolower($region) !== 'all') {
        if ($region_type === 'Continent') {
            $whereClause .= " AND l.ContinentName = '$region'";
        } elseif ($region_type === 'Country') {
            $whereClause .= " AND l.CountryName = '$region'";
        } elseif ($region_type === 'City') {
            $whereClause .= " AND l.City = '$region'";
        }
    }

    // Impact filters 
    if ($impact !== '' && strtolower($impact) !== 'any') {
        $whereClause .= " AND ic.ImpactLevel = '$impact'";
    }

    // Stacked bar chart 
    $sqlMap = "
    SELECT 
        l.ContinentName,
        l.CountryName,
        ic.ImpactLevel,
        COUNT(DISTINCT e.EventID) as disruption_count
    FROM DisruptionEvent e
    JOIN ImpactsCompany ic ON e.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    $whereClause
    GROUP BY l.ContinentName, l.CountryName, ic.ImpactLevel
    ORDER BY l.ContinentName, l.CountryName, ic.ImpactLevel
    ";

    $resultMap = $conn->query($sqlMap);
    if (!$resultMap) {
        throw new Exception("Map Query failed: " . $conn->error);
    }

    $mapData = array();
    while ($row = $resultMap->fetch_assoc()) {
        $row['disruption_count'] = (int)$row['disruption_count'];
        $mapData[] = $row;
    }

    // Table Data Query 
    $sqlTable = "
    SELECT 
        c.CompanyName,
        dc.CategoryName AS event_type,
        ic.ImpactLevel as impact,
        CONCAT(l.City, ', ', l.CountryName) as location,
        e.EventDate as date,
        e.EventRecoveryDate
    FROM DisruptionEvent e
    JOIN ImpactsCompany ic ON e.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    $whereClause
    ORDER BY e.EventDate DESC
    ";

    $resultTable = $conn->query($sqlTable);
    if (!$resultTable) {
        throw new Exception("Table Query failed: " . $conn->error);
    }

    $tableData = array();
    while ($row = $resultTable->fetch_assoc()) {
        $tableData[] = $row;
    }
     // Bundle evereything into a single JSON object
    $response = [
        "map_data" => $mapData,
        "table_data" => $tableData
    ];
    // Wipe any accidental stray output and send clean JSON
    ob_end_clean();
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) {
    $conn->close();
}
?>