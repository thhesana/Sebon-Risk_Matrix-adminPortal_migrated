<?php
include 'db.php';
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fy = $_POST['FiscalYearName'];
    $sql = "INSERT INTO FiscalYear (FiscalYearName) VALUES (?)";
    $params = array($fy);
    if (sqlsrv_query($conn, $sql, $params)) {
        header("Location: fy_master.php");
        exit;
    } else {
        echo "<script>alert('Error inserting record.');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Fiscal Year</title>
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
            max-width: 600px;
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
           FORM STYLES
           ============================================ */
        .form-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: #34495e;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: all 0.3s;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[type="text"]:invalid {
            border-color: #e74c3c;
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-submit {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(39, 174, 96, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(149, 165, 166, 0.3);
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(149, 165, 166, 0.4);
        }

        /* ============================================
           INFO BOX
           ============================================ */
        .info-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #27ae60;
            font-size: 14px;
            color: #2c3e50;
        }

        .info-box strong {
            color: #27ae60;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .page-container {
                padding: 10px;
                margin: 10px;
            }

            .page-container > h2 {
                font-size: 18px;
                padding: 15px;
                margin: -10px -10px 20px -10px;
            }

            .form-container {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="page-container">
    <h2>➕ Add New Fiscal Year</h2>

    <div class="info-box">
        <strong>📝 Note:</strong> Please enter the fiscal year in the format: YYYY/YY (e.g., 2080/81, 2081/82)
    </div>

    <div class="form-container">
        <form method="POST" action="">
            <div class="form-group">
                <label for="FiscalYearName">Fiscal Year Name <span style="color: #e74c3c;">*</span></label>
                <input type="text" 
                       id="FiscalYearName" 
                       name="FiscalYearName" 
                       placeholder="e.g., 2080/81" 
                       required
                       pattern="[0-9]{4}/[0-9]{2}"
                       title="Please enter fiscal year in format: YYYY/YY (e.g., 2080/81)">
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-submit">💾 Save Fiscal Year</button>
                <a href="fy_master.php" class="btn btn-cancel">❌ Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>