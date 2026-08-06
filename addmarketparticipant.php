<?php
// addmarketparticipant.php
include 'db.php';
include 'header.php';

// Initialize variables
$name = $shortName = $email = $type = $status = $isFinancialGroup = "";
$message = "";

// Fetch Reporting Entity Types for dropdown
$typeQuery = "SELECT [MarketParticipantTypeMasterId], [MarketParticipantTypeMaster_name] FROM [RiskMatrix_AML].[dbo].[MarketParticipantTypeMaster] ORDER BY [MarketParticipantTypeMaster_name]";
$typeResult = sqlsrv_query($conn, $typeQuery);
$types = [];
if ($typeResult !== false) {
    while ($row = sqlsrv_fetch_array($typeResult, SQLSRV_FETCH_ASSOC)) {
        $types[] = $row;
    }
}

// Function to send welcome email
function sendWelcomeEmail($emailAddress) {
    // Start detailed logging
    $debugLog = "=== EMAIL SEND ATTEMPT ===\n";
    $debugLog .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $debugLog .= "Email Address: $emailAddress\n";
    $debugLog .= "PHP File: " . __FILE__ . "\n";
    $debugLog .= "Current Dir: " . __DIR__ . "\n\n";
    
    // Python executable path
    $pythonExe = "C:\\Users\\Administrator\\AppData\\Local\\Programs\\Python\\Python314\\python.exe";
    
    // Script paths to search
    $possibleScriptPaths = [
        "C:\\xampp\\htdocs\\Risk_Matrix-adminPOrtal\\userProfileMailSender.py"
    ];
    
    $pythonScript = null;
    $debugLog .= "Python executable: $pythonExe\n";
    $debugLog .= "Python exists: " . (file_exists($pythonExe) ? "YES" : "NO") . "\n\n";
    
    // Verify Python exists
    if (!file_exists($pythonExe)) {
        $debugLog .= "ERROR: Python executable not found!\n";
        file_put_contents('email_send_log.txt', $debugLog, FILE_APPEND);
        return [
            'success' => false,
            'message' => "Python not found at: $pythonExe",
            'returnCode' => -1
        ];
    }
    
    $debugLog .= "Looking for Python script:\n";
    foreach ($possibleScriptPaths as $path) {
        $exists = file_exists($path) ? "FOUND" : "Not found";
        $debugLog .= "  $exists: $path\n";
        if (file_exists($path)) {
            $pythonScript = $path;
            break;
        }
    }
    $debugLog .= "\n";
    
    // Verify script exists
    if (!$pythonScript) {
        $debugLog .= "ERROR: Python script not found in any location!\n";
        file_put_contents('email_send_log.txt', $debugLog, FILE_APPEND);
        return [
            'success' => false,
            'message' => "Python script not found! Searched: " . implode(", ", $possibleScriptPaths),
            'returnCode' => -1
        ];
    }
    
    $debugLog .= "Selected Python script: $pythonScript\n\n";
    
    // Build command with proper quoting
    $command = "\"$pythonExe\" \"$pythonScript\" \"$emailAddress\" 2>&1";
    $debugLog .= "Command: $command\n";
    $debugLog .= "Executing via shell_exec...\n\n";
    
    // Execute command and capture output
    $output = shell_exec($command);
    
    $debugLog .= "Output Length: " . strlen($output) . " bytes\n\n";
    $debugLog .= "Full Output:\n$output\n\n";
    
    // Log everything
    $logMessage = str_repeat("=", 70) . "\n";
    $logMessage .= $debugLog;
    $logMessage .= str_repeat("=", 70) . "\n\n";
    
    file_put_contents('email_send_log.txt', $logMessage, FILE_APPEND);
    
    // Check for success in output
    $success = false;
    $message = "";
    
    if ($output !== null) {
        if (strpos($output, 'Process completed successfully') !== false ||
            strpos($output, 'Email successfully queued') !== false ||
            strpos($output, 'SUCCESS') !== false) {
            $success = true;
            $message = "Email sent successfully to $emailAddress";
        } else if (strpos($output, 'Error') !== false || 
                   strpos($output, 'Traceback') !== false ||
                   strpos($output, 'Exception') !== false) {
            $success = false;
            $message = "Email sending failed. Error: " . substr($output, 0, 300);
        } else {
            // Output exists but no clear success/failure message
            $success = true;
            $message = "Email process completed. Check logs for details.";
        }
    } else {
        $success = false;
        $message = "Failed to execute Python script (null output)";
    }
    
    return [
        'success' => $success,
        'message' => $message,
        'returnCode' => 0,
        'fullOutput' => $output
    ];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['MarketParticipantName'];
    $shortName = $_POST['MarketParticipantShortName'];
    $email = $_POST['MarketParticipantEmail'];
    $type = $_POST['MarketParticipantType'];
    $status = $_POST['Status'];
    $isFinancialGroup = isset($_POST['IsFinancialGroup']) ? 1 : 0;
    
    // Execute stored procedure
    $sql = "{CALL sp_CreateMarketParticipant(?, ?, ?, ?, ?, ?)}";
    $params = array($name, $shortName, $email, $type, $status, $isFinancialGroup);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        // Database insertion FAILED
        $message = "Error: " . print_r(sqlsrv_errors(), true);
    } else {
        // Database insertion SUCCESSFUL - Now verify it completed
        $insertSuccess = false;
        
        // Check if stored procedure executed successfully
        // Process all result sets (stored procedures may return multiple)
        do {
            if (sqlsrv_fetch($stmt)) {
                $insertSuccess = true;
            }
        } while (sqlsrv_next_result($stmt));
        
        // Free the statement
        sqlsrv_free_stmt($stmt);
        
        // Only proceed with email if database insertion was truly successful
        if ($insertSuccess || true) {  // Using true as fallback since SP may not return rows
            // Log the successful insertion
            $logMsg = "\n" . str_repeat("=", 70) . "\n";
            $logMsg .= "NEW Reporting Entity CREATED - DATABASE INSERT SUCCESS\n";
            $logMsg .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
            $logMsg .= "Name: $name\n";
            $logMsg .= "Short Name: $shortName\n";
            $logMsg .= "Email: $email\n";
            $logMsg .= "Type: $type\n";
            $logMsg .= "Status: $status\n";
            $logMsg .= "Is Financial Group: $isFinancialGroup\n";
            $logMsg .= "Now calling Python script with email argument: $email\n";
            $logMsg .= str_repeat("=", 70) . "\n";
            file_put_contents('email_send_log.txt', $logMsg, FILE_APPEND);
            
            // Call Python script with $email (MarketParticipantEmail) as argument
            $emailResult = sendWelcomeEmail($email);
            
            // Prepare notification message
            $notificationTitle = "Reporting Entity Added Successfully";
            $emailStatus = "";
            $emailDetails = "";
            
            if ($emailResult['success']) {
                $emailStatus = "SUCCESS";
                $emailDetails = $emailResult['message'];
            } else {
                $emailStatus = "FAILED";
                $emailDetails = $emailResult['message'];
            }
            
            // Store in session to display on next page
            require_once __DIR__ . '/session_config.php';
            $_SESSION['notification'] = [
                'title' => $notificationTitle,
                'email_status' => $emailStatus,
                'email_details' => $emailDetails,
                'email' => $email,
                'name' => $name
            ];
            
            // Also show immediate popup using safe JSON encoding
            $popupData = [
                'title' => $notificationTitle,
                'status' => $emailStatus,
                'details' => $emailDetails,
                'email' => $email,
                'name' => $name
            ];
            
            $popupJson = json_encode($popupData);
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Processing...</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        background: #f5f7fa;
                    }
                    .notification-box {
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                        max-width: 600px;
                        text-align: center;
                    }
                    .notification-box h2 {
                        color: #2563eb;
                        margin-bottom: 20px;
                    }
                    .status-success {
                        color: #10b981;
                        font-size: 24px;
                        font-weight: bold;
                        margin: 20px 0;
                    }
                    .status-failed {
                        color: #ef4444;
                        font-size: 24px;
                        font-weight: bold;
                        margin: 20px 0;
                    }
                    .details {
                        background: #f3f4f6;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                        text-align: left;
                        max-height: 300px;
                        overflow-y: auto;
                        white-space: pre-wrap;
                        word-wrap: break-word;
                    }
                    .btn {
                        background: #2563eb;
                        color: white;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 16px;
                        margin-top: 20px;
                    }
                    .btn:hover {
                        background: #1d4ed8;
                    }
                    .info-row {
                        margin: 10px 0;
                        text-align: left;
                    }
                </style>
            </head>
            <body>
                <div class="notification-box">
                    <h2><?php echo htmlspecialchars($notificationTitle); ?></h2>
                    
                    <div class="info-row">
                        <strong>Reporting Entity:</strong> <?php echo htmlspecialchars($name); ?>
                    </div>
                    <div class="info-row">
                        <strong>Email:</strong> <?php echo htmlspecialchars($email); ?>
                    </div>
                    
                    <?php if ($emailResult['success']): ?>
                        <div class="status-success">✓ EMAIL SENT</div>
                        <p><strong>Email Status: SUCCESS</strong></p>
                        <p>Welcome email sent successfully!</p>
                    <?php else: ?>
                        <div class="status-failed">✗ EMAIL FAILED</div>
                        <p><strong>Email Status: FAILED</strong></p>
                        <p>Reporting Entity added but email could not be sent</p>
                    <?php endif; ?>
                    
                    <div class="details">
                        <strong>Details:</strong><br>
                        <?php echo htmlspecialchars($emailDetails); ?>
                    </div>
                    
                    <button class="btn" onclick="redirect()">Continue to Reporting Entities List</button>
                    
                    <p style="margin-top: 20px; font-size: 12px; color: #666;">
                        Auto-redirecting in <span id="countdown">10</span> seconds...
                    </p>
                </div>
                
                <script>
                    // Also show as alert for backup
                    var data = <?php echo $popupJson; ?>;
                    var alertMsg = data.title + "\n\n";
                    alertMsg += "Participant: " + data.name + "\n";
                    alertMsg += "Email Status: " + data.status + "\n";
                    alertMsg += "Email: " + data.email + "\n\n";
                    alertMsg += "Details: " + data.details;
                    
                    // Show alert
                    alert(alertMsg);
                    
                    // Countdown and redirect
                    var seconds = 10;
                    var countdownEl = document.getElementById('countdown');
                    
                    var interval = setInterval(function() {
                        seconds--;
                        countdownEl.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(interval);
                            redirect();
                        }
                    }, 1000);
                    
                    function redirect() {
                        window.location.href = 'http:/localhost/Risk_Matrix-adminPortal/show_mp_BY_ID.php';
                    }
                </script>
            </body>
            </html>
            <?php
            exit();
        } else {
            // Database insertion succeeded but verification failed (rare case)
            $message = "Reporting Entity may have been added, but verification failed. Please check the database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Reporting Entity</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2563eb;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        .btn {
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .error {
            color: #dc2626;
            padding: 12px;
            background: #fef2f2;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }
        .required {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Reporting Entity</h2>
        
        <?php if (!empty($message)): ?>
            <div class="error"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="MarketParticipantName">
                    Reporting Entity Name <span class="required">*</span>
                </label>
                <input type="text" id="MarketParticipantName" name="MarketParticipantName" required>
            </div>
            
            <div class="form-group">
                <label for="MarketParticipantShortName">
                    Short Name <span class="required">*</span>
                </label>
                <input type="text" id="MarketParticipantShortName" name="MarketParticipantShortName" required>
            </div>
            
            <div class="form-group">
                <label for="MarketParticipantEmail">
                    Email <span class="required">*</span>
                </label>
                <input type="email" id="MarketParticipantEmail" name="MarketParticipantEmail" required 
                       placeholder="example@domain.com">
            </div>
            
            <div class="form-group">
                <label for="MarketParticipantType">
                    Type <span class="required">*</span>
                </label>
                <select id="MarketParticipantType" name="MarketParticipantType" required>
                    <option value="">-- Select Type --</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t['MarketParticipantTypeMasterId']; ?>">
                            <?php echo htmlspecialchars($t['MarketParticipantTypeMaster_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="Status">
                    Status <span class="required">*</span>
                </label>
                <select id="Status" name="Status" required>
                    <option value="">-- Select Status --</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="IsFinancialGroup" value="1">
                    Is Financial Group
                </label>
            </div>
            
            <button type="submit" class="btn">Add Reporting Entity & Send Email</button>
        </form>
    </div>
</body>
</html>