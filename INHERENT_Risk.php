<?php
// Start output buffering to prevent header issues
ob_start();

// Include database connection and header
include 'db.php';
include 'header.php';

// ==================== INITIALIZE VARIABLES ====================
$successMessage = '';
$errorMessage = '';
$marketParticipants = [];
$fiscalYears = [];
$defaultFiscalYearId = null;
$selectedMarketParticipant = null;
$selectedFiscalYear = null;
$displayData = [];
$qualitativeData = null;
$selectedMarketParticipantName = '';
$selectedFiscalYearName = '';
$activityRisksData = [];

// ==================== HANDLE QUALITATIVE FORM SUBMISSION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_qualitative'])) {
    $marketParticipant = $_POST['market_participant'];
    $fiscalYear = $_POST['fiscal_year'];
    $corpGov = $_POST['corporate_governance'];
    $policies = $_POST['policies_procedures'];
    $riskMgmt = $_POST['risk_management'];
    $internalControls = $_POST['internal_controls'];
    $compliance = $_POST['compliance_function'];
    $training = $_POST['training'];
    $reporting = $_POST['reporting_record_keeping'];
    
    // Check if record exists
    $checkSql = "SELECT COUNT(*) as count FROM RiskControlsAndMitigants 
                 WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($marketParticipant, $fiscalYear));
    
    if ($checkStmt === false) {
        $errorMessage = "❌ Database error: " . print_r(sqlsrv_errors(), true);
    } else {
        $checkResult = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        
        if ($checkResult['count'] > 0) {
            // Update existing record
            $updateSql = "UPDATE RiskControlsAndMitigants 
                          SET CorporateGovernance = ?, PoliciesProcedures = ?, RiskManagement = ?, 
                              InternalControls = ?, ComplianceFunction = ?, Training = ?, 
                              ReportingRecordKeeping = ?, ModifiedDate = GETDATE()
                          WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
            $params = array($corpGov, $policies, $riskMgmt, $internalControls, $compliance, 
                           $training, $reporting, $marketParticipant, $fiscalYear);
            $updateStmt = sqlsrv_query($conn, $updateSql, $params);
            
            if ($updateStmt) {
                $successMessage = "✅ Qualitative data updated successfully!";
                $selectedMarketParticipant = $marketParticipant;
                $selectedFiscalYear = $fiscalYear;
            } else {
                $errorMessage = "❌ Error updating data: " . print_r(sqlsrv_errors(), true);
            }
        } else {
            // Insert new record
            $insertSql = "INSERT INTO RiskControlsAndMitigants 
                          (MarketParticipant_id, FiscalYear_id, CorporateGovernance, PoliciesProcedures, 
                           RiskManagement, InternalControls, ComplianceFunction, Training, 
                           ReportingRecordKeeping, CreatedDate, ModifiedDate)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
            $params = array($marketParticipant, $fiscalYear, $corpGov, $policies, $riskMgmt, 
                           $internalControls, $compliance, $training, $reporting);
            $insertStmt = sqlsrv_query($conn, $insertSql, $params);
            
            if ($insertStmt) {
                $successMessage = "✅ Qualitative data saved successfully!";
                $selectedMarketParticipant = $marketParticipant;
                $selectedFiscalYear = $fiscalYear;
            } else {
                $errorMessage = "❌ Error saving data: " . print_r(sqlsrv_errors(), true);
            }
        }
    }
}

// ==================== RISK CALCULATION FUNCTIONS ====================

function calculateAverageNonZero($values) {
    $nonZeroValues = array_filter($values, function($val) {
        return $val > 0;
    });
    
    if (empty($nonZeroValues)) {
        return 0;
    }
    
    return array_sum($nonZeroValues) / count($nonZeroValues);
}

function calculateRiskScore($values, $weights) {
    $total = array_sum($values);
    
    if ($total == 0) {
        return 0;
    }
    
    $riskScore = 0;
    $i = 0;
    foreach ($values as $value) {
        $riskScore += ($value / $total) * $weights[$i];
        $i++;
    }
    
    return round($riskScore, 2);
}

function getFieldValue($data, $section, $subId, $field, $default = 0) {
    if(isset($data[$section][$subId][$field])) {
        $val = $data[$section][$subId][$field];
        return is_numeric($val) ? floatval($val) : $default;
    }
    return $default;
}

function calculateTotalRAS($corpGov, $policies, $riskMgmt, $internalControls, $compliance, $training, $reporting) {
    $weights = [30, 10, 20, 15, 15, 5, 5];
    $values = [$corpGov, $policies, $riskMgmt, $internalControls, $compliance, $training, $reporting];
    
    $weightedSum = 0;
    $totalWeight = 0;
    
    foreach ($values as $index => $value) {
        if ($value > 0) {
            $weightedSum += ($value * $weights[$index]);
            $totalWeight += $weights[$index];
        }
    }
    
    return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;
}

function getRiskCategory($rating) {
    if ($rating >= 1.00 && $rating <= 1.50) return 'Very Low';
    if ($rating >= 1.51 && $rating <= 2.50) return 'Low';
    if ($rating >= 2.51 && $rating <= 3.50) return 'Medium';
    if ($rating >= 3.51 && $rating <= 4.00) return 'High';
    if ($rating >= 4.01 && $rating <= 5.00) return 'Very High';
    return 'N/A';
}

function getRiskColor($category) {
    if (in_array($category, ['Very Low', 'Low'])) return '#27ae60';
    if ($category == 'Medium') return '#f39c12';
    if (in_array($category, ['High', 'Very High'])) return '#e74c3c';
    return '#95a5a6';
}

// ==================== DATA FETCHING FUNCTIONS ====================

function fetchDisplayData($conn, $marketParticipant_id, $fiscalYear_id, $formType) {
    $data = [];
    
    $subMasterIds = [];
    switch($formType) {
        case 'customer_risk':
            $subMasterIds = [1, 2, 3, 4];
            break;
        case 'peps_risk':
            $subMasterIds = [6, 5];
            break;
        case 'delivery_channel':
            $subMasterIds = [7, 8];
            break;
        case 'geographic_zone':
            $subMasterIds = [9, 10, 11, 12];
            break;
    }
    
    // Fetch StockBrokerService
    foreach($subMasterIds as $subId) {
        $sql = "SELECT ShareTransaction, CommercialDebentureBondTransaction, 
                       GovernmentBondTransaction, MutualFundTransaction, MarginService
                FROM StockBrokerService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['stock_broker'][$subId] = $row;
        }
    }
    
    // Fetch IssueAndSalesManagementService
    foreach($subMasterIds as $subId) {
        $sql = "SELECT IPO_GeneralPublic, IPO_Employees, IPO_LocalPeople, IPO_PF_CIT_Others,
                       FPO, PrivatePlacement, OfferDocument, RightShare, AuctionShare
                FROM IssueAndSalesManagementService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['issue_sales'][$subId] = $row;
        }
    }
    
    // Fetch PortfolioManagementService
    foreach($subMasterIds as $subId) {
        $sql = "SELECT Discretionary, NonDiscretionary, Advisory, ReturnGuarantee
                FROM PortfolioManagementService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['portfolio'][$subId] = $row;
        }
    }
    
    // Fetch BusinessRiskOtherServices
    foreach($subMasterIds as $subId) {
        $sql = "SELECT DematAccountCount
                FROM BusinessRiskOtherServices 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['business_risk'][$subId] = $row;
        }
    }
    
    return $data;
}

function fetchQualitativeData($conn, $marketParticipant_id, $fiscalYear_id) {
    $sql = "SELECT * FROM RiskControlsAndMitigants 
            WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id));
    
    if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    
    return null;
}

// ==================== CONFIGURATION ====================

$sebonWeights = [
    'stock_broker' => [
        'ShareTransaction' => 55,
        'CommercialDebentureBondTransaction' => 10,
        'GovernmentBondTransaction' => 10,
        'MutualFundTransaction' => 20,
        'MarginService' => 5
    ],
    'issue_sales' => [
        'IPO_GeneralPublic' => 5,
        'IPO_Employees' => 25,
        'IPO_LocalPeople' => 10,
        'IPO_PF_CIT_Others' => 20,
        'FPO' => 15,
        'PrivatePlacement' => 5,
        'OfferDocument' => 5,
        'RightShare' => 5,
        'AuctionShare' => 10
    ],
    'portfolio' => [
        'Discretionary' => 60,
        'NonDiscretionary' => 10,
        'Advisory' => 10,
        'ReturnGuarantee' => 20
    ],
    'business_risk' => ['DematAccountCount' => 40]
];

$stockFields = [
    'ShareTransaction' => 'Share Transaction',
    'CommercialDebentureBondTransaction' => 'Commercial Debenture/Bond Transaction',
    'GovernmentBondTransaction' => 'Government Bond Transaction',
    'MutualFundTransaction' => 'Mutual Fund Transaction',
    'MarginService' => 'Margin Service'
];

$issueFields = [
    'IPO_GeneralPublic' => 'Initial Public Offering (For General Public)',
    'IPO_Employees' => 'Initial Public Offering (For Employees)',
    'IPO_LocalPeople' => 'Initial Public Offering (For Local People)',
    'IPO_PF_CIT_Others' => 'Initial Public Offering (Member of PF/CIT/Others)',
    'FPO' => 'Further Public Offering (FPO)',
    'PrivatePlacement' => 'Issue through Private Placement',
    'OfferDocument' => 'Issue through Offer Document',
    'RightShare' => 'Right Share Issue',
    'AuctionShare' => 'Auction Share'
];

$portfolioFields = [
    'Discretionary' => 'Discretionary',
    'NonDiscretionary' => 'Non-Discretionary',
    'Advisory' => 'Advisory',
    'ReturnGuarantee' => 'Return Guarantee'
];

$riskWeights = [
    'customer_risk' => [1, 3, 4, 5],
    'peps_risk' => [4, 5],
    'delivery_channel' => [3, 5],
    'geographic_zone' => [1, 2, 3, 4]
];

// ==================== FETCH MASTER DATA ====================

// Fetch Reporting Entities
$sqlMarketParticipants = "SELECT [MarketParticipant_id], [MarketParticipantName], [MarketParticipantShortName]
                          FROM [RiskMatrix_AML].[dbo].[MarketParticipant]
                          WHERE [Status] = 1
                          ORDER BY [MarketParticipantName]";
$stmtMarketParticipants = sqlsrv_query($conn, $sqlMarketParticipants);

if ($stmtMarketParticipants === false) {
    $errorMessage = "Database error fetching Reporting Entities: " . print_r(sqlsrv_errors(), true);
} else {
    while ($row = sqlsrv_fetch_array($stmtMarketParticipants, SQLSRV_FETCH_ASSOC)) {
        $marketParticipants[] = $row;
    }
}

// Fetch Fiscal Years
$sqlFiscalYears = "SELECT [FiscalYear_id], [FiscalYearName]
                   FROM [RiskMatrix_AML].[dbo].[FiscalYear]
                   ORDER BY [FiscalYear_id] DESC";
$stmtFiscalYears = sqlsrv_query($conn, $sqlFiscalYears);

if ($stmtFiscalYears === false) {
    $errorMessage = "Database error fetching fiscal years: " . print_r(sqlsrv_errors(), true);
} else {
    while ($row = sqlsrv_fetch_array($stmtFiscalYears, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
        if ($defaultFiscalYearId === null) {
            $defaultFiscalYearId = $row['FiscalYear_id'];
        }
    }
}

// Get selected values from POST or maintain after save
if (!isset($selectedMarketParticipant)) {
    $selectedMarketParticipant = isset($_POST['market_participant']) ? $_POST['market_participant'] : null;
}
if (!isset($selectedFiscalYear)) {
    $selectedFiscalYear = isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : null;
}

// ==================== FETCH SELECTED DATA ====================

if ($selectedMarketParticipant && $selectedFiscalYear) {
    // Fetch all quantitative data
    $displayData = [
        'customer_risk' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'customer_risk'),
        'peps_risk' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'peps_risk'),
        'delivery_channel' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'delivery_channel'),
        'geographic_zone' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'geographic_zone')
    ];
    
    // Fetch qualitative data
    $qualitativeData = fetchQualitativeData($conn, $selectedMarketParticipant, $selectedFiscalYear);
    
    // Get names for display
    foreach ($marketParticipants as $mp) {
        if ($mp['MarketParticipant_id'] == $selectedMarketParticipant) {
            $selectedMarketParticipantName = $mp['MarketParticipantName'];
            break;
        }
    }
    
    foreach ($fiscalYears as $fy) {
        if ($fy['FiscalYear_id'] == $selectedFiscalYear) {
            $selectedFiscalYearName = $fy['FiscalYearName'];
            break;
        }
    }
    
    // ==================== CALCULATE ACTIVITY RISKS ====================
    
    // Stock Broker Services
    foreach ($stockFields as $field => $label) {
        $values_client = [
            getFieldValue($displayData['customer_risk'], 'stock_broker', 1, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 2, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 3, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 4, $field)
        ];
        $clientRisk = calculateRiskScore($values_client, $riskWeights['customer_risk']);
        
        $values_peps = [
            getFieldValue($displayData['peps_risk'], 'stock_broker', 6, $field),
            getFieldValue($displayData['peps_risk'], 'stock_broker', 5, $field)
        ];
        $pepsRisk = calculateRiskScore($values_peps, $riskWeights['peps_risk']);
        
        $values_delivery = [
            getFieldValue($displayData['delivery_channel'], 'stock_broker', 7, $field),
            getFieldValue($displayData['delivery_channel'], 'stock_broker', 8, $field)
        ];
        $deliveryRisk = calculateRiskScore($values_delivery, $riskWeights['delivery_channel']);
        
        $values_geo = [
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 9, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 10, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 11, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 12, $field)
        ];
        $geoRisk = calculateRiskScore($values_geo, $riskWeights['geographic_zone']);
        
        $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
        
        $activityRisksData[] = [
            'section' => 'Stock Broker',
            'label' => $label,
            'sebon_weight' => $sebonWeights['stock_broker'][$field] / 100,
            'client_risk' => $clientRisk,
            'peps_risk' => $pepsRisk,
            'delivery_risk' => $deliveryRisk,
            'geo_risk' => $geoRisk,
            'total_inherent_risk' => $totalInherentRisk
        ];
    }
    
    // Issue & Sales Management Services
    foreach ($issueFields as $field => $label) {
        $values_client = [
            getFieldValue($displayData['customer_risk'], 'issue_sales', 1, $field),
            getFieldValue($displayData['customer_risk'], 'issue_sales', 2, $field),
            getFieldValue($displayData['customer_risk'], 'issue_sales', 3, $field),
            getFieldValue($displayData['customer_risk'], 'issue_sales', 4, $field)
        ];
        $clientRisk = calculateRiskScore($values_client, $riskWeights['customer_risk']);
        
        $values_peps = [
            getFieldValue($displayData['peps_risk'], 'issue_sales', 6, $field),
            getFieldValue($displayData['peps_risk'], 'issue_sales', 5, $field)
        ];
        $pepsRisk = calculateRiskScore($values_peps, $riskWeights['peps_risk']);
        
        $values_delivery = [
            getFieldValue($displayData['delivery_channel'], 'issue_sales', 7, $field),
            getFieldValue($displayData['delivery_channel'], 'issue_sales', 8, $field)
        ];
        $deliveryRisk = calculateRiskScore($values_delivery, $riskWeights['delivery_channel']);
        
        $values_geo = [
            getFieldValue($displayData['geographic_zone'], 'issue_sales', 9, $field),
            getFieldValue($displayData['geographic_zone'], 'issue_sales', 10, $field),
            getFieldValue($displayData['geographic_zone'], 'issue_sales', 11, $field),
            getFieldValue($displayData['geographic_zone'], 'issue_sales', 12, $field)
        ];
        $geoRisk = calculateRiskScore($values_geo, $riskWeights['geographic_zone']);
        
        $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
        
        $activityRisksData[] = [
            'section' => 'Issue & Sales',
            'label' => $label,
            'sebon_weight' => $sebonWeights['issue_sales'][$field] / 100,
            'client_risk' => $clientRisk,
            'peps_risk' => $pepsRisk,
            'delivery_risk' => $deliveryRisk,
            'geo_risk' => $geoRisk,
            'total_inherent_risk' => $totalInherentRisk
        ];
    }
    
    // Portfolio Management Services
    foreach ($portfolioFields as $field => $label) {
        $values_client = [
            getFieldValue($displayData['customer_risk'], 'portfolio', 1, $field),
            getFieldValue($displayData['customer_risk'], 'portfolio', 2, $field),
            getFieldValue($displayData['customer_risk'], 'portfolio', 3, $field),
            getFieldValue($displayData['customer_risk'], 'portfolio', 4, $field)
        ];
        $clientRisk = calculateRiskScore($values_client, $riskWeights['customer_risk']);
        
        $values_peps = [
            getFieldValue($displayData['peps_risk'], 'portfolio', 6, $field),
            getFieldValue($displayData['peps_risk'], 'portfolio', 5, $field)
        ];
        $pepsRisk = calculateRiskScore($values_peps, $riskWeights['peps_risk']);
        
        $values_delivery = [
            getFieldValue($displayData['delivery_channel'], 'portfolio', 7, $field),
            getFieldValue($displayData['delivery_channel'], 'portfolio', 8, $field)
        ];
        $deliveryRisk = calculateRiskScore($values_delivery, $riskWeights['delivery_channel']);
        
        $values_geo = [
            getFieldValue($displayData['geographic_zone'], 'portfolio', 9, $field),
            getFieldValue($displayData['geographic_zone'], 'portfolio', 10, $field),
            getFieldValue($displayData['geographic_zone'], 'portfolio', 11, $field),
            getFieldValue($displayData['geographic_zone'], 'portfolio', 12, $field)
        ];
        $geoRisk = calculateRiskScore($values_geo, $riskWeights['geographic_zone']);
        
        $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
        
        $activityRisksData[] = [
            'section' => 'Portfolio',
            'label' => $label,
            'sebon_weight' => $sebonWeights['portfolio'][$field] / 100,
            'client_risk' => $clientRisk,
            'peps_risk' => $pepsRisk,
            'delivery_risk' => $deliveryRisk,
            'geo_risk' => $geoRisk,
            'total_inherent_risk' => $totalInherentRisk
        ];
    }
    
    // Business Risk - Demat Account
    $clientRisk = calculateRiskScore([
        getFieldValue($displayData['customer_risk'], 'business_risk', 1, 'DematAccountCount'),
        getFieldValue($displayData['customer_risk'], 'business_risk', 2, 'DematAccountCount'),
        getFieldValue($displayData['customer_risk'], 'business_risk', 3, 'DematAccountCount'),
        getFieldValue($displayData['customer_risk'], 'business_risk', 4, 'DematAccountCount')
    ], $riskWeights['customer_risk']);
    
    $pepsRisk = calculateRiskScore([
        getFieldValue($displayData['peps_risk'], 'business_risk', 6, 'DematAccountCount'),
        getFieldValue($displayData['peps_risk'], 'business_risk', 5, 'DematAccountCount')
    ], $riskWeights['peps_risk']);
    
    $deliveryRisk = calculateRiskScore([
        getFieldValue($displayData['delivery_channel'], 'business_risk', 7, 'DematAccountCount'),
        getFieldValue($displayData['delivery_channel'], 'business_risk', 8, 'DematAccountCount')
    ], $riskWeights['delivery_channel']);
    
    $geoRisk = calculateRiskScore([
        getFieldValue($displayData['geographic_zone'], 'business_risk', 9, 'DematAccountCount'),
        getFieldValue($displayData['geographic_zone'], 'business_risk', 10, 'DematAccountCount'),
        getFieldValue($displayData['geographic_zone'], 'business_risk', 11, 'DematAccountCount'),
        getFieldValue($displayData['geographic_zone'], 'business_risk', 12, 'DematAccountCount')
    ], $riskWeights['geographic_zone']);
    
    $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
    
    $activityRisksData[] = [
        'section' => 'Other Services',
        'label' => 'Demat Account (in number)',
        'sebon_weight' => 0.40,
        'client_risk' => $clientRisk,
        'peps_risk' => $pepsRisk,
        'delivery_risk' => $deliveryRisk,
        'geo_risk' => $geoRisk,
        'total_inherent_risk' => $totalInherentRisk
    ];
}

// Calculate Total RAS (if qualitative data exists)
$totalRAS = 0;
if ($qualitativeData) {
    $totalRAS = calculateTotalRAS(
        $qualitativeData['CorporateGovernance'] ?? 0,
        $qualitativeData['PoliciesProcedures'] ?? 0,
        $qualitativeData['RiskManagement'] ?? 0,
        $qualitativeData['InternalControls'] ?? 0,
        $qualitativeData['ComplianceFunction'] ?? 0,
        $qualitativeData['Training'] ?? 0,
        $qualitativeData['ReportingRecordKeeping'] ?? 0
    );
}

?>



<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- jQuery (required) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#market_participant').select2({
        placeholder: "-- Select Reporting Entity --",
        allowClear: true,
        width: '100%'
    });
});
</script>

<style>
    <style>
/* ============================================
   GLOBAL STYLES
   ============================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f9f9f9;
    font-family: 'Arial', sans-serif;
    color: #333;
    line-height: 1.6;
}

/* ============================================
   HEADER STYLES
   ============================================ */
.header {
    background: #007bff;
    color: white;
    padding: 30px 15px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.header h1,
.header h2,
.header h3 {
    margin-bottom: 20px;
    font-weight: normal;
}

.header h1 {
    font-size: 2rem;
}

.header h2 {
    font-size: 1.75rem;
}

.header h3 {
    font-size: 1.5rem;
}

.user-info {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.user-info span {
    font-size: 0.95rem;
}

/* ============================================
   PAGE HEADER
   ============================================ */
.page-header {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    margin-bottom: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.page-header h1 {
    font-size: 26px;
    margin-bottom: 8px;
    font-weight: 600;
}

.page-header p {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 10px;
}

.info-badge {
    display: inline-block;
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    margin-top: 10px;
    font-weight: bold;
}

/* ============================================
   BUTTONS
   ============================================ */
.btn,
button {
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.logout-btn {
    background: #dc3545;
    color: white;
}

.logout-btn:hover {
    background: #c82333;
}

.btn-view,
.btn-load {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 40px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
    height: 42px;
}

.btn-view:hover,
.btn-load:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
}

.btn-submit,
.btn-save {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
    padding: 15px 50px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(39, 174, 96, 0.3);
    display: block;
    margin: 30px auto 0 auto;
}

.btn-submit:hover,
.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
}

#printBtn {
    padding: 10px 25px;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 6px;
    font-weight: bold;
    font-size: 14px;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(44, 62, 80, 0.3);
}

#printBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(44, 62, 80, 0.4);
}

/* ============================================
   NAVIGATION TABS
   ============================================ */
.nav-tabs {
    border-bottom: 2px solid #0056b3;
    background: #007bff;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
}

.nav-tabs .nav-link {
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border: none;
    background: transparent;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover,
.nav-tabs .nav-link.active {
    background: #0056b3;
}

.tab-buttons {
    display: flex;
    gap: 5px;
    border-bottom: 3px solid #667eea;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.tab-button {
    background-color: #ecf0f1;
    border: none;
    padding: 15px 25px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    transition: all 0.3s;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.tab-button:hover {
    background-color: #bdc3c7;
}

.tab-button.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.4s;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================================
   CONTAINERS
   ============================================ */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.container h2 {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    margin: -20px -20px 30px -20px;
    border-radius: 8px 8px 0 0;
    font-size: 24px;
    font-weight: 600;
}

.container h3 {
    color: #2c3e50;
    font-size: 20px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 3px solid #667eea;
}

.container h4 {
    color: #34495e;
    font-size: 18px;
    margin-top: 30px;
    margin-bottom: 15px;
}

.container p {
    margin: 10px 0;
    line-height: 1.8;
    font-size: 14px;
}

.content-wrapper {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

/* ============================================
   SECTION TITLES
   ============================================ */
.section-title {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 8px;
    margin: 30px 0 20px 0;
    font-size: 20px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* ============================================
   ALERTS & MESSAGES
   ============================================ */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border: 2px solid #28a745;
    color: #155724;
}

.alert-error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border: 2px solid #dc3545;
    color: #721c24;
}

.no-data-message {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    margin: 30px 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.no-data-message h3 {
    color: #7f8c8d;
    font-size: 22px;
    margin-bottom: 15px;
}

.no-data-message p {
    color: #95a5a6;
    font-size: 16px;
}

.warning-box {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe7a0 100%);
    padding: 25px;
    border-radius: 10px;
    border: 2px solid #ffc107;
    margin: 30px 0;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.warning-box h3 {
    color: #856404;
    margin-bottom: 15px;
    font-size: 20px;
}

.warning-box p {
    color: #856404;
    font-size: 16px;
    margin: 10px 0;
    line-height: 1.6;
}

.required-note {
    background-color: #e8f5e9;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #27ae60;
    font-size: 14px;
    color: #2c3e50;
}

/* ============================================
   CARDS
   ============================================ */
.card {
    border-radius: 15px;
    background-color: #f8f9fa;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.card-header {
    background-color: #007bff;
    color: white;
    padding: 15px;
    border-radius: 15px 15px 0 0;
    margin: -20px -20px 20px -20px;
    font-weight: bold;
}

.card-body {
    padding: 15px 0;
}

.card-footer {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
    margin-top: 15px;
    text-align: right;
}

.card-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 10px;
    color: #007bff;
}

.card-text {
    color: #666;
    margin-bottom: 10px;
}

.info-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: 600;
    color: white;
    border: none;
    padding: 0;
}

.info-card p {
    font-size: 14px;
    opacity: 0.95;
    line-height: 1.8;
    margin: 5px 0;
}

/* ============================================
   FORMS
   ============================================ */
.filter-section,
.selection-panel {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.container form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 10px 0 20px 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.container form label {
    font-weight: 600;
    color: #34495e;
    margin-right: 10px;
    font-size: 14px;
}

.container form select {
    padding: 10px 15px;
    border: 2px solid #dfe6e9;
    border-radius: 6px;
    font-size: 14px;
    background-color: white;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 250px;
}

.container form select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-title {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.filter-title::before {
    content: "🔍";
    margin-right: 10px;
    font-size: 20px;
}

.filter-form,
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 700;
    color: #34495e;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group select,
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="date"],
.form-group input[type="number"] {
    padding: 12px 15px;
    border: 2px solid #dfe6e9;
    border-radius: 8px;
    font-size: 14px;
    background-color: white;
    transition: all 0.3s;
}

.form-group select {
    cursor: pointer;
}

.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group textarea {
    width: 100%;
    min-height: 80px;
    padding: 8px;
    border: 2px solid #dfe6e9;
    border-radius: 4px;
    font-family: Arial, sans-serif;
    font-size: 12px;
    resize: vertical;
    transition: all 0.3s;
}

.form-group input:invalid,
.form-group textarea:invalid {
    border-color: #e74c3c;
}

.submit-section {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
}

/* ============================================
   QUALITATIVE FORM
   ============================================ */
.qualitative-entry-form {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    padding: 30px;
    border-radius: 10px;
    margin: 30px 0;
    border: 3px solid #27ae60;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.qualitative-entry-form h3 {
    color: #27ae60;
    margin-bottom: 20px;
    font-size: 20px;
    font-weight: 700;
    border: none;
    padding: 0;
}

.qualitative-entry-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.weight-badge {
    display: inline-block;
    background: linear-gradient(135deg, #ffd93d 0%, #ffcd00 100%);
    color: #2c3e50;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.total-ras-display {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    margin: 20px 0;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.total-ras-display h4 {
    color: white;
    margin-bottom: 10px;
    font-size: 18px;
}

.total-ras-display .value {
    font-size: 42px;
    font-weight: 700;
    margin: 15px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.total-ras-display p {
    font-size: 14px;
    margin-top: 10px;
    opacity: 0.95;
}

/* ============================================
   TABLES
   ============================================ */
.data-table,
.questionnaire-table,
.container table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.data-table th,
.data-table td,
.questionnaire-table th,
.questionnaire-table td,
.container table th,
.container table td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
}

.data-table thead th,
.questionnaire-table thead th,
.container table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 12px;
    padding: 12px 10px;
}

.container table thead th {
    padding: 12px 8px;
}

.container table thead tr:nth-child(2) th {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    font-size: 11px;
}

.container table tbody tr {
    transition: background-color 0.3s;
}

.container table tbody tr:hover {
    background-color: #f8f9fa;
}

.data-table .section-header,
.section-header {
    background: linear-gradient(135deg, #ffd93d 0%, #ffcd00 100%);
    color: #2c3e50;
    font-weight: 700;
    text-align: left;
    padding-left: 20px !important;
    font-size: 13px;
}

.data-table .activity-cell,
.activity-cell {
    text-align: left;
    padding-left: 40px !important;
    background-color: #f8f9fa;
    font-weight: 500;
}

.data-table .weight-cell,
.weight-cell {
    background: linear-gradient(135deg, #ffd93d 0%, #ffcd00 100%);
    font-weight: 700;
    color: #2c3e50;
}

.data-table .inherent-risk-cell,
.inherent-risk-cell {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    font-weight: 700;
    font-size: 13px;
}

.data-table .composite-risk-cell,
.composite-risk-cell {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    font-weight: 700;
    font-size: 14px;
}

.directive-cell {
    background-color: #d5dbdb;
    font-style: italic;
    color: #34495e;
    font-size: 11px;
    width: 5%;
    text-align: center;
}

.status-cell {
    text-align: center;
    width: 8%;
    font-weight: bold;
}

.status-yes {
    color: #27ae60;
}

.status-no {
    color: #e74c3c;
}

.mp-remarks-cell {
    background-color: #e8f5e9;
    width: 22%;
    font-size: 12px;
    text-align: left;
}

.sebon-remarks-cell {
    background-color: #fff3e0;
    width: 20%;
}

.sebon-remarks-cell textarea {
    width: 100%;
    min-height: 80px;
    padding: 8px;
    border: 2px solid #bdc3c7;
    border-radius: 4px;
    font-family: Arial, sans-serif;
    font-size: 12px;
    resize: vertical;
}

.sebon-remarks-cell textarea:focus {
    outline: none;
    border-color: #f39c12;
    box-shadow: 0 0 5px rgba(243, 156, 18, 0.3);
}

.risk-rating-cell {
    background-color: #fce4ec;
    width: 12%;
    text-align: center;
}

.risk-rating-cell input {
    width: 100%;
    padding: 8px;
    border: 2px solid #bdc3c7;
    border-radius: 4px;
    font-size: 13px;
    text-align: center;
}

.risk-rating-cell input:focus {
    outline: none;
    border-color: #e91e63;
    box-shadow: 0 0 5px rgba(233, 30, 99, 0.3);
}

.sn-cell {
    text-align: center;
    font-weight: bold;
    background-color: #ecf0f1;
    width: 50px;
}

.data-table .total-column {
    background-color: #fff3cd;
    font-weight: 700;
    color: #856404;
}

.data-table .risk-column {
    background-color: #ff4757;
    color: white;
    font-weight: 700;
}

.data-table tbody tr:hover,
.questionnaire-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* ============================================
   RISK SUMMARY BOX
   ============================================ */
.container > div[style*="background: linear-gradient(135deg, #667eea"] {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white;
    padding: 30px !important;
    border-radius: 10px !important;
    margin: 30px 0 !important;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.container > div[style*="background: linear-gradient(135deg, #667eea"] h3 {
    color: white !important;
    margin-bottom: 20px !important;
    font-size: 24px !important;
    border: none !important;
    padding: 0 !important;
}

.container > div[style*="background: linear-gradient(135deg, #667eea"] h4 {
    color: white !important;
    margin-bottom: 10px !important;
    font-size: 16px !important;
}

.container > div[style*="background: linear-gradient(135deg, #667eea"] p {
    font-size: 28px !important;
    font-weight: 700 !important;
}

.container > div[style*="background: linear-gradient(135deg, #667eea"] div[style*="background: rgba(255,255,255,0.2)"] {
    background: rgba(255, 255, 255, 0.2) !important;
    padding: 20px !important;
    border-radius: 8px !important;
    text-align: center !important;
}

/* ============================================
   LEGEND & REFERENCE SECTIONS
   ============================================ */
.legend,
#riskScaleRef {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 30px 0 20px 0;
    border-left: 4px solid #667eea;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.legend h3,
#riskScaleRef h4 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 18px;
}

.legend-item {
    display: inline-block;
    margin-right: 20px;
    font-size: 12px;
    color: #555;
}

.legend-color {
    display: inline-block;
    width: 20px;
    height: 20px;
    margin-right: 5px;
    vertical-align: middle;
    border: 1px solid #ddd;
}

#riskScaleRef table {
    width: 50%;
    margin-top: 10px;
}

#riskScaleRef table thead {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    color: white;
}

/* ============================================
   PRINT BUTTON CONTAINER
   ============================================ */
.container > div[style*="text-align:right"] {
    margin-top: 20px;
    text-align: right;
    padding: 15px 0;
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    body * {
        visibility: hidden;
    }
    #printArea,
    #printArea * {
        visibility: visible;
    }
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    #printBtn {
        display: none !important;
    }
    #riskScaleRef {
        display: none !important;
    }
    .container {
        box-shadow: none;
        margin: 0;
        padding: 10px;
    }
    .container h2 {
        margin: 0 0 20px 0;
    }
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .filter-form,
    .form-row {
        grid-template-columns: 1fr;
    }

    .qualitative-entry-form .form-row {
        grid-template-columns: 1fr;
    }

    .header h1 {
        font-size: 1.5rem;
    }

    .header h2 {
        font-size: 1.25rem;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .container {
        padding: 10px;
    }

    .container h2 {
        font-size: 18px;
        padding: 15px;
        margin: -10px -10px 20px -10px;
    }

    .container form {
        padding: 15px;
    }

    .container form select {
        min-width: 100%;
    }

    .data-table,
    .questionnaire-table,
    .container table {
        font-size: 10px;
    }

    .data-table th,
    .data-table td,
    .questionnaire-table th,
    .questionnaire-table td,
    .container table th,
    .container table td {
        padding: 6px 4px;
    }

    .tab-button {
        padding: 10px 15px;
        font-size: 13px;
    }

    .btn-view,
    .btn-load {
        padding: 10px 20px;
        font-size: 14px;
    }

    .btn-submit,
    .btn-save {
        padding: 12px 30px;
        font-size: 14px;
    }

    #printBtn {
        padding: 8px 20px;
        font-size: 13px;
    }

    #riskScaleRef table {
        width: 100%;
    }

    .section-title {
        font-size: 16px;
        padding: 12px 15px;
    }

    .total-ras-display .value {
        font-size: 32px;
    }

    .qualitative-entry-form {
        padding: 20px;
    }

    .container > div[style*="background: linear-gradient(135deg, #667eea"] div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
</style>

<div class="container">
    <div class="page-header">
        <h1>📊 Complete Risk Assessment Dashboard</h1>
        <p>Quantitative Data (60%) + Qualitative Data (40%) = Composite Risk</p>
    </div>
    
    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <!-- Selection Form -->
    <div class="filter-section">
        <form method="POST" action="" class="filter-form">
            <div class="form-group">
                <label for="market_participant">Reporting Entity *</label>
                <select name="market_participant" id="market_participant" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($marketParticipants as $mp): ?>
                        <option value="<?php echo $mp['MarketParticipant_id']; ?>" 
                                <?php echo ($selectedMarketParticipant == $mp['MarketParticipant_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mp['MarketParticipantName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="fiscal_year">Fiscal Year *</label>
                <select name="fiscal_year" id="fiscal_year" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?php echo $fy['FiscalYear_id']; ?>"
                                <?php echo ($selectedFiscalYear == $fy['FiscalYear_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fy['FiscalYearName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-view">View Data</button>
        </form>
    </div>
    
    <?php if ($selectedMarketParticipant && $selectedFiscalYear): ?>
        
        <div class="info-card">
            <h3>Currently Viewing:</h3>
            <p><strong>Reporting Entity:</strong> <?php echo htmlspecialchars($selectedMarketParticipantName); ?></p>
            <p><strong>Fiscal Year:</strong> <?php echo htmlspecialchars($selectedFiscalYearName); ?></p>
        </div>
        
        <!-- QUANTITATIVE DATA TABLE -->
        <div class="section-title">📊 PART 1: Quantitative Data - Inherent Risks (60%)</div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2">SIGNIFICANT ACTIVITIES</th>
                    <th colspan="6">Quantitative Data<br>Inherent Risks</th>
                    <th rowspan="2" style="background-color: #ffd93d;">60%</th>
                    <th colspan="2">Risk Trend</th>
                </tr>
                <tr>
                    <th style="width: 80px;">Weight (%)<br>SEBON</th>
                    <th>Client Risk</th>
                    <th>PEPs Risk</th>
                    <th>Delivery<br>Channel Risk</th>
                    <th>Geographic<br>Location Risk</th>
                    <th>TOTAL INHERENT RISK</th>
                    <th>Inherent Risk<br>(Previously)</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentSection = '';
                foreach ($activityRisksData as $activity) {
                    if ($currentSection != $activity['section']) {
                        $currentSection = $activity['section'];
                        echo '<tr><td colspan="10" class="section-header">' . htmlspecialchars($currentSection) . '</td></tr>';
                    }
                    ?>
                    <tr>
                        <td class="activity-cell"><?php echo htmlspecialchars($activity['label']); ?></td>
                        <td class="weight-cell"><?php echo number_format($activity['sebon_weight'] * 100, 0); ?>%</td>
                        <td><?php echo number_format($activity['client_risk'], 2); ?></td>
                        <td><?php echo number_format($activity['peps_risk'], 2); ?></td>
                        <td><?php echo number_format($activity['delivery_risk'], 2); ?></td>
                        <td><?php echo number_format($activity['geo_risk'], 2); ?></td>
                        <td class="inherent-risk-cell"><?php echo number_format($activity['total_inherent_risk'], 2); ?></td>
                        <td>-</td>
                        <td><?php echo $activity['total_inherent_risk'] > 0 ? 'Increasing' : 'N/A'; ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        
        <!-- QUALITATIVE DATA ENTRY FORM -->
        <div class="section-title">📝 PART 2: Qualitative Data Entry - Risk Controls and Mitigants (40%)</div>
        
        <form method="POST" action="" id="qualitativeForm">
            <input type="hidden" name="market_participant" value="<?php echo $selectedMarketParticipant; ?>">
            <input type="hidden" name="fiscal_year" value="<?php echo $selectedFiscalYear; ?>">
            
            <div class="qualitative-entry-form">
                <h3>Enter Risk Assessment Scores (1.00 = Very Good, 5.00 = Very Deficient)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>1. Corporate Governance <span class="weight-badge">30%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="corporate_governance" id="corporate_governance" 
                               value="<?php echo isset($qualitativeData['CorporateGovernance']) ? $qualitativeData['CorporateGovernance'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>2. Policies and Procedures <span class="weight-badge">10%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="policies_procedures" id="policies_procedures" 
                               value="<?php echo isset($qualitativeData['PoliciesProcedures']) ? $qualitativeData['PoliciesProcedures'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>3. Risk Management <span class="weight-badge">20%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="risk_management" id="risk_management" 
                               value="<?php echo isset($qualitativeData['RiskManagement']) ? $qualitativeData['RiskManagement'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>4. Internal Controls <span class="weight-badge">15%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="internal_controls" id="internal_controls" 
                               value="<?php echo isset($qualitativeData['InternalControls']) ? $qualitativeData['InternalControls'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>5. Compliance Function <span class="weight-badge">15%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="compliance_function" id="compliance_function" 
                               value="<?php echo isset($qualitativeData['ComplianceFunction']) ? $qualitativeData['ComplianceFunction'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>6. Training <span class="weight-badge">5%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="training" id="training" 
                               value="<?php echo isset($qualitativeData['Training']) ? $qualitativeData['Training'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>7. Reporting and Record Keeping <span class="weight-badge">5%</span></label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="reporting_record_keeping" id="reporting_record_keeping" 
                               value="<?php echo isset($qualitativeData['ReportingRecordKeeping']) ? $qualitativeData['ReportingRecordKeeping'] : ''; ?>" 
                               required oninput="calculateTotal()">
                    </div>
                </div>
                
                <div class="total-ras-display" id="totalRasDisplay" style="display: <?php echo $totalRAS > 0 ? 'block' : 'none'; ?>;">
                    <h4>TOTAL RAS (Risk Controls and Mitigants)</h4>
                    <div class="value" id="totalRasValue"><?php echo number_format($totalRAS, 2); ?></div>
                    <p style="margin-top: 10px; font-size: 14px;">
                        Risk Category: <strong><?php echo getRiskCategory($totalRAS); ?></strong>
                    </p>
                </div>
                
               <!-- <button type="submit" name="submit_qualitative" class="btn-save">💾 SAVE QUALITATIVE DATA</button> -->

            </div>
        </form>
        
        <?php if ($totalRAS > 0): ?>
        
        <!-- COMPOSITE RISK TABLE -->
        <div class="section-title">🎯 PART 3: Composite Risk on Business Activities</div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2">SIGNIFICANT ACTIVITIES</th>
                    <th rowspan="2" style="width: 80px;">Weight (%)<br>SEBON</th>
                    <th rowspan="2">TOTAL INHERENT RISK<br>(Quantitative 60%)</th>
                    <th rowspan="2">TOTAL RAS<br>(Qualitative 40%)</th>
                    <th colspan="3" style="background-color: #e74c3c; color: white;">COMPOSITE RISK</th>
                    <th colspan="2">Trend</th>
                </tr>
                <tr>
                    <th style="background-color: #e74c3c; color: white;">COMPOSITE RISK ON<br>BUSINESS ACTIVITIES</th>
                    <th style="background-color: #e74c3c; color: white;">Risk Category</th>
                    <th>Composite Risk<br>(Previously)</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentSection = '';
                $totalCompositeRiskSum = 0;
                $totalWeightSum = 0;
                
                foreach ($activityRisksData as $activity) {
                    if ($currentSection != $activity['section']) {
                        $currentSection = $activity['section'];
                        echo '<tr><td colspan="9" class="section-header">' . htmlspecialchars($currentSection) . '</td></tr>';
                    }
                    
                    // Composite Risk Formula: IF(Inherent Risk = 0, 0, (Inherent Risk × 60%) + (Total RAS × 40%))
                    $compositeRisk = 0;
                    if ($activity['total_inherent_risk'] > 0) {
                        $compositeRisk = ($activity['total_inherent_risk'] * 0.60) + ($totalRAS * 0.40);
                    }
                    
                    $riskCategory = getRiskCategory($compositeRisk);
                    $riskColor = getRiskColor($riskCategory);
                    
                    // Calculate weighted composite risk for overall assessment
                    if ($compositeRisk > 0) {
                        $totalCompositeRiskSum += ($compositeRisk * $activity['sebon_weight']);
                        $totalWeightSum += $activity['sebon_weight'];
                    }
                    ?>
                    <tr>
                        <td class="activity-cell"><?php echo htmlspecialchars($activity['label']); ?></td>
                        <td class="weight-cell"><?php echo number_format($activity['sebon_weight'] * 100, 0); ?>%</td>
                        <td class="inherent-risk-cell"><?php echo number_format($activity['total_inherent_risk'], 2); ?></td>
                        <td style="background-color: #e74c3c; color: white; font-weight: 700;"><?php echo number_format($totalRAS, 2); ?></td>
                        <td class="composite-risk-cell"><?php echo number_format($compositeRisk, 2); ?></td>
                        <td style="background-color: <?php echo $riskColor; ?>; color: white; font-weight: 700;">
                            <?php echo $riskCategory; ?>
                        </td>
                        <td>-</td>
                        <td><?php echo $compositeRisk > 0 ? 'Increasing' : 'N/A'; ?></td>
                    </tr>
                    <?php
                }
                
                // Calculate Overall Composite Risk
                $overallCompositeRisk = $totalWeightSum > 0 ? ($totalCompositeRiskSum / $totalWeightSum) : 0;
                $overallRiskCategory = getRiskCategory($overallCompositeRisk);
                $overallRiskColor = getRiskColor($overallRiskCategory);
                ?>
                <tr style="background-color: #2c3e50; color: white; font-weight: 700; font-size: 14px;">
                    <td colspan="4" style="text-align: right; padding-right: 20px;">OVERALL COMPOSITE RISK:</td>
                    <td class="composite-risk-cell" style="font-size: 16px;"><?php echo number_format($overallCompositeRisk, 2); ?></td>
                    <td style="background-color: <?php echo $overallRiskColor; ?>; color: white; font-weight: 700;">
                        <?php echo $overallRiskCategory; ?>
                    </td>
                    <td colspan="2">-</td>
                </tr>
            </tbody>
        </table>
        
        <!-- RISK SUMMARY -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin: 30px 0;">
            <h3 style="margin-bottom: 20px; font-size: 24px;">📈 Risk Assessment Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin-bottom: 10px;">Quantitative Risk</h4>
                    <p style="font-size: 28px; font-weight: 700;">60%</p>
                    <p style="font-size: 12px; margin-top: 5px;">Inherent Risks</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin-bottom: 10px;">Qualitative Risk</h4>
                    <p style="font-size: 28px; font-weight: 700;">40%</p>
                    <p style="font-size: 12px; margin-top: 5px;">Risk Controls (RAS)</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin-bottom: 10px;">Total RAS Score</h4>
                    <p style="font-size: 28px; font-weight: 700;"><?php echo number_format($totalRAS, 2); ?></p>
                    <p style="font-size: 12px; margin-top: 5px;"><?php echo getRiskCategory($totalRAS); ?></p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin-bottom: 10px;">Overall Composite Risk</h4>
                    <p style="font-size: 28px; font-weight: 700;"><?php echo number_format($overallCompositeRisk, 2); ?></p>
                    <p style="font-size: 12px; margin-top: 5px;"><?php echo $overallRiskCategory; ?></p>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <div class="warning-box">
            <h3>⚠️ Qualitative Data Required</h3>
            <p>Please fill in the Qualitative Data form above and click "SAVE" to calculate the Composite Risk on Business Activities.</p>
            <p style="margin-top: 10px;">The Composite Risk combines:</p>
            <p><strong>60% Quantitative Data (Inherent Risks)</strong> + <strong>40% Qualitative Data (Risk Controls)</strong></p>
        </div>
        
        <?php endif; ?>
        
    <?php else: ?>
        
        <div class="no-data-message">
            <h3>🔍 Please Select Options</h3>
            <p>Select a Reporting Entity and Fiscal Year from the dropdown above to view and manage risk assessment data.</p>
        </div>
        
    <?php endif; ?>
</div>

<script>
function calculateTotal() {
    const corpGov = parseFloat(document.getElementById('corporate_governance').value) || 0;
    const policies = parseFloat(document.getElementById('policies_procedures').value) || 0;
    const riskMgmt = parseFloat(document.getElementById('risk_management').value) || 0;
    const internalControls = parseFloat(document.getElementById('internal_controls').value) || 0;
    const compliance = parseFloat(document.getElementById('compliance_function').value) || 0;
    const training = parseFloat(document.getElementById('training').value) || 0;
    const reporting = parseFloat(document.getElementById('reporting_record_keeping').value) || 0;
    
    // Check if at least one field has a value
    if (corpGov === 0 && policies === 0 && riskMgmt === 0 && internalControls === 0 && 
        compliance === 0 && training === 0 && reporting === 0) {
        document.getElementById('totalRasDisplay').style.display = 'none';
        return;
    }
    
    // Calculate weighted average
    const weights = [30, 10, 20, 15, 15, 5, 5];
    const values = [corpGov, policies, riskMgmt, internalControls, compliance, training, reporting];
    
    let weightedSum = 0;
    let totalWeight = 0;
    
    for (let i = 0; i < values.length; i++) {
        if (values[i] > 0) {
            weightedSum += (values[i] * weights[i]);
            totalWeight += weights[i];
        }
    }
    
    const totalRas = totalWeight > 0 ? (weightedSum / totalWeight) : 0;
    
    // Determine risk category
    let riskCategory = 'N/A';
    if (totalRas >= 1.00 && totalRas <= 1.50) riskCategory = 'Very Low';
    else if (totalRas >= 1.51 && totalRas <= 2.50) riskCategory = 'Low';
    else if (totalRas >= 2.51 && totalRas <= 3.50) riskCategory = 'Medium';
    else if (totalRas >= 3.51 && totalRas <= 4.00) riskCategory = 'High';
    else if (totalRas >= 4.01 && totalRas <= 5.00) riskCategory = 'Very High';
    
    // Display results
    document.getElementById('totalRasValue').textContent = totalRas.toFixed(2);
    const displayEl = document.getElementById('totalRasDisplay');
    displayEl.style.display = 'block';
    
    // Update risk category text if it exists
    const categoryText = displayEl.querySelector('p strong');
    if (categoryText) {
        categoryText.textContent = riskCategory;
    }
}

// Form validation
document.getElementById('qualitativeForm').addEventListener('submit', function(e) {
    const inputs = this.querySelectorAll('input[type="number"]');
    let valid = true;
    
    inputs.forEach(input => {
        const value = parseFloat(input.value);
        if (isNaN(value) || value < 1.00 || value > 5.00) {
            valid = false;
            input.style.borderColor = '#e74c3c';
        } else {
            input.style.borderColor = '#dfe6e9';
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('⚠️ Please ensure all risk scores are between 1.00 and 5.00');
    }
});

// Initialize calculation on page load if data exists
window.addEventListener('load', function() {
    calculateTotal();
});
</script>

<?php 
include 'footer.php';
ob_end_flush(); // End output buffering and send output
?>