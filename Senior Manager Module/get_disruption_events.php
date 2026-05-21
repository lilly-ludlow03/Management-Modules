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

$data = [];

// Search for unique categories that are matching the term
$sqlCat = "
    SELECT DISTINCT CategoryName 
    FROM DisruptionCategory 
    WHERE CategoryName LIKE '%$q%'
    LIMIT 10
";
$resultCat = $conn->query($sqlCat);
if ($resultCat) {
    while ($row = $resultCat->fetch_assoc()) {
        $data[] = [
            'display' => $row['CategoryName'],
            'value' => $row['CategoryName'],
            'type' => 'category'
        ];
    }
}

echo json_encode($data);
$conn->close();
?>
