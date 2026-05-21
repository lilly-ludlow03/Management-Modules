<?php
// Use the output buffering to catch any unwanted output

ob_start();

// Disable display_errors to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = array();

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    // Open the database connection
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Get parameters
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $granularity = isset($_GET['granularity']) ? $_GET['granularity'] : 'day';

    // Clean
    $date_from = $conn->real_escape_string($date_from);
    $date_to = $conn->real_escape_string($date_to);
    $granularity = $conn->real_escape_string($granularity);

    // Fetch Chart Data 
    switch ($granularity) {
        case 'year':
            $groupBy = "DATE_FORMAT(e.EventDate, '%Y')";
            break;
        case 'month':
            $groupBy = "DATE_FORMAT(e.EventDate, '%Y-%m')";
            break;
        case 'day':
        default:
            $groupBy = "DATE(e.EventDate)";
            break;
    }

    $sqlChart = "
    SELECT 
        $groupBy as event_date,
        COUNT(e.EventID) as frequency
    FROM DisruptionEvent e
    WHERE 1=1
    ";

    if ($date_from !== '') {
        $sqlChart .= " AND e.EventDate >= '$date_from'";
    }
    if ($date_to !== '') {
        $sqlChart .= " AND e.EventDate <= '$date_to'";
    }

    $sqlChart .= " GROUP BY $groupBy";
    $sqlChart .= " ORDER BY $groupBy ASC";

    $resultChart = $conn->query($sqlChart);
    if (!$resultChart) {
        throw new Exception("Chart Query failed: " . $conn->error);
    }

    $chartData = array();
    while ($row = $resultChart->fetch_assoc()) {
        $row['frequency'] = (int)$row['frequency'];
        $chartData[] = $row;
    }

    // Fetch Table Data 
    $sqlTable = "
    SELECT 
        c.CompanyName,
        dc.CategoryName AS event_type,
        e.EventDate as date,
        e.EventRecoveryDate,
        DATEDIFF(e.EventRecoveryDate, e.EventDate) as recovery_time
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN DisruptionEvent e ON ic.EventID = e.EventID
    JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
    WHERE 1=1
    ";

    if ($date_from !== '') {
        $sqlTable .= " AND e.EventDate >= '$date_from'";
    }
    if ($date_to !== '') {
        $sqlTable .= " AND e.EventDate <= '$date_to'";
    }

    $sqlTable .= " ORDER BY e.EventDate DESC"; // Most current at top

    $resultTable = $conn->query($sqlTable);
    if (!$resultTable) {
        throw new Exception("Table Query failed: " . $conn->error);
    }

    $tableData = array();
    while ($row = $resultTable->fetch_assoc()) {
        // Logic: If EventRecoveryDate is NULL, the event is active.
        // Use empty() to catch NULL or other falsy values just in case
        $row['is_active'] = empty($row['EventRecoveryDate']);
        
        $row['recovery_time'] = ($row['recovery_time'] !== null) ? $row['recovery_time'] . " days" : "N/A";
        $tableData[] = $row;
    }

    $response = [
        "chart_data" => $chartData,
        "table_data" => $tableData
    ];

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