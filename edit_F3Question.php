<?php
require 'db.php';
require 'header.php';

// Initialize variables
$errorMessage = '';
$successMessage = '';
$question = null;

// Get question ID from URL
$questionId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($questionId <= 0) {
    header('Location: view_F3Questions.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterId = intval($_POST['master_id']);
    $questionText = trim($_POST['question_text']);
    $relatedDirectives = trim($_POST['related_directives']);
    
    // Validate inputs
    if (empty($masterId) || empty($questionText)) {
        $errorMessage = 'Section and Question are required fields.';
    } else {
        // Update query
        $updateSql = "
            UPDATE Annex2_F3Questions 
            SET 
                Annex2_F3Master_id = ?,
                Annex2_F3QuestionsList = ?,
                Annex2_F3RelatedDirectives = ?
            WHERE Annex2_F3Questions_id = ?
        ";
        
        $updateStmt = sqlsrv_query($conn, $updateSql, array(
            $masterId,
            $questionText,
            $relatedDirectives,
            $questionId
        ));
        
        if ($updateStmt === false) {
            $errorMessage = 'Error updating question: ' . print_r(sqlsrv_errors(), true);
        } else {
            $successMessage = 'Question updated successfully!';
            // Refresh data after update
            sqlsrv_free_stmt($updateStmt);
        }
    }
}

// Fetch current question data
$fetchSql = "
    SELECT 
        q.Annex2_F3Questions_id,
        q.Annex2_F3Master_id,
        q.Annex2_F3QuestionsList,
        q.Annex2_F3RelatedDirectives,
        m.Annex2_F3Master_Section
    FROM Annex2_F3Questions q
    INNER JOIN Annex2_F3Master m ON q.Annex2_F3Master_id = m.Annex2_F3Master_id
    WHERE q.Annex2_F3Questions_id = ?
";

$fetchStmt = sqlsrv_query($conn, $fetchSql, array($questionId));

if ($fetchStmt === false) {
    die('Error fetching question: ' . print_r(sqlsrv_errors(), true));
}

$question = sqlsrv_fetch_array($fetchStmt, SQLSRV_FETCH_ASSOC);

if (!$question) {
    header('Location: view_F3Questions.php');
    exit;
}

sqlsrv_free_stmt($fetchStmt);

// Fetch all master sections for dropdown
$masterSql = "SELECT Annex2_F3Master_id, Annex2_F3Master_Section FROM Annex2_F3Master ORDER BY Annex2_F3Master_id";
$masterStmt = sqlsrv_query($conn, $masterSql);

if ($masterStmt === false) {
    die('Error fetching sections: ' . print_r(sqlsrv_errors(), true));
}

$sections = [];
while ($row = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
    $sections[] = $row;
}
sqlsrv_free_stmt($masterStmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit F3 Question</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #dfe6e9;
            border-radius: 8px;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-update {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            transition: transform 0.2s;
        }
        
        .btn-update:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .btn-back:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .info-card strong {
            color: #1976d2;
        }
        
        textarea.form-control {
            min-height: 120px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h3 class="mb-0">✏️ Edit F3 Question</h3>
        <p class="mb-0 mt-2" style="font-size: 14px;">Update Section Name, Question, and Related Directives</p>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✓ Success!</strong> <?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGE -->
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>✗ Error!</strong> <?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- INFO CARD -->
    <div class="info-card">
        <strong>Question ID:</strong> <?php echo $question['Annex2_F3Questions_id']; ?>
    </div>

    <!-- EDIT FORM -->
    <div class="form-container">
        <form method="POST" action="">
            
            <!-- Section Name -->
            <div class="mb-4">
                <label for="master_id" class="form-label">
                    Section Name <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="master_id" name="master_id" required>
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option 
                            value="<?php echo $section['Annex2_F3Master_id']; ?>"
                            <?php echo ($section['Annex2_F3Master_id'] == $question['Annex2_F3Master_id']) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($section['Annex2_F3Master_Section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Select the section this question belongs to</small>
            </div>

            <!-- Question Text -->
            <div class="mb-4">
                <label for="question_text" class="form-label">
                    Question <span class="text-danger">*</span>
                </label>
                <textarea 
                    class="form-control" 
                    id="question_text" 
                    name="question_text" 
                    required
                    placeholder="Enter the question text..."
                ><?php echo htmlspecialchars($question['Annex2_F3QuestionsList']); ?></textarea>
                <small class="text-muted">Enter the complete question text</small>
            </div>

            <!-- Related Directives -->
            <div class="mb-4">
                <label for="related_directives" class="form-label">
                    Related Directives
                </label>
                <textarea 
                    class="form-control" 
                    id="related_directives" 
                    name="related_directives"
                    placeholder="Enter related directives (optional)..."
                ><?php echo htmlspecialchars($question['Annex2_F3RelatedDirectives'] ?? ''); ?></textarea>
                <small class="text-muted">Enter any related directives or regulations (optional)</small>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="questionaireMastertbl.php" class="btn-back">
                    ← Back to List
                </a>
                <button type="submit" class="btn-update">
                    💾 Update Question
                </button>
            </div>

        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
sqlsrv_close($conn);
?>