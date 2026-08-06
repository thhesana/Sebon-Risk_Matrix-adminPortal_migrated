<?php
require_once __DIR__ . '/session_config.php';


// Include the database connection file
require 'db.php';

// Enable error reporting for debugging (uncomment for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Variable to store success message
$password_updated = false;

// Check if the update password form has been submitted
if (isset($_POST['update_password'])) {
    // Capture form data
    $username = $_SESSION['username']; // Get the username from session
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Prepare the SQL query to fetch the user data based on the username
    $sql = "SELECT [username], [password_hash] FROM [RiskMatrix_AML].[dbo].Users_Detail WHERE username = ? AND active = 'Y'";
    $params = array($username);
    $stmt = sqlsrv_prepare($conn, $sql, $params);

    if (!$stmt) {
        die("Statement preparation failed: " . print_r(sqlsrv_errors(), true));
    }

    if (sqlsrv_execute($stmt)) {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Directly compare the old password with the stored plain text password
            // If your old password is stored in plain text, no need for password_verify here
            if ($old_password === $row['password_hash']) {
                // Validate if the new password matches the confirm password
                if ($new_password === $confirm_password) {
                    // Hash the new password before saving it
                    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);

                    // Prepare the update query to change the password
                    $update_sql = "UPDATE [RiskMatrix_AML].[dbo].Users_Detail SET [password_hash] = ? WHERE [username] = ?";
                    $update_params = array($new_password_hash, $username);

                    $update_stmt = sqlsrv_prepare($conn, $update_sql, $update_params);

                    if (!$update_stmt) {
                        die("Statement preparation failed: " . print_r(sqlsrv_errors(), true));
                    }

                    if (sqlsrv_execute($update_stmt)) {
                        // Set success message
                        $password_updated = true;
                    } else {
                        $error = "Error updating password: " . print_r(sqlsrv_errors(), true);
                    }
                    sqlsrv_free_stmt($update_stmt);
                } else {
                    $error = "New password and confirmation do not match!";
                }
            } else {
                $error = "Old password is incorrect!";
            }
        } else {
            $error = "User not found or account not active!";
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
    <title>Please Change your Password as new Password will be encrypted in hash. </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-container img {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
        }

        .login-container h1 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
        }

        .login-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .login-container input[type="password"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        .login-container button {
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #007BFF;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-container button:hover {
            background-color: #0056b3;
        }

        .login-container p.error {
            color: red;
            margin-top: 10px;
        }

        .login-container p.success {
            color: green;
            margin-top: 10px;
        }
    </style>

    <script>
    // Show success popup if password is updated and redirect to index.php
    <?php if ($password_updated): ?>
        alert("Password updated successfully!");
        window.location.href = "index.php"; // Redirect to index.php after alert
    <?php endif; ?>
</script>

</head>
<body>
    <div class="login-container">
        <img src="sebon_logo.png" alt="Office Logo">
        <h1>Please Change your password as new Password will be encrypted in hash.</h1>
        <?php 
            if (isset($error)) {
                echo "<p class='error'>$error</p>";
            }
        ?>
        <form method="POST" action="">
            <label for="old_password">Old Password:</label>
            <input type="password" id="old_password" name="old_password" required><br>

            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required><br>

            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br>

            <button type="submit" name="update_password">Update Password</button>
        </form>
    </div>
</body>
</html>
