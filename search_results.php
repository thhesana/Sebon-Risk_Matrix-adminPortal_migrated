<?php
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// Debugging output
if (!empty($search)) {
    echo 'Search query: ' . $search;
}

// Perform search logic here
?>
