<?php
// Use output buffering to catch any of the unwanted output
ob_start();

// Disable display_errors to prevent HTML in JSON response, but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// This endpoint always returns JSON
header('Content-Type: application/json');

$response = array();

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    // Open Data Base connection
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error loading character set utf8: " . $conn->error);
    }

    // Read and Clean Input Filters

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

    // Logic for Disruption Frequency 
    // Equation: Number of Disruptions / Period of Time
    
    // 1) Determine Period in Days
    $periodDays = 0;
    if ($date_from !== '' && $date_to !== '') {
        // User specified date range
        $start = strtotime($date_from);
        $end = strtotime($date_to);
        $diff = $end - $start;
        $periodDays = round($diff / (60 * 60 * 24));
        if ($periodDays < 1) $periodDays = 1;
    }
    
    // 2) Build the calculation logic for SQL
    
    if ($periodDays > 0) {
        $calcFreq = "(COUNT(e.EventID) / $periodDays) * 365";
    } else {
        // Dynamic period calculation
        $calcFreq = "(COUNT(e.EventID) / GREATEST(COALESCE(DATEDIFF(MAX(e.EventDate), MIN(e.EventDate)), 0), 1)) * 365";
    }

    // Main Query 
    $sql = "
    SELECT 
        c.CompanyName AS company_name,
        COALESCE($calcFreq, 0) AS disruptionFrequency,
        COUNT(e.EventID) as totalDisruptions,
        c.TierLevel AS tier,
        loc.ContinentName AS continent,
        loc.CountryName AS country,
        loc.City AS city
    FROM Company c
    LEFT JOIN Location loc ON c.LocationID = loc.LocationID
    LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    ";
    
    // Join with DisruptionEvent applying date filters to the events
    $eventJoin = "ic.EventID = e.EventID";
    if ($date_from !== '') {
        $eventJoin .= " AND e.EventDate >= '$date_from'";
    }
    if ($date_to !== '') {
        $eventJoin .= " AND e.EventDate <= '$date_to'";
    }
    
    $sql .= " LEFT JOIN DisruptionEvent e ON $eventJoin";
    
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

    // Company Filter that has multi-select
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
    
    // Sort by Frequency 
    $sql .= " ORDER BY disruptionFrequency ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $row['disruptionFrequency'] = (float)$row['disruptionFrequency'];
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
