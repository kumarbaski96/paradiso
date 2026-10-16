<?php
session_start();
include 'config/db.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$view = $_GET['view'] ?? 'login';
$error = '';
$success = '';
$recoveredEmail = '';
$recoveredId = '';

if (!in_array($view, ['login', 'recover', 'reset'], true)) {
    $view = 'login';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADMIN LOGIN
    if ($action === 'login') {
        $view = 'login';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM admins
             WHERE email = ? AND status = 1
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = false;

        if ($admin) {
            if (password_verify($password, $admin['password'])) {
                $valid = true;
            } elseif (hash_equals((string)$admin['password'], (string)$password)) {
                // Upgrade an old plain-text password to a hash.
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare(
                    "UPDATE admins SET password = ? WHERE id = ?"
                );
                $update->bind_param("si", $hash, $admin['id']);
                $update->execute();
                $update->close();
                $valid = true;
            }
        }

        if ($admin && $valid) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }

    // RECOVER EMAIL / ADMIN ID BY NAME OR EMAIL
    elseif ($action === 'recover') {
        $view = 'recover';
        $identity = trim($_POST['identity'] ?? '');

        if ($identity === '') {
            $error = 'Please enter your admin name or email.';
        } else {
            $stmt = $conn->prepare(
                "SELECT id, name, email
                 FROM admins
                 WHERE (name = ? OR email = ?) AND status = 1
                 LIMIT 1"
            );
            $stmt->bind_param("ss", $identity, $identity);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin) {
                $recoveredEmail = $admin['email'];
                $recoveredId = $admin['id'];
                $success = 'Admin account found.';
            } else {
                $error = 'No active admin account found with those details.';
            }
        }
    }

    // RESET PASSWORD
    elseif ($action === 'reset') {
        $view = 'reset';
        $identity = trim($_POST['identity'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($identity === '') {
            $error = 'Enter your registered email or admin name.';
        } elseif (strlen($newPassword) < 10) {
            $error = 'Password must contain at least 10 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // IMPORTANT: Add email/OTP verification before enabling this
            // password-reset action on a public website.
            $stmt = $conn->prepare(
                "SELECT id FROM admins
                 WHERE (email = ? OR name = ?) AND status = 1
                 LIMIT 1"
            );
            $stmt->bind_param("ss", $identity, $identity);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$admin) {
                $error = 'Admin account not found.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $conn->prepare(
                    "UPDATE admins SET password = ? WHERE id = ?"
                );
                $update->bind_param("si", $hash, $admin['id']);
                $update->execute();
                $update->close();

                $success = 'Password changed successfully. Please log in.';
                $view = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Account | Marriage Hall</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 25px 0;
    background: linear-gradient(135deg, #24140e, #654126);
    font-family: Arial, sans-serif;
}
.login-card {
    width: 100%;
    max-width: 440px;
    border: 0;
    border-radius: 18px;
    box-shadow: 0 15px 45px rgba(0,0,0,.28);
    overflow: hidden;
}
.login-heading {
    background: #8a2525;
    color: white;
    text-align: center;
    padding: 25px 20px;
}
.login-heading i { font-size: 42px; }
.login-heading h3 { margin: 10px 0 5px; font-weight: 700; }
.login-body { padding: 30px; }
.form-control { padding: 11px 13px; border-radius: 8px; }
.btn-login {
    background: #c62828;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px;
    font-weight: 600;
}
.btn-login:hover { background: #a61f1f; color: white; }
.recovery-links a { color: #9b2929; text-decoration: none; font-size: 14px; }
.recovery-links a:hover { text-decoration: underline; }
.back-link { display: inline-block; margin-top: 20px; color: #6c757d; text-decoration: none; }
.back-link:hover { color: #a61f1f; }
</style>
</head>
<body>
<div class="container px-3">
<div class="card login-card mx-auto">

    <div class="login-heading">
        <i class="bi bi-shield-lock-fill"></i>
        <h3>Marriage Hall</h3>
        <div>Admin Management Panel</div>
    </div>

    <div class="login-body">

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($recoveredEmail !== ''): ?>
                    <hr>
                    <div><strong>Admin ID:</strong>
                        <?= htmlspecialchars((string)$recoveredId, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div><strong>Email:</strong>
                        <?= htmlspecialchars($recoveredEmail, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($view === 'login'): ?>

            <h4 class="text-center mb-4">Admin Login</h4>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           placeholder="Enter registered email" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control"
                           autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login Securely
                </button>
            </form>

            <div class="recovery-links d-flex justify-content-between mt-4">
                <a href="?view=recover"><i class="bi bi-person me-1"></i>Forgot ID?</a>
                <a href="?view=reset"><i class="bi bi-key me-1"></i>Forgot Password?</a>
            </div>

        <?php elseif ($view === 'recover'): ?>

            <h4 class="text-center mb-2">Recover Admin ID</h4>
            <p class="text-muted text-center mb-4">
                Enter your registered admin name or email.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="recover">
                <div class="mb-4">
                    <label class="form-label">Admin Name or Email</label>
                    <input type="text" name="identity" class="form-control"
                           value="<?= htmlspecialchars($_POST['identity'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required>
                </div>
                <button type="submit" class="btn btn-login w-100">Find Admin Account</button>
            </form>
            <a class="back-link" href="login.php">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>

        <?php else: ?>

            <h4 class="text-center mb-2">Reset Password</h4>
            <p class="text-muted text-center mb-4">
                Enter your registered email or exact admin name.
            </p>
            <div class="alert alert-warning small">
                This form changes the account password. Do not expose it publicly
                without adding email verification or another secure identity check.
            </div>
            <form method="post">
                <input type="hidden" name="action" value="reset">
                <div class="mb-3">
                    <label class="form-label">Admin Name or Email</label>
                    <input type="text" name="identity" class="form-control"
                           required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           minlength="10" autocomplete="new-password" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           minlength="10" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-login w-100">Reset Password</button>
            </form>
            <a class="back-link" href="login.php">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>

        <?php endif; ?>

    </div>
</div>
</div>
</body>
</html>