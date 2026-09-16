<?php

require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Admin Login - SKZ Engineering";

$error_message = "";

/*
|--------------------------------------------------------------------------
| Login Process
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error_message = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error_message = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare("
            SELECT id, name, email, password
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $admin = $result->fetch_assoc();

                if (password_verify($password, $admin["password"])) {

                    /*
                    |----------------------------------------------------------
                    | Regenerate Session ID
                    |----------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    $_SESSION["admin_id"] = $admin["id"];
                    $_SESSION["admin_name"] = $admin["name"];
                    $_SESSION["admin_email"] = $admin["email"];

                    header("Location: dashboard.php");
                    exit;

                } else {

                    $error_message = "Invalid email or password.";
                }

            } else {

                $error_message = "Invalid email or password.";
            }

            $stmt->close();

        } else {

            $error_message = "Something went wrong. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($page_title); ?>
    </title>

    <meta
        name="description"
        content="SKZ Engineering Admin Login"
    >

    <link
        rel="stylesheet"
        href="admin.css"
    >

</head>

<body class="admin-login-page">

    <main class="login-wrapper">

        <section class="login-card">

            <!-- Logo -->

            <div class="login-logo">

                <a href="../user/index.php">

                    <span class="login-logo-main">
                        SKZ
                    </span>

                    <span class="login-logo-sub">
                        ENGINEERING
                    </span>

                </a>

            </div>


            <!-- Heading -->

            <div class="login-heading">

                <h1>
                    Admin Login
                </h1>

                <p>
                    Sign in to manage your store
                </p>

            </div>


            <!-- Error Message -->

            <?php if ($error_message !== ""): ?>

                <div
                    class="admin-alert admin-alert-danger"
                    id="loginAlert"
                >
                    <?php echo htmlspecialchars($error_message); ?>
                </div>

            <?php endif; ?>


            <!-- Login Form -->

            <form
                action=""
                method="POST"
                class="admin-login-form"
                id="adminLoginForm"
                novalidate
            >

                <!-- Email -->

                <div class="admin-form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="admin-form-control"
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST["email"]) ? htmlspecialchars($_POST["email"]) : ""; ?>"
                        autocomplete="email"
                        required
                    >

                    <small
                        class="form-error"
                        id="emailError"
                    ></small>

                </div>


                <!-- Password -->

                <div class="admin-form-group">

                    <div class="password-label-row">

                        <label for="password">
                            Password
                        </label>

                    </div>

                    <div class="password-input-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="admin-form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >
                            Show
                        </button>

                    </div>

                    <small
                        class="form-error"
                        id="passwordError"
                    ></small>

                </div>


                <!-- Remember Me -->

                <div class="login-options">

                    <label class="remember-me">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        <span>
                            Remember me
                        </span>

                    </label>

                </div>


                <!-- Submit -->

                <button
                    type="submit"
                    class="admin-login-btn"
                    id="loginButton"
                >

                    <span id="loginButtonText">
                        Login
                    </span>

                </button>

            </form>


            <!-- Back to Website -->

            <div class="back-to-site">

                <a href="../user/index.php">
                    ← Back to Website
                </a>

            </div>

        </section>

    </main>


    <script src="../script.js"></script>

</body>

</html>