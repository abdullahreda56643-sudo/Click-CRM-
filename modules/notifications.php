<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../config/config.php';

require_login();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

$page_title = 'الإشعارات - ' . APP_NAME;
$current_module = 'notifications';

$user_id = get_current_user_id();
$notifications = get_user_notifications($user_id, false, 200);

require_once __DIR__ . '/../templates/header.php';
?>

<div class="bg-white rounded-2xl p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold">الإشعارات</h2>
        <button onclick="markAllNotificationsRead()" class="px-3 py-2 bg-gray-100 rounded">تعيين الكل كمقروء</button>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="text-gray-500">لا توجد إشعارات</div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($notifications as $n):
                $read_class = $n['is_read'] ? 'bg-gray-50' : 'bg-blue-50';
            ?>
                <div class="p-4 border rounded-lg <?php echo $read_class; ?>" onclick="viewNotification(<?php echo $n['id']; ?>)">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($n['title']); ?></h4>
                            <p class="text-sm text-gray-700 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($n['message']); ?></p>
                            <p class="text-xs text-gray-500 mt-2"><?php echo time_ago($n['created_at']); ?></p>
                        </div>
                        <?php if (!$n['is_read']): ?>
                            <div class="ml-4 w-3 h-3 bg-blue-500 rounded-full mt-2"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
