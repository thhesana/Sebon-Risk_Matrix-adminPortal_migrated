<?php
// Include database connection
include 'db.php';

require_once __DIR__ . '/session_config.php';


include 'header.php';

// Check if user is logged in and is admin
$isAdmin = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $sqlCheckAdmin = "SELECT [role] FROM [RiskMatrix_AML].[dbo].[Users_detail] WHERE [user_id] = ? AND [active] = 'Y'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheckAdmin, array($userId));
    if ($stmtCheck && $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        if ($row['role'] === 'admin') {
            $isAdmin = true;
            $_SESSION['role'] = 'admin';
        }
    }
}

if (!$isAdmin) {
    die("Access denied. Admin privileges required.");
}

// Get all fiscal years for dropdown
$sqlFY = "SELECT [FiscalYear_id], [FiscalYearName]
          FROM [RiskMatrix_AML].[dbo].[FiscalYear]
          ORDER BY [FiscalYear_id] DESC";
$stmtFY = sqlsrv_query($conn, $sqlFY);

$fiscalYears = [];
if($stmtFY) {
    while($row = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC)) {
        $fiscalYears[] = $row;
    }
}

// Get all Reporting Entities for dropdown
$sqlMP = "SELECT [MarketParticipant_id], [MarketParticipantName], [MarketParticipantType]
          FROM [RiskMatrix_AML].[dbo].[MarketParticipant]
          ORDER BY [MarketParticipantName]";
$stmtMP = sqlsrv_query($conn, $sqlMP);

$marketParticipants = [];
if($stmtMP) {
    while($row = sqlsrv_fetch_array($stmtMP, SQLSRV_FETCH_ASSOC)) {
        $marketParticipants[] = $row;
    }
}

// Get Risk Grading Parameters for the Risk Rating dropdown
$sqlRiskGrading = "SELECT [RiskGradingType], [RiskGradingScale]
                    FROM [RiskMatrix_AML].[dbo].[RiskGradingParameter]
                    ORDER BY [RiskGradingScale]";
$stmtRiskGrading = sqlsrv_query($conn, $sqlRiskGrading);

$riskGradingOptions = [];
if($stmtRiskGrading) {
    while($row = sqlsrv_fetch_array($stmtRiskGrading, SQLSRV_FETCH_ASSOC)) {
        $riskGradingOptions[] = $row;
    }
}

// Initialize variables
$selectedFY = '';
$selectedMP = '';
$fiscalYearName = '';
$marketParticipantName = '';
$marketParticipantType = '';
$questionnaire = [];
$questionnaireAnswers = [];
$showForm = false;

// Handle selection
if(isset($_GET['fiscal_year']) && isset($_GET['market_participant'])) {
    $selectedFY = $_GET['fiscal_year'];
    $selectedMP = $_GET['market_participant'];
    $showForm = true;
    
    // Get fiscal year name
    foreach($fiscalYears as $fy) {
        if($fy['FiscalYear_id'] == $selectedFY) {
            $fiscalYearName = $fy['FiscalYearName'];
            break;
        }
    }
    
    // Get Reporting Entity details
    foreach($marketParticipants as $mp) {
        if($mp['MarketParticipant_id'] == $selectedMP) {
            $marketParticipantName = $mp['MarketParticipantName'];
            $marketParticipantType = $mp['MarketParticipantType'];
            break;
        }
    }
    
    // Fetch all sections with their questions
    $sqlSections = "SELECT DISTINCT 
                        m.[Annex2_F3Master_id],
                        m.[Annex2_F3Master_Section]
                    FROM [RiskMatrix_AML].[dbo].[Annex2_F3Master] m
                    ORDER BY m.[Annex2_F3Master_id]";
    
    $stmtSections = sqlsrv_query($conn, $sqlSections);
    
    if($stmtSections) {
        while($sectionRow = sqlsrv_fetch_array($stmtSections, SQLSRV_FETCH_ASSOC)) {
            $sectionId = $sectionRow['Annex2_F3Master_id'];
            $sectionName = $sectionRow['Annex2_F3Master_Section'];
            
            // Fetch questions for this section
            $sqlQuestions = "SELECT 
                                [Annex2_F3Questions_id],
                                [Annex2_F3QuestionsList],
                                [Annex2_F3RelatedDirectives]
                            FROM [RiskMatrix_AML].[dbo].[Annex2_F3Questions]
                            WHERE [Annex2_F3Master_id] = ?
                            ORDER BY [Annex2_F3Questions_id]";
            
            $stmtQuestions = sqlsrv_query($conn, $sqlQuestions, array($sectionId));
            
            $questions = [];
            if($stmtQuestions) {
                while($questionRow = sqlsrv_fetch_array($stmtQuestions, SQLSRV_FETCH_ASSOC)) {
                    $questions[] = $questionRow;
                }
            }
            
            $questionnaire[] = [
                'section_id' => $sectionId,
                'section_name' => $sectionName,
                'questions' => $questions
            ];
        }
    }
    
    // Fetch Reporting Entity's answers
    $sqlAnswers = "SELECT 
                        q.[Annex2_F3Questions_id],
                        q.[Annex2_F3QuestionsList],
                        a.[Annex2_F3Ques_Ans_collection_RemarksByMP],
                        a.[Annex2_F3Ques_Ans_collection_status],
                        a.[Annex2_F3Ques_Ans_collection_RemarksBySebon],
                        a.[Annex2_F3Ques_Ans_collection_RatingBySebon],
                        m.[Annex2_F3Master_Section]
                   FROM [RiskMatrix_AML].[dbo].[Annex2_F3Ques_Ans_collection] a
                   INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Questions] q 
                        ON a.[Annex2_F3Questions_id] = q.[Annex2_F3Questions_id]
                   INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Master] m 
                        ON q.[Annex2_F3Master_id] = m.[Annex2_F3Master_id]
                   WHERE a.[MarketParticipant_id] = ? AND a.[FiscalYear_id] = ?
                   ORDER BY m.[Annex2_F3Master_id], q.[Annex2_F3Questions_id]";
    
    $stmtAnswers = sqlsrv_query($conn, $sqlAnswers, array($selectedMP, $selectedFY));
    
    if($stmtAnswers) {
        while($row = sqlsrv_fetch_array($stmtAnswers, SQLSRV_FETCH_ASSOC)) {
            $questionnaireAnswers[$row['Annex2_F3Questions_id']] = [
                'mp_remarks' => $row['Annex2_F3Ques_Ans_collection_RemarksByMP'],
                'status' => $row['Annex2_F3Ques_Ans_collection_status'] ? 'Yes' : 'No',
                'sebon_remarks' => $row['Annex2_F3Ques_Ans_collection_RemarksBySebon'],
                'risk_rating' => $row['Annex2_F3Ques_Ans_collection_RatingBySebon']
            ];
        }
    }
}

// Handle admin form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_admin_data'])) {
    $success = true;
    $errorMessages = [];
    
    $fiscalYearId = $_POST['fiscal_year_id'];
    $marketParticipantId = $_POST['market_participant_id'];
    $userId = $_SESSION['user_id'] ?? null;
    
    try {
        // Build a lookup of valid RiskGradingType => RiskGradingScale pairs for server-side validation
        // (mirrors riskGradingMap in JS, since the rating field is derived and shouldn't be trusted as-is)
        $validGradingMap = [];
        foreach($riskGradingOptions as $gradeOpt) {
            $validGradingMap[trim($gradeOpt['RiskGradingType'])] = trim((string)$gradeOpt['RiskGradingScale']);
        }

        // Validate all fields before processing
        $validationErrors = [];
        foreach($_POST as $key => $value) {
            if(strpos($key, 'sebon_remarks_') === 0) {
                $questionId = str_replace('sebon_remarks_', '', $key);
                $sebonRemarks = trim($value);
                $ratingKey = 'risk_rating_' . $questionId;
                $riskRating = isset($_POST[$ratingKey]) ? trim($_POST[$ratingKey]) : '';
                
                // Validate SEBON Remarks (must be a valid Risk Grading Type from the table)
                if(empty($sebonRemarks)) {
                    $validationErrors[] = "SEBON Remarks (Risk Grading) is required for Question ID: $questionId";
                } elseif(!array_key_exists($sebonRemarks, $validGradingMap)) {
                    $validationErrors[] = "Invalid Risk Grading selected for Question ID: $questionId";
                }
                
                // Validate Risk Rating (must exactly match the scale tied to the selected grading type)
                if(empty($riskRating) && $riskRating !== '0') {
                    $validationErrors[] = "Risk Rating is required for Question ID: $questionId";
                } elseif(array_key_exists($sebonRemarks, $validGradingMap) 
                         && (string)$validGradingMap[$sebonRemarks] !== (string)$riskRating) {
                    $validationErrors[] = "Risk Rating does not match the selected Risk Grading for Question ID: $questionId";
                }
            }
        }
        
        if(!empty($validationErrors)) {
            echo "<script>alert('Validation Errors:\\n" . addslashes(implode("\\n", $validationErrors)) . "');</script>";
            $success = false;
        } else {
            // Update each question's SEBON remarks and rating
            foreach($_POST as $key => $value) {
                if(strpos($key, 'sebon_remarks_') === 0) {
                    $questionId = str_replace('sebon_remarks_', '', $key);
                    $sebonRemarks = trim($value);
                    
                    // Get corresponding risk rating
                    $ratingKey = 'risk_rating_' . $questionId;
                    $riskRating = isset($_POST[$ratingKey]) ? trim($_POST[$ratingKey]) : null;
                    
                    // Update query
                    $sql = "UPDATE [RiskMatrix_AML].[dbo].[Annex2_F3Ques_Ans_collection]
                            SET [Annex2_F3Ques_Ans_collection_RemarksBySebon] = ?,
                                [Annex2_F3Ques_Ans_collection_RatingBySebon] = ?,
                                [reviewedBySebon_user_id] = ?
                            WHERE [MarketParticipant_id] = ? 
                            AND [FiscalYear_id] = ? 
                            AND [Annex2_F3Questions_id] = ?";
                    
                    $params = array($sebonRemarks, $riskRating, $userId, $marketParticipantId, $fiscalYearId, $questionId);
                    
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if($stmt === false) {
                        $success = false;
                        $errors = sqlsrv_errors();
                        $errorMessages[] = "Question ID $questionId: " . print_r($errors, true);
                    }
                }
            }
            
            if($success) {
                // Calculate averages by Annex2_F3Master_id and insert into RiskControlsAndMitigants
                $sqlAvgCalc = "SELECT 
                                    m.[Annex2_F3Master_id],
                                    m.[Annex2_F3Master_Section],
                                    AVG(CAST(a.[Annex2_F3Ques_Ans_collection_RatingBySebon] AS FLOAT)) as AvgRating
                               FROM [RiskMatrix_AML].[dbo].[Annex2_F3Ques_Ans_collection] a
                               INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Questions] q 
                                    ON a.[Annex2_F3Questions_id] = q.[Annex2_F3Questions_id]
                               INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Master] m 
                                    ON q.[Annex2_F3Master_id] = m.[Annex2_F3Master_id]
                               WHERE a.[MarketParticipant_id] = ? 
                               AND a.[FiscalYear_id] = ?
                               AND a.[Annex2_F3Ques_Ans_collection_RatingBySebon] IS NOT NULL
                               GROUP BY m.[Annex2_F3Master_id], m.[Annex2_F3Master_Section]";
                
                $stmtAvg = sqlsrv_query($conn, $sqlAvgCalc, array($marketParticipantId, $fiscalYearId));
                
                $averages = [];
                if($stmtAvg) {
                    while($avgRow = sqlsrv_fetch_array($stmtAvg, SQLSRV_FETCH_ASSOC)) {
                        $averages[$avgRow['Annex2_F3Master_id']] = round($avgRow['AvgRating'], 2);
                    }
                }
                
                // Map Annex2_F3Master_id to RiskControlsAndMitigants columns
                // You need to define this mapping based on your section names
                $sectionMapping = [
                    1 => 'CorporateGovernance',
                    2 => 'PoliciesProcedures',
                    3 => 'RiskManagement',
                    4 => 'InternalControls',
                    5 => 'ComplianceFunction',
                    6 => 'Training',
                    7 => 'ReportingRecordKeeping'
                ];
                
                // Check if record exists
                $sqlCheckExists = "SELECT [ID] FROM [RiskMatrix_AML].[dbo].[RiskControlsAndMitigants] 
                                   WHERE [MarketParticipant_id] = ? AND [FiscalYear_id] = ?";
                $stmtCheck = sqlsrv_query($conn, $sqlCheckExists, array($marketParticipantId, $fiscalYearId));
                $recordExists = false;
                
                if($stmtCheck && sqlsrv_fetch($stmtCheck)) {
                    $recordExists = true;
                }
                
                if($recordExists) {
                    // Update existing record
                    $updateParts = [];
                    $updateParams = [];
                    
                    foreach($averages as $masterId => $avgValue) {
                        if(isset($sectionMapping[$masterId])) {
                            $columnName = $sectionMapping[$masterId];
                            $updateParts[] = "[$columnName] = ?";
                            $updateParams[] = $avgValue;
                        }
                    }
                    
                    if(!empty($updateParts)) {
                        $updateParams[] = $marketParticipantId;
                        $updateParams[] = $fiscalYearId;
                        
                        $sqlUpdate = "UPDATE [RiskMatrix_AML].[dbo].[RiskControlsAndMitigants] 
                                      SET " . implode(", ", $updateParts) . ", [ModifiedDate] = GETDATE()
                                      WHERE [MarketParticipant_id] = ? AND [FiscalYear_id] = ?";
                        
                        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $updateParams);
                        
                        if($stmtUpdate === false) {
                            $errors = sqlsrv_errors();
                            $errorMessages[] = "Error updating RiskControlsAndMitigants: " . print_r($errors, true);
                        }
                    }
                } else {
                    // Insert new record
                    $columns = ['MarketParticipant_id', 'FiscalYear_id'];
                    $values = [$marketParticipantId, $fiscalYearId];
                    $placeholders = ['?', '?'];
                    
                    foreach($averages as $masterId => $avgValue) {
                        if(isset($sectionMapping[$masterId])) {
                            $columnName = $sectionMapping[$masterId];
                            $columns[] = $columnName;
                            $values[] = $avgValue;
                            $placeholders[] = '?';
                        }
                    }
                    
                    $columns[] = 'CreatedDate';
                    $placeholders[] = 'GETDATE()';
                    
                    $sqlInsert = "INSERT INTO [RiskMatrix_AML].[dbo].[RiskControlsAndMitigants] 
                                  (" . implode(", ", array_map(function($col) { return "[$col]"; }, $columns)) . ") 
                                  VALUES (" . implode(", ", $placeholders) . ")";
                    
                    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $values);
                    
                    if($stmtInsert === false) {
                        $errors = sqlsrv_errors();
                        $errorMessages[] = "Error inserting into RiskControlsAndMitigants: " . print_r($errors, true);
                    }
                }
                
                if(empty($errorMessages)) {
                    echo "<script>alert('Admin data and averages submitted successfully!'); window.location.href = '?fiscal_year=$fiscalYearId&market_participant=$marketParticipantId';</script>";
                } else {
                    echo "<script>alert('Data submitted but errors occurred in calculating averages!');</script>";
                    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
                    echo "<strong>Errors:</strong><br>";
                    foreach($errorMessages as $msg){
                        echo htmlspecialchars($msg)."<br>";
                    }
                    echo "</div>";
                }
            } else {
                echo "<script>alert('Error submitting admin data!');</script>";
                if(!empty($errorMessages)) {
                    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
                    echo "<strong>Errors:</strong><br>";
                    foreach($errorMessages as $msg){
                        echo htmlspecialchars($msg)."<br>";
                    }
                    echo "</div>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<script>alert('Exception: " . addslashes($e->getMessage()) . "');</script>";
    }
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

// Map of RiskGradingType -> RiskGradingScale, built from RiskGradingParameter table
var riskGradingMap = {
<?php foreach($riskGradingOptions as $go): ?>
    "<?php echo addslashes(trim($go['RiskGradingType'])); ?>": "<?php echo addslashes(trim((string)$go['RiskGradingScale'])); ?>",
<?php endforeach; ?>
};

// Toggle the SEBON Remarks cell between "read-only display" and "editable dropdown"
function toggleRemarksEdit(questionId) {
    var displayEl = document.getElementById('remarks-display-' + questionId);
    var selectEl = document.getElementById('remarks-select-' + questionId);
    if (displayEl) {
        displayEl.style.display = 'none';
    }
    if (selectEl) {
        selectEl.style.display = 'block';
        selectEl.focus();
    }
}

// When a Risk Grading Type is chosen in the SEBON Remarks dropdown,
// auto-fetch its RiskGradingScale into the Risk Rating field for that same row.
function updateRatingFromRemarks(questionId) {
    var selectEl = document.getElementById('remarks-select-' + questionId);
    var ratingEl = document.getElementById('rating-input-' + questionId);
    if (!selectEl || !ratingEl) {
        return;
    }
    var selectedType = selectEl.value;
    ratingEl.value = riskGradingMap.hasOwnProperty(selectedType) ? riskGradingMap[selectedType] : '';
}

function confirmSubmit() {
    return confirm('Are you sure you want to save admin data and calculate averages?');
}

// Keep Risk Rating in sync with any pre-selected Risk Grading (including hidden selects)
$(document).ready(function() {
    document.querySelectorAll('.remarks-select').forEach(function(selectEl) {
        if (!selectEl.value) {
            return;
        }
        var questionId = selectEl.id.replace('remarks-select-', '');
        updateRatingFromRemarks(questionId);
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
    margin-top: 10px;
}

/* Info Badge */
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
.filter-section,
.selection-panel {
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
.form-group input[type="date"] {
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

.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
}

.form-group input:invalid,
.form-group textarea:invalid {
    border-color: #e74c3c;
}

/* Submit Section */
.submit-section {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
}

/* ============================================
   TABLE STYLES
   ============================================ */
.data-table,
.questionnaire-table {
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
.questionnaire-table td {
    border: 1px solid #dfe6e9;
    padding: 12px;
    text-align: center;
}

.data-table thead th,
.questionnaire-table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    font-size: 12px;
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
    width: 25%;
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

.risk-rating-cell input {
    background-color: #f4f6f7;
    color: #2c3e50;
    font-weight: 700;
    cursor: not-allowed;
}

.risk-rating-cell input:focus {
    outline: none;
    border-color: #e91e63;
    box-shadow: 0 0 5px rgba(233, 30, 99, 0.3);
}

/* SEBON Remarks: read-only display state (already saved by SEBON) */
.remarks-display {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 6px;
}

.current-remarks-text {
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
    text-align: left;
    flex: 1;
}

.btn-edit-remarks {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    padding: 2px 4px;
    line-height: 1;
    flex-shrink: 0;
}

.btn-edit-remarks:hover {
    opacity: 0.7;
}

/* SEBON Remarks: editable dropdown state (Risk Grading Type list) */
.remarks-select {
    width: 100%;
    padding: 8px;
    border: 2px solid #bdc3c7;
    border-radius: 4px;
    font-size: 12px;
    background-color: white;
    cursor: pointer;
}

.remarks-select:focus {
    outline: none;
    border-color: #f39c12;
    box-shadow: 0 0 5px rgba(243, 156, 18, 0.3);
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
   LEGEND & HELPER SECTIONS
   ============================================ */
.legend {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.legend h3 {
    color: #2c3e50;
    margin-bottom: 10px;
    font-size: 14px;
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

/* ============================================
   RESPONSIVE DESIGN
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

    .data-table,
    .questionnaire-table {
        font-size: 11px;
    }

    .data-table th,
    .data-table td,
    .questionnaire-table th,
    .questionnaire-table td {
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
}
</style>

<div class="container">
    <div class="page-header">
        <h1>Admin: Risk Assessment Questionnaire Review & Data Entry</h1>
        <p style="font-size: 14px; margin-top: 10px;">
            Review Reporting Entity responses and add SEBON remarks & risk rating
        </p>
    </div>
    
    <!-- Selection Panel -->
    <div class="selection-panel">
        <h2>📋 Select Reporting Entity & Fiscal Year</h2>
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="fiscal_year">Fiscal Year <span style="color: red;">*</span></label>
                    <select name="fiscal_year" id="fiscal_year" required>
                        <option value="">-- Select Fiscal Year --</option>
                        <?php foreach($fiscalYears as $fy): ?>
                            <option value="<?php echo $fy['FiscalYear_id']; ?>" 
                                <?php echo ($selectedFY == $fy['FiscalYear_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fy['FiscalYearName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="market_participant">Reporting Entity <span style="color: red;">*</span></label>
                    <select name="market_participant" id="market_participant" required>
                        <option value="">-- Select Reporting Entity --</option>
                        <?php foreach($marketParticipants as $mp): ?>
                            <option value="<?php echo $mp['MarketParticipant_id']; ?>"
                                <?php echo ($selectedMP == $mp['MarketParticipant_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mp['MarketParticipantName']); ?> 
                                (<?php echo htmlspecialchars($mp['MarketParticipantType']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-load">Load Data</button>
            </div>
        </form>
    </div>
    
    <?php if($showForm): ?>
        
        <?php if(empty($questionnaireAnswers)): ?>
            <div class="no-data-message">
                ⚠️ No questionnaire data found for <strong><?php echo htmlspecialchars($marketParticipantName); ?></strong> 
                in fiscal year <strong><?php echo htmlspecialchars($fiscalYearName); ?></strong>.
                <br><br>
                The Reporting Entity has not submitted their questionnaire yet.
            </div>
        <?php else: ?>
            
            <div class="page-header" style="background-color: #27ae60;">
                <h1>Questionnaire Data - F.Y. <?php echo htmlspecialchars($fiscalYearName); ?></h1>
                <p style="font-size: 14px; margin-top: 10px;">
                    <strong>Reporting Entity:</strong> <?php echo htmlspecialchars($marketParticipantName); ?> 
                    (Type: <?php echo htmlspecialchars($marketParticipantType); ?>)
                </p>
                <div class="info-badge">✓ Reporting Entity has submitted responses</div>
            </div>
            
            <div class="required-note">
                <strong>⚠️ Important:</strong> Both SEBON Remarks and Risk <div class="legend">
            <h3>Column Guide:</h3>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #e8f5e9;"></span>
                Reporting Entity Remarks (Read-only)
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #fff3e0;"></span>
                SEBON Remarks — Risk Grading dropdown (Required, click ✏️ to change a saved value)
            </span>
            <span class="legend-item">
                <span class="legend-color" style="background-color: #fce4ec;"></span>
                Risk Rating — auto-filled from the selected Risk Grading (read-only)
            </span>
        </div>
        
        <form method="POST" action="" id="adminForm">
            <input type="hidden" name="fiscal_year_id" value="<?php echo $selectedFY; ?>">
            <input type="hidden" name="market_participant_id" value="<?php echo $selectedMP; ?>">
            
            <table class="questionnaire-table">
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Question</th>
                        <th>Related Directive</th>
                        <th>Status<br>(छ/छैन)</th>
                        <th>Reporting Entity Remarks</th>
                        <th>SEBON Remarks <span style="color: #e74c3c;">*</span></th>
                        <th>Risk Rating <span style="color: #e74c3c;">*</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $globalSN = 1;
                    foreach($questionnaire as $section): 
                    ?>
                        <!-- Section Header -->
                        <tr>
                            <td colspan="7" class="section-header">
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </td>
                        </tr>
                        
                        <!-- Questions in this section -->
                        <?php foreach($section['questions'] as $question): 
                            $questionId = $question['Annex2_F3Questions_id'];
                            
                            // Skip if no answer from MP
                            if(!isset($questionnaireAnswers[$questionId])) {
                                continue;
                            }
                            
                            $answer = $questionnaireAnswers[$questionId];
                            $statusClass = ($answer['status'] == 'Yes') ? 'status-yes' : 'status-no';

                            // SEBON Remarks now holds the selected Risk Grading Type (text, as-is)
                            $currentRemarks = $answer['sebon_remarks'];
                            $currentRemarksTrimmed = trim((string)$currentRemarks);
                            $hasRemarks = ($currentRemarks !== null && $currentRemarksTrimmed !== '');

                            // Only hide the dropdown when the saved value is a valid Risk Grading option.
                            // Otherwise a hidden empty <select required> blocks submit with no visible error.
                            $remarksInOptions = false;
                            $matchedScale = '';
                            foreach($riskGradingOptions as $gradeOpt) {
                                $gradeTypeTrimmed = trim($gradeOpt['RiskGradingType']);
                                if($currentRemarksTrimmed === $gradeTypeTrimmed) {
                                    $remarksInOptions = true;
                                    $matchedScale = trim((string)$gradeOpt['RiskGradingScale']);
                                    break;
                                }
                            }
                            $hideRemarksSelect = ($hasRemarks && $remarksInOptions);

                            // Risk Rating holds the RiskGradingScale value tied to that type (as-is)
                            // Prefer the scale from the grading map so client/server stay consistent on update
                            $currentRating = $remarksInOptions ? $matchedScale : $answer['risk_rating'];
                        ?>
                        <tr>
                            <td class="sn-cell"><?php echo $globalSN++; ?></td>
                            <td class="question-cell">
                                <?php echo htmlspecialchars($question['Annex2_F3QuestionsList']); ?>
                            </td>
                            <td class="directive-cell">
                                <?php echo htmlspecialchars($question['Annex2_F3RelatedDirectives']); ?>
                            </td>
                            <td class="status-cell <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($answer['status']); ?>
                            </td>
                            <td class="mp-remarks-cell">
                                <?php echo nl2br(htmlspecialchars($answer['mp_remarks'])); ?>
                            </td>
                            <td class="sebon-remarks-cell">
                                <!-- Read-only view: shown when a valid Risk Grading Type is already saved -->
                                <div class="remarks-display" 
                                     id="remarks-display-<?php echo $questionId; ?>"
                                     style="<?php echo $hideRemarksSelect ? '' : 'display:none;'; ?>">
                                    <span class="current-remarks-text">
                                        <?php echo nl2br(htmlspecialchars($currentRemarks)); ?>
                                    </span>
                                    <button type="button" class="btn-edit-remarks"
                                            onclick="toggleRemarksEdit('<?php echo $questionId; ?>')"
                                            title="Edit">
                                        ✏️
                                    </button>
                                </div>

                                <!-- Editable dropdown: shown when empty/invalid, or after clicking edit -->
                                <select 
                                    name="sebon_remarks_<?php echo $questionId; ?>" 
                                    id="remarks-select-<?php echo $questionId; ?>"
                                    class="remarks-select"
                                    style="<?php echo $hideRemarksSelect ? 'display:none;' : ''; ?>"
                                    onchange="updateRatingFromRemarks('<?php echo $questionId; ?>')"
                                    <?php echo $hideRemarksSelect ? '' : 'required'; ?>
                                >
                                    <option value="">-- Select Risk Grading --</option>
                                    <?php foreach($riskGradingOptions as $gradeOpt): ?>
                                        <?php $gradeTypeTrimmed = trim($gradeOpt['RiskGradingType']); ?>
                                        <option value="<?php echo htmlspecialchars($gradeTypeTrimmed); ?>"
                                            <?php echo ($currentRemarksTrimmed === $gradeTypeTrimmed) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($gradeTypeTrimmed); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="risk-rating-cell">
                                <!-- Auto-filled from the RiskGradingScale matching the selected Risk Grading Type -->
                                <input 
                                    type="text" 
                                    name="risk_rating_<?php echo $questionId; ?>" 
                                    id="rating-input-<?php echo $questionId; ?>"
                                    value="<?php echo htmlspecialchars((string)$currentRating); ?>"
                                    placeholder="Auto-filled"
                                    readonly
                                    required
                                >
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="submit-section">
                <button type="submit" name="submit_admin_data" class="btn-submit" onclick="return confirmSubmit()">
                    💾 Save Admin Data & Calculate Averages
                </button>
            </div>
        </form>
        
    <?php endif; ?>
    
<?php endif; ?>