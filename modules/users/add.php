<?php
// Add user
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('users', 'add');

 $page_title = 'إضافة مستخدم - ' . APP_NAME;
 $current_module = 'users';
 $page_header = 'إضافة مستخدم جديد';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/users/');
    }

    $username = clean_input($_POST['username'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $full_name = clean_input($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = clean_input($_POST['role'] ?? 'user');

    if (empty($username) || empty($password) || empty($full_name)) {
        $_SESSION['error'] = 'الحقول الإلزامية مفقودة.';
        redirect('modules/users/add.php');
    }

    $res = create_user($username, $email, $password, $full_name, $role);
    if ($res['success']) {
        $_SESSION['success'] = 'تم إنشاء المستخدم بنجاح.';
        redirect('modules/users/');
    } else {
        $_SESSION['error'] = $res['message'] ?? 'فشل إنشاء المستخدم.';
        redirect('modules/users/add.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';

// Available roles from permissions config
$available_roles = array_keys($permissions ?? []);

?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl mx-auto">
    <div class="mb-6 border-b pb-4">
        <h3 class="text-2xl font-bold text-[#1e4a76]"><?php echo $page_header; ?></h3>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold">الاسم الكامل <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" class="w-full p-3 rounded-xl border border-gray-200" required>
            </div>
            <div>
                <label class="block text-sm font-semibold">اسم المستخدم <span class="text-rose-500">*</span></label>
                <input type="text" name="username" class="w-full p-3 rounded-xl border border-gray-200" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold">البريد الإلكتروني</label>
                <input type="email" name="email" class="w-full p-3 rounded-xl border border-gray-200">
            </div>
            <div>
                <label class="block text-sm font-semibold">كلمة المرور <span class="text-rose-500">*</span></label>
                <input type="password" name="password" class="w-full p-3 rounded-xl border border-gray-200" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-semibold">الدور</label>
                <select name="role" class="w-full p-3 rounded-xl border border-gray-200">
                    <?php foreach ($available_roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold">نشط</label>
                <select name="is_active" class="w-full p-3 rounded-xl border border-gray-200">
                    <option value="1">نعم</option>
                    <option value="0">لا</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="index.php" class="px-6 py-2 bg-gray-100 rounded-xl">إلغاء</a>
            <button type="submit" class="px-6 py-2 bg-[#1e4a76] text-white rounded-xl">حفظ المستخدم</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
