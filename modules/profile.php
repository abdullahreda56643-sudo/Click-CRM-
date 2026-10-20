<?php
// User profile page - view and edit profile + change password
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$user_id = get_current_user_id();
$user = get_user_info($user_id);

if (!$user) {
    $_SESSION['error'] = 'المستخدم غير موجود.';
    redirect('modules/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/profile.php');
    }

    if ($action === 'update_info') {
        $full_name = clean_input($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($full_name === '' || $email === '') {
            $_SESSION['error'] = 'الاسم الكامل والبريد الإلكتروني مطلوبان.';
            redirect('modules/profile.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'البريد الإلكتروني غير صالح.';
            redirect('modules/profile.php');
        }

        $data = [
            'full_name' => $full_name,
            'email' => $email
        ];

        if (update_user($user_id, $data)) {
            // update session info
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['success'] = 'تم تحديث الملف الشخصي بنجاح.';
        } else {
            $_SESSION['error'] = 'فشل تحديث الملف الشخصي.';
        }

        // Handle avatar upload if provided
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/jpg' => 'jpg'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 2 * 1024 * 1024) {
                    $_SESSION['warning'] = 'حجم الصورة أكبر من 2 ميغابايت.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!isset($allowed[$mime])) {
                        $_SESSION['warning'] = 'نوع الملف غير مسموح.';
                    } else {
                        $ext = $allowed[$mime];
                        $uploadDir = __DIR__ . '/../uploads/avatars';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $target = $uploadDir . '/user_' . $user_id . '.' . $ext;
                        // remove other existing avatar files for this user
                        foreach (['png','jpg','jpeg','gif'] as $e) {
                            $old = $uploadDir . '/user_' . $user_id . '.' . $e;
                            if (file_exists($old) && $old !== $target) @unlink($old);
                        }
                        if (move_uploaded_file($file['tmp_name'], $target)) {
                            $_SESSION['success'] = 'تم تحميل صورة الملف الشخصي بنجاح.';
                        } else {
                            $_SESSION['warning'] = 'فشل حفظ الصورة.';
                        }
                    }
                }
            } else {
                $_SESSION['warning'] = 'خطأ في رفع الملف.';
            }
        }

        redirect('modules/profile.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $current === '') {
            $_SESSION['error'] = 'يرجى إدخال كلمة المرور الحالية والجديدة.';
            redirect('modules/profile.php');
        }

        if ($new !== $confirm) {
            $_SESSION['error'] = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
            redirect('modules/profile.php');
        }

        $res = update_user_password($user_id, $current, $new);
        if (is_array($res)) {
            if (!empty($res['success'])) {
                $_SESSION['success'] = 'تم تغيير كلمة المرور بنجاح.';
            } else {
                $_SESSION['error'] = $res['message'] ?? 'فشل تغيير كلمة المرور.';
            }
        } else {
            // backward compat if returns boolean
            $_SESSION['success'] = $res ? 'تم تغيير كلمة المرور بنجاح.' : 'فشل تغيير كلمة المرور.';
        }

        redirect('modules/profile.php');
    }
}

$page_title = 'الملف الشخصي - ' . APP_NAME;
$current_module = 'profile';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="max-w-3xl mx-auto mt-24 mb-12">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-2xl font-bold text-[#1e4a76] mb-4">الملف الشخصي</h2>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="mb-6">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />
            <input type="hidden" name="action" value="update_info" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-sm font-semibold text-gray-600">الاسم الكامل</label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">البريد الإلكتروني</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
                </div>
            </div>

            <div class="mb-4">
                <label class="text-sm font-semibold text-gray-600 block mb-2">صورة الملف الشخصي</label>
                <div class="flex items-center gap-4">
                    <div>
                        <?php
                        $avatarPath = __DIR__ . '/../uploads/avatars/user_' . $user_id;
                        $avatarUrl = '';
                        foreach (['png','jpg','jpeg','gif'] as $ext) {
                            if (file_exists($avatarPath . '.' . $ext)) {
                                $avatarUrl = base_url('uploads/avatars/user_' . $user_id . '.' . $ext);
                                // Add file modification time to prevent caching
                                $avatarUrl .= '?v=' . filemtime($avatarPath . '.' . $ext);
                                break;
                            }
                        }
                        if ($avatarUrl): ?>
                            <img src="<?php echo $avatarUrl; ?>" class="w-16 h-16 rounded-xl object-cover border" alt="avatar">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400"><?php echo strtoupper(substr($user['full_name'] ?? 'U',0,1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="file" name="avatar" accept="image/*" />
                        <p class="text-xs text-gray-400 mt-1">نوع الملفات المسموح: jpg, png, gif. الحد الأقصى 2MB.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="px-6 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">حفظ التغييرات</button>
                <a href="<?php echo base_url('modules/dashboard.php'); ?>" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold">إلغاء</a>
            </div>
        </form>

        <hr class="my-6" />

        <h3 class="text-lg font-bold mb-3">تغيير كلمة المرور</h3>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />
            <input type="hidden" name="action" value="change_password" />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="text-sm font-semibold text-gray-600">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" required class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" required class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" required class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="px-6 py-2.5 bg-amber-500 text-white rounded-xl font-bold">تغيير كلمة المرور</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
