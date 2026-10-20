<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

 $db = get_db();
 $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
 $task = $db->fetch('SELECT * FROM tasks WHERE id = ?', [$id]);
if (!$task) { redirect('modules/tasks/index.php'); }

require_permission('tasks', 'edit');

 $users = $db->fetchAll('SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name');

// ensure user can access this task
if (!can_access_record('tasks', $id, 'edit')) {
    $_SESSION['error'] = 'ليس لديك صلاحية تعديل هذه المهمة.';
    redirect('modules/tasks/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) { $error = 'رمز CSRF غير صالح.'; }

    $title = clean_input($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?: null;
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? ($task['status'] ?? 'pending');
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;

    if (empty($error) && $title === '') {
        $error = 'عنوان المهمة مطلوب.';
    }

    // validate assigned_to exists or is null
    if (!empty($assigned_to)) {
        $u = $db->fetch('SELECT id FROM users WHERE id = ?', [$assigned_to]);
        if (!$u) $error = 'المستخدم المحدد غير صالح.';
    }

    if (empty($error)) {
        $updated = $db->update('tasks', [
            'title' => $title,
            'description' => $description,
            'due_date' => $due_date,
            'priority' => $priority,
            'priority' => $priority,
            'status' => $status,
            'assigned_to' => $assigned_to
        ], 'id = ?', [$id]);
        
        if ($updated) {
            log_audit('task_updated', 'tasks', $id, "Task updated: $title");
            // status change audit & notifications
            $old_status = $task['status'] ?? null;
            if ($old_status !== $status) {
                log_audit('task_status_changed', 'tasks', $id, "Status changed from $old_status to $status");
                notify_task_status_changed($id, $assigned_to ?: null, $old_status, $status);
            }
            // notify if assignee changed
            $old_assigned = $task['assigned_to'] ?? null;
            if (!empty($assigned_to) && $assigned_to != $old_assigned) {
                notify_new_task($id, $assigned_to);
            }
            $_SESSION['success'] = 'تم تحديث المهمة بنجاح.';
        } else {
            $_SESSION['success'] = 'لم يتم إجراء أي تغييرات.';
        }
        redirect('modules/tasks/index.php');
    }
}

 $page_title = 'تعديل مهمة - ' . APP_NAME;
 $current_module = 'tasks';
require_once __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Custom Input Focus & Transition */
    .form-input-advanced {
        transition: all 0.3s ease;
    }
    .form-input-advanced:focus {
        border-color: #1e4a76 !important;
        box-shadow: 0 0 0 4px rgba(30, 74, 118, 0.08) !important;
        background-color: #ffffff !important;
    }
    /* Custom Select Arrow */
    select.form-input-advanced {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: left 0.75rem center;
        background-size: 1.2rem;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        padding-left: 2.5rem;
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
    
    <!-- Header -->
    <div class="mb-8 pb-6 border-b border-gray-100">
        <h3 class="text-2xl font-bold text-[#1e4a76] flex items-center gap-3">
            <div class="bg-[#1e4a76]/10 p-2.5 rounded-xl">
                <i class="fas fa-pen-to-square text-[#1e4a76] text-lg"></i>
            </div>
            تعديل بيانات المهمة
        </h3>
        <p class="text-gray-400 text-sm mt-2 mr-14">قم بتحديث تفاصيل المهمة بدقة. الحقول المميزة بـ <span class="text-rose-500">*</span> مطلوبة.</p>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 flex items-center gap-3 text-sm font-medium">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />

        <!-- Section 1: Basic Info -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-gray-400"></i> المعلومات الأساسية
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">عنوان المهمة <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-heading text-sm"></i>
                        </div>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($task['title']); ?>" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">تاريخ الاستحقاق</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-calendar-alt text-sm"></i>
                        </div>
                        <input type="date" name="due_date" value="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Assignment & Priority -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-users-cog text-gray-400"></i> التعيين والأولوية
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">الأولوية</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-flag text-sm"></i>
                        </div>
                        <select name="priority" class="form-input-advanced w-full pr-11 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
                            <option value="low" <?php echo ($task['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>منخفضة</option>
                            <option value="medium" <?php echo ($task['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>متوسطة</option>
                            <option value="high" <?php echo ($task['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>عالية</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">تعيين إلى</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-user-tie text-sm"></i>
                        </div>
                        <select name="assigned_to" class="form-input-advanced w-full pr-11 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
                            <option value="">غير معين</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($task['assigned_to'] == $u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
                <!-- Section 3: Status -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fas fa-toggle-on text-gray-400"></i> الحالة
                    </h4>
                    <div>
                        <select name="status" class="form-input-advanced w-full pr-11 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
                            <option value="pending" <?php echo ($task['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>معلقة</option>
                            <option value="in_progress" <?php echo ($task['status'] ?? '') == 'in_progress' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                            <option value="completed" <?php echo ($task['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="cancelled" <?php echo ($task['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>ملغاة</option>
                        </select>
                    </div>
                </div>

                <!-- Section 4: Description -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-align-left text-gray-400"></i> التفاصيل
            </h4>
            <div>
                <textarea name="description" rows="4" class="form-input-advanced w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium resize-none"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="index.php" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors">
                إلغاء الأمر
            </a>
            <button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold hover:bg-[#163d63] transition-colors shadow-lg shadow-[#1e4a76]/20 flex items-center gap-2">
                <i class="fas fa-save"></i>
                حفظ التعديلات
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>