<?php
require_once __DIR__ . '/session_config.php';


// Include the database connection file
require 'db.php';

// Enable error reporting for debugging
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

if (isset($_POST['login'])) {
    // Capture form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare the SQL query - now also fetch MarketParticipant_id
    $sql = "SELECT * FROM [RiskMatrix_AML].[dbo].[Users_Detail] WHERE username = ? AND active = 'Y'";
    $params = array($username);

    $stmt = sqlsrv_prepare($conn, $sql, $params);

    if (!$stmt) {
        die("Statement preparation failed: " . print_r(sqlsrv_errors(), true));
    }

    if (sqlsrv_execute($stmt)) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $user_id = $row['user_id'];
            
            // Check if the password is stored as plain text
            if ($row['password_hash'] === $password) {
                // Password is plain text, redirect to change password page
                $_SESSION['username'] = $username;
                header("Location: changepassword.php");
                exit();
            } 
            // Verify the hashed password
            else if (password_verify($password, $row['password_hash'])) {
                // Store session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                
                // IMPORTANT: Store MarketParticipant_id in session
                $_SESSION['MarketParticipant_id'] = $row['MarketParticipant_id'];

                // Redirect based on user role
                if ($row['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Invalid username or account not active";
        }
    } else {
        die("SQL execution failed: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RISK MATRIX CALCULATION</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --text-dark: #1e293b;
            --text-light: #f8fafc;
            --background: #f1f5f9;
            --white: #ffffff;
            --error: #ef4444;
            --success: #10b981;
            --border-radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            --shadow-md: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--background);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: 
                linear-gradient(45deg, rgba(37, 99, 235, 0.05) 25%, transparent 25%), 
                linear-gradient(-45deg, rgba(37, 99, 235, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(37, 99, 235, 0.05) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(37, 99, 235, 0.05) 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .background-shape {
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            top: -50px;
            right: -50px;
            z-index: 0;
            opacity: 0.8;
        }

        .background-shape:nth-child(2) {
            width: 100px;
            height: 100px;
            bottom: -30px;
            left: -30px;
            top: auto;
            right: auto;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .logo {
            max-width: 200px;
            height: auto;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
        }

        h1 {
            color: var(--text-dark);
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        h1:after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 0.5rem auto 0;
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .form-group label {
            display: block;
            color: var(--secondary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            background-color: #f8fafc;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .form-group .icon {
            position: absolute;
            right: 12px;
            top: 36px;
            color: var(--secondary);
        }

        .error {
            color: var(--error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            text-align: center;
            font-weight: 500;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            border: none;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: var(--white);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }

        .btn:hover:before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container {
            animation: fadeIn 0.5s ease forwards;
        }

        @media (max-width: 576px) {
            .login-container {
                max-width: 90%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="background-shape"></div>
        <div class="background-shape"></div>
        
        <div class="logo-container">
            <img class="logo" src="sebon_logo.png" alt="Risk Matrix System Logo">
        </div>
        
        <h1>RISK MATRIX CALCULATION</h1>
        <h1>SEBON ADMIN LOGIN</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="E.g: risk_napit" required>

                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>
</body>
</html>