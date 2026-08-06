<?php
require 'db.php';
require 'header.php';

// Get search filter
$searchSection = isset($_GET['search_section']) ? trim($_GET['search_section']) : '';

// Build SQL with optional filter
$sql = "
SELECT 
    m.Annex2_F3Master_id,
    m.Annex2_F3Master_Section,
    q.Annex2_F3Questions_id,
    q.Annex2_F3QuestionsList,
    q.Annex2_F3RelatedDirectives
FROM Annex2_F3Master m
LEFT JOIN Annex2_F3Questions q 
    ON m.Annex2_F3Master_id = q.Annex2_F3Master_id
";

// Add WHERE clause if search is active
if (!empty($searchSection)) {
    $sql .= " WHERE m.Annex2_F3Master_Section LIKE ?";
}

$sql .= " ORDER BY m.Annex2_F3Master_id, q.Annex2_F3Questions_id";

// Prepare and execute query
if (!empty($searchSection)) {
    $searchParam = '%' . $searchSection . '%';
    $stmt = sqlsrv_query($conn, $sql, array($searchParam));
} else {
    $stmt = sqlsrv_query($conn, $sql);
}

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Annex 2 - F3 View Table</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .search-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .search-section input {
            border-radius: 5px;
        }
        
        .search-section button {
            border-radius: 5px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            border: none;
        }
        
        .btn-reset:hover {
            color: white;
            background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%);
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h3 class="mb-0">📌 Annex-2 Form 3 — Master + Questions</h3>
        <p class="mb-0 mt-2" style="font-size: 14px;">View and manage all sections and questions</p>
    </div>

    <!-- SEARCH FILTER -->
    <div class="search-section">
        <form method="GET" action="">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label for="search_section" class="form-label fw-bold">🔍 Search by Section Name</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="search_section" 
                        name="search_section" 
                        placeholder="Enter section name to filter..."
                        value="<?php echo htmlspecialchars($searchSection); ?>"
                    >
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-light me-2">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <a href="?" class="btn btn-reset">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($searchSection)): ?>
        <div class="alert alert-info">
            <strong>Filter Active:</strong> Showing results for "<?php echo htmlspecialchars($searchSection); ?>"
            <a href="?" class="float-end text-decoration-none">Clear Filter ✕</a>
        </div>
    <?php endif; ?>

    <!-- TABLE -->
    <div class="table-container">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th width="8%">खण्ड ID</th>
                    <th width="20%">खण्ड</th>
                   
                    <th>जोखिम मूल्याङ्कन प्रणाली (Risk Assessment System)</th>
                    <th width="20%">निर्देशनसँग सम्बन्धित दफा</th>
                    <th width="10%" class="text-center">Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php 
            $rowCount = 0;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                $rowCount++;
            ?>
                <tr>
                    <td class="text-center"><?php echo $row['Annex2_F3Master_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['Annex2_F3Master_Section']); ?></strong></td>
                    
                    <td><?php echo htmlspecialchars($row['Annex2_F3QuestionsList'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['Annex2_F3RelatedDirectives'] ?? '—'); ?></td>
                    <td class="text-center">
                        <?php if (!empty($row['Annex2_F3Questions_id'])): ?>
                            <a href="edit_F3Question.php?id=<?php echo $row['Annex2_F3Questions_id']; ?>" 
                               class="btn-edit">
                                ✏️ Edit
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            
            <?php if ($rowCount == 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <strong>No records found</strong>
                        <?php if (!empty($searchSection)): ?>
                            <br>Try adjusting your search criteria
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>