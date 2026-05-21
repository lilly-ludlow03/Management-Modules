<?php
header('Content-Type: application/json');

// Database credentials
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$q = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

if ($q === '') {
    echo json_encode([]);
    exit;
}

// Search for Companies of Type 'Distributor' matching the query
$sql = "SELECT CompanyName FROM Company WHERE CompanyName LIKE '%$q%' AND Type = 'Distributor' LIMIT 10";
$result = $conn->query($sql);

$companies = array();
if ($result) {
    while($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
}

echo json_encode($companies);
$conn->close();
?>
