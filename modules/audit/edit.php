<?php
// Audit logs cannot be edited
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_permission('audit', 'view');

 $page_title = 'تعديل سجل - ' . APP_NAME;
 $current_module = 'audit';
 $page_header = 'تعديل سجل';

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl mx-auto">
    <h3 class="text-xl font-bold text-[#1e4a76] mb-4"><?php echo $page_header; ?></h3>
    <div class="text-gray-600">لا يمكن تعديل سجلات التدقيق يدوياً.</div>
    <div class="mt-6">
        <a href="index.php" class="px-4 py-2 bg-gray-100 rounded-xl">العودة إلى السجل</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
