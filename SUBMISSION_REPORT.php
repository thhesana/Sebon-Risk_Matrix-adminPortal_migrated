<?php
include 'db.php';
include 'header.php';
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
   PAGE CONTAINER
   ============================================ */
.page-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.page-container > h2 {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    margin: -20px -20px 30px -20px;
    border-radius: 8px 8px 0 0;
    font-size: 24px;
    font-weight: 600;
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

button[type="submit"] {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 25px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
}

button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
}

/* ============================================
   FORMS
   ============================================ */
form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

form label {
    font-weight: 700;
    color: #34495e;
    font-size: 14px;
}

form select,
form input[type="text"] {
    padding: 10px 15px;
    border: 2px solid #dfe6e9;
    border-radius: 6px;
    font-size: 14px;
    background-color: white;
    transition: all 0.3s;
}

form select {
    cursor: pointer;
    min-width: 200px;
}

form input[type="text"] {
    min-width: 250px;
}

form select:focus,
form input[type="text"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* ============================================
   TABLES
   ============================================ */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

table th,
table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: center;
}

table thead tr {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
}

table thead th {
    font-weight: 600;
    font-size: 13px;
}

table tbody tr {
    transition: all 0.3s;
}

table tbody tr:hover {
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

table tbody td:first-child {
    font-weight: bold;
    font-size: 14px;
}

table tbody td:nth-child(2) {
    text-align: left;
    padding-left: 15px;
}

/* Status styling */
table span[style*="color:green"] {
    color: #27ae60 !important;
    font-weight: 700 !important;
}

table span[style*="color:red"] {
    color: #e74c3c !important;
    font-weight: 700 !important;
}

table span[style*="color:orange"] {
    color: #f39c12 !important;
    font-weight: 700 !important;
}

/* Row coloring for status */
table tbody tr[style*="background-color: #d4edda"] {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
}

table tbody tr[style*="background-color: #fff3cd"] {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe7a0 100%) !important;
}

table tbody tr[style*="background-color: #f8d7da"] {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
}

/* ============================================
   SUMMARY STATISTICS
   ============================================ */
.summary-container {
    margin-top: 30px;
    padding: 25px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.summary-container h3 {
    margin-bottom: 20px;
    color: #2c3e50;
    font-size: 20px;
    text-align: center;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 2px solid #ddd;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.stat-card.complete {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-color: #28a745;
}

.stat-card.partial {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe7a0 100%);
    border-color: #ffc107;
}

.stat-card.pending {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-color: #dc3545;
}

.stat-card .stat-label {
    font-size: 12px;
    margin-bottom: 8px;
    font-weight: 600;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: 700;
    margin: 10px 0;
}

.stat-card.total .stat-label {
    color: #666;
}

.stat-card.total .stat-value {
    color: #2c3e50;
}

.stat-card.complete .stat-label {
    color: #155724;
}

.stat-card.complete .stat-value {
    color: #155724;
}

.stat-card.partial .stat-label {
    color: #856404;
}

.stat-card.partial .stat-value {
    color: #856404;
}

.stat-card.pending .stat-label {
    color: #721c24;
}

.stat-card.pending .stat-value {
    color: #721c24;
}

/* ============================================
   ALERTS & MESSAGES
   ============================================ */
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

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .page-container {
        padding: 10px;
    }

    .page-container > h2 {
        font-size: 18px;
        padding: 15px;
        margin: -10px -10px 20px -10px;
    }

    form {
        padding: 15px;
        flex-direction: column;
        align-items: stretch;
    }

    form select,
    form input[type="text"] {
        min-width: 100%;
        width: 100%;
    }

    table {
        font-size: 11px;
    }

    table th,
    table td {
        padding: 8px 5px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .stat-card .stat-value {
        font-size: 28px;
    }
}
</style>

<div class="page-container">
    <h2>📋 Reporting Entity Submission Status (By Fiscal Year)</h2>

<?php
// Load Fiscal Years
$sqlFY = "SELECT FiscalYear_id, FiscalYearName FROM FiscalYear ORDER BY FiscalYearName DESC";
$stmtFY = sqlsrv_query($conn, $sqlFY);

// Get filters
$selectedFY = isset($_GET['fy']) ? $_GET['fy'] : '';
$searchName = isset($_GET['search']) ? trim($_GET['search']) : '';
?>

<form method="GET">
    <label>Select Fiscal Year:</label>
    <select name="fy" required>
        <option value="">-- Select Fiscal Year --</option>
        <?php while ($fy = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC)) {
            $sel = ($selectedFY == $fy['FiscalYear_id']) ? "selected" : "";
            echo "<option value='{$fy['FiscalYear_id']}' $sel>{$fy['FiscalYearName']}</option>";
        } ?>
    </select>
    
    <label>Search:</label>
    <input type="text" name="search" placeholder="Search Reporting Entity Name..."
           value="<?php echo htmlspecialchars($searchName); ?>">
    
    <button type="submit">🔍 Search</button>
</form>

<?php
if ($selectedFY != "") {
    // Fetch ALL Reporting Entities (Always show all)
    $sqlMP = "
        SELECT MarketParticipant_id, MarketParticipantName
        FROM MarketParticipant
        WHERE Status = 1
          AND MarketParticipantName LIKE ?
        ORDER BY MarketParticipantName ASC
    ";
    $params = array("%$searchName%");
    $stmtMP = sqlsrv_query($conn, $sqlMP, $params);
    
    function status_badge($isSubmitted) {
        return $isSubmitted
            ? "<span style='color:green;font-weight:bold;'>YES</span>"
            : "<span style='color:red;font-weight:bold;'>NO</span>";
    }

    // Annex-1 Part A/B/C/D from sbn_ANNEX1.php data groups
    function annex1_part_submitted($conn, $mp_id, $fy_id, $partCode) {
        $subMasterMap = [
            'A' => [1, 2, 3, 4],      // Customer Risk
            'B' => [6, 5],            // PEPs Risk
            'C' => [7, 8],            // Delivery Channel
            'D' => [9, 10, 11, 12]    // Geographic Zone
        ];

        if (!isset($subMasterMap[$partCode])) {
            return false;
        }

        $subIds = $subMasterMap[$partCode];
        $tables = [
            "StockBrokerService",
            "IssueAndSalesManagementService",
            "PortfolioManagementService",
            "BusinessRiskOtherServices"
        ];

        foreach ($tables as $table) {
            foreach ($subIds as $subId) {
                $sql = "SELECT 1 FROM $table WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($mp_id, $fy_id, $subId));
                if ($stmt && sqlsrv_fetch_array($stmt)) {
                    return true;
                }
            }
        }

        return false;
    }

    // Annex-1 Part E from sbn_ANNEX1.php (Total Assets tab)
    function annex1_total_assets_submitted($conn, $mp_id, $fy_id) {
        $sql = "SELECT 1 FROM InformationRegardingTotalAssets WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($mp_id, $fy_id));
        return ($stmt && sqlsrv_fetch_array($stmt)) ? true : false;
    }

    // Annex-2 submitted status from sbn_ANNEX2.php questionnaire answers
    function annex2_submitted($conn, $mp_id, $fy_id) {
        $sql = "SELECT 1 FROM Annex2_F3Ques_Ans_collection WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($mp_id, $fy_id));
        return ($stmt && sqlsrv_fetch_array($stmt)) ? true : false;
    }

    // SEBON risk grading status (after Annex-2 review in sbn_ANNEX2.php)
    function sebon_risk_graded($conn, $mp_id, $fy_id) {
        $sql = "SELECT 1 FROM RiskControlsAndMitigants
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ?
                AND (
                    CorporateGovernance IS NOT NULL OR
                    PoliciesProcedures IS NOT NULL OR
                    RiskManagement IS NOT NULL OR
                    InternalControls IS NOT NULL OR
                    ComplianceFunction IS NOT NULL OR
                    Training IS NOT NULL OR
                    ReportingRecordKeeping IS NOT NULL
                )";
        $stmt = sqlsrv_query($conn, $sql, array($mp_id, $fy_id));
        if ($stmt && sqlsrv_fetch_array($stmt)) {
            return true;
        }

        $sqlAnnex2 = "SELECT 1 FROM Annex2_F3Ques_Ans_collection
                      WHERE MarketParticipant_id = ? AND FiscalYear_id = ?
                      AND Annex2_F3Ques_Ans_collection_RatingBySebon IS NOT NULL";
        $stmtAnnex2 = sqlsrv_query($conn, $sqlAnnex2, array($mp_id, $fy_id));
        return ($stmtAnnex2 && sqlsrv_fetch_array($stmtAnnex2)) ? true : false;
    }
    
    // Output Table
    echo "<table>
            <thead>
                <tr>
                    <th style='width: 80px;'>S.N.</th>
                    <th>Reporting Entity Name</th>
                    <th style='width: 120px;'>Annex-1<br><small>A. Customer Risk</small></th>
                    <th style='width: 110px;'>Annex-1<br><small>B. PEPs Risk</small></th>
                    <th style='width: 130px;'>Annex-1<br><small>C. Delivery Channel</small></th>
                    <th style='width: 130px;'>Annex-1<br><small>D. Geographic Zone</small></th>
                    <th style='width: 120px;'>Annex-1<br><small>E. Total Assets</small></th>
                    <th style='width: 120px;'>Annex-2<br><small>Questionnaire</small></th>
                    <th style='width: 130px;'>SEBON Risk<br><small>Graded</small></th>
                    <th style='width: 150px;'>Overall Status</th>
                </tr>
            </thead>
            <tbody>";
    
    $sn = 1;
    while ($mp = sqlsrv_fetch_array($stmtMP, SQLSRV_FETCH_ASSOC)) {
        $mp_id = $mp["MarketParticipant_id"];
        $partA = annex1_part_submitted($conn, $mp_id, $selectedFY, 'A');
        $partB = annex1_part_submitted($conn, $mp_id, $selectedFY, 'B');
        $partC = annex1_part_submitted($conn, $mp_id, $selectedFY, 'C');
        $partD = annex1_part_submitted($conn, $mp_id, $selectedFY, 'D');
        $partE = annex1_total_assets_submitted($conn, $mp_id, $selectedFY);
        $annex2 = annex2_submitted($conn, $mp_id, $selectedFY);
        $sebonGraded = sebon_risk_graded($conn, $mp_id, $selectedFY);
        
        // Determine overall status
        $submittedParts = 0;
        $submittedParts += $partA ? 1 : 0;
        $submittedParts += $partB ? 1 : 0;
        $submittedParts += $partC ? 1 : 0;
        $submittedParts += $partD ? 1 : 0;
        $submittedParts += $partE ? 1 : 0;
        $submittedParts += $annex2 ? 1 : 0;
        
        if ($submittedParts === 6) {
            $overallStatus = "<span style='color:green;font-weight:bold;'>✅ COMPLETE</span>";
            $rowStyle = "background-color: #d4edda;";
        } elseif ($submittedParts > 0) {
            $overallStatus = "<span style='color:orange;font-weight:bold;'>⚠️ PARTIAL</span>";
            $rowStyle = "background-color: #fff3cd;";
        } else {
            $overallStatus = "<span style='color:red;font-weight:bold;'>❌ PENDING</span>";
            $rowStyle = "background-color: #f8d7da;";
        }
        
        echo "<tr style='$rowStyle'>
                <td>$sn</td>
                <td><strong>{$mp['MarketParticipantName']}</strong></td>
                <td>" . status_badge($partA) . "</td>
                <td>" . status_badge($partB) . "</td>
                <td>" . status_badge($partC) . "</td>
                <td>" . status_badge($partD) . "</td>
                <td>" . status_badge($partE) . "</td>
                <td>" . status_badge($annex2) . "</td>
                <td>" . status_badge($sebonGraded) . "</td>
                <td>$overallStatus</td>
              </tr>";
        $sn++;
    }
    echo "</tbody></table>";
    
    // Summary Statistics
    $stmtMP = sqlsrv_query($conn, $sqlMP, $params);
    $totalCount = 0;
    $completeCount = 0;
    $partialCount = 0;
    $pendingCount = 0;
    
    while ($mp = sqlsrv_fetch_array($stmtMP, SQLSRV_FETCH_ASSOC)) {
        $totalCount++;
        $mp_id = $mp["MarketParticipant_id"];

        $submittedParts = 0;
        $submittedParts += annex1_part_submitted($conn, $mp_id, $selectedFY, 'A') ? 1 : 0;
        $submittedParts += annex1_part_submitted($conn, $mp_id, $selectedFY, 'B') ? 1 : 0;
        $submittedParts += annex1_part_submitted($conn, $mp_id, $selectedFY, 'C') ? 1 : 0;
        $submittedParts += annex1_part_submitted($conn, $mp_id, $selectedFY, 'D') ? 1 : 0;
        $submittedParts += annex1_total_assets_submitted($conn, $mp_id, $selectedFY) ? 1 : 0;
        $submittedParts += annex2_submitted($conn, $mp_id, $selectedFY) ? 1 : 0;

        if ($submittedParts === 6) $completeCount++;
        elseif ($submittedParts > 0) $partialCount++;
        else $pendingCount++;
    }
    

} else {
    echo "<div class='no-data-message'>
            <h3>🔍 Please Select a Fiscal Year</h3>
            <p>Choose a fiscal year from the dropdown above to view submission status for all Reporting Entities.</p>
          </div>";
}
?>

</div>

<?php
include 'footer.php';
?>