<?php
// View single document details and preview
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

require_permission('documents', 'view');

 $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
 $id = $id === false || $id === null ? 0 : (int)$id;
if (!$id) {
    $_SESSION['error'] = 'معرّف غير صحيح.';
    redirect('modules/documents/');
}

 $document = get_document($id);
if (!$document) {
    $_SESSION['error'] = 'المستند غير موجود.';
    redirect('modules/documents/');
}

if (!can_access_record('documents', $id, 'view')) {
    $_SESSION['error'] = 'ليس لديك صلاحية عرض هذا الملف.';
    redirect('modules/documents/');
}

 $page_title = htmlspecialchars($document['original_name']) . ' - ' . APP_NAME;
 $current_module = 'documents';

require_once __DIR__ . '/../../templates/header.php';

// Determine preview endpoint
 $preview_url = 'preview.php?id=' . $document['id'];

function safeTxt($s) { return htmlspecialchars($s ?? ''); }

// Helper for file type icons and colors
 $ext = strtolower(pathinfo($document['original_name'], PATHINFO_EXTENSION));
 $fileTypeConfig = [
    'pdf' => ['icon' => 'fa-file-pdf', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
    'doc' => ['icon' => 'fa-file-word', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'],
    'docx' => ['icon' => 'fa-file-word', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'],
    'xls' => ['icon' => 'fa-file-excel', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'],
    'xlsx' => ['icon' => 'fa-file-excel', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50'],
    'ppt' => ['icon' => 'fa-file-powerpoint', 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    'pptx' => ['icon' => 'fa-file-powerpoint', 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    'jpg' => ['icon' => 'fa-file-image', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    'jpeg' => ['icon' => 'fa-file-image', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    'png' => ['icon' => 'fa-file-image', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    'gif' => ['icon' => 'fa-file-image', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    'zip' => ['icon' => 'fa-file-archive', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-50'],
    'rar' => ['icon' => 'fa-file-archive', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-50'],
    'txt' => ['icon' => 'fa-file-alt', 'color' => 'text-gray-500', 'bg' => 'bg-gray-50'],
];
 $currentFileConfig = $fileTypeConfig[$ext] ?? ['icon' => 'fa-file', 'color' => 'text-[#1e4a76]', 'bg' => 'bg-[#1e4a76]/10'];
?>

<!-- Page Header - Premium Gradient Style -->
<div class="relative bg-gradient-to-l from-[#1e4a76] to-[#0f2a47] rounded-3xl shadow-xl p-8 mb-8 text-white overflow-hidden">
    <!-- Decorative Shapes -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-[#4a7db8]/20 rounded-full translate-y-1/2 -translate-x-1/4 blur-xl"></div>
    
    <div class="relative z-10">
        <div class="flex items-center mb-4">
            <a href="index.php" class="text-blue-200 hover:text-white transition mr-3 text-sm font-semibold">
                <i class="fas fa-arrow-right ml-1"></i> العودة للمستندات
            </a>
        </div>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-white text-2xl flex-shrink-0">
                    <i class="fas <?php echo $currentFileConfig['icon']; ?>"></i>
                </div>
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full uppercase"><?php echo safeTxt($ext); ?></span>
                    </div>
                    <h1 class="text-xl md:text-2xl font-extrabold tracking-tight truncate max-w-md lg:max-w-xl"><?php echo safeTxt($document['original_name']); ?></h1>
                </div>
            </div>
            <div class="flex gap-2 mt-4 md:mt-0">
                <a href="download.php?id=<?php echo $document['id']; ?>" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl transition flex items-center text-sm">
                    <i class="fas fa-download ml-2 text-xs"></i> تنزيل
                </a>
                <?php if (get_current_user_role() === 'admin' || get_current_user_id() === (int)($document['uploaded_by'] ?? 0)): ?>
                <a href="edit.php?id=<?php echo $document['id']; ?>" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl transition flex items-center text-sm">
                    <i class="fas fa-pen ml-2 text-xs"></i> تعديل
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Preview & Description -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- File Preview -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-eye text-[#1e4a76] ml-2"></i> معاينة الملف
                </h3>
                <span class="text-xs text-gray-400 font-medium"><?php echo format_file_size_display($document['file_size']); ?></span>
            </div>
            <div class="p-2 bg-gray-50/30">
                <?php
                    $ft = strtolower($document['file_type'] ?? '');
                    // If it's a text file, read and display content
                    if (strpos($ft, 'text/') === 0 || in_array($ext, ['txt','csv','log','ini'])):
                        $path = $document['file_path'] ?? '';
                        if ($path && file_exists($path) && filesize($path) <= 1024*1024) { // limit 1MB for inline text preview
                            $contents = file_get_contents($path);
                            echo '<pre class="p-6 bg-white border border-gray-100 rounded-xl text-sm text-gray-700 font-mono leading-relaxed max-h-[600px] overflow-y-auto" style="white-space:pre-wrap;word-wrap:break-word;">' . safeTxt($contents) . '</pre>';
                        } else {
                            echo '<div class="p-8 text-center">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-file-alt text-gray-300 text-xl"></i>
                                    </div>
                                    <p class="text-gray-500 font-semibold mb-4">ملف نصي كبير جدًا للمعاينة</p>
                                    <a href="download.php?id=' . $document['id'] . '" class="px-6 py-2.5 bg-[#1e4a76] text-white font-bold rounded-xl hover:bg-[#163d63] transition text-sm">تنزيل الملف</a>
                                  </div>';
                        }
                    else:
                        // Use preview endpoint which serves files inline when possible
                        echo '<iframe src="' . $preview_url . '" style="width:100%;height:600px;border:0;border-radius:12px;" class="shadow-inner"></iframe>';
                    endif;
                ?>
            </div>
        </div>

        <!-- Description -->
        <?php if (!empty($document['description'])): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-align-left text-[#1e4a76] ml-2"></i> الوصف
                </h3>
            </div>
            <div class="p-6">
                <div class="text-gray-600 text-sm leading-relaxed whitespace-pre-line"><?php echo nl2br(safeTxt($document['description'])); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Sidebar Details -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- File Information -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-info-circle text-[#1e4a76] ml-2"></i> تفاصيل الملف
                </h3>
            </div>
            <div class="p-5 divide-y divide-gray-100">
                <!-- File Type -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">النوع</span>
                    <div class="flex items-center gap-2">
                        <i class="fas <?php echo $currentFileConfig['icon']; ?> <?php echo $currentFileConfig['color']; ?>"></i>
                        <span class="text-sm font-semibold text-gray-700 uppercase"><?php echo safeTxt($ext); ?> ملف</span>
                    </div>
                </div>
                <!-- File Size -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">الحجم</span>
                    <span class="text-sm font-semibold text-gray-700"><?php echo format_file_size_display($document['file_size']); ?></span>
                </div>
                <!-- Uploaded By -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">تم الرفع بواسطة</span>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-[#1e4a76]/10 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-[8px] text-[#1e4a76]"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-700"><?php echo safeTxt($document['uploader_name'] ?? '-'); ?></span>
                    </div>
                </div>
                <!-- Upload Date -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">تاريخ الرفع</span>
                    <span class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                        <i class="fas fa-calendar-alt text-[10px] text-gray-400"></i>
                        <?php echo safeTxt(date('Y/m/d h:i A', strtotime($document['created_at']))); ?>
                    </span>
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
                <a href="download.php?id=<?php echo $document['id']; ?>" class="w-full px-4 py-2.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl hover:bg-emerald-100 transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-download text-xs"></i> تنزيل الملف
                </a>
                <?php if (get_current_user_role() === 'admin' || get_current_user_id() === (int)($document['uploaded_by'] ?? 0)): ?>
                <a href="edit.php?id=<?php echo $document['id']; ?>" class="w-full px-4 py-2.5 bg-[#1e4a76]/5 text-[#1e4a76] font-bold rounded-xl hover:bg-[#1e4a76]/10 transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-pen text-xs"></i> تعديل البيانات
                </a>
                <?php endif; ?>
                
                <?php if (check_permission('documents','delete')): ?>
                <button onclick="deleteDocument(<?php echo $document['id']; ?>)" class="w-full px-4 py-2.5 bg-red-50 text-red-600 font-bold rounded-xl hover:bg-red-100 transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-trash text-xs"></i> حذف الملف
                </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Custom Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 transform transition-all scale-95 opacity-0" id="deleteModalContent">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 text-center mb-2">تأكيد الحذف</h3>
        <p class="text-gray-500 text-center text-sm mb-8">هل أنت متأكد من حذف هذا الملف؟ لا يمكن التراجع عن هذا الإجراء.</p>
        <div class="flex gap-4">
            <button onclick="confirmDelete()" class="flex-1 px-5 py-3 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition shadow-sm">
                نعم، احذف
            </button>
            <button onclick="closeDeleteModal()" class="flex-1 px-5 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">
                إلغاء
            </button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
let deleteTargetId = null;

window.deleteDocument = function(id) {
    deleteTargetId = id;
    const modal = document.getElementById('deleteModal');
    const content = document.getElementById('deleteModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

window.closeDeleteModal = function() {
    const modal = document.getElementById('deleteModal');
    const content = document.getElementById('deleteModalContent');
    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
    deleteTargetId = null;
}

window.confirmDelete = function() {
    if (!deleteTargetId) return;
    
    fetch('delete.php', { 
        method: 'POST', 
        headers: {'Content-Type':'application/json'}, 
        body: JSON.stringify({ id: deleteTargetId, csrf_token: CSRF_TOKEN }) 
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            window.location.href = 'index.php';
        } else {
            showToast(resp.message || resp.error || 'فشل الحذف', 'error');
            closeDeleteModal();
        }
    })
    .catch(err => { 
        console.error('Delete request failed:', err);
        showToast('خطأ في الاتصال بالخادم', 'error');
        closeDeleteModal();
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>