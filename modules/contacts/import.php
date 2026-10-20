<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$page_title = 'استيراد جهات الاتصال - ' . APP_NAME;
$current_module = 'contacts';

require_permission('contacts', 'add');
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-3xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-[#1e4a76]">استيراد جهات الاتصال من ملف Excel / CSV</h2>
        <p class="text-gray-500 text-sm mt-2">اختر ملفًا وحدد خانة CSRF ثم اضغط رفع لاستيراد جهات الاتصال.</p>
    </div>

    <div id="importAlert" class="hidden p-3 rounded-md mb-4"></div>

    <form id="contactsImportPageForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="mb-4">
            <label class="text-sm font-medium text-gray-700">ملف Excel أو CSV</label>
            <input type="file" name="excel_file" id="excelFileInputPage" accept=".xlsx,.xls,.csv" class="mt-2" required />
        </div>

        <div class="mb-4 text-sm text-gray-600">الرؤوس المقترحة في الصف الأول: <strong>first_name, last_name, email, phone, company, position, type, assigned_to</strong></div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl">عودة</a>
            <button type="submit" id="importSubmitPage" class="px-6 py-2 bg-green-600 text-white rounded-xl">رفع و استيراد</button>
        </div>

        <div id="importProgress" class="mt-4 hidden">
            <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                <div id="importBar" class="h-3 bg-green-500 w-0"></div>
            </div>
            <div id="importStatus" class="mt-2 text-sm text-gray-600"></div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('contactsImportPageForm');
    const alertBox = document.getElementById('importAlert');
    const progress = document.getElementById('importProgress');
    const bar = document.getElementById('importBar');
    const status = document.getElementById('importStatus');

    function showAlert(type, msg, htmlList){
        alertBox.className = '';
        if (type === 'success') alertBox.classList.add('p-3','rounded-md','mb-4','bg-green-50','text-green-700');
        else alertBox.classList.add('p-3','rounded-md','mb-4','bg-rose-50','text-rose-700');
        alertBox.innerHTML = msg || '';
        if (htmlList) alertBox.innerHTML += htmlList;
        alertBox.classList.remove('hidden');
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        alertBox.classList.add('hidden');
        const fileInput = document.getElementById('excelFileInputPage');
        if (!fileInput.files || !fileInput.files[0]) { showAlert('error','الرجاء اختيار ملف.'); return; }

        const fd = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'import_excel.php', true);
        xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

        xhr.upload.onprogress = function(ev){
            if (ev.lengthComputable) {
                const pct = Math.round((ev.loaded/ev.total)*100);
                progress.classList.remove('hidden'); bar.style.width = pct + '%'; status.innerText = 'تحميل: ' + pct + '%';
            }
        };

        xhr.onload = function(){
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    let listHtml = '';
                    if (res.errors && res.errors.length) {
                        listHtml = '<ul class="mt-2 text-sm text-gray-700">' + res.errors.map(e=>'<li>'+e+'</li>').join('') + '</ul>';
                    }
                    showAlert('success', res.message || 'تم الاستيراد', listHtml);
                } else {
                    let listHtml = '';
                    if (res.errors && res.errors.length) listHtml = '<ul class="mt-2 text-sm text-gray-700">' + res.errors.map(e=>'<li>'+e+'</li>').join('') + '</ul>';
                    showAlert('error', res.message || 'فشل الاستيراد', listHtml);
                }
            } catch (err) {
                showAlert('error','استجابة غير صالحة من الخادم.');
            }
        };

        xhr.onerror = function(){ showAlert('error','حدث خطأ أثناء التحميل.'); };
        xhr.send(fd);
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
