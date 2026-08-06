<?php
include 'db.php';
include 'header.php';

if (!isset($_GET['id'])) {
    die("Invalid request. ID missing.");
}

$id = intval($_GET['id']);

// Fetch types for dropdown
$typeQuery = "SELECT MarketParticipantTypeMasterId, MarketParticipantTypeMaster_name 
              FROM MarketParticipantTypeMaster 
              ORDER BY MarketParticipantTypeMaster_name";
$typeResult = sqlsrv_query($conn, $typeQuery);
$types = [];
while ($row = sqlsrv_fetch_array($typeResult, SQLSRV_FETCH_ASSOC)) {
    $types[] = $row;
}

// Fetch existing Reporting Entity detail
$sql = "SELECT * FROM MarketParticipant WHERE MarketParticipant_id = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if ($stmt === false) {
    die("Fetch error: " . print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("Reporting Entity not found.");
}

$message = "";

// Update form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['MarketParticipantName'];
    $shortName = $_POST['MarketParticipantShortName'];
    $email = $_POST['MarketParticipantEmail'];
    $type = $_POST['MarketParticipantType'];
    $status = $_POST['Status'];
    $isFinancialGroup = isset($_POST['IsFinancialGroup']) ? 1 : 0;

    // Call update SP
    $updateSql = "{CALL sp_UpdateMarketParticipant(?, ?, ?, ?, ?, ?, ?)}";
    $params = array($id, $name, $shortName, $email, $type, $status, $isFinancialGroup);

    $update = sqlsrv_query($conn, $updateSql, $params);

    if ($update === false) {
        $message = "Error updating data: " . print_r(sqlsrv_errors(), true);
    } else {
        echo "<script>
                alert('Reporting Entity Updated Successfully.');
                window.location.href='show_mp_BY_ID.php';
              </script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Reporting Entity</title>
<style>
    form { max-width: 500px; margin: 20px auto; }
    label { display: block; margin-top: 10px; font-weight: bold; }
    input, select { width: 100%; padding: 8px; margin-top: 5px; }
    input[type="submit"] {
        background-color: #004080;
        color: white;
        border: none;
        padding: 10px;
        margin-top: 15px;
        cursor: pointer;
        font-weight: bold;
    }
    input[type="submit"]:hover {
        background-color: #003060;
    }
</style>
</head>
<body>

<h2 style="text-align:center;">Edit Reporting Entity</h2>

<form method="post">

    <label>Reporting Entity Name</label>
    <input type="text" name="MarketParticipantName" 
           value="<?php echo htmlspecialchars($data['MarketParticipantName']); ?>" required>

    <label>Short Name</label>
    <input type="text" name="MarketParticipantShortName" 
           value="<?php echo htmlspecialchars($data['MarketParticipantShortName']); ?>" required>

    <label>Email</label>
    <input type="email" name="MarketParticipantEmail" 
           value="<?php echo htmlspecialchars($data['MarketParticipantEmail']); ?>" required>

    <label>Type</label>
    <select name="MarketParticipantType" required>
        <?php foreach ($types as $t): ?>
            <option value="<?php echo $t['MarketParticipantTypeMasterId']; ?>"
                <?php if ($data['MarketParticipantType'] == $t['MarketParticipantTypeMasterId']) echo "selected"; ?>>
                <?php echo $t['MarketParticipantTypeMaster_name']; ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Status</label>
    <select name="Status" required>
        <option value="1" <?php if ($data['Status']==1) echo "selected"; ?>>ACTIVE</option>
        <option value="0" <?php if ($data['Status']==0) echo "selected"; ?>>INACTIVE</option>
    </select>

    <label>
        <input type="checkbox" name="IsFinancialGroup" value="1"
            <?php if ($data['IsFinancialGroup']==1) echo "checked"; ?>>
        Is Financial Group
    </label>

    <input type="submit" value="Update Reporting Entity">

</form>

</body>
</html>

<?php sqlsrv_close($conn); ?>
