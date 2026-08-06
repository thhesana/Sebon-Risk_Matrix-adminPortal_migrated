<?php
require_once __DIR__ . '/session_config.php';
session_destroy();

header("Location: index.php");
exit();
?>
