<?php
header('Content-Type: application/json');

// Database settings
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

// Connect
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Get parameters
$company = isset($_GET['company']) ? $_GET['company'] : '';
$from    = isset($_GET['from']) ? $_GET['from'] : '';
$to      = isset($_GET['to']) ? $_GET['to'] : '';

$company = $conn->real_escape_string($company);

    // Base Query
    $sql = "
        SELECT 
            c.CompanyID,
            c.CompanyName,
            CONCAT(l.ContinentName, ', ', l.CountryName, ', ', l.City) AS region,
            c.TierLevel AS tier,
            COALESCE(AVG(DATEDIFF(e.EventRecoveryDate, e.EventDate)), 0) AS avg_recovery
        FROM Company c
        JOIN Location l ON c.LocationID = l.LocationID
        LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
        LEFT JOIN DisruptionEvent e ON ic.EventID = e.EventID
        WHERE c.CompanyName = '$company'
    ";

    
    // Date Filters 
    if ($from !== "") {
        $from = $conn->real_escape_string($from);
        $sql .= " AND e.EventDate >= '$from' ";
    }

    if ($to !== "") {
        $to = $conn->real_escape_string($to);
        $sql .= " AND e.EventDate <= '$to' ";
    }

    $sql .= "
        GROUP BY 
            c.CompanyID, c.CompanyName, l.ContinentName, l.CountryName, l.City, c.TierLevel
        LIMIT 1
    ";

// Execute
$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $row['avg_recovery'] = $row['avg_recovery'] !== null ? (float)$row['avg_recovery'] : null;
    $data[] = $row;
}

// Return JSON
echo json_encode($data);
$conn->close();
?>
