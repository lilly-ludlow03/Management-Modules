<?php
// add_company.php - Revised Logic
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array('status' => 'error', 'message' => 'Fatal Error: ' . $error['message']));
        exit;
    }
});

$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);
    $conn->set_charset("utf8");

    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        throw new Exception("Invalid request method.");
    }

    // Input Processing
    $rawType = isset($_POST['companyType']) ? trim($_POST['companyType']) : '';
    $companyType = ucfirst($rawType); 

    $companyName = isset($_POST['companyName']) ? trim($_POST['companyName']) : '';
    $tierLevel = isset($_POST['tierLevel']) ? trim($_POST['tierLevel']) : '3';
    
    $factoryCapacity = 0;
    if ($companyType === 'Manufacturer') {
        if (isset($_POST['factoryCapacity']) && trim($_POST['factoryCapacity']) !== '') {
            $factoryCapacity = (int)trim($_POST['factoryCapacity']);
        }
    }

    // Required Field Validation
    if (empty($companyType) || empty($companyName) || empty($tierLevel)) {
        throw new Exception('Company Type, Company Name, and Tier Level are required fields.');
    }
    
    // Validate Company Type
    if (!in_array($companyType, array('Manufacturer', 'Distributor', 'Retailer'))) {
        throw new Exception("Invalid Company Type: " . $companyType);
    }

    // Transaction Start
    $conn->autocommit(FALSE);

    // 1. Location Logic - Unified
    // We need to find the LocationID regardless of whether user clicked "Add New" or "Use Existing".
    // In both cases, the form sends 'continent', 'country', 'city' OR 'existingContinent', etc.
    // But looking at the HTML, the names are DIFFERENT for "Add New" vs "Existing".
    
    $continent = '';
    $country = '';
    $city = '';
    
    $addLocationChoice = isset($_POST['addLocationChoice']) ? $_POST['addLocationChoice'] : '';
    
    if ($addLocationChoice === 'yes') {
        // User selected "Add New Location" -> inputs are 'continent', 'country', 'city'
        $continent = isset($_POST['continent']) ? trim($_POST['continent']) : '';
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    } elseif ($addLocationChoice === 'no') {
        // User selected "Use Existing Location" -> inputs are 'existingContinent', 'existingCountry', 'existingCity'
        $continent = isset($_POST['existingContinent']) ? trim($_POST['existingContinent']) : '';
        $country = isset($_POST['existingCountry']) ? trim($_POST['existingCountry']) : '';
        $city = isset($_POST['existingCity']) ? trim($_POST['existingCity']) : '';
    } else {
        // Fallback if neither is selected explicitly (shouldn't happen if radio buttons work right)
        // Try to grab from standard fields
        $continent = isset($_POST['continent']) ? trim($_POST['continent']) : '';
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    }

    if (empty($continent) || empty($country) || empty($city)) {
        throw new Exception("Location information (Continent, Country, City) is required.");
    }

    // Check existing location
    $locationId = null;
    $stmt = $conn->prepare("SELECT LocationID FROM Location WHERE CountryName = ? AND City = ?");
    if (!$stmt) throw new Exception("Prepare failed (Location Check): " . $conn->error);
    
    $stmt->bind_param("ss", $country, $city);
    if (!$stmt->execute()) throw new Exception("Execute failed (Location Check): " . $stmt->error);
    
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($fetchedLocId);
        $stmt->fetch();
        $locationId = $fetchedLocId;
        $stmt->close();
    } else {
        // Location doesn't exist, create it
        $stmt->close();
        
        $stmt2 = $conn->prepare("INSERT INTO Location (ContinentName, CountryName, City) VALUES (?, ?, ?)");
        if (!$stmt2) throw new Exception("Prepare failed (Location Insert): " . $conn->error);
        
        $stmt2->bind_param("sss", $continent, $country, $city);
        if (!$stmt2->execute()) throw new Exception("Execute failed (Location Insert): " . $stmt2->error);
        
        $locationId = $conn->insert_id;
        $stmt2->close();
    }

    // 2. Insert Company
    $stmt = $conn->prepare("INSERT INTO Company (CompanyName, LocationID, TierLevel, Type) VALUES (?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Prepare failed (Company): " . $conn->error);
    
    $stmt->bind_param("siss", $companyName, $locationId, $tierLevel, $companyType);
    if (!$stmt->execute()) throw new Exception("Execute failed (Company): " . $stmt->error);
    
    $companyId = $conn->insert_id;
    $stmt->close();

    // 3. Insert Subtype
    if ($companyType === 'Manufacturer') {
        $stmt = $conn->prepare("INSERT INTO Manufacturer (CompanyID, FactoryCapacity) VALUES (?, ?)");
        if (!$stmt) throw new Exception("Prepare failed (Manufacturer): " . $conn->error);
        $stmt->bind_param("ii", $companyId, $factoryCapacity);
        $stmt->execute();
        $stmt->close();
    } elseif ($companyType === 'Distributor') {
        $stmt = $conn->prepare("INSERT INTO Distributor (CompanyID) VALUES (?)");
        if (!$stmt) throw new Exception("Prepare failed (Distributor): " . $conn->error);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $stmt->close();
    } elseif ($companyType === 'Retailer') {
        $stmt = $conn->prepare("INSERT INTO Retailer (CompanyID) VALUES (?)");
        if (!$stmt) throw new Exception("Prepare failed (Retailer): " . $conn->error);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(array('status' => 'success', 'message' => 'Company added successfully!'));

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate entry') !== false) {
        echo json_encode(array('status' => 'error', 'message' => 'A company with this name already exists.'));
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Failed to add company: ' . $msg));
    }
}

if ($conn) $conn->close();
?>