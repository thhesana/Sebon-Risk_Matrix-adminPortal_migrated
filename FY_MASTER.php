<?php
// fy_master.php
require_once __DIR__ . '/session_config.php';
include 'db.php';  // your SQL Server connection

include 'header.php';

// Fetch all fiscal years
$sql = "SELECT FiscalYear_id, FiscalYearName FROM FiscalYear ORDER BY FiscalYear_id DESC";
$stmt = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fiscal Year Master</title>
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
            max-width: 1000px;
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
           TOP ACTION BAR
           ============================================ */
        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .btn {
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .add-btn {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 12px 25px;
            font-weight: 700;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 6px rgba(39, 174, 96, 0.3);
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-right: 8px;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }

        .delete-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
        }

        /* ============================================
           TABLES
           ============================================ */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
        }

        table thead tr {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }

        table thead th {
            font-weight: 600;
            font-size: 14px;
        }

        table tbody tr {
            transition: all 0.3s;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        table tbody tr:hover {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        table tbody td:first-child {
            font-weight: bold;
            color: #2c3e50;
            font-size: 15px;
        }

        table tbody td:nth-child(2) {
            font-weight: 600;
            color: #34495e;
            font-size: 15px;
        }

        /* ============================================
           EMPTY STATE
           ============================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin: 30px 0;
        }

        .empty-state h3 {
            color: #7f8c8d;
            font-size: 22px;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #95a5a6;
            font-size: 16px;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .page-container {
                padding: 10px;
                margin: 10px;
            }

            .page-container > h2 {
                font-size: 18px;
                padding: 15px;
                margin: -10px -10px 20px -10px;
            }

            .action-bar {
                justify-content: center;
            }

            .add-btn {
                width: 100%;
                text-align: center;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 10px 8px;
            }

            .btn {
                padding: 6px 12px;
                font-size: 11px;
                display: block;
                margin: 5px auto;
            }

            .btn-edit {
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<div class="page-container">
    <h2>📅 Fiscal Year Master</h2>

    <div class="action-bar">
        <a href="fy_master_add.php" class="btn add-btn">➕ Add New Fiscal Year</a>
    </div>

    <?php if (sqlsrv_has_rows($stmt)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 100px;">S.N.</th>
                    <th>Fiscal Year</th>
                    <th style="width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sn = 1;
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td><?php echo htmlspecialchars($row['FiscalYearName']); ?></td>
                        <td>
                            <a class="btn btn-edit" 
                               href="fy_master_edit.php?id=<?php echo $row['FiscalYear_id']; ?>">
                                ✏️ Edit
                            </a>
                            <a class="btn delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this fiscal year?')" 
                               href="fy_master_delete.php?id=<?php echo $row['FiscalYear_id']; ?>">
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <h3>📅 No Fiscal Years Found</h3>
            <p>Click the "Add New Fiscal Year" button above to create your first fiscal year.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>