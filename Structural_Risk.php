<?php
// Structural_Risk.php
include 'db.php';
include 'header.php';

// Fetch fiscal years
$sqlFiscalYears = "SELECT [FiscalYear_id], [FiscalYearName] FROM [RiskMatrix_AML].[dbo].[FiscalYear] ORDER BY [FiscalYear_id] DESC";
$stmtFiscalYears = sqlsrv_query($conn, $sqlFiscalYears);
$fiscalYears = array();
if ($stmtFiscalYears) {
    while ($row = sqlsrv_fetch_array($stmtFiscalYears, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
    }
}

// Selected fiscal year
$selectedFiscalYear = null;
if (isset($_POST['fiscal_year']) && $_POST['fiscal_year'] !== '') {
    $selectedFiscalYear = $_POST['fiscal_year'];
} elseif (count($fiscalYears) > 0) {
    $selectedFiscalYear = $fiscalYears[0]['FiscalYear_id'];
}

// Functions
function calculateAssetRating($assets, $minAssets, $maxAssets) {
    $x1 = $maxAssets;
    $y1 = 1.0;
    $x2 = $minAssets;
    $y2 = 5.0;
    if ($maxAssets == $minAssets) return 1.0;
    $rating = $y1 + (($assets - $x1) / ($x2 - $x1)) * ($y2 - $y1);
    return round($rating, 2);
}

function getRiskCategory($rating) {
    if ($rating >= 1.00 && $rating <= 1.20) return 'Very low';
    if ($rating >= 1.21 && $rating <= 1.80) return 'Low';
    if ($rating >= 1.81 && $rating <= 3.40) return 'Medium';
    if ($rating >= 3.41 && $rating <= 4.20) return 'High';
    if ($rating >= 4.21 && $rating <= 5.00) return 'Very high';
    return 'N/A';
}

function getRiskColor($category) {
    switch ($category) {
        case 'Very low': return '#27ae60';
        case 'Low': return '#2ecc71';
        case 'Medium': return '#f39c12';
        case 'High': return '#e67e22';
        case 'Very high': return '#e74c3c';
        default: return '#7f8c8d';
    }
}
?>

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
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
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

.btn-submit {
    background-color: #27ae60;
    color: white;
    padding: 15px 50px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-submit:hover {
    background-color: #229954;
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
   INFO BOXES
   ============================================ */
.container > div[style*="background:#fff3cd"] {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe7a0 100%) !important;
    padding: 15px 20px !important;
    margin: 15px 0 !important;
    border-left: 4px solid #ffc107 !important;
    border-radius: 6px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    line-height: 1.8;
}

.container > div[style*="color:red"] {
    background: linear-gradient(135deg, #fee 0%, #fcc 100%) !important;
    padding: 15px 20px !important;
    margin: 15px 0 !important;
    border-left: 4px solid #e74c3c !important;
    border-radius: 6px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
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
   FORMS
   ============================================ */
.filter-section,
.selection-panel {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
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
    font-weight: 600;
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
    border-radius: 6px;
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
.container table th,
.container table td {
    border: 1px solid #dfe6e9;
    padding: 12px;
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
    font-size: 13px;
}

.container table thead th {
    padding: 12px 8px;
}

/* Multi-row header support */
.container table thead tr:nth-child(2) th {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    font-size: 12px;
}

.container table thead th[style*="background:#34495e"] {
    background: linear-gradient(135deg, #5d6d7e 0%, #34495e 100%) !important;
}

.container table thead th[style*="background:#c0392b"] {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
}

.container table tbody tr {
    transition: background-color 0.3s;
}

.container table tbody tr:hover {
    background-color: #f8f9fa;
}

.container table tbody tr:nth-child(even) {
    background-color: #fafbfc;
}

/* Highlighted cells */
.container table td[style*="background:#fff9e6"] {
    background: linear-gradient(135deg, #fff9e6 0%, #fff3d4 100%) !important;
    font-weight: 600;
}

.container table td[style*="background:#ffe6e6"] {
    background: linear-gradient(135deg, #ffe6e6 0%, #ffd4d4 100%) !important;
    font-weight: 700;
}

.data-table .activity-header,
.section-header {
    background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    color: #2c3e50;
    font-weight: 700;
    text-align: left;
    font-size: 14px;
    padding: 15px !important;
}

.data-table .activity-cell,
.question-cell {
    text-align: left;
    background-color: #ecf0f1;
    padding-left: 15px;
    font-weight: 500;
    color: #2c3e50;
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
   MESSAGES & ALERTS
   ============================================ */
.no-data-message {
    text-align: center;
    padding: 60px 20px;
    background-color: #fff3cd;
    color: #856404;
    border-radius: 8px;
    margin: 30px 0;
    font-size: 16px;
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
        font-size: 11px;
    }

    .data-table th,
    .data-table td,
    .questionnaire-table th,
    .questionnaire-table td,
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

    .btn-submit {
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
}
</style>
</style>

<div class="container" style="padding:20px;" id="printArea">
    <h2>Structural Risk Calculator</h2>

    <!-- Fiscal year selector -->
    <form method="POST" style="margin:10px 0 20px 0;">
        <label for="fiscal_year"><strong>Select Fiscal Year:</strong></label>
        <select name="fiscal_year" id="fiscal_year" onchange="this.form.submit()">
            <?php
            foreach ($fiscalYears as $fy) {
                $sel = ($selectedFiscalYear == $fy['FiscalYear_id']) ? 'selected="selected"' : '';
                echo '<option value="' . htmlspecialchars($fy['FiscalYear_id']) . '" ' . $sel . '>' . htmlspecialchars($fy['FiscalYearName']) . '</option>';
            }
            ?>
        </select>
    </form>

<?php
if ($selectedFiscalYear === null) {
    echo '<p>Please select a fiscal year.</p>';
    include 'footer.php';
    exit;
}

// Fetch Reporting Entities
$sql = "
    SELECT 
        mp.MarketParticipant_id,
        mp.MarketParticipantName,
        ISNULL(agg.TotalAssets, 0) AS TotalAssets,
        ISNULL(mp.IsFinancialGroup, 0) AS IsFinancialGroup
    FROM [RiskMatrix_AML].[dbo].[MarketParticipant] mp
    LEFT JOIN (
        SELECT MarketParticipant_id, SUM(ISNULL(TotalAssets,0)) AS TotalAssets
        FROM [RiskMatrix_AML].[dbo].[InformationRegardingTotalAssets]
        WHERE FiscalYear_id = ?
        GROUP BY MarketParticipant_id
    ) agg ON mp.MarketParticipant_id = agg.MarketParticipant_id
    WHERE mp.Status = 1
    ORDER BY agg.TotalAssets DESC, mp.MarketParticipantName ASC
";

$params = array($selectedFiscalYear);
$stmt = sqlsrv_query($conn, $sql, $params);

$allData = array();
$totalSum = 0.0;

if ($stmt === false) {
    $err = sqlsrv_errors();
    echo '<div style="color:red;"><strong>SQL error:</strong> ';
    if ($err !== null) {
        foreach ($err as $e) echo htmlspecialchars($e['message']) . ' ';
    }
    echo '</div>';
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['TotalAssets'] = isset($row['TotalAssets']) ? floatval($row['TotalAssets']) : 0.0;
        $row['IsFinancialGroup'] = isset($row['IsFinancialGroup']) ? intval($row['IsFinancialGroup']) : 0;
        $allData[] = $row;
        $totalSum += $row['TotalAssets'];
    }
}

// min and max
$minAssets = PHP_FLOAT_MAX;
$maxAssets = 0.0;
foreach ($allData as $r) {
    if ($r['TotalAssets'] > 0) {
        if ($r['TotalAssets'] < $minAssets) $minAssets = $r['TotalAssets'];
        if ($r['TotalAssets'] > $maxAssets) $maxAssets = $r['TotalAssets'];
    }
}
if ($minAssets === PHP_FLOAT_MAX) $minAssets = $maxAssets = 0.0;

// Calculate risk
foreach ($allData as &$r) {
    $r['SizeRating'] = ($r['TotalAssets']>0) ? (($maxAssets>$minAssets)?calculateAssetRating($r['TotalAssets'],$minAssets,$maxAssets):1.0):5.0;
    $r['SizeWeight']=0.90; 
    $r['SizeWeightedRisk']=$r['SizeRating']*$r['SizeWeight'];
    $r['FinancialGroupRating']=($r['IsFinancialGroup']==1)?5.0:3.0;
    $r['FinancialGroupWeight']=0.10; 
    $r['FinancialGroupWeightedRisk']=$r['FinancialGroupRating']*$r['FinancialGroupWeight'];
    $r['TotalStructuralRisk']=round($r['SizeWeightedRisk']+$r['FinancialGroupWeightedRisk'],2);
    $r['RiskCategory']=getRiskCategory($r['TotalStructuralRisk']);
}
unset($r);

// Display
if (count($allData) === 0) {
    echo '<p>No Reporting Entities found.</p>';
} else {
    echo '<h3>STRUCTURAL RISK CALCULATION</h3>';
    echo '<p><strong>Formula:</strong> Structural Risk = (Size Rating × 90%) + (Financial Group Rating × 10%)</p>';
    echo '<div style="background:#fff3cd; padding:10px; margin:10px 0; border-left:4px solid #ffc107;">
        <strong>Weights:</strong> Size (Total Assets) = 90% | Financial Group = 10%<br>
        <strong>Financial Group Ratings:</strong> Yes = 5.00 (Very High Risk) | No = 3.00 (Medium Risk)
    </div>';

    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:13px;">';
    echo '<thead style="background:#2c3e50; color:#fff;">';
    echo '<tr>';
    echo '<th rowspan="2">Position</th>';
    echo '<th rowspan="2" style="text-align:left;">Securities Business Person</th>';
    echo '<th colspan="3" style="background:#34495e;">1. Size (90%)</th>';
    echo '<th colspan="3" style="background:#34495e;">2. Financial Group (10%)</th>';
    echo '<th rowspan="2" style="background:#c0392b;">Total<br>Structural<br>Risk</th>';
    echo '<th rowspan="2" style="background:#c0392b;">Risk<br>Category</th>';
    echo '</tr>';
    echo '<tr style="background:#34495e;">';
    echo '<th>Total Assets</th>';
    echo '<th>Rating</th>';
    echo '<th>Weighted<br>(90%)</th>';
    echo '<th>Status</th>';
    echo '<th>Rating</th>';
    echo '<th>Weighted<br>(10%)</th>';
    echo '</tr>';
    echo '</thead><tbody>';

    $pos = 1;
    foreach ($allData as $row) {
        $color = getRiskColor($row['RiskCategory']);
        $financialGroupStatus = ($row['IsFinancialGroup']==1)?'Yes':'No';
        echo '<tr>';
        echo '<td style="text-align:center;">'.$pos.'</td>';
        echo '<td>'.htmlspecialchars($row['MarketParticipantName']).'</td>';
        echo '<td style="text-align:right;">'.number_format($row['TotalAssets'],2).'</td>';
        echo '<td style="text-align:center;">'.number_format($row['SizeRating'],2).'</td>';
        echo '<td style="text-align:center; background:#fff9e6;">'.number_format($row['SizeWeightedRisk'],2).'</td>';
        echo '<td style="text-align:center;">'.$financialGroupStatus.'</td>';
        echo '<td style="text-align:center;">'.number_format($row['FinancialGroupRating'],2).'</td>';
        echo '<td style="text-align:center; background:#fff9e6;">'.number_format($row['FinancialGroupWeightedRisk'],2).'</td>';
        echo '<td style="text-align:center; font-weight:700; font-size:14px; color:'.$color.'; background:#ffe6e6;">'.number_format($row['TotalStructuralRisk'],2).'</td>';
        echo '<td style="text-align:center; font-weight:700; color:'.$color.';">'.htmlspecialchars($row['RiskCategory']).'</td>';
        echo '</tr>';
        $pos++;
    }
    echo '</tbody></table>';

    // Print Button
    echo '<div style="margin-top:10px; text-align:right;">
            <button id="printBtn" onclick="window.print()" style="padding:8px 16px; background:#2c3e50; color:white; border:none; cursor:pointer; border-radius:4px;">Print</button>
          </div>';

    // Risk Scale Reference (visible on page, hidden in print)
    echo '<div id="riskScaleRef" style="margin-top:20px;">
            <h4>Risk Scale Reference</h4>
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:40%;">
                <thead style="background:#34495e; color:#fff;">
                    <tr><th>Scale</th><th>From</th><th>To</th></tr>
                </thead>
                <tbody>
                    <tr><td style="color:#27ae60; font-weight:700;">Very low</td><td>1.00</td><td>1.20</td></tr>
                    <tr><td style="color:#2ecc71; font-weight:700;">Low</td><td>1.21</td><td>1.80</td></tr>
                    <tr><td style="color:#f39c12; font-weight:700;">Medium</td><td>1.81</td><td>3.40</td></tr>
                    <tr><td style="color:#e67e22; font-weight:700;">High</td><td>3.41</td><td>4.20</td></tr>
                    <tr><td style="color:#e74c3c; font-weight:700;">Very high</td><td>4.21</td><td>5.00</td></tr>
                </tbody>
            </table>
          </div>';
}

include 'footer.php';
?>
