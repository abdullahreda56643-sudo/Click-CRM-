<?php
// Tickets list page
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

 $page_title = 'التذاكر - ' . APP_NAME;
 $current_module = 'tickets';

require_permission('tickets', 'view');
require_once __DIR__ . '/../../templates/header.php';
?>

<style>
    /* =========================================
       CLEAN & SIMPLE DATA TABLE STYLING
       ========================================= */
    
    /* Remove default borders */
    table.dataTable, 
    table.dataTable th, 
    table.dataTable td {
        border: none !important;
        outline: none !important;
    }

    .dataTables_wrapper {
        padding-top: 0;
    }

    /* Search & Length Controls */
    .dataTables_filter, .dataTables_length {
        margin-bottom: 1rem;
    }
    .dataTables_filter label, .dataTables_length label {
        font-weight: 500;
        color: #64748b;
        font-size: 0.875rem;
    }
    .dataTables_filter input {
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        padding: 0.5rem 1rem !important;
        outline: none !important;
        transition: all 0.2s !important;
        background: #f8fafc !important;
        min-width: 260px;
    }
    .dataTables_filter input:focus {
        border-color: #1e4a76 !important;
        box-shadow: 0 0 0 3px rgba(30, 74, 118, 0.1) !important;
        background: #ffffff !important;
    }
    .dataTables_length select {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 0.4rem 0.8rem !important;
        outline: none !important;
        background: #f8fafc !important;
        cursor: pointer;
    }

    /* Table Header */
    table.dataTable thead th {
        background: #f8fafc !important;
        border-bottom: 2px solid #e2e8f0 !important;
        padding: 12px 16px !important;
        font-weight: 600 !important;
        color: #64748b !important;
        font-size: 0.8rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.03em !important;
        white-space: nowrap;
    }

    /* Table Body Cells */
    table.dataTable tbody td {
        padding: 14px 16px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        color: #334155 !important;
        font-size: 0.875rem !important;
        vertical-align: middle !important;
    }
    
    /* Row Hover Effect */
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* Sorting Arrows */
    table.dataTable thead .sorting::after,
    table.dataTable thead .sorting_asc::after,
    table.dataTable thead .sorting_desc::after {
        opacity: 0.4 !important;
        color: #1e4a76 !important;
    }
    table.dataTable thead .sorting_asc::after,
    table.dataTable thead .sorting_desc::after {
        opacity: 0.8 !important;
    }

    /* Pagination */
    .dataTables_paginate {
        margin-top: 1.5rem !important;
    }
    .dataTables_paginate .paginate_button {
        border-radius: 8px !important;
        margin: 0 2px !important;
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        color: #334155 !important;
        padding: 0.4em 0.8em !important;
        transition: all 0.2s;
    }
    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        color: #1e4a76 !important;
        border-color: #cbd5e1 !important;
    }
    .dataTables_paginate .paginate_button.current {
        background: #1e4a76 !important;
        color: white !important;
        border-color: #1e4a76 !important;
    }
    .dataTables_paginate .paginate_button.disabled {
        color: #e2e8f0 !important;
    }

    .action-icon {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
        text-decoration: none;
        cursor: pointer;
        border: none;
        background: transparent;
    }
    .action-icon:hover { transform: translateY(-1px); }
</style>

<!-- Page Header (matches Deals module style) -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-bold text-[#1e4a76]">تذاكر الدعم</h2>
        <p class="text-sm text-gray-500">إدارة ومتابعة تذاكر العملاء بكفاءة واحترافية</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="add.php" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63] transition shadow-sm shadow-[#1e4a76]/20">
            <i class="fas fa-plus ml-1"></i> إنشاء تذكرة جديدة
        </a>
    </div>
</div>

<!-- DataTable Container -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 p-6">
    <table class="w-full" id="ticketsTable">
        <thead>
            <tr>
                <th>العنوان</th>
                <th>الجهة</th>
                <th>الأولوية</th>
                <th>الحالة</th>
                <th>المسؤول</th>
                <th>تاريخ الإنشاء</th>
                <th>إجراءات</th>
            </tr>
        </thead>
    </table>
</div>

<!-- Custom Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 transform transition-all scale-95 opacity-0" id="deleteModalContent">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 text-center mb-2">تأكيد الحذف</h3>
        <p class="text-gray-500 text-center text-sm mb-8">هل أنت متأكد من حذف هذه التذكرة؟ لا يمكن التراجع عن هذا الإجراء.</p>
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

<!-- DataTables libs -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
function escapeHtml(unsafe) { if (!unsafe) return ''; return String(unsafe).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }

// Mappers for Arabic translation and Styling
const statusMap = {
    'open': { text: 'مفتوحة', class: 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20' },
    'in_progress': { text: 'قيد التنفيذ', class: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' },
    'resolved': { text: 'محلولة', class: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' },
    'closed': { text: 'مغلقة', class: 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20' }
};

const priorityMap = {
    'low': { text: 'منخفضة', class: 'bg-gray-50 text-gray-600' },
    'medium': { text: 'متوسطة', class: 'bg-blue-50 text-[#1e4a76]' },
    'high': { text: 'عالية', class: 'bg-orange-50 text-orange-700' },
    'urgent': { text: 'عاجلة', class: 'bg-red-50 text-red-700' }
};

let deleteTargetId = null;
let dataTableInstance = null;

 $(function() {
    dataTableInstance = $('#ticketsTable').DataTable({
        ajax: 'list.php',
        language: {
            search: "", 
            searchPlaceholder: "ابحث عن تذكرة...",
            lengthMenu: 'عرض _MENU_ سجلاً',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل',
            infoEmpty: 'عرض 0 إلى 0 من 0 مدخل',
            zeroRecords: 'لا توجد سجلات مطابقة',
            emptyTable: 'لا توجد بيانات متاحة في الجدول',
            paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' }
        },
        columns: [
            { data: 'subject', render: function(d){ return `<div class="font-semibold text-gray-800">${escapeHtml(d)}</div>`; } },
            { data: 'contact_name', render: function(d){ return d ? `<span class="text-gray-600">${escapeHtml(d)}</span>` : '<span class="text-gray-400">غير محدد</span>'; } },
            { data: 'priority', render: function(d){ 
                const p = priorityMap[d] || { text: d || '-', class: 'bg-gray-50 text-gray-600' };
                return `<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ${p.class}">${p.text}</span>`;
            }},
            { data: 'status', render: function(d){ 
                const s = statusMap[d] || { text: d || '-', class: 'bg-gray-50 text-gray-600' };
                return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${s.class}">${s.text}</span>`;
            }},
            { data: 'assigned_name', render: function(d){ 
                if (!d) return '<span class="text-gray-400 text-sm">غير معين</span>';
                return `<div class="flex items-center">
                            <div class="w-6 h-6 rounded-full bg-[#1e4a76]/10 flex items-center justify-center ml-2">
                                <i class="fas fa-user text-[8px] text-[#1e4a76]"></i>
                            </div>
                            <span class="text-sm text-gray-700">${escapeHtml(d)}</span>
                        </div>`;
            }},
            { data: 'created_at', render: function(d){ return d ? `<span class="text-gray-500 text-sm font-medium">${d.split(' ')[0]}</span>` : '-'; } },
            { data: 'id', orderable:false, searchable:false, render: function(d){
                return `
                    <div class="flex items-center gap-2">
                        <a href="view.php?id=${d}" class="action-icon bg-blue-50 text-[#1e4a76] hover:bg-blue-100" title="عرض"><i class="fas fa-eye text-xs"></i></a>
                        <a href="edit.php?id=${d}" class="action-icon bg-amber-50 text-amber-600 hover:bg-amber-100" title="تعديل"><i class="fas fa-pen text-xs"></i></a>
                        <button onclick="deleteTicket(${d})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف"><i class="fas fa-trash text-xs"></i></button>
                    </div>`;
            }}
        ]
    });
});

// Delete Functions
window.deleteTicket = function(id) {
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
    const token = '<?php echo generate_csrf_token(); ?>';
    
    fetch('delete.php', { 
        method: 'POST', 
        headers: {'Content-Type':'application/json'}, 
        body: JSON.stringify({ id: deleteTargetId, csrf_token: token }) 
    })
    .then(r => {
        if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t); });
        return r.json();
    })
    .then(j => { 
        if (j.success) {
            closeDeleteModal();
            dataTableInstance.ajax.reload(); 
        } else {
            alert(j.message || 'حدث خطأ أثناء الحذف');
        }
    })
    .catch(e => {
        console.error('Delete request failed:', e);
        alert('خطأ في الاتصال بالخادم: ' + (e.message || ''));
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>