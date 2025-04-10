<?php
require_once 'shared.php';

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
        move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $picture);
    }
    
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
    
    $query = "UPDATE items SET itemstype_id = ?, manufacturer = ?, name = ?, description = ?, unittype_id = ?, quantity = ?, active = ?";
    $params = [$itemstype_id, $manufacturer, $name, $description, $unittype_id, $quantity, $active];
    $types = "issssiii";
    
    if (!empty($_FILES['picture']['name'])) {
        $upload_dir = '/home/tdawggcat/public_html/bt/images/';
        $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        $picture = 'item_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $picture);
        $query .= ", picture = ?";
        $params[] = $picture;
        $types .= "s";
    }
    $query .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $message = "Item updated successfully!";
    } else {
        $error = "Error updating item: " . $conn->error;
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
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Picture</th>
                    <th style='border:1px solid black;padding:2px;text-align:right;'>ID</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Type</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Manuf</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Name</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Description</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Unit Type</th>
                    <th style='border:1px solid black;padding:2px;text-align:right;'>Qty</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Active</th>
                    <th style='border:1px solid black;padding:2px;text-align:left;'>Action</th>
                  </tr>";
            while ($row = $result->fetch_assoc()) {
                $pic = $row['picture'] ? "<img src='/bt/images/{$row['picture']}' style='max-width:50px;'>" : "";
                $desc = substr($row['description'] ?? '', 0, 50) . (strlen($row['description'] ?? '') > 50 ? '...' : '');
                $active = $row['active'] ? 'Yes' : 'No';
                echo "<tr>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>$pic</td>
                    <td style='border:1px solid black;padding:2px;text-align:right;'>{$row['id']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>{$row['type']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>{$row['manufacturer']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>{$row['name']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>$desc</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>{$row['unittype']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:right;'>{$row['quantity']}</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'>$active</td>
                    <td style='border:1px solid black;padding:2px;text-align:left;'><a href='mi.php?edit={$row['id']}'>Edit</a></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No items found.</p>";
        }
        
        if (isset($_GET['edit'])) {
            $edit_id = $_GET['edit'];
            $stmt = $conn->prepare("SELECT itemstype_id, manufacturer, name, description, picture, unittype_id, quantity, active 
                                   FROM items WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();
            if ($item) {
                ?>
                <h2>Edit Item (ID: <?php echo $edit_id; ?>)</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                    <table style="border:0;padding:2px;margin:0;">
                        <tr><td style="padding:2px;">Type:</td><td style="padding:2px;">
                            <select name="itemstype_id" required>
                                <?php
                                $result = $conn->query("SELECT id, type FROM itemtypes WHERE active = 1 ORDER BY sort");
                                while ($row = $result->fetch_assoc()) {
                                    $selected = $row['id'] == $item['itemstype_id'] ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>{$row['type']}</option>";
                                }
                                ?>
                            </select>
                        </td></tr>
                        <tr><td style="padding:2px;">Manufacturer:</td><td style="padding:2px;"><input type="text" name="manufacturer" value="<?php echo htmlspecialchars($item['manufacturer'] ?? ''); ?>"></td></tr>
                        <tr><td style="padding:2px;">Name:</td><td style="padding:2px;"><input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required></td></tr>
                        <tr><td style="padding:2px;">Description:</td><td style="padding:2px;"><textarea name="description"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea></td></tr>
                        <tr><td style="padding:2px;">Unit Type:</td><td style="padding:2px;">
                            <select name="unittype_id" required>
                                <?php
                                $result = $conn->query("SELECT id, unittype FROM unittypes WHERE active = 1 ORDER BY unittype");
                                while ($row = $result->fetch_assoc()) {
                                    $selected = $row['id'] == $item['unittype_id'] ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>{$row['unittype']}</option>";
                                }
                                ?>
                            </select>
                        </td></tr>
                        <tr><td style="padding:2px;">Qty:</td><td style="padding:2px;"><input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" required></td></tr>
                        <tr><td style="padding:2px;">Active:</td><td style="padding:2px;"><input type="checkbox" name="active" <?php echo $item['active'] ? 'checked' : ''; ?>></td></tr>
                        <tr><td style="padding:2px;">Picture:</td><td style="padding:2px;">
                            <?php if ($item['picture']) echo "<img src='/bt/images/{$item['picture']}' style='max-width:50px;'>"; ?>
                            <input type="file" name="picture">
                        </td></tr>
                    </table>
                    <input type="submit" name="edit_item" value="Update Item">
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
