<?php
// Start output buffering to prevent header issues
ob_start();

// Include database connection and header
include 'db.php';
include 'header.php';

// ==================== INITIALIZE VARIABLES ====================
$errorMessage = '';
$fiscalYears = [];
$selectedFiscalYear = null;
$selectedFiscalYearName = '';
$summaryData = [];

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

/**
 * Maps a composite/net-profile risk rating to its Risk Category, per the
 * official master scale:
 *
 * Category    Score Range      Interpretation
 * Very Low    1.00 – 1.50      Minimal risk exposure / very strong controls
 * Low         1.51 – 2.50      Low risk exposure / adequate controls
 * Medium      2.51 – 3.50      Moderate risk / controls require improvement
 * High        3.51 – 4.00      High risk / significant control deficiencies
 * Very High   4.01 – 5.00      Very high risk / critical control failures
 *
 * FIX: rating is rounded to 2 decimals BEFORE comparison. This closes the
 * floating-point "dead zone" (e.g. a raw value like 2.505000000001, which is
 * neither <= 2.50 nor >= 2.51) that previously fell through to N/A even
 * though the displayed, rounded value (2.51) clearly belongs to "Medium".
 * The boundaries themselves are untouched and match the master table exactly.
 */
function getRiskCategory($rating) {
    $rating = round($rating, 2);

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

function fetchStructuralRisk($conn, $marketParticipant_id, $fiscalYear_id) {
    $sql = "SELECT TotalAssets, FinancialGroup FROM StructuralRisk 
            WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id));
    
    if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    
    return null;
}

function calculateStructuralRisk($structuralData) {
    if (!$structuralData) {
        return 0;
    }
    
    $totalAssetsRating = isset($structuralData['TotalAssets']) ? floatval($structuralData['TotalAssets']) : 0;
    $financialGroupRating = isset($structuralData['FinancialGroup']) ? floatval($structuralData['FinancialGroup']) : 0;
    
    // 90% weight for Total Assets, 10% weight for Financial Group
    $structuralRisk = ($totalAssetsRating * 0.90) + ($financialGroupRating * 0.10);
    
    return round($structuralRisk, 2);
}

function calculateNetProfileRisk($structuralRisk, $inherentRisk) {
    if ($inherentRisk == 0) {
        return 0;
    }
    
    // Net Profile Risk = (25% × Structural Risk) + (75% × Inherent Risk)
    $netProfileRisk = (0.25 * $structuralRisk) + (0.75 * $inherentRisk);
    
    return round($netProfileRisk, 2);
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

// All fields combined for iteration
$allFields = [
    'stock_broker' => $stockFields,
    'issue_sales' => $issueFields,
    'portfolio' => $portfolioFields,
    'business_risk' => ['DematAccountCount' => 'Demat Account (in number)']
];

// ==================== FETCH FISCAL YEARS ====================

$sqlFiscalYears = "SELECT [FiscalYear_id], [FiscalYearName]
                   FROM [RiskMatrix_AML].[dbo].[FiscalYear]
                   ORDER BY [FiscalYear_id] DESC";
$stmtFiscalYears = sqlsrv_query($conn, $sqlFiscalYears);

if ($stmtFiscalYears === false) {
    $errorMessage = "Database error fetching fiscal years: " . print_r(sqlsrv_errors(), true);
} else {
    while ($row = sqlsrv_fetch_array($stmtFiscalYears, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
    }
}

// Get selected fiscal year
$selectedFiscalYear = isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : null;

// ==================== CALCULATE COMPOSITE RISK FOR ALL PARTICIPANTS ====================

if ($selectedFiscalYear) {
    // Get fiscal year name
    foreach ($fiscalYears as $fy) {
        if ($fy['FiscalYear_id'] == $selectedFiscalYear) {
            $selectedFiscalYearName = $fy['FiscalYearName'];
            break;
        }
    }
    
    // Fetch all Reporting Entities
    $sqlMarketParticipants = "SELECT [MarketParticipant_id], [MarketParticipantName], [MarketParticipantShortName]
                              FROM [RiskMatrix_AML].[dbo].[MarketParticipant]
                              WHERE [Status] = 1
                              ORDER BY [MarketParticipantName]";
    $stmtMarketParticipants = sqlsrv_query($conn, $sqlMarketParticipants);
    
    if ($stmtMarketParticipants === false) {
        $errorMessage = "Database error fetching Reporting Entities: " . print_r(sqlsrv_errors(), true);
    } else {
        while ($mp = sqlsrv_fetch_array($stmtMarketParticipants, SQLSRV_FETCH_ASSOC)) {
            $marketParticipant_id = $mp['MarketParticipant_id'];
            
            // Fetch all quantitative data
            $displayData = [
                'customer_risk' => fetchDisplayData($conn, $marketParticipant_id, $selectedFiscalYear, 'customer_risk'),
                'peps_risk' => fetchDisplayData($conn, $marketParticipant_id, $selectedFiscalYear, 'peps_risk'),
                'delivery_channel' => fetchDisplayData($conn, $marketParticipant_id, $selectedFiscalYear, 'delivery_channel'),
                'geographic_zone' => fetchDisplayData($conn, $marketParticipant_id, $selectedFiscalYear, 'geographic_zone')
            ];
            
            // Fetch qualitative data
            $qualitativeData = fetchQualitativeData($conn, $marketParticipant_id, $selectedFiscalYear);
            
            // Calculate Total RAS
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
            
            // Fetch structural risk data
            $structuralData = fetchStructuralRisk($conn, $marketParticipant_id, $selectedFiscalYear);
            $structuralRisk = calculateStructuralRisk($structuralData);
            
            // Calculate activity risks and composite risk
            $totalCompositeRiskSum = 0;
            $totalWeightSum = 0;
            $totalInherentRiskSum = 0;
            $totalInherentWeightSum = 0;
            
            foreach ($allFields as $section => $fields) {
                foreach ($fields as $field => $label) {
                    // Calculate risks for each dimension
                    $values_client = [
                        getFieldValue($displayData['customer_risk'], $section, 1, $field),
                        getFieldValue($displayData['customer_risk'], $section, 2, $field),
                        getFieldValue($displayData['customer_risk'], $section, 3, $field),
                        getFieldValue($displayData['customer_risk'], $section, 4, $field)
                    ];
                    $clientRisk = calculateRiskScore($values_client, $riskWeights['customer_risk']);
                    
                    $values_peps = [
                        getFieldValue($displayData['peps_risk'], $section, 6, $field),
                        getFieldValue($displayData['peps_risk'], $section, 5, $field)
                    ];
                    $pepsRisk = calculateRiskScore($values_peps, $riskWeights['peps_risk']);
                    
                    $values_delivery = [
                        getFieldValue($displayData['delivery_channel'], $section, 7, $field),
                        getFieldValue($displayData['delivery_channel'], $section, 8, $field)
                    ];
                    $deliveryRisk = calculateRiskScore($values_delivery, $riskWeights['delivery_channel']);
                    
                    $values_geo = [
                        getFieldValue($displayData['geographic_zone'], $section, 9, $field),
                        getFieldValue($displayData['geographic_zone'], $section, 10, $field),
                        getFieldValue($displayData['geographic_zone'], $section, 11, $field),
                        getFieldValue($displayData['geographic_zone'], $section, 12, $field)
                    ];
                    $geoRisk = calculateRiskScore($values_geo, $riskWeights['geographic_zone']);
                    
                    $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
                    
                    // Calculate composite risk
                    $compositeRisk = 0;
                    if ($totalInherentRisk > 0 && $totalRAS > 0) {
                        $compositeRisk = ($totalInherentRisk * 0.60) + ($totalRAS * 0.40);
                    }
                    
                    // Get SEBON weight
                    $sebonWeight = isset($sebonWeights[$section][$field]) ? $sebonWeights[$section][$field] / 100 : 0;
                    
                    // Calculate weighted composite risk and inherent risk
                    if ($compositeRisk > 0) {
                        $totalCompositeRiskSum += ($compositeRisk * $sebonWeight);
                        $totalWeightSum += $sebonWeight;
                    }
                    
                    if ($totalInherentRisk > 0) {
                        $totalInherentRiskSum += ($totalInherentRisk * $sebonWeight);
                        $totalInherentWeightSum += $sebonWeight;
                    }
                }
            }
            
            // Calculate Overall Composite Risk
            $overallCompositeRisk = $totalWeightSum > 0 ? ($totalCompositeRiskSum / $totalWeightSum) : 0;
            
            // Calculate Overall Inherent Risk
            $overallInherentRisk = $totalInherentWeightSum > 0 ? ($totalInherentRiskSum / $totalInherentWeightSum) : 0;
            
            // Calculate Net Profile Risk
            $netProfileRisk = calculateNetProfileRisk($structuralRisk, $overallInherentRisk);
            
            // Include every active reporting entity (even with no submitted data / zero scores)
            // NOTE: risk_category is now derived from Net Profile Risk (not Overall Composite Risk),
            // per the requested change. Composite Risk is still calculated and shown, but is no
            // longer the basis for the category badge, rank, or the Highest/Lowest stat cards.
            $summaryData[] = [
                'market_participant_id' => $marketParticipant_id,
                'market_participant_name' => $mp['MarketParticipantName'],
                'market_participant_short' => $mp['MarketParticipantShortName'],
                'composite_risk' => $overallCompositeRisk,
                'risk_category' => getRiskCategory($netProfileRisk),
                'structural_risk' => $structuralRisk,
                'inherent_risk' => $overallInherentRisk,
                'net_profile_risk' => $netProfileRisk
            ];
        }
        
        // Sort by Net Profile Risk in descending order (highest risk first)
        usort($summaryData, function($a, $b) {
            return $b['net_profile_risk'] <=> $a['net_profile_risk'];
        });
    }
}

?>

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
    border-radius: 10px;
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
.btn-save,
.btn-print {
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
    margin: 30px auto;
}

.btn-excel {
    background: linear-gradient(135deg, #1f9d55 0%, #15803d 100%);
    color: white;
    padding: 15px 50px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(21, 128, 61, 0.3);
    display: block;
    margin: 30px auto;
}

.btn-submit:hover,
.btn-save:hover,
.btn-print:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
}

.btn-excel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(21, 128, 61, 0.4);
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
    text-align: center;
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
.section-title,
.report-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin: 30px 0 25px 0;
    font-size: 20px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.report-header h2 {
    font-size: 24px;
    margin-bottom: 10px;
    color: white;
    background: none;
    padding: 0;
    border: none;
}

.report-header p {
    font-size: 16px;
    margin-top: 10px;
    opacity: 0.95;
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
    border: none;
    padding: 0;
}

.no-data-message p {
    color: #95a5a6;
    font-size: 16px;
    margin: 10px 0;
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
   STATISTICS CARDS
   ============================================ */
.statistics-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card h4 {
    margin-bottom: 10px;
    font-size: 14px;
    opacity: 0.9;
    color: white;
}

.stat-card .value {
    font-size: 32px;
    font-weight: 700;
    margin: 10px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.stat-card p {
    font-size: 12px;
    margin-top: 5px;
    opacity: 0.95;
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
    grid-template-columns: 1fr auto;
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
   TABLES
   ============================================ */
.data-table,
.questionnaire-table,
.summary-table,
.container table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.data-table th,
.data-table td,
.questionnaire-table th,
.questionnaire-table td,
.summary-table th,
.summary-table td,
.container table th,
.container table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: center;
}

.data-table thead th,
.questionnaire-table thead th,
.summary-table thead th,
.container table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 13px;
    padding: 12px 10px;
}

.summary-table tbody tr:nth-child(odd) {
    background-color: #f8f9fa;
}

.summary-table tbody tr:hover {
    background-color: #e8f5e9;
    transition: background-color 0.3s;
}

.summary-table .rank-cell {
    font-weight: 700;
    font-size: 16px;
    background: linear-gradient(135deg, #ffd93d 0%, #ffcd00 100%);
    color: #2c3e50;
}

.summary-table .name-cell {
    text-align: left;
    padding-left: 20px !important;
    font-weight: 600;
}

.summary-table .risk-score-cell {
    font-weight: 700;
    font-size: 15px;
    color: #2c3e50;
}

.summary-table .category-cell {
    font-weight: 700;
    color: white;
    font-size: 13px;
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
   RISK DISTRIBUTION ANALYSIS
   ============================================ */
.container > div[style*="background: linear-gradient(135deg, #f8f9fa"] {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    padding: 25px !important;
    border-radius: 10px !important;
    margin: 30px 0 !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.container > div[style*="background: linear-gradient(135deg, #f8f9fa"] h3 {
    color: #2c3e50 !important;
    margin-bottom: 20px !important;
    text-align: center !important;
    border: none !important;
    padding: 0 !important;
}

/* ============================================
   PRINT STYLES
   ============================================ */
.no-print {
    /* Will be hidden in print */
}

.print-hide {
    /* Will be hidden in print */
}

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

    #excelBtn {
        display: none !important;
    }
    
    #riskScaleRef {
        display: none !important;
    }
    
    .no-print {
        display: none !important;
    }
    
    .print-hide {
        display: none !important;
    }
    
    body {
        background-color: white !important;
    }
    
    .container {
        padding: 20px;
        max-width: 100%;
        box-shadow: none;
        margin: 0;
    }
    
    .container h2 {
        margin: 0 0 20px 0;
    }
    
    .report-header {
        background: white !important;
        color: #2c3e50 !important;
        padding: 20px 0 !important;
        border-radius: 0 !important;
        margin-bottom: 20px !important;
        border-bottom: 3px solid #2c3e50;
    }
    
    .report-header h2 {
        color: #2c3e50 !important;
    }
    
    .report-header p {
        display: none !important;
    }
    
    .summary-table {
        page-break-inside: auto;
    }
    
    .summary-table thead {
        display: table-header-group;
    }
    
    .summary-table tbody tr {
        page-break-inside: avoid;
    }
    
    .summary-table thead th {
        background: #2c3e50 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .category-cell {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .rank-cell {
        background-color: #ffd93d !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .summary-table tbody tr:nth-child(odd) {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
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

    .statistics-section {
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
    .summary-table,
    .container table {
        font-size: 11px;
    }

    .data-table th,
    .data-table td,
    .questionnaire-table th,
    .questionnaire-table td,
    .summary-table th,
    .summary-table td,
    .container table th,
    .container table td {
        padding: 8px 5px;
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
    .btn-save,
    .btn-print,
    .btn-excel {
        padding: 12px 30px;
        font-size: 14px;
    }

    #printBtn {
        padding: 8px 20px;
        font-size: 13px;
    }

    .section-title,
    .report-header {
        font-size: 16px;
        padding: 15px;
    }

    .stat-card .value {
        font-size: 24px;
    }

    .summary-table .rank-cell {
        font-size: 14px;
    }

    .summary-table .risk-score-cell {
        font-size: 13px;
    }

    .container > div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<div class="container">
    <div class="page-header no-print">
        <h1>📊 Net Profile Risk & Composite Risk Summary Report</h1>
        <p>Reporting Entity Rankings by Net Profile Risk & Composite Risk</p>
    </div>
    
    <?php if ($errorMessage): ?>
        <div class="alert-error no-print"><?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <!-- Fiscal Year Selection -->
    <div class="filter-section no-print">
        <form method="POST" action="" class="filter-form">
            <div class="form-group">
                <label for="fiscal_year">Select Fiscal Year *</label>
                <select name="fiscal_year" id="fiscal_year" required>
                    <option value="">-- Select Fiscal Year --</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?php echo $fy['FiscalYear_id']; ?>"
                                <?php echo ($selectedFiscalYear == $fy['FiscalYear_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fy['FiscalYearName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-view">Generate Report</button>
        </form>
    </div>
    
    <?php if ($selectedFiscalYear && !empty($summaryData)): ?>
        <div id="printArea">
            <div class="report-header">
                <h2>Net Profile Risk & Composite Risk Summary </h2>
                <p><strong>Fiscal Year:</strong> <?php echo htmlspecialchars($selectedFiscalYearName); ?></p>
                <p style="margin-top: 10px; font-size: 14px;">Generated on: <?php echo date('F d, Y'); ?></p>
            </div>
            
            <!-- Statistics Section -->
            <div class="statistics-section print-hide">
                <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                    <h4>Total Participants</h4>
                    <div class="value"><?php echo count($summaryData); ?></div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                    <h4>Highest Risk</h4>
                    <div class="value"><?php echo number_format($summaryData[0]['net_profile_risk'], 2); ?></div>
                    <p style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($summaryData[0]['market_participant_short']); ?></p>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
                    <h4>Lowest Risk</h4>
                    <div class="value"><?php echo number_format($summaryData[count($summaryData)-1]['net_profile_risk'], 2); ?></div>
                    <p style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($summaryData[count($summaryData)-1]['market_participant_short']); ?></p>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <h4>Average Risk</h4>
                    <div class="value">
                        <?php 
                        $avgRisk = array_sum(array_column($summaryData, 'net_profile_risk')) / count($summaryData);
                        echo number_format($avgRisk, 2); 
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Summary Table -->
            <table class="summary-table" id="summaryTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">Rank</th>
                        <th>Reporting Entity</th>
                        <th style="width: 120px;">Short Name</th>
                        <th style="width: 150px;">Overall Composite Risk</th>
                        <th style="width: 120px;">Risk Category</th>
                        <th style="width: 130px;">Net Profile Risk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($summaryData as $data): 
                        $riskColor = getRiskColor($data['risk_category']);
                    ?>
                        <tr>
                            <td class="rank-cell"><?php echo $rank; ?></td>
                            <td class="name-cell"><?php echo htmlspecialchars($data['market_participant_name']); ?></td>
                            <td><?php echo htmlspecialchars($data['market_participant_short']); ?></td>
                            <td class="risk-score-cell"><?php echo number_format($data['composite_risk'], 2); ?></td>
                            <td class="category-cell" style="background-color: <?php echo $riskColor; ?>;">
                                <?php echo $data['risk_category']; ?>
                            </td>
                            <td class="risk-score-cell"><?php echo number_format($data['net_profile_risk'], 2); ?></td>
                        </tr>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Risk Distribution Analysis -->
        <div class="print-hide" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 30px 0;">
            <h3 style="color: #2c3e50; margin-bottom: 20px; text-align: center;">Risk Distribution Analysis</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <?php
                // Count by risk category
                $riskCounts = [
                    'Very High' => 0,
                    'High' => 0,
                    'Medium' => 0,
                    'Low' => 0,
                    'Very Low' => 0
                ];
                
                foreach ($summaryData as $data) {
                    if (isset($riskCounts[$data['risk_category']])) {
                        $riskCounts[$data['risk_category']]++;
                    }
                }
                
                foreach ($riskCounts as $category => $count):
                    if ($count > 0):
                        $color = getRiskColor($category);
                ?>
                    <div style="background-color: <?php echo $color; ?>; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <h4 style="margin-bottom: 8px; font-size: 14px;"><?php echo $category; ?></h4>
                        <p style="font-size: 28px; font-weight: 700;"><?php echo $count; ?></p>
                        <p style="font-size: 12px; margin-top: 5px;">
                            <?php echo round(($count / count($summaryData)) * 100, 1); ?>%
                        </p>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        
        
        
        <!-- Print Button -->
        <div style="text-align: center; margin: 30px 0; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;" class="no-print">
            <button id="printBtn" onclick="window.print()" class="btn-print">
                🖨️ Print Report
            </button>
            <button id="excelBtn" onclick="downloadReportExcel()" class="btn-excel">
                📥 Download Excel
            </button>
        </div>
        
    <?php elseif ($selectedFiscalYear && empty($summaryData)): ?>
        
        <div class="no-data-message">
            <h3>⚠️ No Data Available</h3>
            <p>No composite risk data found for the selected fiscal year.</p>
            <p style="margin-top: 10px;">Please ensure that both quantitative and qualitative data have been entered for Reporting Entities in this fiscal year.</p>
        </div>
        
    <?php else: ?>
        
        <div class="no-data-message">
            <h3>🔍 Select Fiscal Year</h3>
            <p>Please select a fiscal year from the dropdown above to generate the composite risk summary report.</p>
        </div>
        
    <?php endif; ?>
</div>

<script>
// Auto-submit form on fiscal year change
document.getElementById('fiscal_year').addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});

// Print functionality
window.onbeforeprint = function() {
    document.title = 'Composite Risk Summary - <?php echo htmlspecialchars($selectedFiscalYearName); ?>';
};

window.onafterprint = function() {
    document.title = 'Composite Risk Summary Report';
};

function downloadReportExcel() {
    var reportHeader = document.querySelector('#printArea .report-header');
    var summaryTable = document.getElementById('summaryTable');

    if (!reportHeader || !summaryTable) {
        alert('Report data not available for export.');
        return;
    }

    var fiscalYearText = reportHeader.querySelector('p strong') ? reportHeader.querySelector('p').innerText : '';
    var generatedOnText = reportHeader.querySelectorAll('p').length > 1 ? reportHeader.querySelectorAll('p')[1].innerText : '';

    var workbookHtml = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    workbookHtml += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Composite Risk Summary</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    workbookHtml += '<h2>' + reportHeader.querySelector('h2').innerText + '</h2>';
    workbookHtml += '<p>' + fiscalYearText + '</p>';
    workbookHtml += '<p>' + generatedOnText + '</p>';
    workbookHtml += summaryTable.outerHTML;
    workbookHtml += '</body></html>';

    var blob = new Blob([workbookHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    var safeFiscalYear = '<?php echo addslashes($selectedFiscalYearName); ?>'.replace(/[\\/:*?"<>|]/g, '_');

    link.href = url;
    link.download = 'Composite_Risk_Summary_' + safeFiscalYear + '.xls';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<?php 
include 'footer.php';
ob_end_flush();
?>