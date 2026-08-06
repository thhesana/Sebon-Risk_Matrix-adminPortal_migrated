<?php
include 'db.php';

$id = $_GET['id'];

// Fetch existing data
$sql = "SELECT FiscalYearName FROM FiscalYear WHERE FiscalYear_id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fy = $_POST['FiscalYearName'];

    $update_sql = "UPDATE FiscalYear SET FiscalYearName=? WHERE FiscalYear_id=?";
    $params = array($fy, $id);

    if (sqlsrv_query($conn, $update_sql, $params)) {
        header("Location: fy_master.php");
        exit;
    }
}
?>
<form method="POST">
    Fiscal Year Name:
    <input type="text" name="FiscalYearName" value="<?php echo $row['FiscalYearName']; ?>" required>
    <button type="submit">Update</button>
</form>
