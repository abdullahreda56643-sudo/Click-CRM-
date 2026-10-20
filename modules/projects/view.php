<?php
// View project
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('projects', 'view');

 $id = intval($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['error'] = 'معرّف غير صالح.';
    redirect('modules/projects/');
}

 $db = get_db();
 $project = $db->fetch('SELECT p.*, u.full_name as assigned_name, uc.full_name as creator_name FROM projects p LEFT JOIN users u ON p.assigned_to = u.id LEFT JOIN users uc ON p.created_by = uc.id WHERE p.id = ?', [$id]);
if (!$project) {
    $_SESSION['error'] = 'المشروع غير موجود.';
    redirect('modules/projects/');
}

// Access check
if (!can_access_record('projects', $id, 'view')) {
    $_SESSION['error'] = 'ليس لديك صلاحية عرض هذا المشروع.';
    redirect('modules/projects/');
}

// Mappers for Arabic translation and Styling
 $statusMap = [
    'planning' => ['text' => 'تخطيط', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20'],
    'in_progress' => ['text' => 'قيد التنفيذ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20'],
    'completed' => ['text' => 'مكتمل', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'],
    'on_hold' => ['text' => 'متوقف', 'class' => 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20'],
    'cancelled' => ['text' => 'ملغى', 'class' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20']
];

require_once __DIR__ . '/../../templates/header.php';
?>

<!-- Page Header - Premium Gradient Style -->
<div class="relative bg-gradient-to-l from-[#1e4a76] to-[#0f2a47] rounded-3xl shadow-xl p-8 mb-8 text-white overflow-hidden">
    <!-- Decorative Shapes -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-[#4a7db8]/20 rounded-full translate-y-1/2 -translate-x-1/4 blur-xl"></div>
    
    <div class="relative z-10">
        <div class="flex items-center mb-4">
            <a href="index.php" class="text-blue-200 hover:text-white transition mr-3 text-sm font-semibold">
                <i class="fas fa-arrow-right ml-1"></i> العودة للمشاريع
            </a>
        </div>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">#PRJ-<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></span>
                    <?php 
                    $s_key = $project['status'] ?? 'planning';
                    $s_cfg = $statusMap[$s_key] ?? ['text' => $s_key];
                    ?>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full"><?php echo $s_cfg['text']; ?></span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight"><?php echo htmlspecialchars($project['name']); ?></h1>
            </div>
            <div class="flex gap-2">
                <a href="edit.php?id=<?php echo $id; ?>" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl transition flex items-center text-sm">
                    <i class="fas fa-pen ml-2 text-xs"></i> تعديل المشروع
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
    
    <!-- Left Column: Description & Documents -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Project Description -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-align-left text-[#1e4a76] ml-2"></i> وصف المشروع
                </h3>
            </div>
            <div class="p-6">
                <div class="text-gray-600 text-sm leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($project['description'] ?? 'لا يوجد وصف لهذا المشروع.')); ?>
                </div>
            </div>
        </div>

        <!-- Related Documents -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-paperclip text-[#1e4a76] ml-2"></i> المستندات المرتبطة
                </h3>
            </div>
            <div class="p-6 divide-y divide-gray-100">
                <?php
                $docs = $db->fetchAll('SELECT * FROM documents WHERE related_type = ? AND related_id = ? ORDER BY created_at DESC', ['project', $id]);
                if (empty($docs)): ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-folder-open text-gray-300 text-xl"></i>
                        </div>
                        <p class="text-gray-400 font-semibold text-sm">لا توجد مستندات مرتبطة</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($docs as $d): 
                        $downloadUrl = '../documents/download.php?id=' . urlencode($d['id']);
                        // Use download endpoint to serve file safely regardless of stored file_path format
                        $ext = pathinfo($d['original_name'], PATHINFO_EXTENSION);
                        $iconClass = 'fa-file-alt'; // default
                        if (in_array($ext, ['pdf'])) $iconClass = 'fa-file-pdf text-red-500';
                        elseif (in_array($ext, ['doc', 'docx'])) $iconClass = 'fa-file-word text-blue-500';
                        elseif (in_array($ext, ['xls', 'xlsx'])) $iconClass = 'fa-file-excel text-emerald-500';
                        elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $iconClass = 'fa-file-image text-purple-500';
                    ?>
                        <div class="flex items-center gap-4 py-3 group hover:bg-gray-50/50 transition px-2 -mx-2 rounded-lg">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 flex-shrink-0">
                                <i class="fas <?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <a href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank" class="text-sm font-semibold text-gray-800 hover:text-[#1e4a76] transition truncate block">
                                    <?php echo htmlspecialchars($d['original_name']); ?>
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5"><?php echo date('Y/m/d h:i A', strtotime($d['created_at'])); ?></p>
                            </div>
                            <a href="<?php echo htmlspecialchars($downloadUrl); ?>" class="w-8 h-8 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 hover:bg-[#1e4a76] hover:text-white transition opacity-0 group-hover:opacity-100">
                                <i class="fas fa-download text-xs"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Sidebar Details -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Project Information -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-info-circle text-[#1e4a76] ml-2"></i> تفاصيل المشروع
                </h3>
            </div>
            <div class="p-5 divide-y divide-gray-100">
                <!-- Status -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">الحالة</span>
                    <?php 
                    $s_cfg = $statusMap[$project['status']] ?? ['text' => $project['status'], 'class' => 'bg-gray-50 text-gray-700'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?php echo $s_cfg['class']; ?>">
                        <?php echo $s_cfg['text']; ?>
                    </span>
                </div>
                <!-- Assignee -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">المسؤول</span>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-[#1e4a76]/10 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-[8px] text-[#1e4a76]"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($project['assigned_name'] ?? 'غير معين'); ?></span>
                    </div>
                </div>
                <!-- Start Date -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">تاريخ البدء</span>
                    <span class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                        <i class="fas fa-calendar-alt text-[10px] text-gray-400"></i>
                        <?php echo htmlspecialchars($project['start_date'] ?? '-'); ?>
                    </span>
                </div>
                <!-- End Date -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">تاريخ الانتهاء</span>
                    <span class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                        <i class="fas fa-calendar-check text-[10px] text-gray-400"></i>
                        <?php echo htmlspecialchars($project['end_date'] ?? '-'); ?>
                    </span>
                </div>
                <!-- Created By -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">أُنشئ بواسطة</span>
                    <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($project['creator_name'] ?? '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-bolt text-amber-500 ml-2"></i> إجراءات سريعة
                </h3>
            </div>
            <div class="p-5 space-y-2">
                <a href="edit.php?id=<?php echo $id; ?>" class="w-full px-4 py-2.5 bg-[#1e4a76]/5 text-[#1e4a76] font-bold rounded-xl hover:bg-[#1e4a76]/10 transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-pen text-xs"></i> تعديل البيانات
                </a>
                <a href="index.php" class="w-full px-4 py-2.5 bg-gray-50 text-gray-600 font-bold rounded-xl hover:bg-gray-100 transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-list text-xs"></i> عرض كافة المشاريع
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>