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
        
        <h2>Add New User</h2>
        <form method="post">
            <table style="border:0;padding:2px;margin:0;">
                <tr><td style="padding:2px;">Username:</td><td style="padding:2px;"><input type="text" name="username" required></td></tr>
                <tr><td style="padding:2px;">First Name:</td><td style="padding:2px;"><input type="text" name="firstname" required></td></tr>
                <tr><td style="padding:2px;">Last Name:</td><td style="padding:2px;"><input type="text" name="lastname" required></td></tr>
                <tr><td style="padding:2px;">Email:</td><td style="padding:2px;"><input type="email" name="email" required></td></tr>
                <tr><td style="padding:2px;">Password:</td><td style="padding:2px;"><input type="password" name="password" required></td></tr>
                <tr><td style="padding:2px;">Active:</td><td style="padding:2px;"><input type="checkbox" name="active" checked></td></tr>
                <tr><td style="padding:2px;">Admin:</td><td style="padding:2px;"><input type="checkbox" name="is_admin"></td></tr>
            </table>
            <input type="submit" name="add_user" value="Add User">
        </form>
        
        <h2>Users</h2>
        <?php
        $result = $conn->query("SELECT id, username, firstname, lastname, email, active, is_admin 
                               FROM users ORDER BY id");
        if ($result->num_rows > 0) {
            echo "<table style='border-collapse:collapse;padding:2px;margin:0;'>";
            echo "<tr style='background-color:#e0e0e0;'>
                    <th style='border:1px solid black;padding:2px;'>ID</th>
                    <th style='border:1px solid black;padding:2px;'>Username</th>
                    <th style='border:1px solid black;padding:2px;'>First</th>
                    <th style='border:1px solid black;padding:2px;'>Last</th>
                    <th style='border:1px solid black;padding:2px;'>Email</th>
                    <th style='border:1px solid black;padding:2px;'>Active</th>
                    <th style='border:1px solid black;padding:2px;'>Admin</th>
                    <th style='border:1px solid black;padding:2px;'>Action</th>
                  </tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td style='border:1px solid black;padding:2px;'>{$row['id']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['username']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['firstname']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['lastname']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['email']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['active']}</td>
                    <td style='border:1px solid black;padding:2px;'>{$row['is_admin']}</td>
                    <td style='border:1px solid black;padding:2px;'><a href='mu.php?edit={$row['id']}'>Edit</a></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found.</p>";
        }
        
        if (isset($_GET['edit'])) {
            $edit_id = $_GET['edit'];
            $stmt = $conn->prepare("SELECT username, firstname, lastname, email, active, is_admin 
                                   FROM users WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            if ($user) {
                ?>
                <h2>Edit User (ID: <?php echo $edit_id; ?>)</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                    <table style="border:0;padding:2px;margin:0;">
                        <tr><td style="padding:2px;">Username:</td><td style="padding:2px;"><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></td></tr>
                        <tr><td style="padding:2px;">First Name:</td><td style="padding:2px;"><input type="text" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required></td></tr>
                        <tr><td style="padding:2px;">Last Name:</td><td style="padding:2px;"><input type="text" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required></td></tr>
                        <tr><td style="padding:2px;">Email:</td><td style="padding:2px;"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></td></tr>
                        <tr><td style="padding:2px;">Password:</td><td style="padding:2px;"><input type="password" name="password" placeholder="Leave blank to keep unchanged"></td></tr>
                        <tr><td style="padding:2px;">Active:</td><td style="padding:2px;"><input type="checkbox" name="active" <?php echo $user['active'] ? 'checked' : ''; ?>></td></tr>
                        <tr><td style="padding:2px;">Admin:</td><td style="padding:2px;"><input type="checkbox" name="is_admin" <?php echo $user['is_admin'] ? 'checked' : ''; ?>></td></tr>
                    </table>
                    <input type="submit" name="edit_user" value="Update User">
                </form>
                <?php
            }
        }
        ?>
    </body>
    </html>
    <?php
}

$conn->close();
?>
