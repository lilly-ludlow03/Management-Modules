<?php
header('Content-Type: application/json');

// Database connection settings
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die(json_encode([]));
}

$search = isset($_GET['q']) ? $_GET['q'] : '';
$search = $conn->real_escape_string($search);

// SQL query
$sql = "SELECT c.CompanyID, c.CompanyName, l.ContinentName AS continent, l.CountryName AS country, l.City AS city, c.TierLevel AS tier,
               AVG(fr.HealthScore) AS avg_recovery
        FROM Company c
        JOIN Location l ON c.LocationID = l.LocationID
        LEFT JOIN FinancialReport fr ON c.CompanyID = fr.CompanyID
        WHERE c.CompanyName LIKE '%$search%'
        GROUP BY c.CompanyID, c.CompanyName, l.ContinentName, l.CountryName, l.City, c.TierLevel
        LIMIT 20";

$result = $conn->query($sql);

// Build the response array
$companies = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Optionally convert avg_recovery to float
        $row['avg_recovery'] = $row['avg_recovery'] !== null ? (float)$row['avg_recovery'] : null;
        $companies[] = $row;
    }
}

// Return JSON 
echo json_encode($companies);

$conn->close();
?>
