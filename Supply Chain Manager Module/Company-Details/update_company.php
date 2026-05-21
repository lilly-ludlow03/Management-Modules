<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Fatal Error Handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode(array('status' => 'error', 'message' => 'Fatal Error: ' . $error['message']));
        exit;
    }
});

// Data Base Connections
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
    $conn->set_charset("utf8");

    // Tier Update Logic 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'update_tier') {
            // get company name and new tier
            $companyName = isset($_POST['company']) ? trim($_POST['company']) : '';
            $newTier = isset($_POST['tier']) ? trim($_POST['tier']) : '';

            // check if company name and new tier input fields are empty
            if (empty($companyName) || $newTier === '') {
                throw new Exception('Missing company name or tier level.');
            }

            // prepare statement to update company tier
            $stmt = $conn->prepare("UPDATE Company SET TierLevel = ? WHERE CompanyName = ?");
            // check if prepare statement failed
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            // bind parameters to statement
            $stmt->bind_param("is", $newTier, $companyName);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

            // check if update was successful
            if ($stmt->affected_rows > 0) {
                echo json_encode(array('status' => 'success', 'message' => 'Company tier updated successfully.'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Company not found or tier is already set to this value.'));
            }
            // close statement
            $stmt->close();
            // close connection
            if ($conn) $conn->close();
            // exit after handling the POST request
            exit;
        } else if (isset($_POST['action']) && $_POST['action'] === 'update_type') {
            // get company name and new type
            $companyName = isset($_POST['company']) ? trim($_POST['company']) : '';
            $newType = isset($_POST['type']) ? trim($_POST['type']) : '';

            // check if company name and new type input fields are empty
            if (empty($companyName) || empty($newType)) {
                throw new Exception('Missing company name or new type.');
            }

            $conn->autocommit(FALSE); // Start transaction

            // Get CompanyID and current Type
            // prepare statement to get CompanyID and Type
            $stmt = $conn->prepare("SELECT CompanyID, Type FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get CompanyID/Type): " . $conn->error);
            // bind parameters to statement
            $stmt->bind_param("s", $companyName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get CompanyID/Type): " . $stmt->error);
            // store result
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Company not found.");
            }
            // bind result to variables
            $stmt->bind_result($companyID, $oldType);
            $stmt->fetch();
            $stmt->close();

            // 1. Delete from old role table
            $roleTables = ['Manufacturer', 'Distributor', 'Retailer'];
            if (in_array($oldType, $roleTables)) {
                $stmt = $conn->prepare("DELETE FROM $oldType WHERE CompanyID = ?");
                if (!$stmt) throw new Exception("Prepare failed (Delete from $oldType): " . $conn->error);
                $stmt->bind_param("i", $companyID);
                if (!$stmt->execute()) throw new Exception("Execute failed (Delete from $oldType): " . $stmt->error);
                $stmt->close();
            }

            // 2) Update the Company table
            $stmt = $conn->prepare("UPDATE Company SET Type = ? WHERE CompanyID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Update Company Type): " . $conn->error);
            $stmt->bind_param("si", $newType, $companyID);
            if (!$stmt->execute()) throw new Exception("Execute failed (Update Company Type): " . $stmt->error);
            $stmt->close();

            // 3) Insert into new role table
            if (in_array($newType, $roleTables)) {
                if ($newType === 'Manufacturer') {
                    $capacity = isset($_POST['capacity']) && is_numeric($_POST['capacity']) ? $_POST['capacity'] : 0; // Default to 0
                    $stmt = $conn->prepare("INSERT INTO Manufacturer (CompanyID, FactoryCapacity) VALUES (?, ?)");
                    if (!$stmt) throw new Exception("Prepare failed (Insert into Manufacturer): " . $conn->error);
                    $stmt->bind_param("id", $companyID, $capacity);
                } else {
                    $stmt = $conn->prepare("INSERT INTO $newType (CompanyID) VALUES (?)");
                    if (!$stmt) throw new Exception("Prepare failed (Insert into $newType): " . $conn->error);
                    $stmt->bind_param("i", $companyID);
                }

                if (!$stmt->execute()) {
                    // Ignore duplicate key error in case it already exists
                    if ($conn->errno != 1062) { 
                        throw new Exception("Execute failed (Insert into $newType): " . $stmt->error);
                    }
                }
                $stmt->close();
            }

            $conn->commit(); // Commit transaction
            
            // echo success message
            echo json_encode(['status' => 'success', 'message' => 'Company type updated successfully! The company has been moved to the new role table.']);
            
            // close connection
            if ($conn) $conn->close();
            exit;
        } else if (isset($_POST['action']) && $_POST['action'] === 'update_capacity') {
            $companyName = isset($_POST['company']) ? trim($_POST['company']) : '';
            $newCapacity = isset($_POST['capacity']) ? trim($_POST['capacity']) : '';

            // check if company name and new capacity value are empty
            if (empty($companyName) || $newCapacity === '') {
                throw new Exception('Missing company name or new capacity value.');
            }

            // Get the CompanyID from the CompanyName
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get CompanyID): " . $conn->error);
            $stmt->bind_param("s", $companyName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get CompanyID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Company not found.");
            }
            $stmt->bind_result($companyID);
            $stmt->fetch();
            $stmt->close();

            // Update the Manufacturer table
            $stmt = $conn->prepare("UPDATE Manufacturer SET FactoryCapacity = ? WHERE CompanyID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Update Capacity): " . $conn->error);
            
            // bind parameters to statement
            $stmt->bind_param("di", $newCapacity, $companyID); 

            // check if update was successful
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Update Capacity): " . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Capacity updated successfully!']);
            } else {
                echo json_encode(['status' => 'info', 'message' => 'No changes were made. The capacity may already be set to this value.']);
            }
            
            $stmt->close();
            if ($conn) $conn->close();
            exit;
        } else if (isset($_POST['action']) && $_POST['action'] === 'add_route') {
            // get distributor name, from (sourece) company name, to (receiving) company name
            $distributorName = isset($_POST['distributor']) ? trim($_POST['distributor']) : '';
            $fromName = isset($_POST['from_company']) ? trim($_POST['from_company']) : '';
            $toName = isset($_POST['to_company']) ? trim($_POST['to_company']) : '';

            // check if input fields are empty
            if (empty($distributorName) || empty($fromName) || empty($toName)) {
                throw new Exception('Missing required company names for the route.');
            }

            // start transaction
            $conn->autocommit(FALSE);

            // Function to get CompanyID 
            function getCompanyID($conn, $companyName) {
                $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                if (!$stmt) throw new Exception("Prepare failed for CompanyID ($companyName): " . $conn->error);
                $stmt->bind_param("s", $companyName);
                if (!$stmt->execute()) throw new Exception("Execute failed for CompanyID ($companyName): " . $stmt->error);
                $stmt->store_result();
                if ($stmt->num_rows === 0) {
                    $stmt->close();
                    throw new Exception("Company not found: $companyName");
                }
                $stmt->bind_result($companyID);
                $stmt->fetch();
                $stmt->close();
                return $companyID;
            }

            // get distributor, from, and to company IDs
            $distributorID = getCompanyID($conn, $distributorName);
            $fromCompanyID = getCompanyID($conn, $fromName);
            $toCompanyID = getCompanyID($conn, $toName);

            // prepare statement to insert route
            $stmt = $conn->prepare("INSERT INTO OperatesLogistics (DistributorID, FromCompanyID, ToCompanyID) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (Insert Route): " . $conn->error);

            $stmt->bind_param("iii", $distributorID, $fromCompanyID, $toCompanyID);
            
            // check if insert was successful
            if (!$stmt->execute()) {
                if ($conn->errno == 1062) { // Handle duplicate entry
                    throw new Exception("This logistics route already exists.");
                } else {
                    throw new Exception("Execute failed (Insert Route): " . $stmt->error);
                }
            }
            $stmt->close();

            // commit transaction
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Logistics route added successfully!']);
            
            // close connection
            if ($conn) $conn->close();
            exit;
        } else if (isset($_POST['action']) && $_POST['action'] === 'remove_route') {
            // get distributor name, from (sourece) company name, to (receiving) company name
            $distributorName = isset($_POST['distributor']) ? trim($_POST['distributor']) : '';
            $fromName = isset($_POST['from_company']) ? trim($_POST['from_company']) : '';
            $toName = isset($_POST['to_company']) ? trim($_POST['to_company']) : '';

            // check if input fields are empty
            if (empty($distributorName) || empty($fromName) || empty($toName)) {
                throw new Exception('Missing required company names for the route.');
            }

            // start transaction
            $conn->autocommit(FALSE);

            // Re-use getCompanyID function if not already in scope
            if (!function_exists('getCompanyID')) {
                // function to get company ID
                function getCompanyID($conn, $companyName) {
                    $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                    if (!$stmt) throw new Exception("Prepare failed for CompanyID ($companyName): " . $conn->error);
                    $stmt->bind_param("s", $companyName);
                    if (!$stmt->execute()) throw new Exception("Execute failed for CompanyID ($companyName): " . $stmt->error);
                    $stmt->store_result();
                    if ($stmt->num_rows === 0) {
                        $stmt->close();
                        throw new Exception("Company not found: $companyName");
                    }
                    $stmt->bind_result($companyID);
                    $stmt->fetch();
                    $stmt->close();
                    return $companyID;
                }
            }

            // get distributor, from, and to company IDs
            $distributorID = getCompanyID($conn, $distributorName);
            $fromCompanyID = getCompanyID($conn, $fromName);
            $toCompanyID = getCompanyID($conn, $toName);

            // prepare statement to delete route
            $stmt = $conn->prepare("DELETE FROM OperatesLogistics WHERE DistributorID = ? AND FromCompanyID = ? AND ToCompanyID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Delete Route): " . $conn->error);

            // bind parameters to statement
            $stmt->bind_param("iii", $distributorID, $fromCompanyID, $toCompanyID);
            
            // check if delete was successful
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Delete Route): " . $stmt->error);
            }
            
            // check if no rows were affected and the route does not exist
            if ($stmt->affected_rows === 0) {
                throw new Exception("The specified route does not exist and could not be removed.");
            }

            $stmt->close();

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Logistics route removed successfully!']);
            
            if ($conn) $conn->close();
            exit;
        } 
        // add adjustment transaction
        else if (isset($_POST['action']) && $_POST['action'] === 'add_adjustment_transaction') {
            $companyName = isset($_POST['company']) ? trim($_POST['company']) : '';
            $date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $productName = isset($_POST['product']) ? trim($_POST['product']) : '';
            $quantity = isset($_POST['quantity']) ? trim($_POST['quantity']) : '';
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

            // check if input fields are empty
            if (empty($companyName) || empty($date) || empty($productName) || $quantity === '' || empty($reason)) {
                throw new Exception('Missing required transaction data, including reason.');
            }

            // start transaction
            $conn->autocommit(FALSE);

            // Get CompanyID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (CompanyID): " . $conn->error);
            $stmt->bind_param("s", $companyName);
            if (!$stmt->execute()) throw new Exception("Execute failed (CompanyID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Company not found.");
            }
            $stmt->bind_result($companyID);
            $stmt->fetch();
            $stmt->close();

            // Get ProductID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (ProductID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (ProductID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Product not found.");
            }
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // Insert into InventoryTransaction table to get TransactionID
            $transactionType = 'Adjustment';
            $stmt = $conn->prepare("INSERT INTO InventoryTransaction (Type) VALUES (?)");
            if (!$stmt) throw new Exception("Prepare failed (Transaction Insert): " . $conn->error);
            $stmt->bind_param("s", $transactionType);
            if (!$stmt->execute()) throw new Exception("Execute failed (Transaction Insert): " . $stmt->error);
            $transactionID = $conn->insert_id;
            $stmt->close();

            // Insert into InventoryAdjustment table using TransactionID for both AdjustmentID and TransactionID
            $stmt = $conn->prepare("INSERT INTO InventoryAdjustment (AdjustmentID, TransactionID, CompanyID, ProductID, QuantityChange, Reason, AdjustmentDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (Adjustment Insert): " . $conn->error);
            $stmt->bind_param("iiiidss", $transactionID, $transactionID, $companyID, $productID, $quantity, $reason, $date);
            if (!$stmt->execute()) throw new Exception("Execute failed (Adjustment Insert): " . $stmt->error);
            $stmt->close();

            $conn->commit();
            echo json_encode(array('status' => 'success', 'message' => 'Adjustment transaction added successfully!'));
            
            if ($conn) $conn->close();
            exit;

        } else if (isset($_POST['action']) && $_POST['action'] === 'add_shipping_transaction') {
            $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
            $conn->autocommit(FALSE); // Start transaction

            try {
                // 1) Get all data from POST
                $distributorName = isset($_POST['distributor']) ? trim($_POST['distributor']) : '';
                $sourceCompanyName = isset($_POST['shippingCompany']) ? trim($_POST['shippingCompany']) : '';
                $destinationCompanyName = isset($_POST['receivingCompany']) ? trim($_POST['receivingCompany']) : '';
                $productName = isset($_POST['product']) ? trim($_POST['product']) : '';
                $promisedDate = isset($_POST['promisedDate']) ? trim($_POST['promisedDate']) : '';
                $actualDate = isset($_POST['actualDate']) ? trim($_POST['actualDate']) : null; // Can be null
                $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;

                if (empty($distributorName) || empty($sourceCompanyName) || empty($destinationCompanyName) || empty($productName) || empty($promisedDate) || $quantity <= 0) {
                    throw new Exception("Missing required fields or invalid quantity.");
                }
                if (empty($actualDate)) {
                    $actualDate = null;
                }

                // 2) Helper function to get CompanyID from CompanyName
                function getCompanyId($conn, $companyName) {
                    $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                    if (!$stmt) throw new Exception("Prepare failed (Get Company ID): " . $conn->error);
                    $stmt->bind_param("s", $companyName);
                    if (!$stmt->execute()) throw new Exception("Execute failed (Get Company ID): " . $stmt->error);
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $companyId = 0;
                        $stmt->bind_result($companyId);
                        $stmt->fetch();
                        $stmt->close();
                        return $companyId;
                    }
                    $stmt->close();
                    throw new Exception("Company not found: " . $companyName);
                }

                // 3) Helper function to get ProductID from ProductName
                function getProductId($conn, $productName) {
                    $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
                    if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
                    $stmt->bind_param("s", $productName);
                    if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $productId = 0;
                        $stmt->bind_result($productId);
                        $stmt->fetch();
                        $stmt->close();
                        return $productId;
                    }
                    $stmt->close();
                    throw new Exception("Product not found: " . $productName);
                }

                // 4) Get all necessary IDs
                $distributorId = getCompanyId($conn, $distributorName);
                $sourceCompanyId = getCompanyId($conn, $sourceCompanyName);
                $destinationCompanyId = getCompanyId($conn, $destinationCompanyName);
                $productId = getProductId($conn, $productName);

                // 5) Insert into InventoryTransaction to get a new TransactionID
                $stmt = $conn->prepare("INSERT INTO InventoryTransaction (Type) VALUES ('Shipping')");
                if (!$stmt) throw new Exception("Prepare failed (Transaction Insert): " . $conn->error);
                if (!$stmt->execute()) throw new Exception("Execute failed (Transaction Insert): " . $stmt->error);
                $transactionId = $conn->insert_id;
                $stmt->close();

                // 6) Insert into the Shipping table
                $stmt = $conn->prepare("INSERT INTO Shipping (TransactionID, DistributorID, ProductID, SourceCompanyID, DestinationCompanyID, PromisedDate, ActualDate, Quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed (Shipping Insert): " . $conn->error);
                $stmt->bind_param("iiiiissd", $transactionId, $distributorId, $productId, $sourceCompanyId, $destinationCompanyId, $promisedDate, $actualDate, $quantity);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (Shipping Insert): " . $stmt->error);
                }

                // check if rows were affected
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $response = ['status' => 'success', 'message' => 'Shipping transaction added successfully!'];
                } else {
                    throw new Exception("Failed to insert the shipping record.");
                }
                $stmt->close();

            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = $e->getMessage();
            }

            // echo response
            echo json_encode($response);
            if ($conn) $conn->close();
            exit;
        } 
        // add dependency
        else if (isset($_POST['action']) && $_POST['action'] === 'add_dependency') {
            $upstreamName = isset($_POST['upstream']) ? trim($_POST['upstream']) : '';
            $downstreamName = isset($_POST['downstream']) ? trim($_POST['downstream']) : '';

            // check if input fields are empty
            if (empty($upstreamName) || empty($downstreamName)) {
                throw new Exception('Missing upstream or downstream company name.');
            }

            $conn->autocommit(FALSE);

            // Get Upstream ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Upstream ID): " . $conn->error);
            $stmt->bind_param("s", $upstreamName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Upstream ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Upstream company not found.");
            }
            $stmt->bind_result($upstreamID);
            $stmt->fetch();
            $stmt->close();

            // Get Downstream ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Downstream ID): " . $conn->error);
            $stmt->bind_param("s", $downstreamName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Downstream ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Downstream company not found.");
            }
            $stmt->bind_result($downstreamID);
            $stmt->fetch();
            $stmt->close();

            // Insert into DependsOn
            $stmt = $conn->prepare("INSERT INTO DependsOn (UpstreamCompanyID, DownstreamCompanyID) VALUES (?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (Dependency Insert): " . $conn->error);
            $stmt->bind_param("ii", $upstreamID, $downstreamID);
            if (!$stmt->execute()) {
                if ($conn->errno == 1062) { // Duplicate entry
                    throw new Exception("This dependency already exists.");
                } else {
                    throw new Exception("Execute failed (Dependency Insert): " . $stmt->error);
                }
            }
            $stmt->close();
            
            $conn->commit();
            echo json_encode(array('status' => 'success', 'message' => 'Dependency added successfully!'));

            if ($conn) $conn->close();
            exit;
        } 
        // remove dependency
        else if (isset($_POST['action']) && $_POST['action'] === 'remove_dependency') {
            $upstreamName = isset($_POST['upstream']) ? trim($_POST['upstream']) : '';
            $downstreamName = isset($_POST['downstream']) ? trim($_POST['downstream']) : '';

            if (empty($upstreamName) || empty($downstreamName)) {
                throw new Exception('Missing upstream or downstream company name.');
            }

            $conn->autocommit(FALSE);

            // Get Upstream ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Upstream ID): " . $conn->error);
            $stmt->bind_param("s", $upstreamName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Upstream company not found.");
            }
            $stmt->bind_result($upstreamID);
            $stmt->fetch();
            $stmt->close();

            // Get Downstream ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Downstream ID): " . $conn->error);
            $stmt->bind_param("s", $downstreamName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Downstream company not found.");
            }
            $stmt->bind_result($downstreamID);
            $stmt->fetch();
            $stmt->close();

            // Delete from DependsOn
            $stmt = $conn->prepare("DELETE FROM DependsOn WHERE UpstreamCompanyID = ? AND DownstreamCompanyID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Dependency Delete): " . $conn->error);
            $stmt->bind_param("ii", $upstreamID, $downstreamID);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Dependency Delete): " . $stmt->error);
            }
            
            // check if no rows were affected and the dependency does not exist
            if ($stmt->affected_rows === 0) {
                throw new Exception("Dependency not found or already removed.");
            }

            $stmt->close();
            
            $conn->commit();
            echo json_encode(array('status' => 'success', 'message' => 'Dependency removed successfully!'));

            if ($conn) $conn->close();
            exit;
        } 
        // add supply
        else if (isset($_POST['action']) && $_POST['action'] === 'add_supply') {
            $manufacturerName = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
            $productName = isset($_POST['product']) ? trim($_POST['product']) : '';
            $price = isset($_POST['price']) ? trim($_POST['price']) : '';

            // check if input fields are empty
            if (empty($manufacturerName) || empty($productName) || $price === '') {
                throw new Exception('Missing required fields.');
            }
            // start transaction
            $conn->autocommit(FALSE);

            // Get Manufacturer ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ? AND Type = 'Manufacturer'");
            if (!$stmt) throw new Exception("Prepare failed (Get Manufacturer ID): " . $conn->error);
            $stmt->bind_param("s", $manufacturerName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Manufacturer ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Manufacturer not found.");
            }
            $stmt->bind_result($manufacturerID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
            $stmt->store_result();
             if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Product not found.");
            }
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();
            
            // Insert into SuppliesProduct
            $stmt = $conn->prepare("INSERT INTO SuppliesProduct (SupplierID, ProductID, SupplyPrice) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (Insert Supply): " . $conn->error);
            $stmt->bind_param("iid", $manufacturerID, $productID, $price);
            if (!$stmt->execute()) {
                 if ($conn->errno == 1062) {
                    throw new Exception("This manufacturer already supplies this product.");
                } else {
                    throw new Exception("Execute failed (Insert Supply): " . $stmt->error);
                }
            }
            $stmt->close();

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Product supply relationship added successfully!']);

            if ($conn) $conn->close();
            exit;
        } 
        // update price
        else if (isset($_POST['action']) && $_POST['action'] === 'update_price') {
            // get manufacturer name, product name, and new price
            $manufacturerName = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
            $productName = isset($_POST['product']) ? trim($_POST['product']) : '';
            $price = isset($_POST['price']) ? trim($_POST['price']) : '';

            // check if input fields are empty
            if (empty($manufacturerName) || empty($productName) || $price === '') {
                throw new Exception('Missing required fields.');
            }

            // Get Manufacturer ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ? AND Type = 'Manufacturer'");
            if (!$stmt) throw new Exception("Prepare failed (Get Manufacturer ID): " . $conn->error);
            $stmt->bind_param("s", $manufacturerName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Manufacturer ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Manufacturer not found.");
            }
            $stmt->bind_result($manufacturerID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
            $stmt->store_result();
             if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Product not found.");
            }
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // Update SuppliesProduct
            $stmt = $conn->prepare("UPDATE SuppliesProduct SET SupplyPrice = ? WHERE SupplierID = ? AND ProductID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Update Price): " . $conn->error);
            $stmt->bind_param("dii", $price, $manufacturerID, $productID);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Update Price): " . $stmt->error);
            }
            
            // check if rows were affected 
            if ($stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Price updated successfully!']);
            } else {
                echo json_encode(['status' => 'info', 'message' => 'No changes made. The price may already be set to this value.']);
            }
            $stmt->close();
            
            if ($conn) $conn->close();
            exit;
        } 
        // remove supply
        else if (isset($_POST['action']) && $_POST['action'] === 'remove_supply') {
            $manufacturerName = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
            $productName = isset($_POST['product']) ? trim($_POST['product']) : '';

            // check if input fields are empty
            if (empty($manufacturerName) || empty($productName)) {
                throw new Exception('Missing required fields.');
            }
            // start transaction
            $conn->autocommit(FALSE);

            // Get Manufacturer ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ? AND Type = 'Manufacturer'");
            if (!$stmt) throw new Exception("Prepare failed (Get Manufacturer ID): " . $conn->error);
            $stmt->bind_param("s", $manufacturerName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Manufacturer ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Manufacturer not found.");
            }
            $stmt->bind_result($manufacturerID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
            $stmt->store_result();
             if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Product not found.");
            }
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();
            
            // Delete from SuppliesProduct
            $stmt = $conn->prepare("DELETE FROM SuppliesProduct WHERE SupplierID = ? AND ProductID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Delete Supply): " . $conn->error);
            $stmt->bind_param("ii", $manufacturerID, $productID);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Delete Supply): " . $stmt->error);
            }
            
            // check if no rows were affected and the supply relationship does not exist
            if ($stmt->affected_rows === 0) {
                throw new Exception("The specified supply relationship does not exist.");
            }

            $stmt->close();

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Product supply relationship removed successfully!']);

            if ($conn) $conn->close();
            exit;
        } 
        // add receiving transaction
        else if (isset($_POST['action']) && $_POST['action'] === 'add_receiving_transaction') {
            // get shipment transaction ID, receiving company name, date, and quantity
            $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
            $conn->autocommit(FALSE);
            // check if input fields are empty
            try {
                // This is the TransactionID of the ORIGINAL SHIPMENT this is provided by user
                $shipmentTransactionId = isset($_POST['transactionId']) ? intval($_POST['transactionId']) : 0;
                $receivingCompanyName = isset($_POST['receivingCompany']) ? trim($_POST['receivingCompany']) : '';
                $date = isset($_POST['date']) ? trim($_POST['date']) : '';
                $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;

                // check if input fields are empty
                if (empty($shipmentTransactionId) || empty($receivingCompanyName) || empty($date) || $quantity <= 0) {
                    throw new Exception("All fields are required and quantity must be positive.");
                }

                // Create a new, unique TransactionID for THIS receiving event.
                $stmt = $conn->prepare("INSERT INTO InventoryTransaction (Type) VALUES ('Receiving')");
                if (!$stmt) throw new Exception("Prepare failed (Transaction Insert): " . $conn->error);
                if (!$stmt->execute()) throw new Exception("Execute failed (Transaction Insert): " . $stmt->error);
                $newReceivingTransactionId = $conn->insert_id;
                $stmt->close();

                // Get the Receiving Company's ID for validation.
                $receivingCompanyId = 0;
                $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                if (!$stmt) throw new Exception("Prepare failed (Get Company ID): " . $conn->error);
                $stmt->bind_param("s", $receivingCompanyName);
                if (!$stmt->execute()) throw new Exception("Execute failed (Get Company ID): " . $stmt->error);
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($receivingCompanyId);
                    $stmt->fetch();
                } else {
                    throw new Exception("Receiving company not found.");
                }
                $stmt->close();

                // Find the ShipmentID using the user-provided shipment's TransactionID.
                $shipmentId = 0;
                $destinationCompanyId = 0;
                $stmt = $conn->prepare("SELECT ShipmentID, DestinationCompanyID FROM Shipping WHERE TransactionID = ?");
                if (!$stmt) throw new Exception("Prepare failed (Find Shipment): " . $conn->error);
                $stmt->bind_param("i", $shipmentTransactionId);
                if (!$stmt->execute()) throw new Exception("Execute failed (Find Shipment): " . $stmt->error);
                $stmt->store_result();

                // check if shipment record exists
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($shipmentId, $destinationCompanyId);
                    $stmt->fetch();
                    // check if the receiving company does not match the destination on the shipping record
                    if ($destinationCompanyId != $receivingCompanyId) {
                        throw new Exception("The receiving company does not match the destination on the shipping record.");
                    }
                } else {
                    throw new Exception("No shipping record found for the provided Transaction ID.");
                }
                $stmt->close();

                // Check if this shipment has already been received.
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Receiving WHERE ShipmentID = ?");
                if (!$stmt) throw new Exception("Prepare failed (Check Existing): " . $conn->error);
                $stmt->bind_param("i", $shipmentId);
                if (!$stmt->execute()) throw new Exception("Execute failed (Check Existing): " . $stmt->error);
                $stmt->store_result();
                $count = 0;
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                if ($count > 0) {
                    throw new Exception("A receiving transaction for this shipment already exists. To make changes, please navigate to the 'Existing Transaction' section.");
                }

                // Insert the new receiving record.
                $stmt = $conn->prepare("INSERT INTO Receiving (ReceivingID, TransactionID, ShipmentID, ReceiverCompanyID, ReceivedDate, QuantityReceived) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed (Insert Receiving): " . $conn->error);
                $stmt->bind_param("iiisid", $shipmentId, $newReceivingTransactionId, $shipmentId, $receivingCompanyId, $date, $quantity);
                
                if (!$stmt->execute()) throw new Exception("Execute failed (Insert Receiving): " . $stmt->error);
                
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $response = ['status' => 'success', 'message' => 'Receiving transaction recorded successfully!'];
                } else {
                    throw new Exception("Failed to record the receiving transaction.");
                }
                $stmt->close();

            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = $e->getMessage();
            }

            echo json_encode($response);
            if ($conn) $conn->close();
            exit;
        } 
        // update shipping or receiving transaction
        else if (isset($_POST['action']) && $_POST['action'] === 'update_shipping_transaction' || isset($_POST['action']) && $_POST['action'] === 'update_receiving_transaction') {
            $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
            $conn->autocommit(FALSE);

            // get TransactionID and updates
            try {
                $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
                $updates = isset($_POST['updates']) ? json_decode($_POST['updates'], true) : [];

                // check if TransactionID and updates are empty
                if (empty($transactionId) || empty($updates)) {
                    throw new Exception("Transaction ID and update fields are required.");
                }
                
                // check if action is update shipping transaction
                $isShipping = $_POST['action'] === 'update_shipping_transaction';
                $table = $isShipping ? 'Shipping' : 'Receiving';
                $whereColumn = 'TransactionID';

                // initialize set clauses, bind values, and bind types
                $setClauses = [];
                $bindValues = [];
                $bindTypes = '';

                // map columns to their corresponding database columns
                $columnMap = [
                    'Distributor' => ['col' => 'DistributorID', 'type' => 'i', 'isId' => true],
                    'Shipping Company' => ['col' => 'SourceCompanyID', 'type' => 'i', 'isId' => true],
                    'Receiving Company' => ['col' => $isShipping ? 'DestinationCompanyID' : 'ReceiverCompanyID', 'type' => 'i', 'isId' => true],
                    'Product' => ['col' => 'ProductID', 'type' => 'i', 'isId' => true, 'isProduct' => true],
                    'Promised Delivery Date' => ['col' => 'PromisedDate', 'type' => 's', 'isId' => false],
                    'Actual Delivery Date' => ['col' => 'ActualDate', 'type' => 's', 'isId' => false],
                    'Date Received' => ['col' => 'ReceivedDate', 'type' => 's', 'isId' => false],
                    'Quantity' => ['col' => $isShipping ? 'Quantity' : 'QuantityReceived', 'type' => 'd', 'isId' => false]
                ];
                
                // loop through updates
                foreach ($updates as $key => $value) {
                    // check if key is in column map
                    if (isset($columnMap[$key])) {
                        $mapping = $columnMap[$key];
                        $setClauses[] = "{$mapping['col']} = ?";
                        $bindTypes .= $mapping['type'];

                        // check if column is an ID
                        if ($mapping['isId']) {
                            // check if column is a product 
                            if (isset($mapping['isProduct']) && $mapping['isProduct']) {
                                $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
                             } else {
                                $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                             }
                            // check if prepare failed
                             if (!$stmt) throw new Exception("Prepare failed for ID lookup.");
                             $stmt->bind_param("s", $value);
                             $stmt->execute();
                             $stmt->store_result();
                             // check if rows were found
                             if ($stmt->num_rows > 0) {
                                 $id = 0;
                                 $stmt->bind_result($id);
                                 $stmt->fetch();
                                 $bindValues[] = $id;
                             } else {
                                 throw new Exception("Could not find ID for: " . $value);
                             }
                             $stmt->close();
                        } else {
                            $bindValues[] = ($key === 'Actual Delivery Date' && empty($value)) ? null : $value;
                        }
                    }
                }

                // check if there are no valid fields to update
                if (empty($setClauses)) {
                    throw new Exception("No valid fields to update.");
                }
                
                // prepare update statement
                $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE {$whereColumn} = ?";
                $bindTypes .= 'i';
                $bindValues[] = $transactionId;

                // prepare statement
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed (Update): " . $conn->error);
                
                // Use call_user_func_array for bind_param with dynamic parameters
                $params = array_merge([$bindTypes], $bindValues);
                $refs = [];
                // loop through parameters
                foreach($params as $key => $value) {
                    $refs[$key] = &$params[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $refs);
                
                // check if execute failed
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (Update): " . $stmt->error);
                }
                
                // check if rows were affected
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $response = ['status' => 'success', 'message' => 'Transaction updated successfully!'];
                } else {
                     $conn->rollback();
                    $response['message'] = 'No rows were updated. The data may have been unchanged.';
                }
                $stmt->close();
                
            } 
            // catch exception
                catch (Exception $e) {
                $conn->rollback();
                $response['message'] = $e->getMessage();
            }

            // echo response
            echo json_encode($response);
            if ($conn) $conn->close();
            exit;
        } else {
            throw new Exception("Invalid POST action.");
        }
    }

    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Fetch dropdown options
        if (isset($_GET['fetch_options'])) {
            $types = [];
            $tiers = [];

            // get distinct types
            $typeQuery = "SELECT DISTINCT Type FROM Company ORDER BY Type";
            $typeResult = $conn->query($typeQuery);
            if (!$typeResult) throw new Exception('Query failed for types: ' . $conn->error);
            while ($row = $typeResult->fetch_assoc()) {
                $types[] = $row['Type'];
            }

            // get distinct tiers
            $tierQuery = "SELECT DISTINCT TierLevel FROM Company ORDER BY TierLevel";
            $tierResult = $conn->query($tierQuery);
            if (!$tierResult) throw new Exception('Query failed for tiers: ' . $conn->error);
            while ($row = $tierResult->fetch_assoc()) {
                $tiers[] = $row['TierLevel'];
            }

            // echo types and tiers
            echo json_encode(['types' => $types, 'tiers' => $tiers]);
            if ($conn) $conn->close();
            exit;
        }

        // check if dependency exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_dependency') {
            // get upstream and downstream company names
            $upstreamName = isset($_GET['upstream']) ? trim($_GET['upstream']) : '';
            $downstreamName = isset($_GET['downstream']) ? trim($_GET['downstream']) : '';

            // check if upstream and downstream company names are empty
            if (empty($upstreamName) || empty($downstreamName)) {
                throw new Exception('Missing upstream or downstream company name.');
            }

            // prepare statement to check if dependency exists
            $stmt = $conn->prepare("
                SELECT 1 FROM DependsOn do
                JOIN Company up ON do.UpstreamCompanyID = up.CompanyID
                JOIN Company down ON do.DownstreamCompanyID = down.CompanyID
                WHERE up.CompanyName = ? AND down.CompanyName = ?
            ");

            // check if prepare failed
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            // bind parameters to statement
            $stmt->bind_param("ss", $upstreamName, $downstreamName);
            // check if execute failed
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

            // store result
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            // echo exists
            echo json_encode(['exists' => $exists]);
            if ($conn) $conn->close();
            exit;
        }

        // check if route exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_route') {
            $distributorName = isset($_GET['distributor']) ? trim($_GET['distributor']) : '';
            $fromName = isset($_GET['from_company']) ? trim($_GET['from_company']) : '';
            $toName = isset($_GET['to_company']) ? trim($_GET['to_company']) : '';

            // check if distributor, from, and to company names are empty
            if (empty($distributorName) || empty($fromName) || empty($toName)) {
                throw new Exception('Missing company names to check route.');
            }

            // Re-use getCompanyID function if not already in scope
            if (!function_exists('getCompanyID')) {
                // function to get companyID
                function getCompanyID($conn, $companyName) {
                    $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                    if (!$stmt) return null;
                    $stmt->bind_param("s", $companyName);
                    if (!$stmt->execute()) return null;
                    $stmt->store_result();
                    if ($stmt->num_rows === 0) { $stmt->close(); return null; }
                    $stmt->bind_result($companyID);
                    $stmt->fetch();
                    $stmt->close();
                    return $companyID;
                }
            }

            // get distributor, from, and to company IDs
            $distributorID = getCompanyID($conn, $distributorName);
            $fromCompanyID = getCompanyID($conn, $fromName);
            $toCompanyID = getCompanyID($conn, $toName);

            // check if distributor, from, and to company IDs are null
            if ($distributorID === null || $fromCompanyID === null || $toCompanyID === null) {
                 echo json_encode(['exists' => false, 'error' => 'One or more companies not found.']);
                 if ($conn) $conn->close();
                 exit;
            }

            // prepare statement to check if route exists
            $stmt = $conn->prepare("SELECT 1 FROM OperatesLogistics WHERE DistributorID = ? AND FromCompanyID = ? AND ToCompanyID = ?");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            
            // bind parameters to statement
            $stmt->bind_param("iii", $distributorID, $fromCompanyID, $toCompanyID);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            
            // store result
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            // echo exists
            echo json_encode(['exists' => $exists]);
            if ($conn) $conn->close();
            exit;
        }

        // check if supply exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_supply') {
            // get manufacturer and product names
            $manufacturerName = isset($_GET['manufacturer']) ? trim($_GET['manufacturer']) : '';
            $productName = isset($_GET['product']) ? trim($_GET['product']) : '';

            // check if manufacturer and product names are empty
            if (empty($manufacturerName) || empty($productName)) {
                throw new Exception('Manufacturer and product names are required.');
            }

            // Get Manufacturer ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ? AND Type = 'Manufacturer'");
            if (!$stmt) throw new Exception("Prepare failed (Get Manufacturer ID): " . $conn->error);
            $stmt->bind_param("s", $manufacturerName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Manufacturer ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                echo json_encode(['exists' => false, 'error' => 'Manufacturer not found.']);
                if ($conn) $conn->close();
                exit;
            }
            // bind result
            $stmt->bind_result($manufacturerID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
            $stmt->store_result();
             if ($stmt->num_rows === 0) {
                echo json_encode(['exists' => false, 'error' => 'Product not found.']);
                if ($conn) $conn->close();
                exit;
            }
            // bind result
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // Check SuppliesProduct table
            $stmt = $conn->prepare("SELECT 1 FROM SuppliesProduct WHERE SupplierID = ? AND ProductID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Check SuppliesProduct): " . $conn->error);
            $stmt->bind_param("ii", $manufacturerID, $productID);
            if (!$stmt->execute()) throw new Exception("Execute failed (Check SuppliesProduct): " . $stmt->error);
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            echo json_encode(['exists' => $exists]);
            if ($conn) $conn->close();
            exit;
        }

        // check if shipping record exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_shipping_record') {
            $distributorName = isset($_GET['distributor']) ? trim($_GET['distributor']) : '';
            $shippingCompanyName = isset($_GET['shipping_company']) ? trim($_GET['shipping_company']) : '';
            $receivingCompanyName = isset($_GET['receiving_company']) ? trim($_GET['receiving_company']) : '';
            $productName = isset($_GET['product']) ? trim($_GET['product']) : '';

            // check if distributor, shipping company, receiving company, and product names are empty
            if (empty($distributorName) || empty($shippingCompanyName) || empty($receivingCompanyName) || empty($productName)) {
                throw new Exception('All fields are required to check for a shipping record.');
            }

            // Get Distributor ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            $stmt->bind_param("s", $distributorName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) { echo json_encode(['exists' => false, 'message' => 'Distributor not found.']); $stmt->close(); $conn->close(); exit; }
            $stmt->bind_result($distributorID);
            $stmt->fetch();
            $stmt->close();

            // Get Shipping Company ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            $stmt->bind_param("s", $shippingCompanyName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) { echo json_encode(['exists' => false, 'message' => 'Shipping company not found.']); $stmt->close(); $conn->close(); exit; }
            $stmt->bind_result($shippingCompanyID);
            $stmt->fetch();
            $stmt->close();

            // Get Receiving Company ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
            $stmt->bind_param("s", $receivingCompanyName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) { echo json_encode(['exists' => false, 'message' => 'Receiving company not found.']); $stmt->close(); $conn->close(); exit; }
            $stmt->bind_result($receivingCompanyID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            $stmt->bind_param("s", $productName);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) { echo json_encode(['exists' => false, 'message' => 'Product not found.']); $stmt->close(); $conn->close(); exit; }
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // Check for the shipping record
            $stmt = $conn->prepare("SELECT ShipmentID FROM Shipping WHERE DistributorID = ? AND SourceCompanyID = ? AND DestinationCompanyID = ? AND ProductID = ?");
            $stmt->bind_param("iiii", $distributorID, $shippingCompanyID, $receivingCompanyID, $productID);
            $stmt->execute();
            $stmt->store_result();
            
            // check if shipping record exists
            if ($stmt->num_rows > 0) {
                echo json_encode(['exists' => true]);
            } else {
                echo json_encode(['exists' => false, 'message' => 'No matching shipping record found for the provided details.']);
            }
            $stmt->close();

            // close connection
            if ($conn) $conn->close();
            exit;
        }

        // check if supply price exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_supply_price') {
            $manufacturerName = isset($_GET['manufacturer']) ? trim($_GET['manufacturer']) : '';
            $productName = isset($_GET['product']) ? trim($_GET['product']) : '';

            // check if manufacturer and product names are empty
            if (empty($manufacturerName) || empty($productName)) {
                throw new Exception('Manufacturer and product names are required.');
            }

            // Get Manufacturer ID
            $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ? AND Type = 'Manufacturer'");
            if (!$stmt) throw new Exception("Prepare failed (Get Manufacturer ID): " . $conn->error);
            $stmt->bind_param("s", $manufacturerName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Manufacturer ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                echo json_encode(['exists' => false, 'error' => 'Manufacturer not found.']);
                if ($conn) $conn->close();
                exit;
            }
            // bind result
            $stmt->bind_result($manufacturerID);
            $stmt->fetch();
            $stmt->close();

            // Get Product ID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            if (!$stmt) throw new Exception("Prepare failed (Get Product ID): " . $conn->error);
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed (Get Product ID): " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                echo json_encode(['exists' => false, 'error' => 'Product not found.']);
                if ($conn) $conn->close();
                exit;
            }
            // bind result
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // Check SuppliesProduct table
            $stmt = $conn->prepare("SELECT SupplyPrice FROM SuppliesProduct WHERE SupplierID = ? AND ProductID = ?");
            if (!$stmt) throw new Exception("Prepare failed (Check SuppliesProduct): " . $conn->error);
            $stmt->bind_param("ii", $manufacturerID, $productID);
            if (!$stmt->execute()) throw new Exception("Execute failed (Check SuppliesProduct): " . $stmt->error);
            $stmt->store_result();
            
            // check if supply price exists
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($price);
                $stmt->fetch();
                echo json_encode(['exists' => true, 'price' => $price]);
            } else {
                echo json_encode(['exists' => false]);
            }
            $stmt->close();

            // close connection
            if ($conn) $conn->close();
            exit;
        }

        // check if shipping exists
        if (isset($_GET['action']) && $_GET['action'] === 'check_shipping_exists') {
            $distributorName = isset($_GET['distributor']) ? trim($_GET['distributor']) : '';
            $sourceName = isset($_GET['source_company']) ? trim($_GET['source_company']) : '';
            $destinationName = isset($_GET['destination_company']) ? trim($_GET['destination_company']) : '';
            $productName = isset($_GET['product']) ? trim($_GET['product']) : '';

            // check if distributor, source, destination, and product names are empty
            if (empty($distributorName) || empty($sourceName) || empty($destinationName) || empty($productName)) {
                throw new Exception('Missing parameters for shipment check.');
            }

            // function to get companyID
            if (!function_exists('getCompanyID')) {
                function getCompanyID($conn, $companyName) {
                    $stmt = $conn->prepare("SELECT CompanyID FROM Company WHERE CompanyName = ?");
                    if (!$stmt) return null;
                    $stmt->bind_param("s", $companyName);
                    if (!$stmt->execute()) return null;
                    $stmt->store_result();
                    if ($stmt->num_rows === 0) { $stmt->close(); return null; }
                    $stmt->bind_result($companyID);
                    $stmt->fetch();
                    $stmt->close();
                    return $companyID;
                }
            }

            // get distributor, source, and destination company IDs
            $distributorID = getCompanyID($conn, $distributorName);
            $sourceCompanyID = getCompanyID($conn, $sourceName);
            $destinationCompanyID = getCompanyID($conn, $destinationName);

            // prepare statement to get productID
            $stmt = $conn->prepare("SELECT ProductID FROM Product WHERE ProductName = ?");
            // check if prepare failed
            if (!$stmt) throw new Exception("Prepare failed for ProductID: " . $conn->error);
            // bind parameters to statement
            $stmt->bind_param("s", $productName);
            if (!$stmt->execute()) throw new Exception("Execute failed for ProductID: " . $stmt->error);
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Product not found: $productName");
            }
            // bind result
            $stmt->bind_result($productID);
            $stmt->fetch();
            $stmt->close();

            // check if distributor, source, destination, and product IDs are null
            if ($distributorID === null || $sourceCompanyID === null || $destinationCompanyID === null || $productID === null) {
                 echo json_encode(['exists' => false, 'error' => 'One or more entities not found.']);
                 if ($conn) $conn->close();
                 exit;
            }

            // prepare statement to check if shipping exists
            $stmt = $conn->prepare("SELECT 1 FROM Shipping WHERE DistributorID = ? AND SourceCompanyID = ? AND DestinationCompanyID = ? AND ProductID = ?");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            
            // bind parameters to statement
            $stmt->bind_param("iiii", $distributorID, $sourceCompanyID, $destinationCompanyID, $productID);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            
            // store result
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            // echo exists
            echo json_encode(['exists' => $exists]);
            if ($conn) $conn->close();
            exit;
        }

        // get company context
        if (isset($_GET['action']) && $_GET['action'] === 'get_company_context') {
            $companyName = isset($_GET['company']) ? trim($_GET['company']) : '';
            // check if company name is empty
            if (empty($companyName)) {
                throw new Exception('Company name is required.');
            }

            // prepare statement to get company context
            $stmt = $conn->prepare("
                SELECT c.CompanyName, c.Type, c.TierLevel, m.FactoryCapacity
                FROM Company c
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                LEFT JOIN Manufacturer m ON c.CompanyID = m.CompanyID
                WHERE c.CompanyName = ?
            ");
            // check if prepare failed
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

            // bind parameters to statement
            $stmt->bind_param("s", $companyName);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

            // store result
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new Exception("Company not found.");
            }

            // bind result
            $stmt->bind_result($companyNameResult, $companyType, $tierLevel, $factoryCapacity);
            $stmt->fetch();
            
            // create company data
            $companyData = [
                'CompanyName' => $companyNameResult,
                'company_type' => $companyType,
                'tier_level' => $tierLevel,
                'FactoryCapacity' => $factoryCapacity
            ];

            $stmt->close();

            // echo company data
            echo json_encode(['status' => 'success', 'company' => $companyData]);
            if ($conn) $conn->close();
            exit;
        }

        // get transaction details
        if (isset($_GET['action']) && $_GET['action'] === 'get_transaction_details') {
            $transactionId = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            $response = ['status' => 'error', 'message' => 'Invalid request.'];

            // check if transaction ID is greater than 0 and type is shipping or receiving
            if ($transactionId > 0 && ($type === 'shipping' || $type === 'receiving')) {
                try {
                    // if type is shipping
                    if ($type === 'shipping') {
                        // prepare statement to get shipping details
                        $sql = "
                            SELECT 
                                s.PromisedDate, s.ActualDate, s.Quantity,
                                dist.CompanyName, src.CompanyName, dest.CompanyName, p.ProductName
                            FROM Shipping s
                            LEFT JOIN Company dist ON s.DistributorID = dist.CompanyID
                            LEFT JOIN Company src ON s.SourceCompanyID = src.CompanyID
                            LEFT JOIN Company dest ON s.DestinationCompanyID = dest.CompanyID
                            LEFT JOIN Product p ON s.ProductID = p.ProductID
                            WHERE s.TransactionID = ?
                        ";
                        $stmt = $conn->prepare($sql);
                        // check if prepare failed
                        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                        // bind parameters to statement
                        $stmt->bind_param("i", $transactionId);
                        // execute statement
                        $stmt->execute();
                        // store result
                        $stmt->store_result();
                        // check if shipping record exists
                        if ($stmt->num_rows > 0) {
                            $promisedDate = ''; $actualDate = null; $quantity = 0;
                            $distributor = ''; $shippingCompany = ''; $receivingCompany = ''; $product = '';
                            $stmt->bind_result($promisedDate, $actualDate, $quantity, $distributor, $shippingCompany, $receivingCompany, $product);
                            $stmt->fetch();
                            // create data
                            $data = [
                                'PromisedDate' => $promisedDate, 'ActualDate' => $actualDate, 'Quantity' => $quantity,
                                'Distributor' => $distributor, 'ShippingCompany' => $shippingCompany,
                                'ReceivingCompany' => $receivingCompany, 'Product' => $product
                            ];
                            // create response
                            $response = ['status' => 'success', 'data' => $data];
                        // if shipping record does not exist
                        } else {
                             $response['message'] = 'No shipping transaction found with that ID.';
                        }
                    } else { // if type is receiving
                        // prepare statement to get receiving details
                         $sql = "
                            SELECT r.ReceivedDate, r.QuantityReceived, rcv_comp.CompanyName
                            FROM Receiving r
                            LEFT JOIN Company rcv_comp ON r.ReceiverCompanyID = rcv_comp.CompanyID
                            WHERE r.TransactionID = ?
                        ";
                         $stmt = $conn->prepare($sql);
                        // check if prepare failed
                        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                        // bind parameters to statement
                        $stmt->bind_param("i", $transactionId);
                        // execute statement
                        $stmt->execute();
                        // store result
                        $stmt->store_result();
                        // check if receiving record exists
                         if ($stmt->num_rows > 0) {
                            $receivedDate = ''; $quantity = 0; $receivingCompany = '';
                            $stmt->bind_result($receivedDate, $quantity, $receivingCompany);
                            $stmt->fetch();
                            // create data
                            $data = [
                                'ReceivedDate' => $receivedDate, 'Quantity' => $quantity,
                                'ReceivingCompany' => $receivingCompany
                            ];
                            // create response
                            $response = ['status' => 'success', 'data' => $data];
                        // if receiving record does not exist
                        } else {
                             $response['message'] = 'No receiving transaction found with that ID.';
                        }
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $response['message'] = $e->getMessage();
                }
            }
            
            // echo response
            echo json_encode($response);
            if ($conn) $conn->close();
            exit;
        }

        // Autocomplete for Companies (GET)
        if (isset($_GET['term'])) {
            $term = $conn->real_escape_string($_GET['term']);
            $query = "SELECT CompanyName FROM Company WHERE CompanyName LIKE '%$term%'";

            if (isset($_GET['type'])) {
                $companyTypes = explode(',', $_GET['type']);
                $sanitizedTypes = [];
                foreach ($companyTypes as $type) {
                    $sanitizedTypes[] = "'" . $conn->real_escape_string(trim($type)) . "'";
                }
                if (!empty($sanitizedTypes)) {
                    // Use LOWER() on both the column and the input for case-insensitive matching
                    $query .= " AND LOWER(Type) IN (" . strtolower(implode(',', $sanitizedTypes)) . ")";
                }
            }

            // add limit to query
            $query .= " LIMIT 10";
            $result = $conn->query($query);
            if (!$result) throw new Exception('Query failed: ' . $conn->error);
            
            // store result
            $names = array();
            while ($row = $result->fetch_assoc()) {
                $names[] = $row['CompanyName'];
            }
            // echo names
            echo json_encode($names);
            if ($conn) $conn->close();
            exit;
        }
        
        // Autocomplete for Products (GET)
        if (isset($_GET['product_term'])) {
            $term = $conn->real_escape_string($_GET['product_term']);
            $query = "SELECT ProductName FROM Product WHERE ProductName LIKE '%$term%' LIMIT 10";
            $result = $conn->query($query);
            if (!$result) throw new Exception('Query failed: ' . $conn->error);
            
            // store result
            $names = array();
            while ($row = $result->fetch_assoc()) {
                $names[] = $row['ProductName'];
            }
            // echo names
            echo json_encode($names);
            if ($conn) $conn->close();
            exit;
        }

        // Company Details Fetching (GET)
        if (isset($_GET['companyName'])) {
            $companyName = $conn->real_escape_string($_GET['companyName']);

            // prepare statement to get company details
            $sqlCompany = "
                SELECT 
                    c.CompanyID,
                    c.CompanyName,
                    CONCAT(l.City, ', ', l.CountryName, ', ', l.ContinentName) as location,
                    c.Type as company_type,
                    c.TierLevel as tier_level,
                    m.FactoryCapacity
                FROM Company c
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                LEFT JOIN Manufacturer m ON c.CompanyID = m.CompanyID
                WHERE c.CompanyName = '$companyName'
            ";
            // execute statement
            $resComp = $conn->query($sqlCompany);
            if (!$resComp) {
                throw new Exception("Error fetching company data: " . $conn->error);
            }
            // check if company data is found
            if ($resComp->num_rows === 0) {
                // Send error in JSON format instead of dying
                echo json_encode(array('error' => 'Company not found.'));
                if ($conn) $conn->close();
                exit;
            }
            // fetch company data
            $companyData = $resComp->fetch_assoc();
            $companyID = $companyData['CompanyID'];

            // Add FactoryCapacity check here to prevent errors
            if ($companyData['company_type'] !== 'Manufacturer') {
                $companyData['FactoryCapacity'] = 'N/A';
            }

            // Depends On (Upstream)
            $sqlUpstream = "SELECT GROUP_CONCAT(c.CompanyName SEPARATOR ', ') as depends_on FROM DependsOn d JOIN Company c ON d.UpstreamCompanyID = c.CompanyID WHERE d.DownstreamCompanyID = $companyID";
            $resUp = $conn->query($sqlUpstream);
            if (!$resUp) throw new Exception("Error fetching upstream dependencies: " . $conn->error);
            $rowUp = $resUp->fetch_assoc();
            $companyData['depends_on'] = ($rowUp && $rowUp['depends_on']) ? $rowUp['depends_on'] : 'None';

            // Dependents (Downstream)
            $sqlDownstream = "SELECT GROUP_CONCAT(c.CompanyName SEPARATOR ', ') as dependents FROM DependsOn d JOIN Company c ON d.DownstreamCompanyID = c.CompanyID WHERE d.UpstreamCompanyID = $companyID";
            $resDown = $conn->query($sqlDownstream);
            if (!$resDown) throw new Exception("Error fetching downstream dependencies: " . $conn->error);
            $rowDown = $resDown->fetch_assoc();
            $companyData['dependents'] = ($rowDown && $rowDown['dependents']) ? $rowDown['dependents'] : 'None';

            // Products & Diversity
            $sqlProd = "SELECT p.ProductName, p.Category FROM SuppliesProduct sp JOIN Product p ON sp.ProductID = p.ProductID WHERE sp.SupplierID = $companyID";
            $resProd = $conn->query($sqlProd);
            if (!$resProd) throw new Exception("Error fetching products: " . $conn->error);
            $productsList = array();
            $categories = array();
            while($r = $resProd->fetch_assoc()) {
                $productsList[] = $r['ProductName'];
                $categories[] = $r['Category'];
            }
            
            // check if products list is empty
            if (empty($productsList)) {
                $sqlProdShip = "SELECT DISTINCT p.ProductName, p.Category FROM Shipping s JOIN Product p ON s.ProductID = p.ProductID WHERE s.SourceCompanyID = $companyID LIMIT 20";
                $resProdShip = $conn->query($sqlProdShip);
                if (!$resProdShip) throw new Exception("Error fetching shipped products: " . $conn->error);
                while($r = $resProdShip->fetch_assoc()) {
                    $productsList[] = $r['ProductName'];
                    $categories[] = $r['Category'];
                }
            }
            // create products and diversity
            $companyData['products'] = !empty($productsList) ? implode(', ', array_unique($productsList)) : 'None';
            $companyData['diversity'] = count(array_unique($categories)) > 0 ? count(array_unique($categories)) . ' Categories (' . implode(', ', array_unique($categories)) . ')' : 'None';

            // Transaction Tables
            $sqlShipping = "SELECT s.ActualDate as date, p.ProductName as product, s.Quantity as volume FROM Shipping s JOIN Product p ON s.ProductID = p.ProductID WHERE s.SourceCompanyID = $companyID ORDER BY s.ActualDate DESC LIMIT 50";
            $shippingData = array();
            $resShip = $conn->query($sqlShipping);
            if (!$resShip) throw new Exception("Error fetching shipping data: " . $conn->error);
            if($resShip) while($r = $resShip->fetch_assoc()) $shippingData[] = $r;

            // prepare statement to get receiving data
            $sqlReceiving = "SELECT r.ReceivedDate as date, p.ProductName as product, r.QuantityReceived as volume FROM Receiving r JOIN Shipping s ON r.ShipmentID = s.ShipID JOIN Product p ON s.ProductID = p.ProductID WHERE r.ReceiverCompanyID = $companyID ORDER BY r.ReceivedDate DESC LIMIT 50";
            $receivingData = array();
            $resRec = $conn->query($sqlReceiving);
            if (!$resRec) throw new Exception("Error fetching receiving data: " . $conn->error);
            if($resRec) while($r = $resRec->fetch_assoc()) $receivingData[] = $r;

            // prepare statement to get adjustment data
            $sqlAdjustment = "SELECT ia.AdjustmentDate as date, p.ProductName as product, ia.QuantityChange as volume FROM InventoryAdjustment ia JOIN Product p ON ia.ProductID = p.ProductID WHERE ia.CompanyID = $companyID ORDER BY ia.AdjustmentDate DESC LIMIT 50";
            $adjustmentData = array();
            $resAdj = $conn->query($sqlAdjustment);
            if (!$resAdj) throw new Exception("Error fetching adjustment data: " . $conn->error);
            if($resAdj) while($r = $resAdj->fetch_assoc()) $adjustmentData[] = $r;

            // echo company data, shipping data, receiving data, and adjustment data
            echo json_encode(array(
                'company' => $companyData,
                'shipping' => $shippingData,
                'receiving' => $receivingData,
                'adjustments' => $adjustmentData
            ));
            // close connection
            if ($conn) $conn->close();
            exit;
        }
    }


    // Fallback for invalid request
    throw new Exception('Invalid request.');

} catch (Exception $e) {
    if ($conn && $conn->ping()) {
        // You might rollback a transaction here if you had one
    }
    
    // Set HTTP status code for errors
    http_response_code(400); // Bad Request by default
    
    // get error message
    $msg = $e->getMessage();
    echo json_encode(array('status' => 'error', 'message' => $msg));
    if ($conn) {
        $conn->close();
    }
}
?>
