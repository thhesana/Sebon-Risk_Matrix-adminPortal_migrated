<?php
// db.php
// Adjust these for your environment
$serverName = "DESKTOP-IGPDORH"; // example: localhost\SQLEXPRESS
$connectionOptions = array(
    "Database" => "RiskMatrix_AML",
    "Uid" => "sa",          // or your DB user
    "PWD" => "Lazy-Car92",    // your password
    "CharacterSet" => "UTF-8"
	
);

// Connect
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die("SQLSRV Connect error: " . print_r(sqlsrv_errors(), true));
}
