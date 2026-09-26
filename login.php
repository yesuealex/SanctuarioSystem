<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: adminDashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Sanctuario Nature Farm</title>

    <!-- Poppins Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>

    /* =========================================================
       RESET
    ========================================================= */

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html,
    body {
        width: 100%;
        min-height: 100%;
    }

    body {
        font-family: "Poppins", sans-serif;
        background: #f5f7f4;
        color: #1f2937;
    }


    /* =========================================================
       MAIN CONTAINER
    ========================================================= */

    .login-container {
        min-height: 100vh;
        display: flex;
        background: #ffffff;
    }


    /* =========================================================
       LEFT PANEL
    ========================================================= */

    .login-left {
        width: 50%;
        min-height: 100vh;

        background:
            radial-gradient(
                circle at 20% 20%,
                rgba(248, 246, 99, 0.07),
                transparent 35%
            ),
            #163318;

        color: #ffffff;

        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;

        text-align: center;

        padding: 50px;

        position: relative;
        overflow: hidden;
    }


    /* Decorative circles */

    .login-left::before {
        content: "";

        position: absolute;

        width: 320px;
        height: 320px;

        border: 1px solid rgba(248, 246, 99, 0.08);

        border-radius: 50%;

        top: -120px;
        left: -120px;
    }


    .login-left::after {
        content: "";

        position: absolute;

        width: 400px;
        height: 400px;

        border: 1px solid rgba(248, 246, 99, 0.06);

        border-radius: 50%;

        bottom: -200px;
        right: -170px;
    }


    /* =========================================================
       LEFT CONTENT
    ========================================================= */

    .left-content {
        width: 100%;
        max-width: 500px;

        position: relative;
        z-index: 2;
    }


    /* =========================================================
       EYEBROW
    ========================================================= */

    .eyebrow {
        display: inline-block;

        font-size: 12px;
        font-weight: 600;

        letter-spacing: 2px;
        text-transform: uppercase;

        color: rgba(248, 246, 99, 0.65);

        margin-bottom: 30px;
    }


    /* =========================================================
       LOGO
    ========================================================= */

    .login-logo {
        display: block;

        width: 559px;
        max-width: 120%;

        height: auto;

        margin: 0 auto 30px;

        object-fit: contain;
    }


    /* =========================================================
       ACCENT LINE
    ========================================================= */

    .accent-line {
        width: 50px;
        height: 3px;

        background: #f8f663;

        border-radius: 20px;

        margin: 0 auto 24px;

        opacity: 0.7;
    }


    /* =========================================================
       DESCRIPTION
    ========================================================= */

    .login-left-description {
        font-size: 14px;
        font-weight: 400;

        line-height: 1.8;

        color: rgba(255, 255, 255, 0.62);

        max-width: 350px;

        margin: 0 auto;
    }


    /* =========================================================
       RIGHT SIDE
    ========================================================= */

    .login-right {
        width: 50%;
        min-height: 100vh;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 50px;

        background: #ffffff;
    }


    /* =========================================================
       LOGIN CARD
    ========================================================= */

    .login-card {
        width: 100%;
        max-width: 400px;
    }


    /* =========================================================
       HEADING
    ========================================================= */

    .login-card h2 {
        font-size: 28px;
        font-weight: 600;

        color: #1f3f24;

        margin-bottom: 8px;

        letter-spacing: -0.5px;
    }


    .sub {
        font-size: 13px;
        font-weight: 400;

        color: #8a948c;

        margin-bottom: 32px;
    }


    /* =========================================================
       ERROR MESSAGE
    ========================================================= */

    .error-msg {
        display: flex;
        align-items: center;

        gap: 10px;

        background: #fff1f1;

        border: 1px solid #ffd4d4;

        color: #b42318;

        padding: 12px 14px;

        border-radius: 9px;

        margin-bottom: 20px;

        font-size: 13px;

        line-height: 1.4;
    }


    .error-icon {
        width: 20px;
        height: 20px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        background: #b42318;

        color: white;

        border-radius: 50%;

        font-size: 12px;

        font-weight: 600;
    }


    /* =========================================================
       FORM
    ========================================================= */

    .form-group {
        margin-bottom: 20px;
    }


    .form-group label {
        display: block;

        font-size: 13px;
        font-weight: 500;

        color: #344238;

        margin-bottom: 8px;
    }


    /* =========================================================
       INPUT
    ========================================================= */

    .input-wrapper {
        position: relative;
    }


    .form-group input {
        width: 100%;

        height: 50px;

        padding: 0 15px;

        border: 1px solid #dce3dd;

        border-radius: 9px;

        background: #ffffff;

        font-family: "Poppins", sans-serif;

        font-size: 13px;

        color: #1f2937;

        outline: none;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease;
    }


    .form-group input::placeholder {
        color: #a7b0a9;
    }


    .form-group input:hover {
        border-color: #b8c5ba;
    }


    .form-group input:focus {
        border-color: #3c7a3d;

        box-shadow:
            0 0 0 3px rgba(60, 122, 61, 0.10);
    }


    /* =========================================================
       LOGIN BUTTON
    ========================================================= */

    .btn-login {
        width: 100%;

        height: 50px;

        margin-top: 8px;

        border: none;

        border-radius: 9px;

        background: #2f6b2f;

        color: #ffffff;

        font-family: "Poppins", sans-serif;

        font-size: 13px;

        font-weight: 600;

        cursor: pointer;

        transition:
            background 0.2s ease,
            transform 0.1s ease,
            box-shadow 0.2s ease;
    }


    .btn-login:hover {
        background: #255a27;

        box-shadow:
            0 5px 15px rgba(47, 107, 47, 0.18);
    }


    .btn-login:active {
        transform: translateY(1px);
    }


    .btn-login:focus-visible {
        outline: 3px solid rgba(47, 107, 47, 0.20);

        outline-offset: 2px;
    }


    /* =========================================================
       TABLET
    ========================================================= */

    @media (max-width: 900px) {

        .login-logo {
            width: 330px;
        }

        .login-left {
            padding: 40px 30px;
        }

        .login-right {
            padding: 40px 30px;
        }
    }


    /* =========================================================
       MOBILE
    ========================================================= */

    @media (max-width: 800px) {

        .login-container {
            flex-direction: column;
        }


        .login-left {
            width: 100%;
            min-height: auto;

            padding: 45px 25px 40px;
        }


        .login-right {
            width: 100%;
            min-height: auto;

            padding: 45px 25px 55px;
        }


        .login-logo {
            width: 300px;
            max-width: 80%;
        }


        .eyebrow {
            margin-bottom: 20px;
        }


        .login-left-description {
            font-size: 13px;
        }
    }


    /* =========================================================
       SMALL PHONES
    ========================================================= */

    @media (max-width: 480px) {

        .login-left {
            padding: 35px 20px;
        }


        .login-right {
            padding: 35px 20px 45px;
        }


        .login-logo {
            width: 250px;
            max-width: 85%;
        }


        .login-card h2 {
            font-size: 25px;
        }


        .sub {
            margin-bottom: 26px;
        }
    }


    </style>
</head>

<body>

<div class="login-container">


    <!-- =====================================================
         LEFT PANEL
    ====================================================== -->

    <section class="login-left">

        <div class="left-content">

            <div class="eyebrow">
                Farm Management System
            </div>


            <img
                src="logo2.png"
                alt="Sanctuario Nature Farm"
                class="login-logo"
            >


            <div class="accent-line"></div>


            <p class="login-left-description">
                Track crops, monitor harvests, and manage your farm
                in one simple and organized system.
            </p>

        </div>

    </section>



    <!-- =====================================================
         RIGHT PANEL
    ====================================================== -->

    <section class="login-right">

        <div class="login-card">

            <h2>
                Welcome back
            </h2>


            <p class="sub">
                Sign in to access your farm dashboard.
            </p>


            <?php if (isset($_GET['error'])): ?>

                <div class="error-msg">

                    <span class="error-icon">!</span>

                    <span>
                        Invalid username or password.
                    </span>

                </div>

            <?php endif; ?>


            <form
                action="login_process.php"
                method="POST"
            >


                <!-- USERNAME -->

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <div class="input-wrapper">

                        <input
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Enter your username"
                            required
                            autocomplete="username"
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >

                    </div>

                </div>



                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="btn-login"
                >
                    Login to Dashboard
                </button>


            </form>

        </div>

    </section>

</div>

</body>
</html>