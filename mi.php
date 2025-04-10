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
    
    $query = "UPDATE items SET itemstype_id = ?, manufacturer = ?, name = ?, description = ?, unittype_id = ?, quantity = ?, active = ?";
    $params = [$itemstype_id, $manufacturer, $name, $description, $unittype_id, $quantity, $active];
    $types = "issssii";
    
    if (!empty($_FILES['picture']['name'])) {
        $upload_dir = '/home/tdawggcat/public_html/bt/images/';
        $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
        $picture = 'item_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_dir . $picture)) {
            $query .= ", picture = ?";
            $params[] = $picture;
            $types .= "s";
        } else {
            $error = "Failed to upload new picture.";
        }
    }
    
    $query .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    
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
        
        <h2>Items</h2>
        <table style="border-collapse:collapse;padding:2px;margin:0;">
            <tr style="background-color:#e0e0e0;">
                <th style="border:1px solid black;padding:2px;text-align:center;">Picture</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Type</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Manufacturer</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Name</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Description</th>
                <th style="border:1px solid black;padding:2px;text-align:left;">Unit Type</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Qty</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Active</th>
                <th style="border:1px solid black;padding:2px;text-align:center;">Action</th>
            </tr>
            <?php
            $result = $conn->query("SELECT i.id, i.manufacturer, i.name, i.description, i.picture, i.unittype_id, i.quantity, i.active, t.type, u.unittype 
                                   FROM items i 
                                   JOIN itemtypes t ON i.itemstype_id = t.id 
                                   JOIN unittypes u ON i.unittype_id = u.id 
                                   ORDER BY t.sort, i.name");
            while ($row = $result->fetch_assoc()) {
                if (isset($_GET['edit']) && $_GET['edit'] == $row['id']) {
                    // Edit mode for this row
                    ?>
                    <tr>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <form method="post" enctype="multipart/form-data" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <?php if ($row['picture']) { ?>
                                    <img src="/bt/images/<?php echo $row['picture']; ?>" style="max-width:50px;cursor:pointer;" onclick="document.getElementById('picture_<?php echo $row['id']; ?>').click();">
                                <?php } else { ?>
                                    <span style="cursor:pointer;" onclick="document.getElementById('picture_<?php echo $row['id']; ?>').click();">[Upload]</span>
                                <?php } ?>
                                <input type="file" name="picture" id="picture_<?php echo $row['id']; ?>" style="display:none;">
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <select name="itemstype_id" required>
                                <?php
                                $types_result = $conn->query("SELECT id, type FROM itemtypes WHERE active = 1 ORDER BY sort");
                                while ($type_row = $types_result->fetch_assoc()) {
                                    $selected = $type_row['id'] == $row['itemstype_id'] ? 'selected' : '';
                                    echo "<option value='{$type_row['id']}' $selected>{$type_row['type']}</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <input type="text" name="manufacturer" value="<?php echo htmlspecialchars($row['manufacturer'] ?? ''); ?>">
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <textarea name="description"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:left;">
                            <select name="unittype_id" required>
                                <?php
                                $units_result = $conn->query("SELECT id, unittype FROM unittypes WHERE active = 1 ORDER BY unittype");
                                while ($unit_row = $units_result->fetch_assoc()) {
                                    $selected = $unit_row['id'] == $row['unittype_id'] ? 'selected' : '';
                                    echo "<option value='{$unit_row['id']}' $selected>{$unit_row['unittype']}</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="number" name="quantity" value="<?php echo htmlspecialchars($row['quantity']); ?>" min="0" required>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="checkbox" name="active" <?php echo $row['active'] ? 'checked' : ''; ?>>
                        </td>
                        <td style="border:1px solid black;padding:2px;text-align:center;">
                            <input type="submit" name="edit_item" value="Save">
                            <a href="mi.php">Cancel</a>
                            </form>
                        </td>
                    </tr>
                    <?php
                } else {
                    // Normal display row
                    $pic = $row['picture'] ? "<img src='/bt/images/{$row['picture']}' style='max-width:50px;'>" : "";
                    $desc = substr($row['description'] ?? '', 0, 50) . (strlen($row['description'] ?? '') > 50 ? '...' : '');
                    $active = $row['active'] ? 'Yes' : 'No';
                    ?>
                    <tr>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><?php echo $pic; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['type']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['manufacturer']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['name']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $desc; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:left;"><?php echo $row['unittype']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><?php echo $row['quantity']; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><?php echo $active; ?></td>
                        <td style="border:1px solid black;padding:2px;text-align:center;"><a href="mi.php?edit=<?php echo $row['id']; ?>">Edit</a></td>
                    </tr>
                    <?php
                }
            }
            // Add new item row if clicked
            if (isset($_GET['add'])) {
                ?>
                <tr>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <form method="post" enctype="multipart/form-data" style="display:inline;">
                            <span style="cursor:pointer;" onclick="document.getElementById('picture_new').click();">[Upload]</span>
                            <input type="file" name="picture" id="picture_new" style="display:none;">
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <select name="itemstype_id" required>
                            <?php
                            $types_result = $conn->query("SELECT id, type FROM itemtypes WHERE active = 1 ORDER BY sort");
                            while ($type_row = $types_result->fetch_assoc()) {
                                echo "<option value='{$type_row['id']}'>{$type_row['type']}</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <input type="text" name="manufacturer">
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <input type="text" name="name" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <textarea name="description"></textarea>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:left;">
                        <select name="unittype_id" required>
                            <?php
                            $units_result = $conn->query("SELECT id, unittype FROM unittypes WHERE active = 1 ORDER BY unittype");
                            while ($unit_row = $units_result->fetch_assoc()) {
                                echo "<option value='{$unit_row['id']}'>{$unit_row['unittype']}</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="number" name="quantity" min="0" required>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="checkbox" name="active" checked>
                    </td>
                    <td style="border:1px solid black;padding:2px;text-align:center;">
                        <input type="submit" name="add_item" value="Save">
                        <a href="mi.php">Cancel</a>
                        </form>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php if (!isset($_GET['add']) && !isset($_GET['edit'])) { ?>
            <p><button onclick="window.location.href='mi.php?add=1'">Add Item</button></p>
        <?php } ?>
    </body>
    </html>
    <?php
}

$conn->close();
?>
