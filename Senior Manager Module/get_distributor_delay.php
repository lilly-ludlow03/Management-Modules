<?php
// Use the output buffering to catch any unwanted output
ob_start();

// Disable display_errors to prevent HTML in JSON response
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
     // Open a connection to MySQL
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Read filters from query string 
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $region = isset($_GET['region']) ? $_GET['region'] : '';

    // Build WHERE Clause
    // Base filter only Distributors
    $whereClause = "WHERE c.Type = 'Distributor'";
    
    // Company Filter
    if ($companies !== '') {
        $companyList = explode('|', $companies);
        $safeList = [];
        foreach ($companyList as $comp) {
            $comp = trim($comp);
            if ($comp !== '') {
                $safeList[] = "'" . $conn->real_escape_string($comp) . "'";
            }
        }
        if (!empty($safeList)) {
            $companyIn = implode(',', $safeList);
            $whereClause .= " AND c.CompanyName IN ($companyIn)";
        }
    }

    // Region Filter
    if ($region_type && $region && strtolower($region) !== 'all') {
        $rVal = $conn->real_escape_string($region);
        if ($region_type === 'Continent') {
            $whereClause .= " AND l.ContinentName = '$rVal'";
        } elseif ($region_type === 'Country') {
            $whereClause .= " AND l.CountryName = '$rVal'";
        } elseif ($region_type === 'City') {
            $whereClause .= " AND l.City = '$rVal'";
        }
    }

    // Date Filter for shipping transactions 
    // We only consider transactions that have both dates to calculate delay
    $dateWhere = " AND s.ActualDate IS NOT NULL AND s.PromisedDate IS NOT NULL";
    
    if (!empty($start_date)) {
        $safe_start_date = $conn->real_escape_string($start_date);
        $dateWhere .= " AND s.ActualDate >= '$safe_start_date'";
    }
    if (!empty($end_date)) {
        $safe_end_date = $conn->real_escape_string($end_date);
        $dateWhere .= " AND s.ActualDate <= '$safe_end_date'";
    }

    // Main Query
    // Calculate Average Delay formula: VG(ActualDate - PromisedDate)
    // So DATEDIFF is the actual, promised dates gives positive if actual is later (delayed)
    $sql = "
    SELECT 
        c.CompanyName,
        AVG(DATEDIFF(s.ActualDate, s.PromisedDate)) as avg_delay
    FROM Company c
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN Shipping s ON c.CompanyID = s.DistributorID
    $whereClause $dateWhere
    GROUP BY c.CompanyID, c.CompanyName
    ORDER BY avg_delay DESC, c.CompanyName ASC
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        // Format to 2 decimal places
        $row['avg_delay'] = number_format((float)$row['avg_delay'], 2, '.', '');
        $data[] = $row;
    }
    // Clear any stray output and send clean JSON.
    ob_end_clean();
    echo json_encode($data);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) {
    $conn->close();
}
?>
