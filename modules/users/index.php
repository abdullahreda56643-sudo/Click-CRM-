<?php
// Users list page
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('users', 'view');



require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-xl font-bold text-[#1e4a76]">قائمة المستخدمين</h2>
        <p class="text-sm text-gray-500">إدارة حسابات النظام والصلاحيات</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if (check_permission('users','add')): ?>
        <a href="add.php" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63] transition shadow-sm shadow-[#1e4a76]/20">
            <i class="fas fa-user-plus ml-1"></i> إضافة مستخدم
        </a>
        <?php endif; ?>
        <?php if (check_permission('users','edit')): ?>
        <a href="roles.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">إدارة الأدوار</a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <table class="w-full" id="usersTable">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>اسم المستخدم</th>
                <th>البريد الإلكتروني</th>
                <th>الدور</th>
                <th>نشط</th>
                <th>تاريخ الإنشاء</th>
                <th>إجراءات</th>
            </tr>
        </thead>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable().destroy();
    }

    const table = $('#usersTable').DataTable({
        language: {
            search: "",
            searchPlaceholder: "ابحث عن اسم، اسم مستخدم، بريد...",
            lengthMenu: 'عرض _MENU_ سجلاً',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل',
            infoEmpty: 'عرض 0 إلى 0 من 0 مدخل',
            infoFiltered: '(منتقاة من _MAX_ إجمالي السجلات)',
            zeroRecords: 'لا توجد سجلات مطابقة',
            emptyTable: 'لا توجد بيانات متاحة في الجدول',
            paginate: {
                first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير'
            }
        },
        processing: true,
        serverSide: true,
        ajax: { url: 'list.php?ajax=1', type: 'GET' },
        columns: [
            { data: null, render: function(data){
                const name = escapeHtml(data.full_name || data.username);
                return `<div class="font-semibold text-gray-800"><a href="view.php?id=${data.id}" class="text-[#1e4a76] hover:underline">${name}</a></div>`;
            } },
            { data: 'username', render: function(d){ return `<div class="text-gray-700">${escapeHtml(d)}</div>`; } },
            { data: 'email', render: function(d){ return d ? `<a href="mailto:${escapeHtml(d)}" class="text-[#1e4a76] hover:underline">${escapeHtml(d)}</a>` : '<span class="text-gray-400">-</span>'; } },
            { data: 'role', render: function(d){ return `<div class="text-gray-700">${escapeHtml(d || '-')}</div>`; } },
            { data: 'is_active', render: function(d){ return d == 1 ? '<span class="text-green-600">نعم</span>' : '<span class="text-gray-400">لا</span>'; } },
            { data: 'created_at', render: function(d){ if(!d) return '-'; return `<span class="text-gray-500 text-sm">${escapeHtml(d.split(' ')[0])}</span>`; } },
            { data: 'id', orderable:false, searchable:false, render: function(d,type,row){
                return `
                    <div class="flex items-center gap-1">
                        <a href="view.php?id=${d}" class="action-icon bg-blue-50 text-[#1e4a76] hover:bg-blue-100" title="عرض"><i class="fas fa-eye text-xs"></i></a>
                        ${<?php echo check_permission('users','edit')? 'true':'false'; ?> ? `<a href="edit.php?id=${d}" class="action-icon bg-yellow-50 text-yellow-600 hover:bg-yellow-100" title="تعديل"><i class="fas fa-pen text-xs"></i></a>` : ''}
                        ${<?php echo check_permission('users','delete')? 'true':'false'; ?> ? `<button onclick="deleteUser(${d})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف"><i class="fas fa-trash text-xs"></i></button>` : ''}
                    </div>
                `;
            } }
        ],
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf><"mb-4 text-right"B>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>',
        buttons: [
            {
                text: '<i class="fas fa-file-excel ml-1 text-green-600"></i> تصدير إكسل',
                className: 'btn-export no-print',
                action: function(){ window.location = 'export_excel.php'; }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print ml-1 text-gray-600"></i> طباعة',
                className: 'btn-export no-print',
                exportOptions: { columns: [0,1,2,3,4,5] },
                customize: function (win) {
                    try {
                        $(win.document.body).css({'font-size':'12px','direction':'rtl','text-align':'right'});
                        $(win.document.body).find('.dt-buttons, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate, .no-print').remove();
                        $(win.document.body).find('table').css({'width':'100%','border-collapse':'collapse'});
                    } catch(e) {}
                }
            }
        ],
        order: [[5,'desc']],
        pageLength: 10,
        lengthMenu: [[10,25,50,-1],[10,25,50,'الكل']],
        initComplete: function(){ try{ this.api().columns.adjust(); } catch(e){} }
    });

});

function deleteUser(id) {
    if (!confirm('هل أنت متأكد من حذف المستخدم؟')) return;

    fetch('delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            $('#usersTable').DataTable().ajax.reload(null,false);
            showToast(resp.message || 'تم الحذف بنجاح', 'success');
        } else {
            showToast(resp.message || 'فشل في حذف المستخدم', 'error');
        }
    })
    .catch(err => console.error('Network error deleting user:', err));
}
</script>

<!-- Buttons Extension -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
