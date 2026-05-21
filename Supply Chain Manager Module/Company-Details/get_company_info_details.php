<?php
// Start output buffering so we can safely clear any partial output
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Data Base Conections 
$servername = "mydb.itap.purdue.edu";
$username   = "g1151919";
$password   = "yM84JeHv";
$database   = "g1151919";

$conn = null;

try {
    // Connect to data base 
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);
    if (!$conn->set_charset("utf8")) throw new Exception("Error loading charset utf8: " . $conn->error);

    // Read and Validate Input 
    $companyName = isset($_GET['company']) ? trim($_GET['company']) : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    if (!$companyName) throw new Exception("Company name is required.");

    $cNameSafe = $conn->real_escape_string($companyName);

    // 1) Company Details 
    $sqlCompany = "
        SELECT 
            c.CompanyID,
            c.CompanyName,
            CONCAT(l.City, ', ', l.CountryName, ', ', l.ContinentName) as location,
            c.Type as company_type,
            c.TierLevel as tier_level,
            -- Manufacturer Capacity
            m.FactoryCapacity
        FROM Company c
        JOIN Location l ON c.LocationID = l.LocationID
        LEFT JOIN Manufacturer m ON c.CompanyID = m.CompanyID
        WHERE c.CompanyName = '$cNameSafe'
    ";
    $resComp = $conn->query($sqlCompany);
    if (!$resComp || $resComp->num_rows === 0) throw new Exception("Company not found.");
    $companyData = $resComp->fetch_assoc();
    $companyID = $companyData['CompanyID'];

    // Depends On 
    $sqlUpstream = "
        SELECT GROUP_CONCAT(c.CompanyName SEPARATOR ', ') as depends_on
        FROM DependsOn d
        JOIN Company c ON d.UpstreamCompanyID = c.CompanyID
        WHERE d.DownstreamCompanyID = $companyID
    ";
    $resUp = $conn->query($sqlUpstream);
    $rowUp = $resUp->fetch_assoc();
    $companyData['depends_on'] = $rowUp['depends_on'] ? $rowUp['depends_on'] : 'None';

    // Dependents
    $sqlDownstream = "
        SELECT GROUP_CONCAT(c.CompanyName SEPARATOR ', ') as dependents
        FROM DependsOn d
        JOIN Company c ON d.DownstreamCompanyID = c.CompanyID
        WHERE d.UpstreamCompanyID = $companyID
    ";
    $resDown = $conn->query($sqlDownstream);
    $rowDown = $resDown->fetch_assoc();
    $companyData['dependents'] = $rowDown['dependents'] ? $rowDown['dependents'] : 'None';

    // Products and Diversity
    $sqlProd = "
        SELECT p.ProductName, p.Category
        FROM SuppliesProduct sp
        JOIN Product p ON sp.ProductID = p.ProductID
        WHERE sp.SupplierID = $companyID
    ";
    $resProd = $conn->query($sqlProd);
    $productsList = [];
    $categories = [];
    while($r = $resProd->fetch_assoc()) {
        $productsList[] = $r['ProductName'];
        $categories[] = $r['Category'];
    }
    
    if (empty($productsList)) {
        $sqlProdShip = "
            SELECT DISTINCT p.ProductName, p.Category
            FROM Shipping s
            JOIN Product p ON s.ProductID = p.ProductID
            WHERE s.SourceCompanyID = $companyID
            LIMIT 20
        ";
        $resProdShip = $conn->query($sqlProdShip);
        while($r = $resProdShip->fetch_assoc()) {
            $productsList[] = $r['ProductName'];
            $categories[] = $r['Category'];
        }
    }

    $companyData['products'] = !empty($productsList) ? implode(', ', array_unique($productsList)) : 'None';
    $companyData['diversity'] = count(array_unique($categories)) . ' Categories (' . implode(', ', array_unique($categories)) . ')';

    // Capacity Logic
    if ($companyData['company_type'] !== 'Manufacturer') {
        $companyData['FactoryCapacity'] = 'N/A';
    }

    // Route Logic
    if ($companyData['company_type'] === 'Distributor') {
        $sqlRoute = "
            SELECT 
                cFrom.CompanyName as from_company,
                cTo.CompanyName as to_company
            FROM OperatesLogistics ol
            JOIN Company cFrom ON ol.FromCompanyID = cFrom.CompanyID
            JOIN Company cTo ON ol.ToCompanyID = cTo.CompanyID
            WHERE ol.DistributorID = $companyID
        ";
        $resRoute = $conn->query($sqlRoute);
        $routes = [];
        if ($resRoute) {
            while ($r = $resRoute->fetch_assoc()) {
                $routes[] = "From " . $r['from_company'] . " to " . $r['to_company'];
            }
        }
        $companyData['route'] = !empty($routes) ? implode('<br>', $routes) : 'None';
    } else {
        $companyData['route'] = 'N/A';
    }

    // Financial Health Score Logic
    $finHealth = 'N/A';
    $scoreCol = 'HealthScore';
    $dateCol = null;
    $colsSql = "SHOW COLUMNS FROM FinancialReport";
    $colsRes = $conn->query($colsSql);
    if ($colsRes) {
        while ($row = $colsRes->fetch_assoc()) {
            $field = $row['Field'];
            if ($dateCol === null) {
                if (stripos($field, 'Date') !== false || stripos($field, 'Time') !== false) {
                    $dateCol = $field;
                }
            } else {
                if (stripos($field, 'Report') !== false || strcasecmp($field, 'Date') === 0) {
                    $dateCol = $field;
                }
            }
        }
    }

    if ($dateCol) {
        $sqlFin = "SELECT $scoreCol FROM FinancialReport WHERE CompanyID = $companyID ORDER BY $dateCol DESC LIMIT 1";
        $resFin = $conn->query($sqlFin);
        if ($resFin && $row = $resFin->fetch_assoc()) {
            $finHealth = $row[$scoreCol];
        }
    } else {
        $sqlFin = "SELECT $scoreCol FROM FinancialReport WHERE CompanyID = $companyID LIMIT 1";
        $resFin = $conn->query($sqlFin);
        if ($resFin && $row = $resFin->fetch_assoc()) {
            $finHealth = $row[$scoreCol];
        }
    }
    $companyData['financial_health'] = $finHealth;

    // 2) Transactions Tables 
    $dateFilterShip = "";
    $dateFilterRec = "";
    $dateFilterAdj = "";
    
    if ($dateFrom) {
        $d = $conn->real_escape_string($dateFrom);
        $dateFilterShip .= " AND s.ActualDate >= '$d'";
        $dateFilterRec .= " AND r.ReceivedDate >= '$d'"; // Receiving uses ReceivedDate
        $dateFilterAdj .= " AND ia.AdjustmentDate >= '$d'";
    }
    if ($dateTo) {
        $d = $conn->real_escape_string($dateTo);
        $dateFilterShip .= " AND s.ActualDate <= '$d'";
        $dateFilterRec .= " AND r.ReceivedDate <= '$d'";
        $dateFilterAdj .= " AND ia.AdjustmentDate <= '$d'";
    }

    // Shipping Table 
    $sqlShipping = "
        SELECT s.ActualDate as date, p.ProductName as product, s.Quantity as volume
        FROM Shipping s
        JOIN Product p ON s.ProductID = p.ProductID
        WHERE s.SourceCompanyID = $companyID $dateFilterShip
        ORDER BY s.ActualDate DESC
        LIMIT 50
    ";
    $shippingData = [];
    $resShip = $conn->query($sqlShipping);
    if($resShip) while($r = $resShip->fetch_assoc()) $shippingData[] = $r;

    // Receiving Table 
    $sqlReceiving = "
        SELECT r.ReceivedDate as date, p.ProductName as product, r.QuantityReceived as volume
        FROM Receiving r
        JOIN Shipping s ON r.ShipmentID = s.ShipmentID
        JOIN Product p ON s.ProductID = p.ProductID
        WHERE r.ReceiverCompanyID = $companyID $dateFilterRec
        ORDER BY r.ReceivedDate DESC
        LIMIT 50
    ";
    $receivingData = [];
    $resRec = $conn->query($sqlReceiving);
    if($resRec) while($r = $resRec->fetch_assoc()) $receivingData[] = $r;

    // Adjustment Table
    $sqlAdjustment = "
        SELECT ia.AdjustmentDate as date, p.ProductName as product, ia.QuantityChange as volume
        FROM InventoryAdjustment ia
        JOIN Product p ON ia.ProductID = p.ProductID
        WHERE ia.CompanyID = $companyID $dateFilterAdj
        ORDER BY ia.AdjustmentDate DESC
        LIMIT 50
    ";
    $adjustmentData = [];
    $resAdj = $conn->query($sqlAdjustment);
    if($resAdj) while($r = $resAdj->fetch_assoc()) $adjustmentData[] = $r;

    echo json_encode([
        'company' => $companyData,
        'shipping' => $shippingData,
        'receiving' => $receivingData,
        'adjustments' => $adjustmentData
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(array("error" => $e->getMessage()));
}

if ($conn) $conn->close();
?>
