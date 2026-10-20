<?php
// Edit user
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('users', 'edit');

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) { $_SESSION['error']='المستخدم غير موجود.'; redirect('modules/users/'); }

$user = get_user_info($user_id);
if (!$user) { $_SESSION['error']='المستخدم غير موجود.'; redirect('modules/users/'); }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/users/');
    }

    $full_name = clean_input($_POST['full_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $role = clean_input($_POST['role'] ?? $user['role']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    $new_password = $_POST['new_password'] ?? '';

    if (empty($full_name)) { $_SESSION['error']='الاسم الكامل مطلوب.'; redirect('modules/users/edit.php?id='.$user_id); }

    $data = [ 'full_name'=>$full_name, 'email'=>$email, 'role'=>$role, 'is_active'=>$is_active ];

    $ok = update_user($user_id, $data);
    if ($ok && !empty($new_password)) {
        // update password directly
        $db = get_db();
        $db->update('users', ['password'=>password_hash($new_password, PASSWORD_DEFAULT)], 'id = ?', [$user_id]);
        log_audit('password_changed', 'users', $user_id, 'Password changed by admin');
    }

    if ($ok) {
        $_SESSION['success']='تم تحديث المستخدم.';
        redirect('modules/users/');
    } else {
        $_SESSION['error']='فشل تحديث المستخدم.';
        redirect('modules/users/edit.php?id='.$user_id);
    }
}

require_once __DIR__ . '/../../templates/header.php';
$available_roles = array_keys($permissions ?? []);
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl mx-auto">
    <div class="mb-6 border-b pb-4">
        <h3 class="text-2xl font-bold text-[#1e4a76]">تعديل المستخدم</h3>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold">الاسم الكامل</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="w-full p-3 rounded-xl border border-gray-200" required>
            </div>
            <div>
                <label class="block text-sm font-semibold">اسم المستخدم</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full p-3 rounded-xl border border-gray-200" disabled>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold">البريد الإلكتروني</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full p-3 rounded-xl border border-gray-200">
            </div>
            <div>
                <label class="block text-sm font-semibold">كلمة مرور جديدة (اختياري)</label>
                <input type="password" name="new_password" class="w-full p-3 rounded-xl border border-gray-200">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-semibold">الدور</label>
                <select name="role" class="w-full p-3 rounded-xl border border-gray-200">
                    <?php foreach ($available_roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $user['role']==$r? 'selected':''; ?>><?php echo htmlspecialchars($r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold">نشط</label>
                <select name="is_active" class="w-full p-3 rounded-xl border border-gray-200">
                    <option value="1" <?php echo $user['is_active']? 'selected':''; ?>>نعم</option>
                    <option value="0" <?php echo !$user['is_active']? 'selected':''; ?>>لا</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="index.php" class="px-6 py-2 bg-gray-100 rounded-xl">إلغاء</a>
            <button type="submit" class="px-6 py-2 bg-[#1e4a76] text-white rounded-xl">حفظ التغييرات</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
