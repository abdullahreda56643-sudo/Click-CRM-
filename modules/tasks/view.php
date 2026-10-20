<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

 $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
 $db = get_db();

require_permission('tasks', 'view');

 $task = $db->fetch('SELECT t.*, u.full_name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ?', [$id]);
if (!$task) redirect('modules/tasks/index.php');

if (!can_access_record('tasks', $id, 'view')) {
    $_SESSION['error'] = 'ليس لديك صلاحية عرض هذه المهمة.';
    redirect('modules/tasks/index.php');
}

 $page_title = 'عرض مهمة - ' . APP_NAME;
 $current_module = 'tasks';
require_once __DIR__ . '/../../templates/header.php';

// Helper mapping for Status and Priority
 $statusConfig = [
    'pending' => ['label' => 'معلقة', 'bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-500'],
    'in_progress' => ['label' => 'قيد التنفيذ', 'bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
    'completed' => ['label' => 'مكتملة', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
    'cancelled' => ['label' => 'ملغاة', 'bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500']
];

 $priorityConfig = [
    'high' => ['label' => 'عالية', 'bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => 'fa-arrow-up text-red-500'],
    'medium' => ['label' => 'متوسطة', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'icon' => 'fa-minus text-yellow-500'],
    'low' => ['label' => 'منخفضة', 'bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'fa-arrow-down text-blue-500']
];

 $currentStatus = $statusConfig[$task['status'] ?? 'pending'] ?? $statusConfig['pending'];
 $currentPriority = $priorityConfig[$task['priority'] ?? 'medium'] ?? $priorityConfig['medium'];
?>

<style>
    .profile-card {
        transition: all 0.3s ease;
    }
    .profile-card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01) !important;
        transform: translateY(-2px);
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-5xl mx-auto">
    
    <!-- Profile Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-8 border-b border-gray-100 mb-8">
        <div class="flex items-center gap-5">
            <!-- Icon -->
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center shadow-md flex-shrink-0 <?php echo $currentStatus['bg']; ?>">
                <i class="fas fa-tasks text-2xl <?php echo $currentStatus['text']; ?>"></i>
            </div>
            
            <!-- Title & Meta -->
            <div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($task['title']); ?></h3>
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Status Badge -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $currentStatus['bg'] . ' ' . $currentStatus['text']; ?>">
                        <span class="w-2 h-2 rounded-full <?php echo $currentStatus['dot']; ?>"></span>
                        <?php echo $currentStatus['label']; ?>
                    </span>
                    <!-- Priority Badge -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $currentPriority['bg'] . ' ' . $currentPriority['text']; ?>">
                        <i class="fas <?php echo $currentPriority['icon']; ?> text-[10px]"></i>
                        أولوية <?php echo $currentPriority['label']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            <a href="index.php" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors flex items-center gap-2">
                <i class="fas fa-arrow-right text-sm"></i> العودة
            </a>
            <a href="edit.php?id=<?php echo $task['id']; ?>" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-semibold hover:bg-amber-600 transition-colors shadow-sm shadow-amber-500/20 flex items-center gap-2">
                <i class="fas fa-pen-to-square text-sm"></i> تعديل
            </a>
            <a href="delete.php?id=<?php echo $task['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذه المهمة؟ لا يمكن التراجع عن هذا الإجراء.')" class="px-4 py-2.5 bg-white border-2 border-rose-200 text-rose-600 rounded-xl font-semibold hover:bg-rose-50 transition-colors flex items-center gap-2">
                <i class="fas fa-trash-can text-sm"></i> حذف
            </a>
        </div>
    </div>

    <!-- Task Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        
        <!-- Assigned To Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-user-tie text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">المسؤول</p>
                <p class="text-gray-800 font-semibold">
                    <?php echo !empty($task['assigned_name']) ? htmlspecialchars($task['assigned_name']) : 'غير معين'; ?>
                </p>
            </div>
        </div>

        <!-- Due Date Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-calendar-alt text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">تاريخ الاستحقاق</p>
                <p class="text-gray-800 font-semibold">
                    <?php echo !empty($task['due_date']) ? htmlspecialchars($task['due_date']) : 'غير محدد'; ?>
                </p>
            </div>
        </div>

        <!-- Created At Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-clock text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">تاريخ الإنشاء</p>
                <p class="text-gray-800 font-semibold">
                    <?php echo !empty($task['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($task['created_at']))) : '-'; ?>
                </p>
            </div>
        </div>

    </div>

    <!-- Description Section -->
    <?php if (!empty($task['description'])): ?>
    <div class="bg-blue-50/40 border border-blue-100 rounded-2xl p-6">
        <h4 class="text-sm font-bold text-[#1e4a76] uppercase tracking-wider flex items-center gap-2 mb-4">
            <i class="fas fa-align-left text-blue-300"></i> وصف المهمة
        </h4>
        <div class="bg-white rounded-xl p-5 border-r-4 border-[#1e4a76] shadow-sm text-gray-700 leading-relaxed whitespace-pre-line font-medium">
            <?php echo htmlspecialchars($task['description']); ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>