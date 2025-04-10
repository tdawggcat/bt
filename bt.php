<?php
require_once 'shared.php';

// Handle logout
handle_logout($conn);

// Handle login
$error = handle_login($conn);

// Check login status
$user = check_login($conn);

if ($user === false) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Login - Medical Supplies</title></head>
    <body>
        <h1>Login</h1>
        <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="post">
            <table style="border:0;padding:2px;margin:0;">
                <tr><td style="padding:2px;">Username:</td><td style="padding:2px;"><input type="text" name="username" required></td></tr>
                <tr><td style="padding:2px;">Password:</td><td style="padding:2px;"><input type="password" name="password" required></td></tr>
            </table>
            <input type="submit" name="login" value="Login">
        </form>
    </body>
    </html>
    <?php
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Main Menu - Medical Supplies</title></head>
    <body>
        <h1>Main Menu</h1>
        <p><a href="bt.php">Main Menu</a> | <a href="bt.php?logout=1">Logout</a></p>
        <table style="border:0;padding:2px;margin:0;">
            <?php if ($user['is_admin']) { ?>
            <tr><td style="padding:2px;"><a href="mu.php">Manage Users</a></td></tr>
            <tr><td style="padding:2px;"><a href="mi.php">Manage Items</a></td></tr>
            <?php } ?>
            <!-- Future menu options here -->
        </table>
    </body>
    </html>
    <?php
}

$conn->close();
?>
