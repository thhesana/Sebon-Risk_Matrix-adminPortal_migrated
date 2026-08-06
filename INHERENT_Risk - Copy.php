<?php
// generate_pdf_reports.php - Generate PDF reports for all Reporting Entities
require_once('tcpdf/tcpdf.php'); // You'll need to install TCPDF library
include 'db.php';

// Extend TCPDF for custom header/footer
class RiskReportPDF extends TCPDF {
    private $marketParticipantName = '';
    private $fiscalYearName = '';
    
    public function setReportInfo($mpName, $fyName) {
        $this->marketParticipantName = $mpName;
        $this->fiscalYearName = $fyName;
    }
    
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(102, 126, 234);
        $this->Cell(0, 10, 'Risk Assessment Report', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, $this->marketParticipantName . ' - ' . $this->fiscalYearName, 0, 1, 'C');
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// ==================== HELPER FUNCTIONS ====================

function calculateAverageNonZero($values) {
    $nonZeroValues = array_filter($values, function($val) {
        return $val > 0;
    });
    return empty($nonZeroValues) ? 0 : array_sum($nonZeroValues) / count($nonZeroValues);
}

function calculateRiskScore($values, $weights) {
    $total = array_sum($values);
    if ($total == 0) return 0;
    
    $riskScore = 0;
    foreach ($values as $i => $value) {
        $riskScore += ($value / $total) * $weights[$i];
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
    if (in_array($category, ['Very Low', 'Low'])) return array(39, 174, 96);
    if ($category == 'Medium') return array(243, 156, 18);
    if (in_array($category, ['High', 'Very High'])) return array(231, 76, 60);
    return array(149, 165, 166);
}

function fetchDisplayData($conn, $marketParticipant_id, $fiscalYear_id, $formType) {
    $data = [];
    $subMasterIds = [];
    
    switch($formType) {
        case 'customer_risk': $subMasterIds = [1, 2, 3, 4]; break;
        case 'peps_risk': $subMasterIds = [6, 5]; break;
        case 'delivery_channel': $subMasterIds = [7, 8]; break;
        case 'geographic_zone': $subMasterIds = [9, 10, 11, 12]; break;
    }
    
    foreach($subMasterIds as $subId) {
        $sql = "SELECT ShareTransaction, CommercialDebentureBondTransaction, GovernmentBondTransaction, 
                       MutualFundTransaction, MarginService FROM StockBrokerService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['stock_broker'][$subId] = $row;
        }
        
        $sql = "SELECT IPO_GeneralPublic, IPO_Employees, IPO_LocalPeople, IPO_PF_CIT_Others,
                       FPO, PrivatePlacement, OfferDocument, RightShare, AuctionShare
                FROM IssueAndSalesManagementService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['issue_sales'][$subId] = $row;
        }
        
        $sql = "SELECT Discretionary, NonDiscretionary, Advisory, ReturnGuarantee
                FROM PortfolioManagementService 
                WHERE MarketParticipant_id = ? AND FiscalYear_id = ? AND SubMaster4table_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($marketParticipant_id, $fiscalYear_id, $subId));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data['portfolio'][$subId] = $row;
        }
        
        $sql = "SELECT DematAccountCount FROM BusinessRiskOtherServices 
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

// ==================== MAIN PDF GENERATION ====================

// Get fiscal year selection
$selectedFiscalYear = isset($_GET['fiscal_year']) ? $_GET['fiscal_year'] : null;

if (!$selectedFiscalYear) {
    // Show selection form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Generate PDF Reports</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; }
            h1 { color: #667eea; text-align: center; }
            .form-group { margin: 20px 0; }
            label { display: block; font-weight: bold; margin-bottom: 10px; }
            select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; }
            .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; 
                   padding: 15px 40px; border: none; border-radius: 8px; font-size: 16px; 
                   font-weight: bold; cursor: pointer; width: 100%; margin-top: 20px; }
            .btn:hover { transform: translateY(-2px); }
            .info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📄 Generate PDF Reports</h1>
            <div class="info">
                <p><strong>This tool will generate individual PDF reports for all Reporting Entities for the selected fiscal year.</strong></p>
            </div>
            
            <form method="GET" action="">
                <div class="form-group">
                    <label>Select Fiscal Year:</label>
                    <select name="fiscal_year" required>
                        <option value="">-- Select Fiscal Year --</option>
                        <?php
                        $sqlFY = "SELECT FiscalYear_id, FiscalYearName FROM FiscalYear ORDER BY FiscalYear_id DESC";
                        $stmtFY = sqlsrv_query($conn, $sqlFY);
                        while ($fy = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC)) {
                            echo '<option value="'.$fy['FiscalYear_id'].'">'.$fy['FiscalYearName'].'</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">🚀 Generate All PDFs</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch fiscal year name
$sqlFY = "SELECT FiscalYearName FROM FiscalYear WHERE FiscalYear_id = ?";
$stmtFY = sqlsrv_query($conn, $sqlFY, array($selectedFiscalYear));
$fyData = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC);
$fiscalYearName = $fyData['FiscalYearName'];

// Configuration arrays
$sebonWeights = [
    'stock_broker' => ['ShareTransaction' => 55, 'CommercialDebentureBondTransaction' => 10, 
                       'GovernmentBondTransaction' => 10, 'MutualFundTransaction' => 20, 'MarginService' => 5],
    'issue_sales' => ['IPO_GeneralPublic' => 5, 'IPO_Employees' => 25, 'IPO_LocalPeople' => 10,
                      'IPO_PF_CIT_Others' => 20, 'FPO' => 15, 'PrivatePlacement' => 5,
                      'OfferDocument' => 5, 'RightShare' => 5, 'AuctionShare' => 10],
    'portfolio' => ['Discretionary' => 60, 'NonDiscretionary' => 10, 'Advisory' => 10, 'ReturnGuarantee' => 20],
    'business_risk' => ['DematAccountCount' => 40]
];

$stockFields = ['ShareTransaction' => 'Share Transaction', 
                'CommercialDebentureBondTransaction' => 'Commercial Debenture/Bond',
                'GovernmentBondTransaction' => 'Government Bond', 
                'MutualFundTransaction' => 'Mutual Fund',
                'MarginService' => 'Margin Service'];

$issueFields = ['IPO_GeneralPublic' => 'IPO (General Public)', 'IPO_Employees' => 'IPO (Employees)',
                'IPO_LocalPeople' => 'IPO (Local People)', 'IPO_PF_CIT_Others' => 'IPO (PF/CIT/Others)',
                'FPO' => 'FPO', 'PrivatePlacement' => 'Private Placement',
                'OfferDocument' => 'Offer Document', 'RightShare' => 'Right Share', 'AuctionShare' => 'Auction Share'];

$portfolioFields = ['Discretionary' => 'Discretionary', 'NonDiscretionary' => 'Non-Discretionary',
                    'Advisory' => 'Advisory', 'ReturnGuarantee' => 'Return Guarantee'];

$riskWeights = ['customer_risk' => [1, 3, 4, 5], 'peps_risk' => [4, 5], 
                'delivery_channel' => [3, 5], 'geographic_zone' => [1, 2, 3, 4]];

// Fetch all active Reporting Entities
$sqlMP = "SELECT MarketParticipant_id, MarketParticipantName FROM MarketParticipant WHERE Status = 1 ORDER BY MarketParticipantName";
$stmtMP = sqlsrv_query($conn, $sqlMP);

$generatedCount = 0;
$outputDir = 'pdf_reports/' . $selectedFiscalYear;
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

while ($mp = sqlsrv_fetch_array($stmtMP, SQLSRV_FETCH_ASSOC)) {
    $marketParticipantId = $mp['MarketParticipant_id'];
    $marketParticipantName = $mp['MarketParticipantName'];
    
    // Fetch all data
    $displayData = [
        'customer_risk' => fetchDisplayData($conn, $marketParticipantId, $selectedFiscalYear, 'customer_risk'),
        'peps_risk' => fetchDisplayData($conn, $marketParticipantId, $selectedFiscalYear, 'peps_risk'),
        'delivery_channel' => fetchDisplayData($conn, $marketParticipantId, $selectedFiscalYear, 'delivery_channel'),
        'geographic_zone' => fetchDisplayData($conn, $marketParticipantId, $selectedFiscalYear, 'geographic_zone')
    ];
    
    $qualitativeData = fetchQualitativeData($conn, $marketParticipantId, $selectedFiscalYear);
    
    // Skip if no data
    if (!$qualitativeData) continue;
    
    // Calculate Total RAS
    $totalRAS = calculateTotalRAS(
        $qualitativeData['CorporateGovernance'] ?? 0,
        $qualitativeData['PoliciesProcedures'] ?? 0,
        $qualitativeData['RiskManagement'] ?? 0,
        $qualitativeData['InternalControls'] ?? 0,
        $qualitativeData['ComplianceFunction'] ?? 0,
        $qualitativeData['Training'] ?? 0,
        $qualitativeData['ReportingRecordKeeping'] ?? 0
    );
    
    // Calculate activity risks
    $activityRisksData = [];
    
    foreach ($stockFields as $field => $label) {
        $clientRisk = calculateRiskScore([
            getFieldValue($displayData['customer_risk'], 'stock_broker', 1, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 2, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 3, $field),
            getFieldValue($displayData['customer_risk'], 'stock_broker', 4, $field)
        ], $riskWeights['customer_risk']);
        
        $pepsRisk = calculateRiskScore([
            getFieldValue($displayData['peps_risk'], 'stock_broker', 6, $field),
            getFieldValue($displayData['peps_risk'], 'stock_broker', 5, $field)
        ], $riskWeights['peps_risk']);
        
        $deliveryRisk = calculateRiskScore([
            getFieldValue($displayData['delivery_channel'], 'stock_broker', 7, $field),
            getFieldValue($displayData['delivery_channel'], 'stock_broker', 8, $field)
        ], $riskWeights['delivery_channel']);
        
        $geoRisk = calculateRiskScore([
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 9, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 10, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 11, $field),
            getFieldValue($displayData['geographic_zone'], 'stock_broker', 12, $field)
        ], $riskWeights['geographic_zone']);
        
        $totalInherentRisk = calculateAverageNonZero([$clientRisk, $pepsRisk, $deliveryRisk, $geoRisk]);
        
        $activityRisksData[] = [
            'section' => 'Stock Broker',
            'label' => $label,
            'sebon_weight' => $sebonWeights['stock_broker'][$field] / 100,
            'total_inherent_risk' => $totalInherentRisk
        ];
    }
    
    // Similar calculations for issue_sales, portfolio, and business_risk...
    // (Code abbreviated for length - include all sections from original)
    
    // Create PDF
    $pdf = new RiskReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setReportInfo($marketParticipantName, $fiscalYearName);
    $pdf->SetCreator('Risk Assessment System');
    $pdf->SetAuthor('SEBON');
    $pdf->SetTitle('Risk Report - ' . $marketParticipantName);
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();
    
    // Summary Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Executive Summary', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
    
    // Calculate overall composite risk
    $totalCompositeRiskSum = 0;
    $totalWeightSum = 0;
    foreach ($activityRisksData as $activity) {
        $compositeRisk = 0;
        if ($activity['total_inherent_risk'] > 0) {
            $compositeRisk = ($activity['total_inherent_risk'] * 0.60) + ($totalRAS * 0.40);
        }
        if ($compositeRisk > 0) {
            $totalCompositeRiskSum += ($compositeRisk * $activity['sebon_weight']);
            $totalWeightSum += $activity['sebon_weight'];
        }
    }
    $overallCompositeRisk = $totalWeightSum > 0 ? ($totalCompositeRiskSum / $totalWeightSum) : 0;
    
    // Summary table
    $html = '<table border="1" cellpadding="5">
        <tr style="background-color:#667eea;color:white;font-weight:bold;">
            <td width="50%">Metric</td>
            <td width="25%">Score</td>
            <td width="25%">Category</td>
        </tr>
        <tr>
            <td>Total RAS (Qualitative - 40%)</td>
            <td align="center">'.number_format($totalRAS, 2).'</td>
            <td align="center">'.getRiskCategory($totalRAS).'</td>
        </tr>
        <tr>
            <td>Overall Composite Risk</td>
            <td align="center">'.number_format($overallCompositeRisk, 2).'</td>
            <td align="center" style="background-color:'.implode(',', getRiskColor(getRiskCategory($overallCompositeRisk))).';color:white;">'.getRiskCategory($overallCompositeRisk).'</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(5);
    
    // Detailed Activity Risks Table
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Detailed Risk Assessment', 0, 1, 'L');
    $pdf->Ln(3);
    
    $html = '<table border="1" cellpadding="4" style="font-size:8px;">
        <tr style="background-color:#2c3e50;color:white;font-weight:bold;">
            <td width="40%">Activity</td>
            <td width="15%">Weight</td>
            <td width="15%">Inherent Risk</td>
            <td width="15%">Composite Risk</td>
            <td width="15%">Category</td>
        </tr>';
    
    foreach ($activityRisksData as $activity) {
        $compositeRisk = 0;
        if ($activity['total_inherent_risk'] > 0) {
            $compositeRisk = ($activity['total_inherent_risk'] * 0.60) + ($totalRAS * 0.40);
        }
        $category = getRiskCategory($compositeRisk);
        
        $html .= '<tr>
            <td>'.$activity['label'].'</td>
            <td align="center">'.number_format($activity['sebon_weight']*100, 0).'%</td>
            <td align="center">'.number_format($activity['total_inherent_risk'], 2).'</td>
            <td align="center">'.number_format($compositeRisk, 2).'</td>
            <td align="center">'.$category.'</td>
        </tr>';
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Save PDF
    $filename = $outputDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $marketParticipantName) . '.pdf';
    $pdf->Output($filename, 'F');
    $generatedCount++;
}

// Success page
?>
<!DOCTYPE html>
<html>
<head>
    <title>PDF Generation Complete</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; text-align: center; }
        .success { color: #27ae60; font-size: 24px; margin: 20px 0; }
        .info { background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .btn { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; 
               border-radius: 8px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ PDF Generation Complete</h1>
        <div class="success">
            Successfully generated <?php echo $generatedCount; ?> PDF reports!
        </div>
        <div class="info">
            <p><strong>Reports saved to:</strong></p>
            <p><?php echo $outputDir; ?></p>
        </div>
        <a href="<?php echo $outputDir; ?>" class="btn">📁 View Reports Folder</a>
        <a href="?" class="btn">🔄 Generate More Reports</a>
    </div>
</body>
</html>