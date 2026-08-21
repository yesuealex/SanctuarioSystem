<?php
session_start();

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| USER INFORMATION
|--------------------------------------------------------------------------
*/

$userName = $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'User';

$role = $_SESSION['role'] ?? 'staff';

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Account Settings | Sanctuario Nature Farm</title>


    <!-- =================================================
         POPPINS
         ================================================= -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- =================================================
         MATERIAL SYMBOLS
         ================================================= -->

    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:wght@400;500;600"
        rel="stylesheet"
    >


    <!-- =================================================
         MAIN CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="../style.css"
    >


    <!-- =================================================
         SIDEBAR CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="../sidebar.css"
    >


    <!-- =================================================
         ACCOUNT SETTINGS CSS
         ================================================= -->

    <link
        rel="stylesheet"
        href="accountSettings.css"
    >

</head>


<body>


<div class="layout">


    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <?php include '../sidebar.php'; ?>


    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="dashboard">


        <!-- =================================================
             TOP RIGHT BAR
             ================================================= -->

        <?php include '../topRightBar.php'; ?>


        <div class="account-page">


            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <div class="account-header">

                <div>

                    <h1>
                        Account Settings
                    </h1>

                    <p>
                        Manage your profile and account information.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 PROFILE BOARD
                 ================================================= -->

            <section class="account-board profile-board">


                <div class="profile-section">


                    <!-- AVATAR -->

                    <div class="avatar-container">

                        <div class="avatar">

                            <span class="material-symbols-rounded">
                                person
                            </span>

                        </div>


                        <button
                            type="button"
                            class="avatar-camera"
                            title="Change profile picture"
                        >

                            <span class="material-symbols-rounded">
                                photo_camera
                            </span>

                        </button>

                    </div>


                    <!-- PROFILE INFORMATION -->

                    <div class="profile-details">

                        <h2>
                            Xialienn B. Manalo
                        </h2>

                        <span class="profile-role">
                            <?= htmlspecialchars(ucfirst($role)) ?>
                        </span>

                        <p>
                            09123567890
                        </p>

                        <p>
                            xialienn@gmail.com
                        </p>


                        <div class="profile-buttons">

                            <button
                                type="button"
                                class="small-btn"
                            >
                                Upload Avatar
                            </button>


                            <button
                                type="button"
                                class="small-btn danger-btn"
                            >
                                Delete Avatar
                            </button>

                        </div>

                    </div>

                </div>

            </section>


            <!-- =================================================
                 PERSONAL INFORMATION BOARD
                 ================================================= -->

            <section class="account-board">


                <div class="board-header">

                    <div class="board-icon">

                        <span class="material-symbols-rounded">
                            person
                        </span>

                    </div>


                    <div>

                        <h2>
                            Personal Information
                        </h2>

                        <p>
                            Update your personal account information.
                        </p>

                    </div>

                </div>


                <div class="account-form">


                    <!-- FIRST NAME -->

                    <div class="form-group">

                        <label>
                            First Name
                        </label>

                        <input
                            type="text"
                            name="first_name"
                            value="Xialienn"
                        >

                    </div>


                    <!-- LAST NAME -->

                    <div class="form-group">

                        <label>
                            Last Name
                        </label>

                        <input
                            type="text"
                            name="last_name"
                            value="Manalo"
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="xialienn@gmail.com"
                        >

                    </div>


                    <!-- CONTACT -->

                    <div class="form-group">

                        <label>
                            Contact Number
                        </label>

                        <input
                            type="text"
                            name="contact_number"
                            value="09123567890"
                        >

                    </div>


                    <!-- GENDER -->

                    <div class="form-group">

                        <label>
                            Gender
                        </label>


                        <div class="gender-options">


                            <label class="gender-option">

                                <input
                                    type="radio"
                                    name="gender"
                                    value="Male"
                                >

                                <span>
                                    Male
                                </span>

                            </label>


                            <label class="gender-option">

                                <input
                                    type="radio"
                                    name="gender"
                                    value="Female"
                                    checked
                                >

                                <span>
                                    Female
                                </span>

                            </label>


                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="form-group">

                        <label>
                            Current Address
                        </label>

                        <input
                            type="text"
                            name="address"
                            value=""
                        >

                    </div>


                </div>

            </section>


            <!-- =================================================
                 SECURITY BOARD
                 ================================================= -->

            <section class="account-board security-board">


                <div class="board-header">


                    <div class="board-icon security-icon">

                        <span class="material-symbols-rounded">
                            lock
                        </span>

                    </div>


                    <div>

                        <h2>
                            Security
                        </h2>

                        <p>
                            Change your account password.
                        </p>

                    </div>


                </div>


                <div class="password-form">


                    <!-- CURRENT PASSWORD -->

                    <div class="form-group">

                        <label>
                            Current Password
                        </label>


                        <div class="password-input">

                            <input
                                type="password"
                                name="current_password"
                                placeholder="Enter current password"
                            >


                            <button
                                type="button"
                                class="show-password"
                            >

                                <span class="material-symbols-rounded">
                                    visibility
                                </span>

                            </button>

                        </div>

                    </div>


                    <!-- NEW PASSWORD -->

                    <div class="form-group">

                        <label>
                            New Password
                        </label>


                        <div class="password-input">

                            <input
                                type="password"
                                name="new_password"
                                placeholder="Enter new password"
                            >


                            <button
                                type="button"
                                class="show-password"
                            >

                                <span class="material-symbols-rounded">
                                    visibility
                                </span>

                            </button>

                        </div>

                    </div>


                    <!-- PASSWORD HINT -->

                    <div class="password-hint">

                        <span class="material-symbols-rounded">
                            info
                        </span>

                        <span>
                            Use at least 8 characters for a stronger password.
                        </span>

                    </div>


                </div>

            </section>


            <!-- =================================================
                 ACTION BUTTONS
                 ================================================= -->

            <div class="account-actions">


                <button
                    type="button"
                    class="cancel-btn"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    class="save-btn"
                >

                    <span class="material-symbols-rounded">
                        save
                    </span>

                    Save Changes

                </button>


            </div>


        </div>


    </main>


</div>


<!-- =================================================
     PASSWORD VISIBILITY
     ================================================= -->

<script>

document.querySelectorAll('.show-password').forEach(button => {

    button.addEventListener('click', function () {

        const input = this.parentElement.querySelector('input');

        const icon = this.querySelector('.material-symbols-rounded');

        if (input.type === 'password') {

            input.type = 'text';

            icon.textContent = 'visibility_off';

        } else {

            input.type = 'password';

            icon.textContent = 'visibility';

        }

    });

});

</script>


</body>

</html>