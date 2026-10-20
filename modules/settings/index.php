<?php
// Settings page
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('settings', 'view');

 $page_title = 'الإعدادات - ' . APP_NAME;
 $current_module = 'settings';
 $page_header = 'إعدادات النظام';

// Handle POST - save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('settings', 'edit');
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/settings/');
    }

    $company_name = clean_input($_POST['company_name'] ?? '');
    $company_email = clean_input($_POST['company_email'] ?? '');
    $timezone = clean_input($_POST['timezone'] ?? DATE_DEFAULT ?? 'UTC');
    $items_per_page = intval($_POST['items_per_page'] ?? ITEMS_PER_PAGE);
    $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';

    // minimal validation
    if (!filter_var($company_email, FILTER_VALIDATE_EMAIL) && $company_email !== '') {
        $_SESSION['error'] = 'البريد الإلكتروني غير صالح.';
        redirect('modules/settings/');
    }

    // persist
    set_setting('company_name', $company_name);
    set_setting('company_email', $company_email);
    set_setting('timezone', $timezone);
    set_setting('items_per_page', (string)$items_per_page);
    set_setting('email_notifications', $email_notifications);

    $_SESSION['success'] = 'تم حفظ الإعدادات بنجاح.';
    redirect('modules/settings/');
}

// Load current settings
$company_name = get_setting('company_name', '');
$company_email = get_setting('company_email', '');
$timezone = get_setting('timezone', TIMEZONE);
$items_per_page = get_setting('items_per_page', ITEMS_PER_PAGE);
$email_notifications = get_setting('email_notifications', '1');

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <h2 class="text-xl font-bold text-[#1e4a76]"><?php echo $page_header; ?></h2>
    <p class="text-sm text-gray-500">تعديل إعدادات النظام العامة.</p>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl mx-auto">
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-rose-50 text-rose-700 border border-rose-100"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-green-50 text-green-700 border border-green-100"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="mb-6">
            <label class="text-sm font-semibold text-gray-600 block mb-1">اسم الشركة</label>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" class="form-input-advanced w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
        </div>

        <div class="mb-6">
            <label class="text-sm font-semibold text-gray-600 block mb-1">بريد الشركة</label>
            <input type="email" name="company_email" value="<?php echo htmlspecialchars($company_email); ?>" class="form-input-advanced w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="text-sm font-semibold text-gray-600 block mb-1">المنطقة الزمنية</label>
                <select name="timezone" class="form-input-advanced w-full py-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <?php
                    $timezones = DateTimeZone::listIdentifiers();
                    foreach ($timezones as $tz) {
                        $sel = ($tz === $timezone) ? 'selected' : '';
                        echo "<option value=\"$tz\" $sel>$tz</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-600 block mb-1">عناصر في الصفحة</label>
                <input type="number" name="items_per_page" value="<?php echo (int)$items_per_page; ?>" min="1" max="200" class="form-input-advanced w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
            </div>
        </div>

        <div class="mb-6 flex items-center gap-3">
            <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo ($email_notifications === '1') ? 'checked' : ''; ?> />
            <label for="email_notifications" class="text-sm text-gray-600">تفعيل إشعارات البريد الإلكتروني</label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="<?php echo base_url(); ?>" class="px-5 py-2 bg-gray-100 rounded-xl">إلغاء</a>
            <button type="submit" class="px-6 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">حفظ الإعدادات</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
