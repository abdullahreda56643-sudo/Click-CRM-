<?php
// Documents module main page
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

 $page_title = 'المستندات - ' . APP_NAME;
 $current_module = 'documents';
 
require_permission('documents', 'view');

// fetch users for uploader filter
$db = get_db();
$users = $db->fetchAll('SELECT id, full_name FROM users ORDER BY full_name');

require_once __DIR__ . '/../../templates/header.php';
?>

<style>
/* Unique card-based styling for documents module */
.docs-hero { background: linear-gradient(90deg,#eef8ff,#f7fff8); padding:18px }
.docs-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap:16px }
.doc-card { background:white; border-radius:12px; padding:12px; box-shadow:0 6px 18px rgba(17,24,39,0.06); border:1px solid #eef2ff }
.doc-file { display:flex; gap:12px; align-items:center }
.doc-icon { width:56px; height:56px; display:flex; align-items:center; justify-content:center; border-radius:10px; background:#f1f5ff; font-size:22px }
.doc-meta { font-size:13px; color:#475569 }
.doc-actions { margin-top:10px; display:flex; gap:8px }
.doc-desc { margin-top:8px; color:#64748b; font-size:13px }
.upload-box { border:1px dashed #cfe8ff; padding:12px; border-radius:10px; background:#fbfdff }
</style>

<div class="docs-hero bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-xl font-bold text-[#164e63]">مكتبة المستندات</h2>
        <p class="text-sm text-gray-500">عرض المستندات كمربعات — تصميم مخصص للمستندات</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if (check_permission('documents', 'upload')): ?>
        <button id="openUpload" class="px-4 py-2 bg-[#164e63] text-white rounded-xl font-semibold hover:bg-[#0f3b4d]">رفع مستند</button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex flex-wrap gap-3 items-center">
        <input id="filterSearch" type="text" placeholder="ابحث باسم الملف أو الوصف..." class="px-3 py-2 border rounded-lg w-60" />
        <select id="filterRelatedType" class="px-3 py-2 border rounded-lg">
            <option value="">الكل (نوع مرتبط)</option>
            <option value="contact">جهة اتصال</option>
            <option value="deal">صفقة</option>
            <option value="project">مشروع</option>
            <option value="ticket">تذكرة</option>
        </select>
        <select id="filterFileType" class="px-3 py-2 border rounded-lg">
            <option value="">كل الأنواع</option>
            <option value="pdf">PDF</option>
            <option value="doc">DOC/DOCX</option>
            <option value="xls">XLS/XLSX</option>
            <option value="img">صور</option>
            <option value="txt">TXT</option>
        </select>
        <select id="filterUploader" class="px-3 py-2 border rounded-lg">
            <option value="">كل المرفعين</option>
            <?php foreach($users as $u): ?>
                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input id="filterDateFrom" type="date" class="px-3 py-2 border rounded-lg" />
        <input id="filterDateTo" type="date" class="px-3 py-2 border rounded-lg" />
        <button id="applyFilters" class="px-4 py-2 bg-[#164e63] text-white rounded-lg">تطبيق</button>
        <button id="clearFilters" class="px-3 py-2 border rounded-lg">مسح</button>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div id="docsContainer" class="docs-grid"></div>
    <div id="docsEmpty" class="text-center text-gray-400 mt-6 hidden">لا توجد مستندات بعد</div>
</div>

<!-- Upload Modal (Professional) -->
<div id="uploadModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-xs" aria-hidden="true"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 overflow-hidden transform transition-all scale-95 opacity-0" id="uploadModalContent">
        <div class="flex items-center justify-between p-5 border-b">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#1e4a76] text-white rounded-xl flex items-center justify-center text-xl">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">رفع مستند جديد</h3>
                    <p class="text-sm text-gray-500">اسحب الملف إلى هنا أو اختره من جهازك. يدعم PDF، الصور، Office، والمزيد.</p>
                </div>
            </div>
            <button id="closeUpload" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>

        <form id="uploadForm" class="p-6 grid grid-cols-1 gap-4" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />

            <div id="dropZone" class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 text-center bg-gray-50 hover:border-[#1e4a76] transition-colors">
                <i class="fas fa-file-upload text-3xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600">اسحب الملف هنا أو اضغط للاختيار</p>
                <p class="text-xs text-gray-400 mt-1">الحد الأقصى للملف يعتمد على إعدادات الخادم</p>
                <input id="fileInput" type="file" name="file" required class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-10" />
            </div>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">وصف (اختياري)</label>
                    <input type="text" name="description" class="mt-2 w-full px-3 py-2 border rounded-lg" placeholder="أضف وصفًا يساعد في البحث" />
                </div>
                <div class="w-44">
                    <label class="block text-sm font-medium text-gray-700">مرتبط ب</label>
                    <select name="related_type" class="mt-2 w-full px-3 py-2 border rounded-lg bg-white">
                        <option value="">بدون</option>
                        <option value="contact">جهة اتصال</option>
                        <option value="deal">صفقة</option>
                        <option value="project">مشروع</option>
                        <option value="ticket">تذكرة</option>
                    </select>
                    <input type="text" name="related_id" placeholder="معرّف" class="mt-2 w-full px-3 py-2 border rounded-lg" />
                </div>
            </div>

            <div id="fileMeta" class="hidden p-3 bg-white border rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div id="fileIcon" class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center text-2xl text-gray-500"></div>
                        <div>
                            <div id="fileName" class="font-semibold text-sm text-gray-800"></div>
                            <div id="fileSize" class="text-xs text-gray-500 mt-1"></div>
                        </div>
                    </div>
                    <div id="removeFile" class="text-sm text-red-500 cursor-pointer">إزالة</div>
                </div>
                <div class="mt-3">
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div id="uploadProgress" class="h-2 bg-[#1e4a76] w-0"></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <button type="button" id="cancelUpload" class="px-4 py-2 rounded-lg border">إلغاء</button>
                <button type="submit" id="startUpload" class="px-4 py-2 bg-[#1e4a76] text-white rounded-lg font-semibold">رفع الآن</button>
            </div>
        </form>
    </div>
</div>

<!-- Include DataTables assets (same as other modules) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
const CURRENT_USER_ID = <?php echo json_encode(get_current_user_id()); ?>;
const CURRENT_USER_ROLE = <?php echo json_encode(get_current_user_role()); ?>;
function formatSize(bytes){ if(!bytes) return '-'; if(bytes>=1048576) return (bytes/1048576).toFixed(2)+' MB'; if(bytes>=1024) return (bytes/1024).toFixed(2)+' KB'; return bytes+' bytes'; }

function renderDocs(docs){
    const container = document.getElementById('docsContainer');
    container.innerHTML = '';
    if(!docs || docs.length === 0){ document.getElementById('docsEmpty').classList.remove('hidden'); return; }
    document.getElementById('docsEmpty').classList.add('hidden');
    docs.forEach(d => {
        const card = document.createElement('div'); card.className='doc-card';
        const icon = document.createElement('div'); icon.className='doc-icon';
        // choose icon color by type
        const ext = (d.filename||'').split('.').pop().toLowerCase();
        let iconHtml = '<i class="fas fa-file"></i>';
        if(ext==='pdf') iconHtml='<i class="fas fa-file-pdf text-red-600"></i>';
        if(ext==='doc' || ext==='docx') iconHtml='<i class="fas fa-file-word text-blue-600"></i>';
        if(['xls','xlsx'].includes(ext)) iconHtml='<i class="fas fa-file-excel text-green-600"></i>';
        icon.innerHTML = iconHtml;

        const meta = document.createElement('div'); meta.className='doc-meta';
        meta.innerHTML = `<div class="font-semibold">${escapeHtml(d.original_name)}</div>
                          <div class="text-xs mt-1">${escapeHtml(d.uploader_name || '-') } · ${escapeHtml(d.created_at)}</div>
                          <div class="text-xs">${formatSize(d.file_size)} · ${escapeHtml(d.file_type)}</div>`;

        const desc = document.createElement('div'); desc.className='doc-desc'; desc.textContent = d.description || '';

        const actions = document.createElement('div'); actions.className='doc-actions';
        const view = document.createElement('a'); view.href = `view.php?id=${d.id}`; view.className='action-icon text-[#164e63]'; view.title='عرض'; view.innerHTML='<i class="fas fa-eye"></i>';
        actions.appendChild(view);

        const down = document.createElement('a'); down.href = `download.php?id=${d.id}`; down.className='action-icon'; down.title='تحميل'; down.innerHTML='<i class="fas fa-download"></i>';
        actions.appendChild(down);

        // Show edit icon if current user is admin or uploader of the file
        try {
            const uploaderId = parseInt(d.uploaded_by || 0, 10);
            if (CURRENT_USER_ROLE === 'admin' || uploaderId === CURRENT_USER_ID) {
                const edit = document.createElement('a'); edit.href = `edit.php?id=${d.id}`; edit.className='action-icon text-amber-500'; edit.title='تعديل'; edit.innerHTML='<i class="fas fa-pen"></i>';
                actions.appendChild(edit);
            }
        } catch (e) {
            // ignore
        }

        <?php if (check_permission('documents','delete')): ?>
        const del = document.createElement('a'); del.href='#'; del.className='action-icon delete-doc text-red-600'; del.setAttribute('data-id', d.id); del.title='حذف'; del.innerHTML='<i class="fas fa-trash"></i>';
        actions.appendChild(del);
        <?php endif; ?>

        const top = document.createElement('div'); top.className='doc-file'; top.appendChild(icon); top.appendChild(meta);
        card.appendChild(top);
        if(d.description) card.appendChild(desc);
        card.appendChild(actions);
        container.appendChild(card);
    });
}

function escapeHtml(unsafe){ if(!unsafe) return ''; return String(unsafe).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

function loadDocs(){
    const params = new URLSearchParams();
    params.set('view', 'cards');
    const s = document.getElementById('filterSearch').value.trim();
    if(s) params.set('search', s);
    const rt = document.getElementById('filterRelatedType').value;
    if(rt) params.set('related_type', rt);
    const ft = document.getElementById('filterFileType').value;
    if(ft) params.set('file_type', ft);
    const ub = document.getElementById('filterUploader').value;
    if(ub) params.set('uploaded_by', ub);
    const df = document.getElementById('filterDateFrom').value;
    if(df) params.set('date_from', df);
    const dt = document.getElementById('filterDateTo').value;
    if(dt) params.set('date_to', dt);

    fetch('list.php?' + params.toString())
    .then(r=>r.json())
    .then(data=>{ renderDocs(data); })
    .catch(()=>{ document.getElementById('docsEmpty').classList.remove('hidden'); });
}

document.addEventListener('DOMContentLoaded', function(){
    loadDocs();
    document.getElementById('applyFilters').addEventListener('click', function(e){ e.preventDefault(); loadDocs(); });
    document.getElementById('clearFilters').addEventListener('click', function(e){ e.preventDefault();
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterRelatedType').value = '';
        document.getElementById('filterFileType').value = '';
        document.getElementById('filterUploader').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        loadDocs();
    });
    // Upload modal open/close with animation
    $('#openUpload').on('click', function(){
        $('#uploadModal').removeClass('hidden');
        setTimeout(function(){ $('#uploadModalContent').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'); }, 10);
    });

    function closeUploadModal() {
        $('#uploadModalContent').removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
        setTimeout(function(){ $('#uploadModal').addClass('hidden'); }, 200);
        // reset
        $('#uploadForm')[0].reset();
        $('#fileMeta').addClass('hidden');
        $('#uploadProgress').css('width','0%');
    }

    $('#closeUpload, #cancelUpload').on('click', function(){ closeUploadModal(); });

    // Drag & drop + preview
    const drop = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileMeta = document.getElementById('fileMeta');
    const fileNameEl = document.getElementById('fileName');
    const fileSizeEl = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const removeFileBtn = document.getElementById('removeFile');

    function humanFileSize(bytes) { if(!bytes) return '-'; if(bytes>=1048576) return (bytes/1048576).toFixed(2)+' MB'; if(bytes>=1024) return (bytes/1024).toFixed(2)+' KB'; return bytes+' B'; }

    function setSelectedFile(file) {
        if(!file) return;
        fileMeta.classList.remove('hidden');
        fileNameEl.textContent = file.name;
        fileSizeEl.textContent = humanFileSize(file.size);
        const ext = (file.name.split('.').pop()||'').toLowerCase();
        let icon = '<i class="fas fa-file"></i>';
        if(ext==='pdf') icon = '<i class="fas fa-file-pdf text-red-600"></i>';
        if(ext==='doc' || ext==='docx') icon = '<i class="fas fa-file-word text-blue-600"></i>';
        if(['xls','xlsx'].includes(ext)) icon = '<i class="fas fa-file-excel text-green-600"></i>';
        if(['jpg','jpeg','png','gif','webp'].includes(ext)) icon = '<i class="fas fa-file-image text-purple-600"></i>';
        fileIcon.innerHTML = icon;
    }

    drop.addEventListener('dragover', function(e){ e.preventDefault(); drop.classList.add('border-[#1e4a76]'); });
    drop.addEventListener('dragleave', function(e){ e.preventDefault(); drop.classList.remove('border-[#1e4a76]'); });
    drop.addEventListener('drop', function(e){ e.preventDefault(); drop.classList.remove('border-[#1e4a76]'); const f = e.dataTransfer.files && e.dataTransfer.files[0]; if(f){ fileInput.files = e.dataTransfer.files; setSelectedFile(f); } });

    fileInput.addEventListener('change', function(e){ const f = this.files && this.files[0]; if(f){ setSelectedFile(f); } });
    removeFileBtn.addEventListener('click', function(){ fileInput.value = ''; fileMeta.classList.add('hidden'); });

    // Submit with progress (XHR to support progress events)
    $('#uploadForm').on('submit', function(e){
        e.preventDefault();
        const formEl = this;
        if(!fileInput.files || !fileInput.files[0]){ alert('الرجاء اختيار ملف للرفع'); return; }
        const formData = new FormData(formEl);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload.php');
        xhr.upload.addEventListener('progress', function(ev){ if(ev.lengthComputable){ const pct = Math.round((ev.loaded/ev.total)*100); document.getElementById('uploadProgress').style.width = pct + '%'; } });
        xhr.onreadystatechange = function(){ if(xhr.readyState===4){ try{ const res = JSON.parse(xhr.responseText); if(res && res.success){ if(window.showToast) showToast(res.message || 'تم الرفع', 'success'); else alert(res.message || 'تم الرفع'); closeUploadModal(); loadDocs(); } else { if(window.showToast) showToast(res.message || 'فشل الرفع', 'error'); else alert(res.message || 'فشل الرفع'); } } catch(e){ console.error('Invalid response', xhr.responseText); alert('خطأ في استجابة الخادم'); } } };
        xhr.onerror = function(){ alert('خطأ في الاتصال أثناء الرفع'); };
        xhr.send(formData);
    });

    $(document).on('click', '.delete-doc', function(e){
        e.preventDefault(); if(!confirm('هل تريد حذف هذا الملف؟')) return;
        const id = $(this).data('id');
        $.post('delete.php', { id: id, csrf_token: CSRF_TOKEN }, function(res){ if(res.success){ loadDocs(); if(window.showToast) showToast('تم الحذف','success'); } else { alert(res.message || 'خطأ'); } }, 'json');
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
