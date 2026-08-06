<?php
require_once __DIR__ . '/session_config.php';


if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit();
}

// Handle logout
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEBON Risk Matrix - MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }

        /* ============================================
           HEADER STYLES
           ============================================ */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            height: 90px;
            width: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .header-logo:hover {
            transform: scale(1.05);
        }

        .header-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
        }

        /* User Info Section */
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }

        .user-info p {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .user-info strong {
            font-weight: 700;
            margin-left: 5px;
        }

        /* ============================================
           BUTTON STYLES
           ============================================ */
        .btn,
        button {
            border: none;
            padding: 8px 18px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .logout-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 10px 25px;
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(231, 76, 60, 0.4);
        }

        /* ============================================
           NAVIGATION TABS
           ============================================ */
        .nav-tabs {
            border-bottom: none;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-item {
            margin: 0;
        }

        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 15px 20px;
            text-decoration: none;
            border: none;
            background: transparent;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border-radius: 0;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-link:active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Dropdown Menu Styling */
        .nav-tabs .dropdown-menu {
            background: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            padding: 10px 0;
            margin-top: 5px;
        }

        .nav-tabs .dropdown-item {
            padding: 10px 20px;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-tabs .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-tabs .dropdown-toggle::after {
            margin-left: 6px;
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
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        /* ============================================
           RESPONSIVE DESIGN
           ============================================ */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .header-title {
                flex-direction: column;
            }

            .header-logo {
                height: 40px;
            }

            .header-title h2 {
                font-size: 1.2rem;
            }

            .user-info {
                flex-direction: column;
                padding: 15px;
                border-radius: 15px;
                width: 100%;
            }

            .nav-tabs {
                flex-direction: column;
            }

            .nav-tabs .nav-link {
                text-align: center;
                padding: 12px 15px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .logout-btn {
                width: 100%;
                margin-top: 10px;
            }
        }

        /* ============================================
           ANIMATIONS
           ============================================ */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            animation: fadeIn 0.5s ease;
        }

        .nav-tabs {
            animation: fadeIn 0.7s ease;
        }

        /* ============================================
           SCROLL BAR
           ============================================ */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="header-title">
            <img src="sebon_logo.png" alt="SEBON Logo" class="header-logo">
            <h2>RISK MATRIX CALCULATION</h2>
        </div>
        <div class="user-info">
            <p class="mb-0">User: <strong><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <form method="POST" style="display:inline; margin: 0;">
                <button type="submit" name="logout" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
</div>

<ul class="nav nav-tabs justify-content-center">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link text-white">DASHBOARD</a>
    </li>

    <!-- ANNEX DROPDOWN -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle text-white" data-bs-toggle="dropdown" href="#" role="button">
            ANNEX
        </a>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="sbn_annex1.php">ANNEX-1</a></li>
            <li><a class="dropdown-item" href="sbn_annex2.php">ANNEX-2</a></li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="RATING_BASED_ON_SBPS´ASSETS.php" class="nav-link text-white">RATING BASED ON SBPS´ASSETS</a>
    </li>

    <!-- RISK DROPDOWN -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle text-white" data-bs-toggle="dropdown" href="#" role="button">
            RISK
        </a>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="Structural_Risk.php">STRUCTURAL RISK</a></li>
            <li><a class="dropdown-item" href="INHERENT_Risk.php">INHERENT RISK</a></li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="COMPOSITE_RISK_REPORT.php" class="nav-link text-white">NET PROFILE RISK</a>
    </li>

    <li class="nav-item">
        <a href="SUBMISSION_REPORT.php" class="nav-link text-white">SUBMISSION</a>
    </li>

    <!-- MASTER TABLE DROPDOWN -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle text-white" data-bs-toggle="dropdown" href="#" role="button">
            MASTER TABLE
        </a>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="show_mp_BY_ID.php">REPORTING ENTITY PROFILE</a></li>
            <li><a class="dropdown-item" href="FY_MASTER.php">FY MASTER</a></li>
            <li><a class="dropdown-item" href="questionaireMastertbl.php">QUESTIONAIRE</a></li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="generate_pdf_reports.php" class="nav-link text-white">REPORT GEN</a>
    </li>
</ul>

</body>
</html>