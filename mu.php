<?php
require_once 'shared.php';

// Handle logout
handle_logout($conn);

// Handle add user
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $active = isset($_POST['active']) ? 1 : 0;
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO users (username, firstname, lastname, email, password, active, is_admin) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssii", $username, $firstname, $lastname, $email, $password, $active, $is_admin);
    if ($stmt->execute()) {
        $message = "User added successfully!";
    } else {
        $error = "Error adding user: " . $conn->error;
    }
    $stmt->close();
}

// Handle edit user
if (isset($_POST['edit_user'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $active = isset($_POST['active']) ? 1 : 0;
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    $query = "UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, active = ?, is_admin = ?";
    $params = [$username, $firstname, $lastname, $email, $active, $is_admin];
    $types = "ssssii";
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $query .= ", password = ?";
        $params[] = $password;
        $types .= "s";
    }
    $query .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $message = "User updated successfully!";
    } else {
        $error = "Error updating user: " . $conn->error;
    }
    $stmt->close();
}

// Check login status (must be admin)
$user = check_login($conn);
if ($user === false || !$user['is_admin']) {
    $error = handle_login($conn) ?? "Admin access required.";
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Login - Manage Users</title></head>
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
    <head><title>Manage Users</title></head>
    <body>
        <h1>User Management</h1>
        <p><a href="bt.php">Main Menu</a> | <a href="mu.php?logout=1">Logout</a></p>
        <?php 
        if (isset($message)) echo "<p style='color:green;'>$message</p>";
        if (isset($error)) echo "<p style='color:red;'>$error</p>"; 
        ?>
        
        <h2>Users</h2>
        <table style="border-collapse:collapse;padding:2px;margin:0;">
            <tr style="background-color:#e0e0e0;">
                <th style="border:1px solid black;padding:2px;text-align:left;">ID</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Username</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">First</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Last</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Email</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Active</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Admin</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Action</th>
            </tr>
            <?php
            $result = $conn->query("SELECT id, username, firstname, lastname, email, active, is_admin 
                                   FROM users ORDER BY id");
            while ($row = $result->fetch_assoc()) {
                if (isset($_GET['edit']) && $_GET['edit'] == $row['id']) {
                    // Edit mode for this row
                    ?>
                    <tr>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['id']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="text" name="username" value="<?php echo htmlspecialchars($row['username']); ?>" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <input type="text" name="firstname" value="<?php echo htmlspecialchars($row['firstname']); ?>" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <input type="text" name="lastname" value="<?php echo htmlspecialchars($row['lastname']); ?>" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="checkbox" name="active" <?php echo $row['active'] ? 'checked' : ''; ?>>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="checkbox" name="is_admin" <?php echo $row['is_admin'] ? 'checked' : ''; ?>>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="password" name="password" placeholder="New password (optional)">
                            <input type="submit" name="edit_user" value="Save">
                            <a href="mu.php">Cancel</a>
                            </form>
                        </td>
                    </tr>
                    <?php
                } else {
                    // Normal display row
                    $active = $row['active'] ? 'Yes' : 'No';
                    $is_admin = $row['is_admin'] ? 'Yes' : 'No';
                    ?>
                    <tr>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['id']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['username']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['firstname']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['lastname']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['email']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><?php echo $active; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><?php echo $is_admin; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><a href="mu.php?edit=<?php echo $row['id']; ?>">Edit</a></td>
                    </tr>
                    <?php
                }
            }
            // Add new user row if clicked
            if (isset($_GET['add'])) {
                ?>
                <tr>
                    <td style="border:1px solid black;padding:2px;text-align:left;">New</td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <form method="post" style="display:inline;">
                            <input type="text" name="username" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <input type="text" name="firstname" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <input type="text" name="lastname" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <input type="email" name="email" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="checkbox" name="active" checked>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="checkbox" name="is_admin">
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="password" name="password" required>
                        <input type="submit" name="add_user" value="Save">
                        <a href="mu.php">Cancel</a>
                        </form>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php if (!isset($_GET['add']) && !isset($_GET['edit'])) { ?>
            <p><button onclick="window.location.href='mu.php?add=1'">Add User</button></p>
        <?php } ?>
    </body>
    </html>
    <?php
}

$conn->close();
?>
