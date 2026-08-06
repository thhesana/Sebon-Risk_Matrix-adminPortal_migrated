<?php
require_once __DIR__ . '/session_config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {

    header('Location: index.php');
    exit();
}

$username = $_SESSION['username']; // Get logged-in username
include 'header.php';
?>

<div class="container mt-5 text-center">
    <div class="card shadow-sm p-5">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
      
    </div>
</div>

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

/* User Info Section */
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
   BUTTON STYLES
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

.logout-btn {
    background: #dc3545;
    color: white;
}

.logout-btn:hover {
    background: #c82333;
}

/* ============================================
   NAVIGATION TABS
   ============================================ */
.nav-tabs {
    border-bottom: 2px solid #0056b3;
    background: #007bff;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
}

.nav-tabs .nav-link {
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border: none;
    background: transparent;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover,
.nav-tabs .nav-link.active {
    background: #0056b3;
}

/* ============================================
   CONTAINER & LAYOUT
   ============================================ */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 15px;
}

.content-wrapper {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

/* ============================================
   CARD STYLES (Dashboard & Components)
   ============================================ */
.card {
    border-radius: 15px;
    background-color: #f8f9fa;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.card-header {
    background-color: #007bff;
    color: white;
    padding: 15px;
    border-radius: 15px 15px 0 0;
    margin: -20px -20px 20px -20px;
    font-weight: bold;
}

.card-body {
    padding: 15px 0;
}

.card-footer {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
    margin-top: 15px;
    text-align: right;
}

.card-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 10px;
    color: #007bff;
}

.card-text {
    color: #666;
    margin-bottom: 10px;
}
</style>
