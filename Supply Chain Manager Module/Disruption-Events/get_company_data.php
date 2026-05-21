<?php
// Use output buffering to catch any unwanted output
ob_start();

// Disable display_errors to prevent HTML in JSON response, but log them
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

    // Get parameters (PHP 5 compatible)
    $region = isset($_GET['region']) ? $_GET['region'] : '';
    $region_type = isset($_GET['region_type']) ? $_GET['region_type'] : '';
    $tier = isset($_GET['tier']) ? $_GET['tier'] : 'All';
    $companies = isset($_GET['companies']) ? $_GET['companies'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Sanitize
    $region = $conn->real_escape_string($region);
    $region_type = $conn->real_escape_string($region_type);
    $tier = $conn->real_escape_string($tier);
    $date_from = $conn->real_escape_string($date_from);
    $date_to = $conn->real_escape_string($date_to);

    // Build Query
    // METRIC: Average Recovery Time
    // Calculation: AVG(DATEDIFF(EventRecoveryDate, EventDate))
    
    $sql = "
    SELECT 
        c.CompanyName AS company_name,
        COALESCE(AVG(DATEDIFF(e.EventRecoveryDate, e.EventDate)), 0) AS avgRecovery,
        c.TierLevel AS tier,
        loc.ContinentName AS continent,
        loc.CountryName AS country,
        loc.City AS city
    FROM Company c
    LEFT JOIN Location loc ON c.LocationID = loc.LocationID
    LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    ";

    // Handle Date Filters in the JOIN
    $eventJoinOn = "ic.EventID = e.EventID";
    if ($date_from !== '') {
        $eventJoinOn .= " AND e.EventDate >= '$date_from'";
    }
    if ($date_to !== '') {
        $eventJoinOn .= " AND e.EventDate <= '$date_to'";
    }

    $sql .= " LEFT JOIN DisruptionEvent e ON $eventJoinOn";

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

    // Company Filter (Multi-select)
    if ($companies !== '') {
        // Use pipe '|' delimiter as standardized
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
    
    // Sort by Average Recovery Time ASC (Lower is better/first)
    $sql .= " ORDER BY avgRecovery ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['avgRecovery'] = (float)$row['avgRecovery'];
        $data[] = $row;
    }

    // --- CHECK FOR ONGOING EVENTS ---
    // An ongoing event has a NULL EventRecoveryDate
    // We also join with DisruptionCategory to get the type name
    // Use LEFT JOIN to ensure we count events even if category is missing/invalid
    
    $ongoingSql = "
        SELECT 
            COUNT(*) as ongoing_count,
            GROUP_CONCAT(DISTINCT dc.CategoryName SEPARATOR ', ') as event_types
        FROM DisruptionEvent e
        LEFT JOIN DisruptionCategory dc ON e.CategoryID = dc.CategoryID
        WHERE e.EventRecoveryDate IS NULL
    ";
    
    // Apply same date filters if relevant
    if ($date_from !== '') {
        $ongoingSql .= " AND e.EventDate >= '$date_from'";
    }
    if ($date_to !== '') {
        $ongoingSql .= " AND e.EventDate <= '$date_to'";
    }

    $ongoingRes = $conn->query($ongoingSql);
    $ongoingData = array('count' => 0, 'types' => '');
    
    if ($ongoingRes) {
        $row = $ongoingRes->fetch_assoc();
        $ongoingData['count'] = (int)$row['ongoing_count'];
        $ongoingData['types'] = $row['event_types'];
    }

    // Clear buffer and output JSON
    ob_end_clean();
    echo json_encode(array(
        'data' => $data,
        'ongoing' => $ongoingData
    ));

} catch (Exception $e) {
    ob_end_clean();
    // Return 500 error with JSON details
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) {
    $conn->close();
}
?>