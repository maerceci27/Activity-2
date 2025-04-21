<?php
require 'config.php';
session_start();

$message = "";
$cooldownTriggered = false;
$maxAttempts = 4;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check for cooldown
    $stmt = $conn->prepare("SELECT * FROM login_attempts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $cooldown_result = $stmt->get_result()->fetch_assoc();

    if ($cooldown_result) {
        $last_failed = strtotime($cooldown_result['last_failed']);
        $attempts = $cooldown_result['attempts'];
        $time_diff = time() - $last_failed;

        if ($attempts >= $maxAttempts && $time_diff < 300) {
            $cooldownTriggered = true;
            $message = "Too many failed attempts. Please try again later.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['username'] = $username;
                $conn->query("DELETE FROM login_attempts WHERE username = '$username'");
                $message = "Login successful!";
            } else {
                $new_attempts = ($attempts >= $maxAttempts) ? 1 : $attempts + 1;
                $stmt = $conn->prepare("UPDATE login_attempts SET attempts = ?, last_failed = CURRENT_TIMESTAMP WHERE username = ?");
                $stmt->bind_param("is", $new_attempts, $username);
                $stmt->execute();

                if ($new_attempts >= $maxAttempts) {
                    $cooldownTriggered = true;
                    $message = "Too many failed attempts. Please try again later.";
                } else {
                    $message = "Incorrect credentials.";
                }
            }
        }
    } else {
        // First failed attempt
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['username'] = $username;
            $message = "Login successful!";
        } else {
            $stmt = $conn->prepare("INSERT INTO login_attempts (username, attempts, last_failed) VALUES (?, 1, CURRENT_TIMESTAMP)");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $message = "Incorrect credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<nav>
    <a href="register.php">Register</a> |
    <a href="login.php">Login</a>
</nav>

<div class="form-container">
    <h2>Login</h2>
    <?php if (isset($_GET['registered'])): ?>
        <p style="color: green;">Registration successful. Please log in.</p>
    <?php endif; ?>
    <form method="POST" action="" id="loginForm">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button id="loginBtn" type="submit">Login</button>
    </form>
    <p style="color: red;"><?php echo $message; ?></p>
</div>

<script>
    const cooldownTime = 5 * 60 * 1000; // 5 minutes
    const btn = document.getElementById('loginBtn');
    const form = document.getElementById('loginForm');

    // If server triggered cooldown, start local countdown
    <?php if ($cooldownTriggered): ?>
        localStorage.setItem('cooldown_start', Date.now());
    <?php endif; ?>

    // Handle disabling and countdown display
    function checkCooldown() {
        const cooldownStart = localStorage.getItem('cooldown_start');
        if (cooldownStart) {
            const elapsed = Date.now() - cooldownStart;
            if (elapsed < cooldownTime) {
                disableLoginButton(cooldownTime - elapsed);
            } else {
                localStorage.removeItem('cooldown_start');
                btn.disabled = false;
                btn.textContent = "Login";
            }
        }
    }

    function disableLoginButton(remaining) {
        btn.disabled = true;
        const countdown = setInterval(() => {
            const seconds = Math.floor(remaining / 1000);
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            btn.textContent = `Try again in ${mins}:${secs < 10 ? '0' : ''}${secs}`;
            remaining -= 1000;

            if (remaining <= 0) {
                clearInterval(countdown);
                localStorage.removeItem('cooldown_start');
                btn.disabled = false;
                btn.textContent = "Login";
            }
        }, 1000);
    }

    window.onload = checkCooldown;
</script>
</body>
</html>
