<?php
// footer.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Make sure footer stays at bottom */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        footer {
            background-color: #002147; /* SEBON dark blue */
            color: white;
            text-align: center;
            padding: 12px 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin-top: auto;
            box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.2);
        }

        footer p {
            margin: 2px 0;
        }

        @media (max-width: 600px) {
            footer {
                font-size: 12px;
                padding: 10px 0;
            }
        }
    </style>
</head>
<body>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Securities Board of Nepal (SEBON) — Risk Matrix System</p>
        <p>Developed & Maintained by IT SECTION, SEBON</p>
    </footer>
</body>
</html>
