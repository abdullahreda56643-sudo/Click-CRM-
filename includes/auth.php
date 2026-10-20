<?php
// Authentication System
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Include functions for log_audit function
require_once __DIR__ . '/functions.php';

// Function to authenticate user
function authenticate_user($username, $password) {
    try {
        $db = get_db();
        
        $sql = "SELECT id, username, email, password, full_name, role, is_active 
                FROM users 
                WHERE username = ? OR email = ? 
                LIMIT 1";
        
        $result = $db->fetch($sql, [$username, $username]);
        
        if ($result && $result['is_active']) {
            if (password_verify($password, $result['password'])) {
                // Password is correct, set session
                $_SESSION['user_id'] = $result['id'];
                $_SESSION['username'] = $result['username'];
                $_SESSION['email'] = $result['email'];
                $_SESSION['full_name'] = $result['full_name'];
                $_SESSION['user_role'] = $result['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Log successful login
                log_audit('login', 'users', $result['id'], 'User logged in: ' . $result['username']);
                
                return true;
            }
        }
        
        // Log failed login attempt
        log_audit('login_failed', 'users', null, 'Failed login attempt for: ' . $username);
        
        return false;
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

// Function to logout user
function logout_user() {
    if (is_logged_in()) {
        $user_id = get_current_user_id();
        $username = $_SESSION['username'] ?? 'Unknown';
        
        // Log logout
        log_audit('logout', 'users', $user_id, 'User logged out: ' . $username);
        
        // Destroy session
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
}

// Function to check if session is still valid
function is_session_valid() {
    if (!is_logged_in()) {
        return false;
    }
    
    // Check session timeout
    $last_activity = $_SESSION['last_activity'] ?? 0;
    $timeout = SESSION_LIFETIME;
    
    if (time() - $last_activity > $timeout) {
        logout_user();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

// Function to regenerate session ID
function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Function to create a new user
function create_user($username, $email, $password, $full_name, $role = 'user') {
    try {
        $db = get_db();
        
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $existing = $db->fetch($check_sql, [$username, $email]);
        
        if ($existing) {
            return ['success' => false, 'message' => 'اسم المستخدم أو البريد الإلكتروني موجود بالفعل'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $user_data = [
            'username' => $username,
            'email' => $email,
            'password' => $hashed_password,
            'full_name' => $full_name,
            'role' => $role,
            'is_active' => 1
        ];
        
        $user_id = $db->insert('users', $user_data);
        
        if ($user_id) {
            log_audit('user_created', 'users', $user_id, "New user created: $username");
            return ['success' => true, 'user_id' => $user_id];
        }
        
        return ['success' => false, 'message' => 'فشل في إنشاء المستخدم'];
        
    } catch (Exception $e) {
        error_log("Create user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء المستخدم'];
    }
}

// Function to update user password
function update_user_password($user_id, $current_password, $new_password) {
    try {
        $db = get_db();
        
        // Get current password
        $sql = "SELECT password FROM users WHERE id = ?";
        $result = $db->fetch($sql, [$user_id]);
        
        if (!$result) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }
        
        // Verify current password
        if (!password_verify($current_password, $result['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
        }
        
        // Hash new password
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $updated = $db->update('users', 
            ['password' => $new_hashed_password], 
            'id = ?', 
            [$user_id]
        );
        
        if ($updated) {
            log_audit('password_changed', 'users', $user_id, 'User password changed');
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'فشل في تحديث كلمة المرور'];
        
    } catch (Exception $e) {
        error_log("Update password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث كلمة المرور'];
    }
}

// Function to get user info
function get_user_info($user_id) {
    try {
        $db = get_db();
        
        $sql = "SELECT id, username, email, full_name, role, is_active, created_at, updated_at 
                FROM users 
                WHERE id = ?";
        
        return $db->fetch($sql, [$user_id]);
        
    } catch (Exception $e) {
        error_log("Get user info error: " . $e->getMessage());
        return null;
    }
}

// Function to get all users (for admin)
function get_all_users() {
    try {
        $db = get_db();
        
        $sql = "SELECT id, username, email, full_name, role, is_active, created_at 
                FROM users 
                ORDER BY created_at DESC";
        
        return $db->fetchAll($sql);
        
    } catch (Exception $e) {
        error_log("Get all users error: " . $e->getMessage());
        return [];
    }
}

// Function to update user
function update_user($user_id, $data) {
    try {
        $db = get_db();
        
        // Remove sensitive fields
        unset($data['password']);
        unset($data['id']);
        
        $updated = $db->update('users', $data, 'id = ?', [$user_id]);
        
        if ($updated) {
            log_audit('user_updated', 'users', $user_id, 'User information updated');
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        return false;
    }
}

// Function to delete user
function delete_user($user_id) {
    try {
        $db = get_db();
        
        // Don't allow deletion of currently logged in user
        if ($user_id == get_current_user_id()) {
            return ['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي'];
        }
        
        // Get user info before deletion
        $user_info = get_user_info($user_id);
        
        $deleted = $db->delete('users', 'id = ?', [$user_id]);
        
        if ($deleted) {
            log_audit('user_deleted', 'users', $user_id, "User deleted: " . $user_info['username']);
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'فشل في حذف المستخدم'];
        
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'حدث خطأ أثناء حذف المستخدم'];
    }
}

// Function to remember login (cookie)
function remember_login($user_id) {
    $token = bin2hex(random_bytes(32));
    $selector = bin2hex(random_bytes(16));
    $expires = time() + (30 * 24 * 60 * 60); // 30 days
    
    try {
        $db = get_db();
        
        // Store token in database (you might want to create a separate table for this)
        $token_data = [
            'user_id' => $user_id,
            'selector' => $selector,
            'token' => hash('sha256', $token),
            'expires' => date('Y-m-d H:i:s', $expires)
        ];
        
        // For now, we'll use a simple approach with sessions
        $_SESSION['remember_token'] = $token;
        $_SESSION['remember_expires'] = $expires;
        
        // Set cookie
        setcookie('remember_me', $selector . ':' . $token, $expires, '/', '', false, true);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Remember login error: " . $e->getMessage());
        return false;
    }
}

// Function to check remember me cookie
function check_remember_login() {
    if (!isset($_COOKIE['remember_me'])) {
        return false;
    }
    
    list($selector, $token) = explode(':', $_COOKIE['remember_me'], 2);
    
    if (!isset($_SESSION['remember_token']) || !isset($_SESSION['remember_expires'])) {
        return false;
    }
    
    if (time() > $_SESSION['remember_expires']) {
        return false;
    }
    
    if (hash_equals($_SESSION['remember_token'], $token)) {
        // Token is valid, refresh session
        return true;
    }
    
    return false;
}

// Validate session on every page load
if (is_logged_in()) {
    is_session_valid();
}
?>