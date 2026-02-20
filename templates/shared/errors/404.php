<?php
// templates/errors/404.php
if (!headers_sent()) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | LogicPanel</title>
    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #1f2937;
        }

        .container {
            text-align: center;
            background: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 90%;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 30px;
            display: block;
        }

        h1 {
            font-size: 80px;
            margin: 0;
            color: #d9534f;
            /* Error Red */
            font-weight: 900;
            line-height: 1;
        }

        h2 {
            margin-top: 20px;
            margin-bottom: 15px;
            font-size: 22px;
            color: #111827;
        }

        p {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background-color: #3C873A;
            /* LogicPanel Green */
            color: white;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #2D6A2E;
        }
    </style>
</head>

<body>
    <div class="container">
        <span class="logo">LogicPanel</span>
        <h1>404</h1>
        <h2>Page Not Available</h2>
        <p>Sorry, the page you are looking for could not be found. Please check the URL or return to the dashboard.</p>
        <a href="/" class="btn">Back to Dashboard</a>
    </div>
</body>

</html>