<?php
session_start();
require_once 'vendor/autoload.php';
$error = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
];
$activeForm = $_GET['form'] ?? ($_SESSION['active_form'] ?? 'login');
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);

function showError($error)
{
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm)
{
    return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- Title that appears in search results -->
    <title>Login & Register</title>
    <link rel="stylesheet" href="styel.css">
    <link rel="icon" type="image/png" href="SENOAMADI_BANK.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <!-- Short description that appears under the title in Google -->
    <meta name="description" content="Get access to reliable digital banking, financial partner, and technology-driven
            banking services." />
    <!-- Keywords (optional but still useful) -->
    <meta name="keywords" content="Senoamadi bank, loan, Investment, technology-driven
            banking services, secure future, Judge" />
    <!-- Author -->
    <meta name="author" content="Judge Tshwarelo" />
    <!-- Favicon (small icon that appears next to your site title) -->
    <link rel="icon" type="image/png" href="SENOAMADI_BANK.png" />
    <!-- Boxicons for menu icons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background-color: gray;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            height: 100vh;
        }

        .container {
            background-color: white;
            border-radius: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
            width: 700px;
            max-width: 100%;
            min-height: 380px;
        }

        .container form {
            background-color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0 40px;
            height: 100%;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .social-icons {
            margin: 5px 0;
        }

        .social-icons a {
            color: #D4AF37;
            border: 1px solid #D4AF37;
            border-radius: 20%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin: 0 5px;
            width: 40px;
            height: 40px;
        }

        .container input,
        select {
            background-color: #eee;
            border: none;
            margin: 8px 0;
            padding: 10px 15px;
            font-size: 13px;
            border-radius: 8px;
            width: 100%;
            outline: none;
        }

        .button,
        button {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #D4AF37;
            background-color: #0A1A2F;
            border: none;
            border-radius: 5px;
            width: 100%;
            height: 40px;
            margin-top: 10px;
            cursor: pointer;
            transition: 0.3s;
        }

        .button:hover,
        button:hover {
            background-color: #D4AF37;
            color: #0A1A2F;
        }

        .error-message {
            background: #f8d7da;
            color: #a42834;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            text-align: center;
        }

        .toggle-container {
            position: absolute;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex-direction: column;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            border-radius: 100px 0 0 100px;
        }

        .toggle {
            background-color: #0A1A2F;
            color: #D4AF37;
            height: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .toggle a {
            text-decoration: none;
            color: white;
            font-weight: bold;
            border: 2px solid white;
            border-radius: 5px;
            padding: 8px 20px;
            transition: 0.3s;
        }

        .toggle a:hover {
            background-color: white;
            color: #0A1A2F;
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            transition: all 0.6s ease-in-out;
        }

        #login-form {
            left: 0;
        }

        #register-form {
            right: 0;
        }

        .active {
            display: block;
        }

        .inactive {
            display: none;
        }

        /* Form containers */
        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            transition: all 0.6s ease-in-out;
        }

        /* REGISTER FORM LEFT */
        #register-form {
            left: 0;
        }

        /* ===== MOBILE RESPONSIVENESS ===== */
        @media (max-width: 768px) {
            body {
                height: auto;
                padding: 20px 0;
            }

            .container {
                flex-direction: column;
                border-radius: 20px;
                height: auto;
                width: 95%;
            }

            .form-container {
                position: relative;
                width: 100%;
                height: auto;
            }

            .toggle-container {
                position: relative;
                left: 0;
                width: 100%;
                height: auto;
                border-radius: 0;
                padding: 20px 0;
            }

            .toggle {
                flex-direction: column;
                height: auto;
                padding: 10px 0;
            }

            .container form {
                padding: 20px;
            }

            h1 {
                font-size: 22px;
            }

            .social-icons a {
                width: 35px;
                height: 35px;
                margin: 0 3px;
            }

            button {
                font-size: 14px;
                height: 38px;
            }
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 15px;
            }

            h1 {
                font-size: 20px;
            }

            input,
            select {
                font-size: 12px;
                padding: 8px 12px;
            }

            button {
                font-size: 13px;
                height: 35px;
            }
        }
    </style>
</head>

<body>

    <div class="container" id="content">
        <!-- LOGIN FORM -->
        <div class="form-container <?= isActiveForm('login', $activeForm) ? 'active' : 'inactive'; ?>" id="login-form">
            <form action="login_registor.php" method="post">
                <h1>Login</h1>
                <?= showError($error['login']); ?>
                <div class="social-icons">
                    <a href="google_login.php"><i class="fab fa-google"></i></a>
                    <a href="#"><i class="fab fa-google"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-microsoft"></i></a>
                </div>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <span style="display: flex; gap: 20px;">
                    <button type="submit" name="login">Login</button>
                    <button type="button" onclick="window.location.href='index.php'">Home</button>
                </span>
                <p style="margin-top:10px;">Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
            </form>
        </div>

        <!-- REGISTER FORM -->
        <div class="form-container <?= isActiveForm('register', $activeForm) ? 'active' : 'inactive'; ?>" id="register-form">
            <form action="login_registor.php" method="post">
                <h1>Create Account</h1>
                <?= showError($error['register']); ?>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-google"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-microsoft"></i></a>
                </div>
                <input type="text" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <div style="display: flex; gap: 10px; width: 100%;">
                    <input type="text" name="country_code" value="+27" readonly style="width: 20%; background-color: #ddd; text-align: center;">
                    <input type="text" name="phone" placeholder="Cellphone Number (e.g. 678123456)" pattern="[0-9]{9}" maxlength="9" required style="width: 80%;">
                </div>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="">--Select Role--</option>
                    <option value="user">User</option>
                </select>
                <button type="submit" name="register">Register</button>
            </form>
        </div>

        <!-- BLUE TOGGLE SIDE -->
        <div class="toggle-container">
            <div class="toggle">
                <h1>Welcome!</h1>
                <p>Access your account or register to continue</p>
                <p style="margin-top:10px;">Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </div>
        </div>
    </div>

    <script>
        function showForm(formId) {
            document.getElementById('login-form').classList.add('inactive');
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.add('inactive');
            document.getElementById('register-form').classList.remove('active');

            document.getElementById(formId).classList.remove('inactive');
            document.getElementById(formId).classList.add('active');
        }


        function loadHome() {
            fetch('index.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('content').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('content').innerHTML = `<p style="color:red;">Error loading home page: ${error}</p>`;
                });
        }
    </script>
</body>

</html>