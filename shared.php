<?php
// Start the session
session_start();

// Read MySQL credentials
$cred_file = '/home/tdawggcat/.bt_mysql_user';
if (!file_exists($cred_file)) {
    die("Error: MySQL credentials file not found.");
}
$cred_data = trim(file_get_contents($cred_file));
list($db_user, $db_pass) = explode(':', $cred_data, 2);

// Database connection
$db_host = 'localhost';
$db_name = 'tdawggcat_bt';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set session cookie parameters: 60 days, HTTPS-only
$lifetime = 60 * 24 * 60 * 60; // 60 days in seconds
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Generate a secure session ID
function generate_session_id() {
    return bin2hex(random_bytes(32));
}

// Check if user is logged in, return user_id and is_admin status
function check_login($conn) {
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }
    $session_id = $_COOKIE[session_name()];
    
    $stmt = $conn->prepare("SELECT s.user_id, s.last_active, s.expires_at, u.active, u.is_admin 
                            FROM sessions s 
                            JOIN users u ON s.user_id = u.id 
                            WHERE s.session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
    
    if (!$session || $session['expires_at'] < date('Y-m-d H:i:s') || !$session['active']) {
        return false;
    }
    
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE sessions SET last_active = ? WHERE session_id = ?");
    $stmt->bind_param("ss", $now, $session_id);
    $stmt->execute();
    $stmt->close();
    
    $last_active = strtotime($session['last_active']);
    global $lifetime;
    if (time() - $last_active > 24 * 60 * 60) {
        $new_session_id = generate_session_id();
        $stmt = $conn->prepare("UPDATE sessions SET session_id = ? WHERE session_id = ?");
        $stmt->bind_param("ss", $new_session_id, $session_id);
        $stmt->execute();
        $stmt->close();
        setcookie(session_name(), $new_session_id, time() + $lifetime, '/', '', true, true);
    }
    
    return ['user_id' => $session['user_id'], 'is_admin' => $session['is_admin']];
}

// Handle login
function handle_login($conn) {
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT id, password, active FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password']) && $user['active']) {
            $session_id = generate_session_id();
            $now = date('Y-m-d H:i:s');
            global $lifetime;
            $expires = date('Y-m-d H:i:s', time() + $lifetime);
            
            $stmt = $conn->prepare("INSERT INTO sessions (session_id, user_id, created_at, last_active, expires_at) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sisss", $session_id, $user['id'], $now, $now, $expires);
            $stmt->execute();
            $stmt->close();
            
            setcookie(session_name(), $session_id, time() + $lifetime, '/', '', true, true);
            header("Location: bt.php");
            exit;
        }
        return "Invalid login or inactive user.";
    }
    return null;
}

// Handle logout
function handle_logout($conn) {
    if (isset($_GET['logout'])) {
        if (isset($_COOKIE[session_name()])) {
            $session_id = $_COOKIE[session_name()];
            $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ?");
            $stmt->bind_param("s", $session_id);
            $stmt->execute();
            $stmt->close();
            setcookie(session_name(), '', time() - 3600, '/', '', true, true);
        }
        header("Location: bt.php");
        exit;
    }
}
?>
