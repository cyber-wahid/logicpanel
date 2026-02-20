<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LogicPanel</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f7f7f7;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: #333;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 360px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            border: 1px solid #e5e5e5;
        }

        .logo {
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e1e1e1;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fdfdfd;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #4CAF50;
            background: #fff;
        }

        button {
            width: 100%;
            padding: 12px;
            /* Dynamic Button Color: Red for Master, Green for User */
            background-color:
                <?= isset($is_master_login) && $is_master_login ? '#d9534f' : '#3C873A' ?>
            ;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.2s;
        }

        button:hover {
            background-color:
                <?= isset($is_master_login) && $is_master_login ? '#c9302c' : '#327530' ?>
            ;
        }

        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: #999;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        /* Placeholder style */
        ::placeholder {
            color: #aaa;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="logo">LogicPanel</div>

        <?php if (isset($error) && $error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $base_url ?? '' ?>/login" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit">Sign In</button>
        </form>

        <div class="footer">
            &copy; <?= date('Y') ?> LogicPanel. All rights reserved.
        </div>
    </div>

</body>

</html>