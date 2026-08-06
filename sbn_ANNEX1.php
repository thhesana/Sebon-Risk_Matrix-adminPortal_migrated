<?php
// Include database connection
include 'db.php';
include 'header.php';

// Fetch all active Reporting Entities with user details
$sqlMarketParticipants = "
    SELECT 
        mp.MarketParticipant_id,
        mp.MarketParticipantName,
        mp.MarketParticipantShortName,
        u.user_id,
        u.username,
        u.role,
        u.active AS user_active
    FROM [RiskMatrix_AML].[dbo].[MarketParticipant] mp
    LEFT JOIN [RiskMatrix_AML].[dbo].[Users_Detail_mp] u
        ON mp.MarketParticipant_id = u.MarketParticipant_id
    WHERE mp.[Status] = 1
    ORDER BY mp.[MarketParticipantName];
";

$stmtMarketParticipants = sqlsrv_query($conn, $sqlMarketParticipants);


$marketParticipants = [];
if ($stmtMarketParticipants) {
    while ($row = sqlsrv_fetch_array($stmtMarketParticipants, SQLSRV_FETCH_ASSOC)) {
        $marketParticipants[] = $row;
    }
}

// Fetch all Fiscal Years
$sqlFiscalYears = "SELECT [FiscalYear_id], [FiscalYearName]
                   FROM [RiskMatrix_AML].[dbo].[FiscalYear]
                   ORDER BY [FiscalYear_id] DESC";
$stmtFiscalYears = sqlsrv_query($conn, $sqlFiscalYears);

$fiscalYears = [];
$defaultFiscalYearId = null;
if ($stmtFiscalYears) {
    while ($row = sqlsrv_fetch_array($stmtFiscalYears, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
        if ($defaultFiscalYearId === null) {
            $defaultFiscalYearId = $row['FiscalYear_id'];
        }
    }
}

// Get selected values from form submission or use defaults
$selectedMarketParticipant = isset($_POST['market_participant']) ? $_POST['market_participant'] : (count($marketParticipants) > 0 ? $marketParticipants[0]['MarketParticipant_id'] : null);
$selectedFiscalYear = isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : $defaultFiscalYearId;

// Function to fetch data for display
function fetchDisplayData($conn, $marketParticipant_id, $fiscalYear_id, $formType) {
    $data = [];
    
    if($formType == 'total_assets') {
        $sql = "SELECT TotalAssets FROM InformationRegardingTotalAssets 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            return $row['TotalAssets'];
        }
        return null;
    }
    
    // Define SubMaster4table_id mapping based on form type
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
    
    // Fetch StockBrokerService data
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
    
    // Fetch IssueAndSalesManagementService data
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
    
    // Fetch PortfolioManagementService data
    foreach($subMasterIds as $subId) {
        $sql = "SELECT Discretionary, NonDiscretionary, Advisory, ReturnGuarantee
                FROM PortfolioManagementService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['portfolio'][$subId] = $row;
        }
    }
    
    // Fetch BusinessRiskOtherServices data
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

// Helper function to get field value
function getFieldValue($data, $section, $subId, $field, $default = 0) {
    if(isset($data[$section][$subId][$field])) {
        $val = $data[$section][$subId][$field];
        return is_numeric($val) ? $val : $default;
    }
    return $default;
}

// Fetch data if Reporting Entity is selected
$displayData = [];
$selectedMarketParticipantName = '';
$selectedFiscalYearName = '';

if ($selectedMarketParticipant && $selectedFiscalYear) {
    $displayData = [
        'customer_risk' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'customer_risk'),
        'peps_risk' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'peps_risk'),
        'delivery_channel' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'delivery_channel'),
        'geographic_zone' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'geographic_zone'),
        'total_assets' => fetchDisplayData($conn, $selectedMarketParticipant, $selectedFiscalYear, 'total_assets')
    ];
    
    // Get selected names for display
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
}

// Field definitions
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

// Risk Weight Definitions
$riskWeights = [
    'customer_risk' => [
        'np_resident' => 1,
        'np_nonresident' => 3,
        'lp_resident' => 4,
        'lp_nonresident' => 5
    ],
    'peps_risk' => [
        'domestic' => 4,
        'foreign' => 5
    ],
    'delivery_channel' => [
        'otc' => 3,
        'non_face' => 5
    ],
    'geographic_zone' => [
        'rural' => 1,
        'urban' => 2,
        'kathmandu' => 3,
        'border' => 4
    ]
];

// Function to calculate risk score
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

// Function to calculate linear interpolation rating for assets
function calculateAssetRating($assets, $minAssets, $maxAssets) {
    if ($maxAssets == $minAssets) {
        return 1.00;
    }
    
    // Linear interpolation: Higher assets = Lower rating (1), Lower assets = Higher rating (5)
    $rating = (($assets - $minAssets) / ($maxAssets - $minAssets)) * (1 - 5) + 5;
    return round($rating, 2);
}

// Function to get risk category
function getRiskCategory($rating) {
    if ($rating >= 1.00 && $rating <= 1.20) return 'Very low';
    if ($rating >= 1.21 && $rating <= 1.80) return 'Low';
    if ($rating >= 1.81 && $rating <= 3.40) return 'Medium';
    if ($rating >= 3.41 && $rating <= 4.20) return 'High';
    if ($rating >= 4.21 && $rating <= 5.00) return 'Very high';
    return 'N/A';
}

// Function to get risk color
function getRiskColor($category) {
    switch($category) {
        case 'Very low': return '#27ae60';
        case 'Low': return '#2ecc71';
        case 'Medium': return '#f39c12';
        case 'High': return '#e67e22';
        case 'Very high': return '#e74c3c';
        default: return '#95a5a6';
    }
}

// Function to render table rows for each risk category
function renderTableRows($displayData, $formType, $fields, $subMasterIds, $riskWeights) {
    $html = '';
    foreach ($fields as $field => $label) {
        $values = [];
        foreach ($subMasterIds as $subId) {
            $values[] = getFieldValue($displayData, $formType, $subId, $field);
        }
        $total = array_sum($values);
        $riskScore = calculateRiskScore($values, $riskWeights);
        
        $html .= '<tr>';
        $html .= '<td class="activity-cell">' . htmlspecialchars($label) . '</td>';
        foreach ($values as $value) {
            $html .= '<td>' . number_format($value, ($field == 'DematAccountCount' ? 0 : 2)) . '</td>';
        }
        $html .= '<td class="total-column">' . number_format($total, ($field == 'DematAccountCount' ? 0 : 2)) . '</td>';
        $html .= '<td class="risk-column">' . number_format($riskScore, 2) . '</td>';
        $html .= '</tr>';
    }
    return $html;
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

/* User Info Section */
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
   PAGE HEADER (Alternative Style)
   ============================================ */
.page-header {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.page-header h1 {
    font-size: 24px;
    margin-bottom: 8px;
    font-weight: 600;
}

.page-header p {
    font-size: 14px;
    opacity: 0.9;
}

/* ============================================
   BUTTON STYLES
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

.btn-view {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 40px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
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

/* Tab Buttons (Alternative Style) */
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
   CONTAINER & LAYOUT
   ============================================ */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background-color: white;
}

.content-wrapper {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

/* ============================================
   CARD STYLES
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

/* Info Card (Alternative Style) */
.info-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: 600;
}

.info-card p {
    font-size: 14px;
    opacity: 0.95;
    line-height: 1.6;
}

/* ============================================
   FORM STYLES
   ============================================ */
.filter-section {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
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

.filter-form {
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
    font-weight: 600;
    color: #34495e;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group select,
.form-group input {
    padding: 12px 15px;
    border: 2px solid #dfe6e9;
    border-radius: 6px;
    font-size: 14px;
    background-color: white;
    transition: all 0.3s;
}

.form-group select {
    cursor: pointer;
}

.form-group select:focus,
.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* ============================================
   TABLE STYLES
   ============================================ */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.data-table th,
.data-table td {
    border: 1px solid #dfe6e9;
    padding: 12px;
    text-align: center;
}

.data-table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table .activity-header {
    background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    color: #2c3e50;
    font-weight: 700;
    text-align: left;
    font-size: 14px;
    padding-left: 15px;
}

.data-table .activity-cell {
    text-align: left;
    background-color: #e8f5e9;
    padding-left: 35px;
    font-weight: 500;
    color: #2c3e50;
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

.data-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* ============================================
   MESSAGES & ALERTS
   ============================================ */
.no-data-message {
    text-align: center;
    padding: 60px 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin: 30px 0;
}

.no-data-message h3 {
    font-size: 20px;
    color: #7f8c8d;
    margin-bottom: 10px;
}

.no-data-message p {
    color: #95a5a6;
    font-size: 14px;
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 768px) {
    .filter-form {
        grid-template-columns: 1fr;
    }

    .header h1 {
        font-size: 1.5rem;
    }

    .header h2 {
        font-size: 1.25rem;
    }

    .page-header h1 {
        font-size: 20px;
    }

    .container {
        padding: 10px;
    }

    .data-table {
        font-size: 11px;
    }

    .data-table th,
    .data-table td {
        padding: 8px 5px;
    }

    .tab-button {
        padding: 10px 15px;
        font-size: 13px;
    }
}
</style>

<div class="container">
    <div class="page-header">
        <h1>📊 Admin Portal - Risk Assessment Data Viewer</h1>
        <p>View and analyze client-submitted risk assessment data</p>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section"> 
	<div class="form-group">
                <label for="fiscal_year">Fiscal Year</label>
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
        <div class="filter-title">Select Reporting Entity & Fiscal Year</div>
        <form method="POST" action="" class="filter-form">
            <div class="form-group">
                <label for="market_participant">Reporting Entity</label>
                <select name="market_participant" id="market_participant" required>
                    <option value="">-- Select Reporting Entity --</option>
                    <?php foreach ($marketParticipants as $mp): ?>
                        <option value="<?php echo $mp['MarketParticipant_id']; ?>" 
                                <?php echo ($selectedMarketParticipant == $mp['MarketParticipant_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mp['MarketParticipantName']); ?>
                            <?php if (!empty($mp['MarketParticipantShortName'])): ?>
                                (<?php echo htmlspecialchars($mp['MarketParticipantShortName']); ?>)
                            <?php endif; ?>
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
        
        <!-- Tab Buttons -->
        <div class="tab-buttons">
            <button class="tab-button active" onclick="openTab(event, 'customer_risk')">A. Customer Risk</button>
            <button class="tab-button" onclick="openTab(event, 'peps_risk')">B. PEPs Risk</button>
            <button class="tab-button" onclick="openTab(event, 'delivery_channel')">C. Delivery Channel</button>
            <button class="tab-button" onclick="openTab(event, 'geographic_zone')">D. Geographic Zone</button>
            <button class="tab-button" onclick="openTab(event, 'total_assets')">E. Total Assets</button>
        </div>
        
        <!-- Tab 1: Customer Risk -->
        <div id="customer_risk" class="tab-content active">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">A. Customer Information on the basis of Product & Services</h2>
            
            <?php if (!empty($displayData['customer_risk']['stock_broker']) || !empty($displayData['customer_risk']['issue_sales']) || 
                      !empty($displayData['customer_risk']['portfolio']) || !empty($displayData['customer_risk']['business_risk'])): ?>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="3" style="width: 250px;">Significant Activities</th>
                        <th colspan="4">Customer Risk</th>
                        <th rowspan="3">TOTAL AMT<br>(Rs.)</th>
                        <th rowspan="3">TOTAL RISK<br>BY ACTIVITY</th>
                    </tr>
                    <tr>
                        <th colspan="2">Natural Person</th>
                        <th colspan="2">Legal Person</th>
                    </tr>
                    <tr>
                        <th style="background-color: #3498db;">Resident (Rs.)</th>
                        <th style="background-color: #3498db;">Non-Resident (Rs.)</th>
                        <th style="background-color: #3498db;">Resident (Rs.)</th>
                        <th style="background-color: #3498db;">Non-Resident (Rs.)</th>
                    </tr>
                    <tr style="background-color: #ffe6e6;">
                        <th style="background-color: #e74c3c; color: white;">Risk Weights</th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['customer_risk']['np_resident']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['customer_risk']['np_nonresident']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['customer_risk']['lp_resident']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['customer_risk']['lp_nonresident']; ?></th>
                        <th colspan="2" style="background-color: #dfe6e9;"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Stock Broker Service -->
                    <tr><td colspan="7" class="activity-header">1. Stock Broker Service :</td></tr>
                    <?php echo renderTableRows($displayData['customer_risk'], 'stock_broker', $stockFields, [1, 2, 3, 4], array_values($riskWeights['customer_risk'])); ?>
                    
                    <!-- Issue & Sales Management -->
                    <tr><td colspan="7" class="activity-header">2. Issue & Sales Management Service :</td></tr>
                    <?php echo renderTableRows($displayData['customer_risk'], 'issue_sales', $issueFields, [1, 2, 3, 4], array_values($riskWeights['customer_risk'])); ?>
                    
                    <!-- Portfolio Management -->
                    <tr><td colspan="7" class="activity-header">3. Portfolio Management services:</td></tr>
                    <?php echo renderTableRows($displayData['customer_risk'], 'portfolio', $portfolioFields, [1, 2, 3, 4], array_values($riskWeights['customer_risk'])); ?>
                    
                    <!-- Business Risk -->
                    <tr><td colspan="7" class="activity-header">4. Business Risk for Other services:</td></tr>
                    <?php
                    $np_resident = getFieldValue($displayData['customer_risk'], 'business_risk', 1, 'DematAccountCount');
                    $np_nonresident = getFieldValue($displayData['customer_risk'], 'business_risk', 2, 'DematAccountCount');
                    $lp_resident = getFieldValue($displayData['customer_risk'], 'business_risk', 3, 'DematAccountCount');
                    $lp_nonresident = getFieldValue($displayData['customer_risk'], 'business_risk', 4, 'DematAccountCount');
                    $total = $np_resident + $np_nonresident + $lp_resident + $lp_nonresident;
                    $values = [$np_resident, $np_nonresident, $lp_resident, $lp_nonresident];
                    $riskScore = calculateRiskScore($values, array_values($riskWeights['customer_risk']));
                    ?>
                    <tr>
                        <td class="activity-cell">Demat Account (in number)</td>
                        <td><?php echo number_format($np_resident, 0); ?></td>
                        <td><?php echo number_format($np_nonresident, 0); ?></td>
                        <td><?php echo number_format($lp_resident, 0); ?></td>
                        <td><?php echo number_format($lp_nonresident, 0); ?></td>
                        <td class="total-column"><?php echo number_format($total, 0); ?></td>
                        <td class="risk-column"><?php echo number_format($riskScore, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📭 No Data Available</h3>
                    <p>This Reporting Entity has not submitted customer risk data for the selected fiscal year.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 2: PEPs Risk -->
        <div id="peps_risk" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">B. PEPs Risk Information</h2>
            
            <?php if (!empty($displayData['peps_risk']['stock_broker']) || !empty($displayData['peps_risk']['issue_sales']) || 
                      !empty($displayData['peps_risk']['portfolio']) || !empty($displayData['peps_risk']['business_risk'])): ?>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="3" style="width: 250px;">Significant Activities</th>
                        <th colspan="2">PEPs (Local and Foreign)</th>
                        <th rowspan="3">TOTAL AMT<br>(Rs.)</th>
                        <th rowspan="3">TOTAL RISK<br>BY ACTIVITY</th>
                    </tr>
                    <tr>
                        <th style="background-color: #3498db;">Domestic (Rs.)</th>
                        <th style="background-color: #3498db;">Foreign (Rs.)</th>
                    </tr>
                    <tr style="background-color: #ffe6e6;">
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['peps_risk']['domestic']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['peps_risk']['foreign']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Stock Broker Service -->
                    <tr><td colspan="5" class="activity-header">1. Stock Broker Service :</td></tr>
                    <?php echo renderTableRows($displayData['peps_risk'], 'stock_broker', $stockFields, [6, 5], array_values($riskWeights['peps_risk'])); ?>
                    
                    <!-- Issue & Sales -->
                    <tr><td colspan="5" class="activity-header">2. Issue & Sales Management Service :</td></tr>
                    <?php echo renderTableRows($displayData['peps_risk'], 'issue_sales', $issueFields, [6, 5], array_values($riskWeights['peps_risk'])); ?>
                    
                    <!-- Portfolio Management -->
                    <tr><td colspan="5" class="activity-header">3. Portfolio Management services:</td></tr>
                    <?php echo renderTableRows($displayData['peps_risk'], 'portfolio', $portfolioFields, [6, 5], array_values($riskWeights['peps_risk'])); ?>
                    
                    <!-- Business Risk -->
                    <tr><td colspan="5" class="activity-header">4. Business Risk for Other services:</td></tr>
                    <?php
                    $domestic = getFieldValue($displayData['peps_risk'], 'business_risk', 6, 'DematAccountCount');
                    $foreign = getFieldValue($displayData['peps_risk'], 'business_risk', 5, 'DematAccountCount');
                    $total = $domestic + $foreign;
                    $values = [$domestic, $foreign];
                    $riskScore = calculateRiskScore($values, array_values($riskWeights['peps_risk']));
                    ?>
                    <tr>
                        <td class="activity-cell">Demat Account (in number)</td>
                        <td><?php echo number_format($domestic, 0); ?></td>
                        <td><?php echo number_format($foreign, 0); ?></td>
                        <td class="total-column"><?php echo number_format($total, 0); ?></td>
                        <td class="risk-column"><?php echo number_format($riskScore, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📭 No Data Available</h3>
                    <p>This Reporting Entity has not submitted PEPs risk data for the selected fiscal year.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 3: Delivery Channel -->
        <div id="delivery_channel" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">C. Information Regarding Delivery Channel</h2>
            
            <?php if (!empty($displayData['delivery_channel']['stock_broker']) || !empty($displayData['delivery_channel']['issue_sales']) || 
                      !empty($displayData['delivery_channel']['portfolio']) || !empty($displayData['delivery_channel']['business_risk'])): ?>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="3" style="width: 250px;">Significant Activities</th>
                        <th colspan="2">Delivery Channel</th>
                        <th rowspan="3">TOTAL AMT<br>(Rs.)</th>
                        <th rowspan="3">TOTAL RISK<br>BY ACTIVITY</th>
                    </tr>
                    <tr>
                        <th style="background-color: #3498db;">Over the Counter (Rs.)</th>
                        <th style="background-color: #3498db;">Non-Face to Face (Rs.)</th>
                    </tr>
                    <tr style="background-color: #ffe6e6;">
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['delivery_channel']['otc']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['delivery_channel']['non_face']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Stock Broker Service -->
                    <tr><td colspan="5" class="activity-header">1. Stock Broker Service :</td></tr>
                    <?php echo renderTableRows($displayData['delivery_channel'], 'stock_broker', $stockFields, [7, 8], array_values($riskWeights['delivery_channel'])); ?>
                    
                    <!-- Issue & Sales -->
                    <tr><td colspan="5" class="activity-header">2. Issue & Sales Management Service :</td></tr>
                    <?php echo renderTableRows($displayData['delivery_channel'], 'issue_sales', $issueFields, [7, 8], array_values($riskWeights['delivery_channel'])); ?>
                    
                    <!-- Portfolio Management -->
                    <tr><td colspan="5" class="activity-header">3. Portfolio Management services:</td></tr>
                    <?php echo renderTableRows($displayData['delivery_channel'], 'portfolio', $portfolioFields, [7, 8], array_values($riskWeights['delivery_channel'])); ?>
                    
                    <!-- Business Risk -->
                    <tr><td colspan="5" class="activity-header">4. Business Risk for Other services:</td></tr>
                    <?php
                    $otc = getFieldValue($displayData['delivery_channel'], 'business_risk', 7, 'DematAccountCount');
                    $non_face = getFieldValue($displayData['delivery_channel'], 'business_risk', 8, 'DematAccountCount');
                    $total = $otc + $non_face;
                    $values = [$otc, $non_face];
                    $riskScore = calculateRiskScore($values, array_values($riskWeights['delivery_channel']));
                    ?>
                    <tr>
                        <td class="activity-cell">Demat Account (in number)</td>
                        <td><?php echo number_format($otc, 0); ?></td>
                        <td><?php echo number_format($non_face, 0); ?></td>
                        <td class="total-column"><?php echo number_format($total, 0); ?></td>
                        <td class="risk-column"><?php echo number_format($riskScore, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📭 No Data Available</h3>
                    <p>This Reporting Entity has not submitted delivery channel data for the selected fiscal year.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 4: Geographic Zone -->
        <div id="geographic_zone" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">D. Geographic Zone Risk Information</h2>
            
            <?php if (!empty($displayData['geographic_zone']['stock_broker']) || !empty($displayData['geographic_zone']['issue_sales']) || 
                      !empty($displayData['geographic_zone']['portfolio']) || !empty($displayData['geographic_zone']['business_risk'])): ?>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="3" style="width: 250px;">Significant Activities</th>
                        <th colspan="4">Geographic Zone</th>
                        <th rowspan="3">TOTAL AMT<br>(Rs.)</th>
                        <th rowspan="3">TOTAL RISK<br>BY ACTIVITY</th>
                    </tr>
                    <tr>
                        <th style="background-color: #3498db;">Rural Areas (Rs.)</th>
                        <th style="background-color: #3498db;">Other Urban (Rs.)</th>
                        <th style="background-color: #3498db;">Kathmandu (Rs.)</th>
                        <th style="background-color: #3498db;">Border Areas (Rs.)</th>
                    </tr>
                    <tr style="background-color: #ffe6e6;">
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['geographic_zone']['rural']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['geographic_zone']['urban']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['geographic_zone']['kathmandu']; ?></th>
                        <th style="background-color: #ffeaa7; color: #2c3e50; font-weight: bold;"><?php echo $riskWeights['geographic_zone']['border']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Stock Broker Service -->
                    <tr><td colspan="7" class="activity-header">1. Stock Broker Service :</td></tr>
                    <?php echo renderTableRows($displayData['geographic_zone'], 'stock_broker', $stockFields, [9, 10, 11, 12], array_values($riskWeights['geographic_zone'])); ?>
                    
                    <!-- Issue & Sales -->
                    <tr><td colspan="7" class="activity-header">2. Issue & Sales Management Service :</td></tr>
                    <?php echo renderTableRows($displayData['geographic_zone'], 'issue_sales', $issueFields, [9, 10, 11, 12], array_values($riskWeights['geographic_zone'])); ?>
                    
                    <!-- Portfolio Management -->
                    <tr><td colspan="7" class="activity-header">3. Portfolio Management services:</td></tr>
                    <?php echo renderTableRows($displayData['geographic_zone'], 'portfolio', $portfolioFields, [9, 10, 11, 12], array_values($riskWeights['geographic_zone'])); ?>
                    
                    <!-- Business Risk -->
                    <tr><td colspan="7" class="activity-header">4. Business Risk for Other services:</td></tr>
                    <?php
                    $rural = getFieldValue($displayData['geographic_zone'], 'business_risk', 9, 'DematAccountCount');
                    $urban = getFieldValue($displayData['geographic_zone'], 'business_risk', 10, 'DematAccountCount');
                    $kathmandu = getFieldValue($displayData['geographic_zone'], 'business_risk', 11, 'DematAccountCount');
                    $border = getFieldValue($displayData['geographic_zone'], 'business_risk', 12, 'DematAccountCount');
                    $total = $rural + $urban + $kathmandu + $border;
                    $values = [$rural, $urban, $kathmandu, $border];
                    $riskScore = calculateRiskScore($values, array_values($riskWeights['geographic_zone']));
                    ?>
                    <tr>
                        <td class="activity-cell">Demat Account (in number)</td>
                        <td><?php echo number_format($rural, 0); ?></td>
                        <td><?php echo number_format($urban, 0); ?></td>
                        <td><?php echo number_format($kathmandu, 0); ?></td>
                        <td><?php echo number_format($border, 0); ?></td>
                        <td class="total-column"><?php echo number_format($total, 0); ?></td>
                        <td class="risk-column"><?php echo number_format($riskScore, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📭 No Data Available</h3>
                    <p>This Reporting Entity has not submitted geographic zone data for the selected fiscal year.</p>
                </div>
            <?php endif; ?>
        </div>
        
         <?php /* Tab 5: Total Assets */ ?>

        <div id="total_assets" class="tab-content">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">E. Information Regarding Total Assets</h2>
            
            <?php 
            // Fetch all Reporting Entities' total assets for the selected fiscal year
            $sqlAllAssets = "SELECT mp.MarketParticipantName, mp.MarketParticipant_id, 
                 ISNULL(ta.TotalAssets, 0) as TotalAssets
                 FROM [RiskMatrix_AML].[dbo].[MarketParticipant] mp
                 LEFT JOIN [RiskMatrix_AML].[dbo].[InformationRegardingTotalAssets] ta 
                 ON mp.MarketParticipant_id = ta.MarketParticipant_id 
                 AND ta.FiscalYear_id = ?
                 WHERE mp.Status = 1
                 AND mp.MarketParticipant_id = ?
                 ORDER BY TotalAssets DESC";

$stmtAllAssets = sqlsrv_query($conn, $sqlAllAssets, array(
    (int)$selectedFiscalYear, 
    (int)$selectedMarketParticipant
));
            
            $allAssets = [];
            $totalSum = 0;
            if ($stmtAllAssets) {
                while ($row = sqlsrv_fetch_array($stmtAllAssets, SQLSRV_FETCH_ASSOC)) {
                    $allAssets[] = $row;
                    $totalSum += $row['TotalAssets'];
                }
            }
            
            // Find min and max assets (excluding zeros for proper interpolation)
            $minAssets = PHP_FLOAT_MAX;
            $maxAssets = 0;
            foreach ($allAssets as $asset) {
                if ($asset['TotalAssets'] > 0) {
                    if ($asset['TotalAssets'] < $minAssets) $minAssets = $asset['TotalAssets'];
                    if ($asset['TotalAssets'] > $maxAssets) $maxAssets = $asset['TotalAssets'];
                }
            }
            
            // Calculate ratings for all participants
            foreach ($allAssets as &$asset) {
                $asset['Percentage'] = $totalSum > 0 ? ($asset['TotalAssets'] / $totalSum) * 100 : 0;
                $asset['Rating'] = $asset['TotalAssets'] > 0 ? calculateAssetRating($asset['TotalAssets'], $minAssets, $maxAssets) : 5.00;
                $asset['RiskCategory'] = getRiskCategory($asset['Rating']);
            }
            
            if (!empty($allAssets)): ?>
                <div style="margin-bottom: 20px; padding: 15px; background-color: #e8f5e9; border-left: 4px solid #27ae60; border-radius: 4px;">
                    <p style="margin: 0; color: #2c3e50; font-size: 14px;">
                        <strong>📊 Linear Interpolation Method:</strong> Assets ratings are calculated using linear interpolation where the Reporting Entity with the <strong>highest assets</strong> receives a rating of <strong>1.00 (Very low risk)</strong> and the <strong>lowest assets</strong> receives <strong>5.00 (Very high risk)</strong>. Formula: Y = ((X-Xmin)/(Xmax-Xmin))×(Ymax-Ymin)+Ymin
                    </p>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Position</th>
                            <th style="width: 300px; text-align: left;">Name of Securities Business Persons</th>
                            <th>Total Assets (Rs.)</th>
                            <th>Percentage of Total</th>
                            <th>Rating (Y)</th>
                            <th>Risk Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $position = 1;
                        foreach ($allAssets as $asset): 
                            $isSelected = ($asset['MarketParticipant_id'] == $selectedMarketParticipant);
                            $rowStyle = $isSelected ? 'background-color: #fff9c4; font-weight: 600;' : '';
                            $riskColor = getRiskColor($asset['RiskCategory']);
                        ?>
                        <tr style="<?php echo $rowStyle; ?>">
                            <td style="text-align: center;"><?php echo $position; ?></td>
                            <td style="text-align: left; padding-left: 15px;">
                                <?php echo htmlspecialchars($asset['MarketParticipantName']); ?>
                                <?php if ($isSelected): ?>
                                    <span style="color: #f39c12; margin-left: 10px;">⭐ Selected</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; padding-right: 20px;">
                                <?php echo number_format($asset['TotalAssets'], 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo number_format($asset['Percentage'], 1); ?>%
                            </td>
                            <td style="text-align: center; font-weight: 700; color: <?php echo $riskColor; ?>;">
                                <?php echo number_format($asset['Rating'], 2); ?>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: <?php echo $riskColor; ?>;">
                                <?php echo $asset['RiskCategory']; ?>
                            </td>
                        </tr>
                        <?php 
                        $position++;
                        endforeach; 
                        ?>
                        <tr style="background-color: #f8f9fa; font-weight: 700; border-top: 3px solid #2c3e50;">
                            <td colspan="2" style="text-align: left; padding-left: 15px;">Sum Total</td>
                            <td style="text-align: right; padding-right: 20px;"><?php echo number_format($totalSum, 2); ?></td>
                            <td style="text-align: center;">100.0%</td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-bottom: 15px; color: #2c3e50; font-size: 16px;">📋 Risk Scale Reference</h3>
                    <table style="width: 100%; max-width: 500px; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: #34495e; color: white;">
                                <th style="padding: 10px; border: 1px solid #ddd;">Scale</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">From</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background-color: #d5f4e6;">
                                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600; color: #27ae60;">Very low</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1.00</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1.20</td>
                            </tr>
                            <tr style="background-color: #e8f8f5;">
                                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600; color: #2ecc71;">Low</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1.21</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1.80</td>
                            </tr>
                            <tr style="background-color: #fff3cd;">
                                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600; color: #f39c12;">Medium</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1.81</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">3.40</td>
                            </tr>
                            <tr style="background-color: #ffe8d1;">
                                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600; color: #e67e22;">High</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">3.41</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">4.20</td>
                            </tr>
                            <tr style="background-color: #fadbd8;">
                                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600; color: #e74c3c;">Very high</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">4.21</td>
                                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">5.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📭 No Data Available</h3>
                    <p>No Reporting Entities have submitted total assets information for the selected fiscal year.</p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <div class="no-data-message">
            <h3>🔍 Please Select Options</h3>
            <p>Please select a Reporting Entity and Fiscal Year from the filters above to view the data.</p>
        </div>
    <?php endif; ?>
</div>


<script>
function openTab(evt, tabName) {
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove("active");
    }
    
    var tabButtons = document.getElementsByClassName("tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }
    
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>

<?php include 'footer.php'; ?>