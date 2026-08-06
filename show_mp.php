<?php
// show_mp.php

// Include database connection
include 'db.php';
include 'header.php';

// SQL query with join and status mapping
$sql = "
SELECT TOP (1000)
    mp.MarketParticipant_id,
    mp.MarketParticipantName,
    mp.MarketParticipantShortName,
    mp.MarketParticipantEmail,
    mpt.MarketParticipantTypeMaster_name AS MarketParticipantType,
    CASE 
        WHEN mp.Status = 1 THEN 'ACTIVE'
        ELSE 'INACTIVE'
    END AS Status
FROM 
    [dbo].[MarketParticipant] mp
LEFT JOIN 
    [dbo].[MarketParticipantTypeMaster] mpt
    ON mp.MarketParticipantType = mpt.MarketParticipantTypeMasterId
ORDER BY mp.MarketParticipant_id
";

$query = sqlsrv_query($conn, $sql);
if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reporting Entities</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #004080; /* dark blue */
            color: white;
        }
    </style>
</head>
<body>
    <h2>Reporting Entities</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Short Name</th>
            <th>Email</th>
            <th>Type</th>
            <th>Status</th>
        </tr>
        <?php
        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['MarketParticipant_id'] . "</td>";
            echo "<td>" . $row['MarketParticipantName'] . "</td>";
            echo "<td>" . $row['MarketParticipantShortName'] . "</td>";
            echo "<td>" . $row['MarketParticipantEmail'] . "</td>";
            echo "<td>" . $row['MarketParticipantType'] . "</td>";
            echo "<td>" . $row['Status'] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>

<?php
// Close connection
sqlsrv_close($conn);
?>
