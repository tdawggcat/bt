<?php
require_once 'shared.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle logout
handle_logout($conn);

// Handle add item
if (isset($_POST['add_item'])) {
    $itemstype_id = $_POST['itemstype_id'];
    $manufacturer = $_POST['manufacturer'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $unittype_id = $_POST['unittype_id'];
    $quantity = $_POST['quantity'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    $picture = null;
    if (!empty($_FILES['picture']['name'])) {
        $upload_dir = '/home/tdawggcat/public_html/bt/images/';
        $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        $picture = 'item_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $picture)) {
            $error = "Failed to upload picture.";
        }
    }
    
    if (!isset($error)) {
        $stmt = $conn->prepare("INSERT INTO items (itemstype_id, manufacturer, name, description, picture, unittype_id, quantity, active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiii", $itemstype_id, $manufacturer, $name, $description, $picture, $unittype_id, $quantity, $active);
        if ($stmt->execute()) {
            $message = "Item added successfully!";
        } else {
            $error = "Error adding item: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle edit item
if (isset($_POST['edit_item'])) {
    $id = $_POST['id'];
    $itemstype_id = $_POST['itemstype_id'];
    $manufacturer = $_POST['manufacturer'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $unittype_id = $_POST['unittype_id'];
    $quantity = $_POST['quantity'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    $query = "UPDATE items SET itemstype_id = ?, manufacturer = ?, name = ?, description = ?, unittype_id = ?, quantity = ?, active = ?, id = ?";
    $params = [$itemstype_id, $manufacturer, $name, $description, $unittype_id, $quantity, $active, $id];
    $types = "issssiii";
    
    if (!empty($_FILES['picture']['name'])) {
        $upload_dir = '/home/tdawggcat/public_html/bt/images/';
        $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        $picture = 'item_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $picture)) {
            $query = "UPDATE items SET itemstype_id = ?, manufacturer = ?, name = ?, description = ?, unittype_id = ?, quantity = ?, active = ?, picture = ?, id = ?";
            $params = [$itemstype_id, $manufacturer, $name, $description, $unittype_id, $quantity, $active, $picture, $id];
            $types = "issssiiisi";
        } else {
            $error = "Failed to upload new picture.";
        }
    }
    
    if (!isset($error)) {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = "Item updated successfully!";
            } else {
                $error = "Error updating item: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Check login status (must be admin)
$user = check_login($conn);
if ($user === false || !$user['is_admin']) {
    $error = handle_login($conn) ?? "Admin access required.";
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Login - Manage Items</title></head>
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
    <head><title>Manage Items</title></head>
    <body>
        <h1>Manage Items</h1>
        <p><a href="bt.php">Main Menu</a> | <a href="mi.php?logout=1">Logout</a></p>
        <?php 
        if (isset($message)) echo "<p style='color:green;'>$message</p>";
        if (isset($error)) echo "<p style='color:red;'>$error</p>"; 
        ?>
        
        <h2>Add New Item</h2>
        <form method="post" enctype="multipart/form-data">
            <table style="border:0;padding:2px;margin:0;">
                <tr><td style="padding:2px;">Type:</td><td style="padding:2px;">
                    <select name="itemstype_id" required>
                        <?php
                        $result = $conn->query("SELECT id, type FROM itemtypes WHERE active = 1 ORDER BY sort");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>{$row['type']}</option>";
                        }
                        ?>
                    </select>
                </td></tr>
                <tr><td style="padding:2px;">Manufacturer:</td><td style="padding:2px;"><input type="text" name="manufacturer"></td></tr>
                <tr><td style="padding:2px;">Name:</td><td style="padding:2px;"><input type="text" name="name" required></td></tr>
                <tr><td style="padding:2px;">Description:</td><td style="padding:2px;"><textarea name="description"></textarea></td></tr>
                <tr><td style="padding:2px;">Unit Type:</td><td style="padding:2px;">
                    <select name="unittype_id" required>
                        <?php
                        $result = $conn->query("SELECT id, unittype FROM unittypes WHERE active = 1 ORDER BY unittype");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>{$row['unittype']}</option>";
                        }
                        ?>
                    </select>
                </td></tr>
                <tr><td style="padding:2px;">Qty:</td><td style="padding:2px;"><input type="number" name="quantity" min="0" required></td></tr>
                <tr><td style="padding:2px;">Active:</td><td style="padding:2px;"><input type="checkbox" name="active" checked></td></tr>
                <tr><td style="padding:2px;">Picture:</td><td style="padding:2px;"><input type="file" name="picture"></td></tr>
            </table>
            <input type="submit" name="add_item" value="Add Item">
        </form>
        
        <h2>Items</h2>
        <?php
        $result = $conn->query("SELECT i.id, i.manufacturer, i.name, i.description, i.picture, i.unittype_id, i.quantity, i.active, t.type, u.unittype 
                               FROM items i 
                               JOIN itemtypes t ON i.itemstype_id = t.id 
                               JOIN unittypes u ON i.unittype_id = u.id 
                               ORDER BY t.sort, i.name");
        if ($result->num_rows > 0) {
            echo "<table style='border-collapse:collapse;padding:2px;margin:0;'>";
            echo "<tr style='background-color:#e0e0e0;'>
                    <th style='border:1px solid black;padding:2px;text
