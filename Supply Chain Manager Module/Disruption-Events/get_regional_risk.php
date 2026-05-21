<?php
// Use output buffering to catch any unwanted output in the code
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
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Get parameters
    $region = isset($_GET['region']) ? $_GET['region'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $tier = isset($_GET['tier']) ? $_GET['tier'] : 'All';
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Clean
    $region = $conn->real_escape_string($region);
    $region_type = $conn->real_escape_string($region_type);
    $tier = $conn->real_escape_string($tier);
    $date_from = $conn->real_escape_string($date_from);
    $date_to = $conn->real_escape_string($date_to);

    // 1) Calculate Global Total Disruptions 
    // Counts all unique events in the data
    $globalSql = "SELECT COUNT(DISTINCT EventID) as total FROM DisruptionEvent";
    $globalConds = array();
    if ($date_from !== '') $globalConds[] = "EventDate >= '$date_from'";
    if ($date_to !== '') $globalConds[] = "EventDate <= '$date_to'";
    
    if (!empty($globalConds)) {
        $globalSql .= " WHERE " . implode(' AND ', $globalConds);
    }
    
    $globalRes = $conn->query($globalSql);
    if (!$globalRes) throw new Exception("Global count query failed: " . $conn->error);
    $globalRow = $globalRes->fetch_assoc();
    $N_total = (int)$globalRow['total'];
    if ($N_total === 0) $N_total = 1; // Prevent division by zero

    // 2) Build Main Query for Companies
    // RRC = N_region / N_total
    
    // Determine the scope of risk calculations
    $risk_scope_field = "ContinentName"; 
    if ($region_type === 'Continent' && $region && strtolower($region) !== 'all') {
        $risk_scope_field = "CountryName";
    } elseif ($region_type === 'Country' && $region && strtolower($region) !== 'all') {
        $risk_scope_field = "City";
    }

    $dateSubQuery = "";
    if ($date_from !== '') $dateSubQuery .= " AND e2.EventDate >= '$date_from'";
    if ($date_to !== '') $dateSubQuery .= " AND e2.EventDate <= '$date_to'";

    $sql = "
    SELECT 
        c.CompanyName AS company_name,
        c.TierLevel AS tier,
        loc.ContinentName AS continent,
        loc.CountryName AS country,
        loc.City AS city,
        (
            SELECT COUNT(DISTINCT e2.EventID)
            FROM DisruptionEvent e2
            JOIN ImpactsCompany ic2 ON e2.EventID = ic2.EventID
            JOIN Company c2 ON ic2.AffectedCompanyID = c2.CompanyID
            JOIN Location l2 ON c2.LocationID = l2.LocationID
            WHERE l2.$risk_scope_field = loc.$risk_scope_field
            $dateSubQuery
        ) as regionCount
    FROM Company c
    LEFT JOIN Location loc ON c.LocationID = loc.LocationID
    LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    ";
    
    // RRC is based on the regional risk not just the company events
    // A company can have 0 disruptions and it can still be in a high-risk region
    // so what we did is filter companies by user inputs but calculate the risk from the regional activity 
    
    $sql .= " WHERE 1=1";

    // Region Filter
    if ($region_type && $region && strtolower($region) !== 'all') {
        if ($region_type === 'Continent') {
            $sql .= " AND loc.ContinentName = '$region'";
        } elseif ($region_type === 'Country') {
            $sql .= " AND loc.CountryName = '$region'";
        } elseif ($region_type === 'City') {
            $sql .= " AND loc.City = '$region'";
        }
    }

    // Tier Filter
    if ($tier !== 'All') {
        $sql .= " AND c.TierLevel = '$tier'";
    }

    // Company Filter 
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

    $sql .= " GROUP BY c.CompanyID, c.CompanyName, c.TierLevel, loc.ContinentName, loc.CountryName, loc.City";
    
    
    $wrapperSql = "
    SELECT *, (regionCount / $N_total) as rrc
    FROM ($sql) as sub
    ORDER BY rrc DESC
    ";

    $result = $conn->query($wrapperSql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['rrc'] = (float)$row['rrc'];
        // Add percentage for display convenience when needed
        $data[] = $row;
    }

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
