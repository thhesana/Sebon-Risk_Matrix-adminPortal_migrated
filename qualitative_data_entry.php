<?php
// Include database connection
include 'db.php';
include 'header.php';

// Handle form submission
$successMessage = '';
$errorMessage = '';

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
    
    // Check if record already exists
    $checkSql = "SELECT COUNT(*) as count FROM RiskControlsAndMitigants 
                 WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($marketParticipant, $fiscalYear));
    $checkResult = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    
    if ($checkResult['count'] > 0) {
        // Update existing record
        $updateSql = "UPDATE RiskControlsAndMitigants 
                      SET CorporateGovernance = ?, 
                          PoliciesProcedures = ?, 
                          RiskManagement = ?, 
                          InternalControls = ?, 
                          ComplianceFunction = ?, 
                          Training = ?, 
                          ReportingRecordKeeping = ?
                      WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
        $params = array($corpGov, $policies, $riskMgmt, $internalControls, $compliance, 
                       $training, $reporting, $marketParticipant, $fiscalYear);
        $updateStmt = sqlsrv_query($conn, $updateSql, $params);
        
        if ($updateStmt) {
            $successMessage = "✅ Qualitative data updated successfully!";
        } else {
            $errorMessage = "❌ Error updating data: " . print_r(sqlsrv_errors(), true);
        }
    } else {
        // Insert new record
        $insertSql = "INSERT INTO RiskControlsAndMitigants 
                      (MarketParticipant_id, FiscalYear_id, CorporateGovernance, PoliciesProcedures, 
                       RiskManagement, InternalControls, ComplianceFunction, Training, ReportingRecordKeeping)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = array($marketParticipant, $fiscalYear, $corpGov, $policies, $riskMgmt, 
                       $internalControls, $compliance, $training, $reporting);
        $insertStmt = sqlsrv_query($conn, $insertSql, $params);
        
        if ($insertStmt) {
            $successMessage = "✅ Qualitative data saved successfully!";
        } else {
            $errorMessage = "❌ Error saving data: " . print_r(sqlsrv_errors(), true);
        }
    }
}

// Fetch Reporting Entities
$sqlMarketParticipants = "SELECT [MarketParticipant_id], [MarketParticipantName], [MarketParticipantShortName]
                          FROM [RiskMatrix_AML].[dbo].[MarketParticipant]
                          WHERE [Status] = 1
                          ORDER BY [MarketParticipantName]";
$stmtMarketParticipants = sqlsrv_query($conn, $sqlMarketParticipants);

$marketParticipants = [];
if ($stmtMarketParticipants) {
    while ($row = sqlsrv_fetch_array($stmtMarketParticipants, SQLSRV_FETCH_ASSOC)) {
        $marketParticipants[] = $row;
    }
}

// Fetch Fiscal Years
$sqlFiscalYears = "SELECT [FiscalYear_id], [FiscalYearName]
                   FROM [RiskMatrix_AML].[dbo].[FiscalYear]
                   ORDER BY [FiscalYear_id] DESC";
$stmtFiscalYears = sqlsrv_query($conn, $sqlFiscalYears);

$fiscalYears = [];
if ($stmtFiscalYears) {
    while ($row = sqlsrv_fetch_array($stmtFiscalYears, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
    }
}

// Handle AJAX request to load existing data
$existingData = null;
if (isset($_GET['action']) && $_GET['action'] === 'load_data' && isset($_GET['mp_id']) && isset($_GET['fy_id'])) {
    $mpId = $_GET['mp_id'];
    $fyId = $_GET['fy_id'];
    
    $loadSql = "SELECT * FROM RiskControlsAndMitigants 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ?";
    $loadStmt = sqlsrv_query($conn, $loadSql, array($mpId, $fyId));
    
    if ($loadStmt && $row = sqlsrv_fetch_array($loadStmt, SQLSRV_FETCH_ASSOC)) {
        header('Content-Type: application/json');
        echo json_encode($row);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'not_found']);
        exit;
    }
}

// Calculate Total RAS
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
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
    }
    
    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        background-color: white;
    }
    
    .page-header {
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        margin-bottom: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .page-header h1 {
        font-size: 28px;
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .page-header p {
        font-size: 16px;
        opacity: 0.95;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-success {
        background-color: #d4edda;
        border: 2px solid #28a745;
        color: #155724;
    }
    
    .alert-error {
        background-color: #f8d7da;
        border: 2px solid #dc3545;
        color: #721c24;
    }
    
    .form-section {
        background-color: #f8f9fa;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .form-section h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        font-size: 20px;
        border-bottom: 3px solid #667eea;
        padding-bottom: 10px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 20px;
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
    
    .form-group .weight-badge {
        display: inline-block;
        background-color: #ffd93d;
        color: #2c3e50;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        margin-left: 8px;
    }
    
    .form-group select,
    .form-group input {
        padding: 12px 15px;
        border: 2px solid #dfe6e9;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
        background-color: white;
    }
    
    .form-group select:focus,
    .form-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-group input[type="number"] {
        font-weight: 600;
        font-size: 16px;
    }
    
    .info-box {
        background-color: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 25px;
    }
    
    .info-box p {
        color: #1565c0;
        font-size: 14px;
        margin: 5px 0;
    }
    
    .risk-factors-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .risk-factor-card {
        background-color: white;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s;
    }
    
    .risk-factor-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    
    .risk-factor-card h3 {
        color: #2c3e50;
        font-size: 16px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .total-ras-display {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        text-align: center;
        margin: 30px 0;
        box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
    }
    
    .total-ras-display h3 {
        font-size: 18px;
        margin-bottom: 15px;
        opacity: 0.9;
    }
    
    .total-ras-display .value {
        font-size: 48px;
        font-weight: 700;
        margin: 10px 0;
    }
    
    .total-ras-display .category {
        font-size: 20px;
        font-weight: 600;
        background-color: rgba(255,255,255,0.2);
        padding: 10px 20px;
        border-radius: 6px;
        display: inline-block;
        margin-top: 10px;
    }
    
    .btn-container {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }
    
    .btn {
        padding: 15px 40px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }
    
    .scale-reference {
        background-color: #fff9e6;
        padding: 20px;
        border-radius: 10px;
        margin-top: 30px;
        border: 2px solid #ffd93d;
    }
    
    .scale-reference h3 {
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 18px;
    }
    
    .scale-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    
    .scale-table th,
    .scale-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: center;
    }
    
    .scale-table th {
        background-color: #34495e;
        color: white;
        font-weight: 600;
    }
    
    .scale-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .risk-factors-grid {
            grid-template-columns: 1fr;
        }
        
        .btn-container {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <div class="page-header">
        <h1>📝 Qualitative Data Entry Form</h1>
        <p>Risk Assessment System (Risk Controls and Mitigants) - 40% Weight</p>
    </div>
    
    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <span style="font-size: 24px;">✅</span>
            <span><?php echo $successMessage; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <span style="font-size: 24px;">❌</span>
            <span><?php echo $errorMessage; ?></span>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="qualitativeForm">
        <!-- Selection Section -->
        <div class="form-section">
            <h2>🎯 Select Reporting Entity & Fiscal Year</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="market_participant">Reporting Entity *</label>
                    <select name="market_participant" id="market_participant" required onchange="loadExistingData()">
                        <option value="">-- Select Reporting Entity --</option>
                        <?php foreach ($marketParticipants as $mp): ?>
                            <option value="<?php echo $mp['MarketParticipant_id']; ?>">
                                <?php echo htmlspecialchars($mp['MarketParticipantName']); ?>
                                <?php if (!empty($mp['MarketParticipantShortName'])): ?>
                                    (<?php echo htmlspecialchars($mp['MarketParticipantShortName']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fiscal_year">Fiscal Year *</label>
                    <select name="fiscal_year" id="fiscal_year" required onchange="loadExistingData()">
                        <option value="">-- Select Fiscal Year --</option>
                        <?php foreach ($fiscalYears as $fy): ?>
                            <option value="<?php echo $fy['FiscalYear_id']; ?>">
                                <?php echo htmlspecialchars($fy['FiscalYearName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="info-box">
                <p><strong>ℹ️ Instructions:</strong></p>
                <p>• Enter risk scores from 1.00 (Very Good) to 5.00 (Very Deficient)</p>
                <p>• Each factor has a predefined weight that will be applied automatically</p>
                <p>• If data already exists for the selected combination, it will be loaded for editing</p>
            </div>
        </div>
        
        <!-- Risk Factors Section -->
        <div class="form-section">
            <h2>📊 Risk Assessment Factors</h2>
            
            <div class="risk-factors-grid">
                <!-- Factor 1: Corporate Governance -->
                <div class="risk-factor-card">
                    <h3>
                        <span>1. Corporate Governance</span>
                        <span class="weight-badge">30%</span>
                    </h3>
                    <div class="form-group">
                        <label for="corporate_governance">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="corporate_governance" id="corporate_governance" 
                               required placeholder="e.g., 3.00" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 2: Policies and Procedures -->
                <div class="risk-factor-card">
                    <h3>
                        <span>2. Policies and Procedures</span>
                        <span class="weight-badge">10%</span>
                    </h3>
                    <div class="form-group">
                        <label for="policies_procedures">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="policies_procedures" id="policies_procedures" 
                               required placeholder="e.g., 3.75" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 3: Risk Management -->
                <div class="risk-factor-card">
                    <h3>
                        <span>3. Risk Management</span>
                        <span class="weight-badge">20%</span>
                    </h3>
                    <div class="form-group">
                        <label for="risk_management">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="risk_management" id="risk_management" 
                               required placeholder="e.g., 3.90" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 4: Internal Controls -->
                <div class="risk-factor-card">
                    <h3>
                        <span>4. Internal Controls</span>
                        <span class="weight-badge">15%</span>
                    </h3>
                    <div class="form-group">
                        <label for="internal_controls">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="internal_controls" id="internal_controls" 
                               required placeholder="e.g., 4.00" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 5: Compliance Function -->
                <div class="risk-factor-card">
                    <h3>
                        <span>5. Compliance Function</span>
                        <span class="weight-badge">15%</span>
                    </h3>
                    <div class="form-group">
                        <label for="compliance_function">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="compliance_function" id="compliance_function" 
                               required placeholder="e.g., 3.25" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 6: Training -->
                <div class="risk-factor-card">
                    <h3>
                        <span>6. Training</span>
                        <span class="weight-badge">5%</span>
                    </h3>
                    <div class="form-group">
                        <label for="training">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="training" id="training" 
                               required placeholder="e.g., 3.65" oninput="calculateTotal()">
                    </div>
                </div>
                
                <!-- Factor 7: Reporting and Record Keeping -->
                <div class="risk-factor-card">
                    <h3>
                        <span>7. Reporting and Record Keeping</span>
                        <span class="weight-badge">5%</span>
                    </h3>
                    <div class="form-group">
                        <label for="reporting_record_keeping">Risk Score (1.00 - 5.00) *</label>
                        <input type="number" step="0.01" min="1.00" max="5.00" 
                               name="reporting_record_keeping" id="reporting_record_keeping" 
                               required placeholder="e.g., 4.25" oninput="calculateTotal()">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total RAS Display -->
        <div class="total-ras-display" id="totalRasDisplay" style="display: none;">
            <h3>TOTAL RAS (Risk Controls and Mitigants)</h3>
            <div class="value" id="totalRasValue">0.00</div>
            <div class="category" id="totalRasCategory">N/A</div>
            <p style="margin-top: 15px; font-size: 14px; opacity: 0.9;">
                Calculated using weighted average of all factors
            </p>
        </div>
        
        <!-- Submit Buttons -->
        <div class="btn-container">
            <button type="submit" name="submit_qualitative" class="btn btn-primary">
                💾 Save Qualitative Data
            </button>
            <a href="admin_view_data.php" class="btn btn-secondary">
                👁️ View All Data
            </a>
        </div>
    </form>
    
    <!-- Risk Scale Reference -->
    <div class="scale-reference">
        <h3>📋 Risk Management Quality Scale Reference</h3>
        <table class="scale-table">
            <thead>
                <tr>
                    <th>Risk Scale</th>
                    <th>Risk Management Quality</th>
                    <th>Ranking From</th>
                    <th>Ranking To</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background-color: #d5f4e6;">
                    <td><strong>1</strong></td>
                    <td><strong>Very Good</strong></td>
                    <td>1.00</td>
                    <td>1.50</td>
                </tr>
                <tr>
                    <td><strong>2</strong></td>
                    <td><strong>Good</strong></td>
                    <td>1.51</td>
                    <td>2.50</td>
                </tr>
                <tr style="background-color: #fff3cd;">
                    <td><strong>3</strong></td>
                    <td><strong>Acceptable</strong></td>
                    <td>2.51</td>
                    <td>3.50</td>
                </tr>
                <tr>
                    <td><strong>4</strong></td>
                    <td><strong>Deficient</strong></td>
                    <td>3.51</td>
                    <td>4.00</td>
                </tr>
                <tr style="background-color: #fadbd8;">
                    <td><strong>5</strong></td>
                    <td><strong>Very Deficient</strong></td>
                    <td>4.01</td>
                    <td>5.00</td>
                </tr>
            </tbody>
        </table>
    </div>
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
    
    // Get category
    let category = 'N/A';
    let color = '#95a5a6';
    
    if (totalRas >= 1.00 && totalRas <= 1.50) {
        category = 'Very Good';
        color = '#27ae60';
    } else if (totalRas >= 1.51 && totalRas <= 2.50) {
        category = 'Good';
        color = '#2ecc71';
    } else if (totalRas >= 2.51 && totalRas <= 3.50) {
        category = 'Acceptable';
        color = '#f39c12';
    } else if (totalRas >= 3.51 && totalRas <= 4.00) {
        category = 'Deficient';
        color = '#e67e22';
    } else if (totalRas >= 4.01 && totalRas <= 5.00) {
        category = 'Very Deficient';
        color = '#e74c3c';
    }
    
    // Display results
    document.getElementById('totalRasValue').textContent = totalRas.toFixed(2);
    document.getElementById('totalRasCategory').textContent = category;
    document.getElementById('totalRasDisplay').style.background = 
        `linear-gradient(135deg, ${color} 0%, ${adjustColor(color, -20)} 100%)`;
    document.getElementById('totalRasDisplay').style.display = 'block';
}

function adjustColor(color, amount) {
    return '#' + color.replace(/^#/, '').replace(/../g, color => ('0'+Math.min(255, Math.max(0, parseInt(color, 16) + amount)).toString(16)).substr(-2));
}

function loadExistingData() {
    const mpId = document.getElementById('market_participant').value;
    const fyId = document.getElementById('fiscal_year').value;
    
    if (!mpId || !fyId) {
        return;
    }
    
    // Fetch existing data via AJAX
    fetch(`?action=load_data&mp_id=${mpId}&fy_id=${fyId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'not_found') {
                // Populate form fields with existing data
                document.getElementById('corporate_governance').value = data.CorporateGovernance || '';
                document.getElementById('policies_procedures').value = data.PoliciesProcedures || '';
                document.getElementById('risk_management').value = data.RiskManagement || '';
                document.getElementById('internal_controls').value = data.InternalControls || '';
                document.getElementById('compliance_function').value = data.ComplianceFunction || '';
                document.getElementById('training').value = data.Training || '';
                document.getElementById('reporting_record_keeping').value = data.ReportingRecordKeeping || '';
                
                // Calculate and display total
                calculateTotal();
                
                // Show info message
                const infoBox = document.querySelector('.info-box');
                if (infoBox) {
                    infoBox.style.backgroundColor = '#fff3cd';
                    infoBox.style.borderColor = '#ffc107';
                    infoBox.innerHTML = `
                        <p><strong>⚠️ Existing Data Found:</strong></p>
                        <p>• The form has been populated with previously saved data</p>
                        <p>• You can modify the values and click "Save" to update</p>
                    `;
                }
            } else {
                // Clear form fields
                document.getElementById('corporate_governance').value = '';
                document.getElementById('policies_procedures').value = '';
                document.getElementById('risk_management').value = '';
                document.getElementById('internal_controls').value = '';
                document.getElementById('compliance_function').value = '';
                document.getElementById('training').value = '';
                document.getElementById('reporting_record_keeping').value = '';
                
                // Hide total display
                document.getElementById('totalRasDisplay').style.display = 'none';
                
                // Reset info box
                const infoBox = document.querySelector('.info-box');
                if (infoBox) {
                    infoBox.style.backgroundColor = '#e3f2fd';
                    infoBox.style.borderColor = '#2196f3';
                    infoBox.innerHTML = `
                        <p><strong>ℹ️ Instructions:</strong></p>
                        <p>• Enter risk scores from 1.00 (Very Good) to 5.00 (Very Deficient)</p>
                        <p>• Each factor has a predefined weight that will be applied automatically</p>
                        <p>• If data already exists for the selected combination, it will be loaded for editing</p>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error loading data:', error);
        });
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
</script>

<?php include 'footer.php'; ?>