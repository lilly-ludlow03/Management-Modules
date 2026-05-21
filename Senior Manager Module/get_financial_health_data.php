<?php
// Use output buffering to catch any unwanted output
ob_start();

// Disable display_errors to prevent HTML in JSON response, but log them
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
    // Try to connecto to mySQL
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    // Force UTF-8 so company names, accents, etc. do not get mangled
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Get parameters
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $year = isset($_GET['year']) ? $_GET['year'] : '';
    $quarter = isset($_GET['quarter']) ? $_GET['quarter'] : '';
    $company_type = isset($_GET['company_type']) ? $_GET['company_type'] : '';

    // Base Query
    $sql = "
    SELECT 
        c.CompanyName, 
        c.Type AS CompanyType, 
        AVG(fr.HealthScore) as health_score
    FROM Company c
    JOIN FinancialReport fr ON c.CompanyID = fr.CompanyID
    WHERE 1=1
    ";

    // Company Filter
    // Multi-select
    if ($companies !== '') {
        $companyList = explode('|', $companies);
        $safeList = array();
        foreach ($companyList as $comp) {
            $comp = trim($comp);
            if ($comp !== '') {
                $safeList[] = "'" . $conn->real_escape_string($comp) . "'";
            }
        }
        if (!empty($safeList)) {
             $sqlIn = implode(',', $safeList);
             $sql .= " AND c.CompanyName IN ($sqlIn)";
        }
    }

    // Year Filter
    if ($year !== '') {
        $y = intval($year);
        $sql .= " AND fr.RepYear = $y";
    }

    // Quarter Filter 
    if ($quarter !== '') {
        $q = $conn->real_escape_string($quarter);
        // Valid quarters are Q1, Q2, Q3, Q4
        if (in_array($q, ['Q1', 'Q2', 'Q3', 'Q4'])) {
             $sql .= " AND fr.Quarter = '$q'";
        }
    }

    // Company Type Filter
    if ($company_type !== '') {
        $ct = $conn->real_escape_string($company_type);
        $sql .= " AND c.Type = '$ct'";
    }

    // Grouping and Sorting
    $sql .= " GROUP BY c.CompanyID, c.CompanyName, c.Type";
    $sql .= " ORDER BY health_score DESC";

    $result = $conn->query($sql);

    if (!$result) {
         // If the query itself fails, we want to see why in the JSON error
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['health_score'] = round((float)$row['health_score'], 2);
        $data[] = $row;
    }

    ob_end_clean();
    echo json_encode($data);

} catch (Exception $e) {
    // Any error we threw above ends up here
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) {
    $conn->close();
}
?>
