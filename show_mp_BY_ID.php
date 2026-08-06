<?php
// show_all_mp.php

include 'db.php';
include 'header.php';

// SQL query: join Users_Detail_mp with MarketParticipant and type
$sql = "
SELECT
    u.user_id,
    u.username,
    u.active,
    mp.MarketParticipant_id,
    mp.MarketParticipantName,
    mp.MarketParticipantShortName,
    mp.MarketParticipantEmail,
    mp.IsFinancialGroup,
    mpt.MarketParticipantTypeMaster_name AS MarketParticipantType,
    CASE WHEN mp.Status = 1 THEN 'ACTIVE' ELSE 'INACTIVE' END AS Status
FROM [dbo].[Users_Detail_mp] u
INNER JOIN [dbo].[MarketParticipant] mp
    ON u.MarketParticipant_id = mp.MarketParticipant_id
LEFT JOIN [dbo].[MarketParticipantTypeMaster] mpt
    ON mp.MarketParticipantType = mpt.MarketParticipantTypeMasterId
ORDER BY mp.MarketParticipant_id
";

// Execute query
$query = sqlsrv_query($conn, $sql);

if ($query === false) {
    die("SQL Server query error: " . print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>All Reporting Entities</title>
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
            max-width: 1400px;
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
           TOP BAR
           ============================================ */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .search-container {
            flex: 1;
            text-align: center;
        }

        #searchBox {
            width: 100%;
            max-width: 400px;
            padding: 12px 20px;
            border: 2px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: white;
        }

        #searchBox:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        #searchBox::placeholder {
            color: #95a5a6;
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

        .add-button {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            font-weight: 700;
            border-radius: 8px;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 6px rgba(39, 174, 96, 0.3);
            display: inline-block;
            white-space: nowrap;
        }

        .add-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
        }

        .edit-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
            display: inline-block;
        }

        .edit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }

        /* ============================================
           TABLES
           ============================================ */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        table thead tr {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }

        table thead th {
            font-weight: 600;
            font-size: 13px;
            text-align: center;
        }

        table thead th:nth-child(2),
        table thead th:nth-child(3) {
            text-align: left;
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
            text-align: center;
            color: #2c3e50;
        }

        table tbody td:nth-child(9) {
            text-align: center;
        }

        /* Status badges */
        table tbody td:nth-child(8) {
            text-align: center;
            font-weight: 700;
        }

        /* ============================================
           STATUS BADGES
           ============================================ */
        .status-active {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .status-inactive {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
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

            .top-bar {
                flex-direction: column;
                padding: 15px;
            }

            .search-container {
                width: 100%;
            }

            #searchBox {
                max-width: 100%;
            }

            .add-button {
                width: 100%;
                text-align: center;
            }

            table {
                font-size: 11px;
            }

            table th,
            table td {
                padding: 8px 5px;
            }

            .edit-button {
                padding: 6px 12px;
                font-size: 11px;
            }
        }

        /* ============================================
           TABLE RESPONSIVE WRAPPER
           ============================================ */
        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        @media (max-width: 1200px) {
            .table-wrapper {
                overflow-x: scroll;
            }

            table {
                min-width: 1000px;
            }
        }
    </style>
    <script>
        function searchTable() {
            const input = document.getElementById("searchBox").value.toLowerCase();
            const table = document.getElementById("mpTable");
            const trs = table.getElementsByTagName("tr");

            for (let i = 1; i < trs.length; i++) { // skip header row
                const tds = trs[i].getElementsByTagName("td");
                let found = false;
                for (let j = 1; j < tds.length; j++) { // skip SN column
                    if (tds[j].innerText.toLowerCase().includes(input)) {
                        found = true;
                        break;
                    }
                }
                trs[i].style.display = found ? "" : "none";
            }
        }
    </script>
</head>
<body>
<h2>All Reporting Entities</h2>

<div class="top-bar">
    <!-- Centered search box -->
    <div style="flex:1; text-align:center;">
        <input type="text" id="searchBox" onkeyup="searchTable()" placeholder="Search Reporting Entity">
    </div>

    <!-- Right aligned add button -->
    <div>
        <a href="addmarketparticipant.php" class="add-button">+ Add New Reporting Entity</a>
    </div>
</div>

<table id="mpTable">
    <tr>
        <th>SN</th>
        <th>Username</th>
        <th>Name</th>
        <th>Short Name</th>
        <th>Email</th>
        <th>Type</th>
        <th>Financial Group</th>
        <th>Status</th>
        <th>Edit</th>
    </tr>
    <?php
    $sn = 1;
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
        $isFinancial = $row['IsFinancialGroup'] ? 'Yes' : 'No';
    ?>
        <tr>
            <td><?php echo $sn++; ?></td>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['MarketParticipantName']); ?></td>
            <td><?php echo htmlspecialchars($row['MarketParticipantShortName']); ?></td>
            <td><?php echo htmlspecialchars($row['MarketParticipantEmail']); ?></td>
            <td><?php echo htmlspecialchars($row['MarketParticipantType']); ?></td>
            <td><?php echo $isFinancial; ?></td>
            <td><?php echo htmlspecialchars($row['Status']); ?></td>

            <!-- ⭐ EDIT BUTTON -->
            <td>
                <a href="editmarketparticipant.php?id=<?php echo $row['MarketParticipant_id']; ?>"
                   style="background:#004080; color:white; padding:6px 12px; text-decoration:none; border-radius:4px;">
                    Edit
                </a>
            </td>

        </tr>
    <?php endwhile; ?>
</table>


</body>
</html>

<?php
sqlsrv_close($conn);
?>
