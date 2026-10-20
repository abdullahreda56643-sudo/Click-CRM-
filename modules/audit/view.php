<?php
// View audit entry
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';

require_permission('audit', 'view');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('modules/audit/');
}

try {
    $db = get_db();
    $sql = 'SELECT a.*, u.full_name as user_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?';
    $entry = $db->fetch($sql, [$id]);
} catch (Exception $e) {
    $entry = null;
}

 $page_title = 'عرض سجل النشاط - ' . APP_NAME;
 $current_module = 'audit';
 $page_header = 'تفاصيل سجل النشاط';

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl mx-auto">
    <h3 class="text-xl font-bold text-[#1e4a76] mb-4"><?php echo $page_header; ?></h3>

    <?php if (!$entry): ?>
        <div class="text-center text-gray-500">السجل غير موجود.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">المستخدم</label>
                <div class="mt-1 font-semibold"><?php echo htmlspecialchars($entry['user_name'] ?? 'نظام'); ?></div>
            </div>
            <div>
                <label class="text-sm text-gray-500">الإجراء</label>
                <div class="mt-1 font-semibold"><?php echo htmlspecialchars($entry['action'] ?? ''); ?></div>
            </div>
            <div>
                <label class="text-sm text-gray-500">الجدول</label>
                <div class="mt-1"><?php echo htmlspecialchars($entry['table_name'] ?? ''); ?></div>
            </div>
            <div>
                <label class="text-sm text-gray-500">رقم السجل</label>
                <div class="mt-1"><?php echo htmlspecialchars($entry['record_id'] ?? ''); ?></div>
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-gray-500">الوصف</label>
                <div class="mt-1 whitespace-pre-wrap bg-gray-50 p-3 rounded-xl border border-gray-100"><?php echo htmlspecialchars($entry['description'] ?? ''); ?></div>
            </div>
            <div>
                <label class="text-sm text-gray-500">IP</label>
                <div class="mt-1"><?php echo htmlspecialchars($entry['ip_address'] ?? ''); ?></div>
            </div>
            <div>
                <label class="text-sm text-gray-500">التاريخ</label>
                <div class="mt-1"><?php echo htmlspecialchars($entry['created_at'] ?? ''); ?></div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="index.php" class="px-5 py-2 bg-gray-100 text-gray-700 rounded-xl">العودة</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
