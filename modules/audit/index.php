<?php
// Audit log list
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('audit', 'view');



require_once __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Reuse clean table styling from other modules for consistency */
    table.dataTable, table.dataTable th, table.dataTable td { border: none !important; outline: none !important; }
    .dataTables_wrapper { padding-top: 0; }
    .dataTables_filter, .dataTables_length { margin-bottom: 1rem; }
    .dataTables_filter label, .dataTables_length label { font-weight: 500; color: #64748b; font-size: 0.875rem; }
    .dataTables_filter input { border: 1px solid #e2e8f0 !important; border-radius: 10px !important; padding: 0.5rem 1rem !important; outline: none !important; transition: all 0.2s !important; background: #f8fafc !important; min-width: 260px; }
    .dataTables_filter input:focus { border-color: #1e4a76 !important; box-shadow: 0 0 0 3px rgba(30, 74, 118, 0.1) !important; background: #ffffff !important; }
    .dataTables_length select { border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 0.4rem 0.8rem !important; outline: none !important; background: #f8fafc !important; cursor: pointer; }
    table.dataTable thead th { background: #f8fafc !important; border-bottom: 2px solid #e2e8f0 !important; padding: 12px 16px !important; font-weight: 600 !important; color: #64748b !important; font-size: 0.8rem !important; text-transform: uppercase !important; letter-spacing: 0.03em !important; white-space: nowrap; }
    table.dataTable tbody td { padding: 14px 16px !important; border-bottom: 1px solid #f1f5f9 !important; color: #334155 !important; font-size: 0.875rem !important; vertical-align: middle !important; }
    table.dataTable tbody tr:hover { background-color: #f8fafc !important; }
    .dataTables_paginate { margin-top: 1.5rem !important; }
    .dt-buttons .dt-button { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.5rem 1rem; color: #334155; font-weight: 500; margin-left: 5px; transition: all 0.2s; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.03); }
    .dt-buttons .dt-button:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e4a76; }
    .action-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; text-decoration: none; }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-xl font-bold text-[#1e4a76]">عرض سجل النشاطات</h2>
        <p class="text-sm text-gray-500">عرض جميع سجلات الأنشطة المنفذة في النظام</p>
    </div>
    <div class="flex items-center gap-2">
       
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <table class="w-full" id="auditTable">
        <thead>
            <tr>
                <th>المستخدم</th>
                <th>الإجراء</th>
                <th>الجدول</th>
                <th>رقم السجل</th>
                <th>الوصف</th>
                <th>IP</th>
                <th>التاريخ</th>
                <th>إجراءات</th>
            </tr>
        </thead>
    </table>
</div>

<!-- Include jQuery & DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<!-- Buttons Extension -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#auditTable')) { $('#auditTable').DataTable().destroy(); }

    const table = $('#auditTable').DataTable({
        language: { search: "", searchPlaceholder: "ابحث في الوصف، الإجراء، المستخدم...", lengthMenu: 'عرض _MENU_ سجلاً', info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل', infoEmpty: 'عرض 0 إلى 0 من 0 مدخل', infoFiltered: '(منتقاة من _MAX_ إجمالي السجلات)', zeroRecords: 'لا توجد سجلات مطابقة', emptyTable: 'لا توجد بيانات متاحة في الجدول', paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' } },
        processing: true,
        serverSide: true,
        ajax: { url: 'list.php?ajax=1', type: 'GET' },
        columns: [
            { data: 'user_name' },
            { data: 'action' },
            { data: 'table_name' },
            { data: 'record_id' },
            { data: 'description' },
            { data: 'ip_address' },
            { data: 'created_at' },
            { data: 'id', orderable: false, searchable: false, render: function(data) {
                return `
                    <div class="flex items-center gap-1">
                        <a href="view.php?id=${data}" class="action-icon bg-blue-50 text-[#1e4a76] hover:bg-blue-100" title="عرض">
                            <i class="fas fa-eye text-xs"></i>
                        </a>
                        <button onclick="deleteAudit(${data})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                `;
            }}
        ],
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf><"mb-4 text-right"B>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>',
        buttons: [
            { text: '<i class="fas fa-file-csv ml-1 text-green-600"></i> تصدير CSV', className: 'btn-export no-print', action: function(){ window.location = 'index.php?export=csv'; } },
            { extend: 'print', text: '<i class="fas fa-print ml-1 text-gray-600"></i> طباعة', className: 'btn-export no-print', exportOptions: { columns: [0,1,2,3,4,5,6] }, customize: function (win) { try{ $(win.document.body).css({'font-size':'12px','direction':'rtl','text-align':'right'}); $(win.document.body).find('.dt-buttons, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate, .no-print').remove(); } catch(e){} } }
        ],
        order: [[6, 'desc']],
        pageLength: 15,
        lengthMenu: [[15,25,50,-1],[15,25,50,'الكل']]
    });
});

function deleteAudit(id) {
    if (!confirm('هل أنت متأكد من حذف هذا السجل؟')) return;

    fetch('delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            $('#auditTable').DataTable().ajax.reload(null, false);
            showToast(resp.message || 'تم الحذف', 'success');
        } else {
            showToast(resp.message || 'فشل الحذف', 'error');
        }
    })
    .catch(err => { console.error('Network error deleting audit:', err); });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
