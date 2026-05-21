<?php
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";     
$password   = "yM84JeHv";     
$database   = $username;

// Single connection used for the whole script
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}
// Region Filter
$region = $_GET['region'] ?? '';
$tier   = $_GET['tier'] ?? 'All';

$region = strtolower(str_replace("-", " ", $region));
// Core Metrics
$sql = "
    SELECT 
        c.CompanyID,
        c.CompanyName,
        loc.ContinentName,
        loc.CountryName,
        loc.City,
        c.TierLevel,
        AVG(DATEDIFF(e.EventRecoveryDate, e.EventDate)) AS AvgRecoveryTime
    FROM Company c
    INNER JOIN Location loc ON c.LocationID = loc.LocationID
    INNER JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    INNER JOIN DisruptionEvent e ON ic.EventID = e.EventID
    WHERE e.EventRecoveryDate IS NOT NULL
";

// Region Filter
if ($region !== "" && $region !== "all") {
    $sql .= "
        AND (
            LOWER(loc.ContinentName) = '$region' OR
            LOWER(loc.CountryName)  = '$region' OR
            LOWER(loc.City) = '$region'
        )
    ";
}
// Optional Filter
if ($tier !== "All") {
    $sql .= " AND c.TierLevel = '$tier' ";
}
 // Group by Company and Location
$sql .= "
    GROUP BY 
        c.CompanyID, c.CompanyName, c.TierLevel,
        loc.ContinentName, loc.CountryName, loc.City
    ORDER BY AvgRecoveryTime DESC
";

$result = mysqli_query($conn, $sql);

$output = [];
foreach ($result as $row) {
    $output[] = $row;
}

echo json_encode($output);

$conn->close();
?>
