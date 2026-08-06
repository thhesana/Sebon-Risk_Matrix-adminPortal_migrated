<?php
include 'db.php';

$id = $_GET['id'];

$sql = "DELETE FROM FiscalYear WHERE FiscalYear_id=?";
$params = array($id);

sqlsrv_query($conn, $sql, $params);

header("Location: fy_master.php");
exit;
