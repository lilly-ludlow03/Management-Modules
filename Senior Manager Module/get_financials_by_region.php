<?php
// Use the output buffering to catch any unwanted of the output
ob_start();

// Disable the display_errors to prevent HTML in JSON response
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
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Read filters from the request
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $year = isset($_GET['year']) ? $_GET['year'] : '';
    $quarter = isset($_GET['quarter']) ? $_GET['quarter'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $region = isset($_GET['region']) ? $_GET['region'] : '';

    // Build WHERE 
    $whereClause = "WHERE 1=1";
    
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

    // Date Filter 
    if ($year !== '') {
        $y = intval($year);
        $whereClause .= " AND fr.RepYear = $y";
    }
    if ($quarter !== '') {
        $q = $conn->real_escape_string($quarter);
        $whereClause .= " AND fr.Quarter = '$q'";
    }

    //  Table Data of the companies
    $sqlTable = "
    SELECT 
        c.CompanyName,
        AVG(fr.HealthScore) as health_score
    FROM Company c
    JOIN FinancialReport fr ON c.CompanyID = fr.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    $whereClause
    GROUP BY c.CompanyID, c.CompanyName
    ORDER BY health_score DESC
    ";

    $resultTable = $conn->query($sqlTable);
    if (!$resultTable) throw new Exception("Table query failed: " . $conn->error);

    $tableData = [];
    while ($row = $resultTable->fetch_assoc()) {
        $row['health_score'] = number_format((float)$row['health_score'], 2, '.', '');
        $tableData[] = $row;
    }

    // Graph Data by location
    // Determine grouping based on region filter
    
    
    $groupBy = "l.CountryName";
    $selectLoc = "l.CountryName as location";
    
    if ($region_type === 'Country' && $region) {
        $groupBy = "l.City";
        $selectLoc = "l.City as location";
    } elseif ($region_type === 'City' && $region) {
         // For a specific city filter, we still keep grouping by city
        $groupBy = "l.City";
        $selectLoc = "l.City as location";
    }

    $sqlGraph = "
    SELECT 
        $selectLoc,
        AVG(fr.HealthScore) as avg_health
    FROM Company c
    JOIN FinancialReport fr ON c.CompanyID = fr.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    $whereClause
    GROUP BY $groupBy
    ORDER BY avg_health DESC
    LIMIT 20
    ";

    $resultGraph = $conn->query($sqlGraph);
    if (!$resultGraph) throw new Exception("Graph query failed: " . $conn->error);

    $graphData = [];
    while ($row = $resultGraph->fetch_assoc()) {
        $row['avg_health'] = number_format((float)$row['avg_health'], 2, '.', '');
        $graphData[] = $row;
    }
  // If we reached this point, everything worked.
    ob_end_clean();
    echo json_encode(['table' => $tableData, 'graph' => $graphData]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) {
    $conn->close();
}
?>
