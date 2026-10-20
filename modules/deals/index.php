<?php
// Deals list page
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

 $page_title = 'الصفقات - ' . APP_NAME;
 $current_module = 'deals';

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

    /* Export Buttons Container */
    .dt-buttons {
        margin-bottom: 1rem;
    }
    .dt-buttons .dt-button {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.5rem 1rem;
        color: #334155;
        font-weight: 500;
        margin-left: 5px;
        transition: all 0.2s;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
    }
    .dt-buttons .dt-button:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e4a76;
    }

    /* Custom Components */
    .filter-input { 
        border: 1px solid #e2e8f0; 
        border-radius: 10px; 
        padding: 0.5rem 1rem; 
        background: #f8fafc; 
        outline: none; 
        transition: all 0.2s; 
        font-size: 0.875rem; 
    }
    .filter-input:focus { 
        border-color: #1e4a76; 
        box-shadow: 0 0 0 3px rgba(30,74,118,0.1); 
        background: #fff; 
    }
    
    .stage-badge { 
        display: inline-block;
        padding: 4px 10px; 
        border-radius: 6px; 
        font-size: 0.75rem; 
        font-weight: 600; 
    }
    .progress-bar-bg { background: #e2e8f0; height: 6px; border-radius: 3px; width: 60px; display: inline-block; vertical-align: middle; }
    .progress-bar-fill { height: 100%; border-radius: 3px; }

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

    /* Kanban Styles */
    .kanban-col { background: #f8fafc; border-radius: 12px; padding: 1rem; min-height: 200px; border: 1px solid #f1f5f9; }
    .kanban-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 0.75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.2s; }
    .kanban-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.04); transform: translateY(-1px); }

    #dealsTable { width: 100% !important; }

    /* Print Styles */
    @media print {
        body * { visibility: visible; }
        .no-print, .no-print * { display: none !important; }
        .dataTables_length, .dataTables_filter, .dt-buttons, .dataTables_info, .dataTables_paginate, .toolbar-section { display: none !important; }
        .bg-white, .rounded-2xl { box-shadow: none !important; border: none !important; background: transparent !important; }
        #dealsTable { width: 100% !important; border-collapse: collapse !important; }
        #dealsTable thead th, #dealsTable tbody td { border: 1px solid #ddd !important; padding: 6px !important; }
        #dealsTable thead th { background: #f8fafc !important; }
        header, footer, nav, .sidebar { display: none !important; }
    }
</style>

<!-- Page Header -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-bold text-[#1e4a76]">قائمة الصفقات</h2>
        <p class="text-sm text-gray-500">إدارة الصفقات المتوقعة والمغلقة</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="add.php" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63] transition shadow-sm shadow-[#1e4a76]/20">
            <i class="fas fa-plus ml-1"></i> إضافة صفقة جديدة
        </a>
    </div>
</div>

<!-- Dashboard Widgets -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6" id="widgetsContainer">
    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-[#1e4a76]/10 flex items-center justify-center text-[#1e4a76]"><i class="fas fa-coins"></i></div>
        <div><div class="text-xs text-gray-400 font-semibold">القيمة الإجمالية</div><div class="text-lg font-bold text-gray-800" id="widgetTotal">0</div></div>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600"><i class="fas fa-chart-line"></i></div>
        <div><div class="text-xs text-gray-400 font-semibold">متوسط الصفقة</div><div class="text-lg font-bold text-gray-800" id="widgetAvg">0</div></div>
    </div>
    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600"><i class="fas fa-layer-group"></i></div>
        <div><div class="text-xs text-gray-400 font-semibold">عدد الصفقات</div><div class="text-lg font-bold text-gray-800" id="widgetCount">0</div></div>
    </div>
</div>

<!-- Toolbar: Filters & Actions -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6 toolbar-section no-print">
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        <!-- View Toggle -->
        <div class="flex items-center bg-gray-100 rounded-lg p-1">
            <button id="btnTableView" class="px-3 py-1.5 rounded-md text-sm font-semibold bg-white shadow-sm text-[#1e4a76]"><i class="fas fa-table ml-1"></i> جدول</button>
            <button id="btnKanbanView" class="px-3 py-1.5 rounded-md text-sm font-semibold text-gray-500 hover:text-gray-700"><i class="fas fa-columns ml-1"></i> كانبان</button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <select id="filterStage" class="filter-input"><option value="">كل المراحل</option><option value="prospecting">اكتشاف</option><option value="qualification">تأهيل</option><option value="proposal">عرض</option><option value="negotiation">تفاوض</option><option value="closed_won">فائزة</option><option value="closed_lost">خاسرة</option></select>
            <input id="filterMin" type="number" placeholder="الحد الأدنى" class="filter-input w-28" />
            <input id="filterMax" type="number" placeholder="الحد الأقصى" class="filter-input w-28" />
            <select id="filterAssigned" class="filter-input"><option value="">كل المسؤولين</option></select>
            <input id="filterCloseDate" type="date" class="filter-input" />
            <button id="btnRefresh" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-600"><i class="fas fa-sync-alt"></i></button>
        </div>

        <!-- Bulk & Import -->
        <div class="flex items-center gap-2">
            <select id="bulkAction" class="filter-input"><option value="">إجراءات جماعية</option><option value="change_stage">تغيير المرحلة</option><option value="assign_to">تعيين المسؤول</option><option value="delete">حذف</option></select>
            <button id="runBulk" class="px-3 py-2 bg-[#1e4a76] text-white rounded-lg text-sm font-semibold hover:bg-[#163d63]">تطبيق</button>
            <form id="importForm" method="POST" action="<?php echo base_url('modules/deals/import_excel.php'); ?>" enctype="multipart/form-data" class="flex items-center gap-2">
                <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" class="text-sm" />
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />
                <button type="submit" class="px-3 py-2 bg-gray-100 rounded-lg text-sm font-semibold hover:bg-gray-200 text-gray-700"><i class="fas fa-file-excel ml-1 text-emerald-600"></i> استيراد</button>
                <!-- download template links removed per request -->
            </form>
        </div>
    </div>
</div>

<!-- Kanban Board (Hidden by default) -->
<div id="kanbanBoard" class="hidden mb-6"></div>

<!-- DataTable -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <table class="w-full" id="dealsTable">
        <thead>
            <tr>
                <th>العنوان</th>
                <th>جهة الاتصال</th>
                <th>المبلغ</th>
                <th>المرحلة</th>
                <th>تاريخ الإغلاق</th>
                <th>الاحتمال</th>
                <th>تاريخ الإنشاء</th>
                <th>إجراءات</th>
            </tr>
        </thead>
    </table>
</div>

<!-- DataTables + Buttons -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
const LIST_URL = '<?php echo base_url("modules/deals/list.php"); ?>';
const BULK_URL = '<?php echo base_url("modules/deals/bulk.php"); ?>';
const DELETE_URL = '<?php echo base_url("modules/deals/delete.php"); ?>';
function escapeHtml(unsafe) { if (!unsafe) return ''; return String(unsafe).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

const stageConfig = {
    prospecting: { label: 'اكتشاف', bg: 'bg-blue-100', text: 'text-blue-700', bar: 'bg-blue-500' },
    qualification: { label: 'تأهيل', bg: 'bg-indigo-100', text: 'text-indigo-700', bar: 'bg-indigo-500' },
    proposal: { label: 'عرض', bg: 'bg-amber-100', text: 'text-amber-700', bar: 'bg-amber-500' },
    negotiation: { label: 'تفاوض', bg: 'bg-purple-100', text: 'text-purple-700', bar: 'bg-purple-500' },
    closed_won: { label: 'فائزة', bg: 'bg-green-100', text: 'text-green-700', bar: 'bg-green-500' },
    closed_lost: { label: 'خاسرة', bg: 'bg-rose-100', text: 'text-rose-700', bar: 'bg-rose-500' }
};

// Robust JSON response parser: prefer response.json(), fall back to text parse with BOM trimming
async function parseJSONResponse(response) {
    try {
        return await response.json();
    } catch (e) {
        try {
            let text = await response.text();
            text = text.replace(/^[\uFEFF\u200B]+/, '').trim();
            return JSON.parse(text);
        } catch (ex) {
            return null;
        }
    }
}

 $(document).ready(function() {
    let allDeals = [];
    let table = null;

    function renderWidgets(rows) {
        let total = 0, count = rows.length;
        rows.forEach(r => { if (r.amount) total += parseFloat(r.amount); });
        const avg = count ? (total / count) : 0;
        $('#widgetTotal').text(total.toLocaleString() + ' ج.م');
        $('#widgetAvg').text(avg.toFixed(2) + ' ج.م');
        $('#widgetCount').text(count);
    }

    function populateAssigned(rows) {
        const map = {};
        rows.forEach(r => { if (r.assigned_name) map[r.assigned_name + '||' + (r.assigned_to||'')] = {id: r.assigned_to, name: r.assigned_name}; });
        let opts = '<option value="">كل المسؤولين</option>';
        Object.values(map).forEach(u => { opts += `<option value="${u.id}">${escapeHtml(u.name)}</option>`; });
        $('#filterAssigned').html(opts);
    }

    function loadDeals(callback) {
        $.getJSON(LIST_URL).done(function(data){
            allDeals = data;
            populateAssigned(allDeals);
            if (callback) callback(allDeals);
        }).fail(function(jqxhr, textStatus, error){ console.error('Failed loading deals:', textStatus, error); /* suppressed user-facing toast to avoid false-positive network errors */ });
    }

    

    function applyFilters(rows) {
        const stage = $('#filterStage').val();
        const min = parseFloat($('#filterMin').val() || 0);
        const max = parseFloat($('#filterMax').val() || 0) || Infinity;
        const assigned = $('#filterAssigned').val();
        const closeDate = $('#filterCloseDate').val();
        return rows.filter(r => {
            if (stage && r.stage !== stage) return false;
            const amt = parseFloat(r.amount || 0);
            if ($('#filterMin').val() && amt < min) return false;
            if ($('#filterMax').val() && amt > max) return false;
            if (assigned && String(r.assigned_to) !== String(assigned)) return false;
            if (closeDate && r.expected_close_date !== closeDate) return false;
            return true;
        });
    }

    function updateView(rows) {
        renderWidgets(rows);
        if ($('#dealsTable').is(':visible')) {
             renderTable(rows);
        } else {
             renderKanban(rows);
        }
    }

    function renderKanban(rows) {
        let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
        Object.keys(stageConfig).forEach(stage => {
            const cfg = stageConfig[stage];
            const colItems = rows.filter(r => r.stage === stage);
            html += `<div class="kanban-col">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200">
                            <h4 class="font-bold text-gray-700">${cfg.label}</h4>
                            <span class="text-xs font-bold px-2 py-1 rounded-md ${cfg.bg} ${cfg.text}">${colItems.length}</span>
                        </div>`;
            colItems.forEach(it => {
                html += `<div class="kanban-card">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-semibold text-gray-800 text-sm">${escapeHtml(it.title)}</div>
                                <div class="text-sm font-bold text-[#1e4a76]">${it.amount ? escapeHtml(it.amount) + ' ج.م' : '-'}</div>
                            </div>
                            <div class="text-xs text-gray-500 mb-3"><i class="fas fa-user ml-1"></i>${escapeHtml(it.contact_name||'غير معين')}</div>
                            <div class="flex gap-2 border-t border-gray-100 pt-2 mt-1">
                                <a href="view.php?id=${it.id}" class="text-xs text-[#1e4a76] hover:underline ml-2">عرض</a>
                                <a href="edit.php?id=${it.id}" class="text-xs text-amber-600 hover:underline ml-2">تعديل</a>
                            </div>
                        </div>`;
            });
            html += `</div>`;
        });
        html += '</div>';
        $('#kanbanBoard').html(html);
    }

    function renderTable(rows) {
        if (table) { table.clear().rows.add(rows).draw(); return; }
        table = $('#dealsTable').DataTable({
            data: rows,
            language: {
                search: "", 
                searchPlaceholder: "ابحث عن صفقة...",
                lengthMenu: 'عرض _MENU_ سجلاً',
                info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل',
                infoEmpty: 'عرض 0 إلى 0 من 0 مدخل',
                zeroRecords: 'لا توجد سجلات مطابقة',
                emptyTable: 'لا توجد بيانات متاحة في الجدول',
                paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' }
            },
            columns: [
                { data: 'title', render: d => `<div class="font-semibold text-gray-800">${escapeHtml(d)}</div>` },
                { data: 'contact_name', render: d => d ? `<div class="text-gray-600">${escapeHtml(d)}</div>` : '-' },
                { data: 'amount', render: d => d ? `<span class="font-bold text-[#1e4a76]">${parseFloat(d).toLocaleString()} <span class="text-xs text-gray-400">ج.م</span></span>` : '-' },
                { data: 'stage', render: d => {
                    const cfg = stageConfig[d] || { label: d, bg: 'bg-gray-100', text: 'text-gray-700' };
                    return `<span class="stage-badge ${cfg.bg} ${cfg.text}">${cfg.label}</span>`;
                }},
                { data: 'expected_close_date', render: d => d ? `<span class="text-gray-500 text-sm">${escapeHtml(d)}</span>` : '-' },
                { data: 'probability', render: d => {
                    const val = d ? parseInt(d) : 0;
                    const color = val >= 70 ? 'bg-green-500' : val >= 40 ? 'bg-amber-500' : 'bg-rose-500';
                    return `<div class="flex items-center gap-2"><div class="progress-bar-bg"><div class="progress-bar-fill ${color}" style="width: ${val}%"></div></div><span class="text-xs font-bold text-gray-500">${val}%</span></div>`;
                }},
                { data: 'created_at', render: d => d ? `<span class="text-gray-400 text-sm">${d.split(' ')[0]}</span>` : '-' },
                { data: 'id', orderable: false, searchable: false, render: function(data){
                    return `
                        <div class="flex items-center gap-1">
                            <input type="checkbox" class="bulk-check h-4 w-4 rounded border-gray-300 text-[#1e4a76] focus:ring-[#1e4a76] ml-1" data-id="${data}" />
                            <a href="view.php?id=${data}" class="action-icon bg-blue-50 text-[#1e4a76] hover:bg-blue-100" title="عرض"><i class="fas fa-eye text-xs"></i></a>
                            <a href="edit.php?id=${data}" class="action-icon bg-yellow-50 text-yellow-600 hover:bg-yellow-100" title="تعديل"><i class="fas fa-pen text-xs"></i></a>
                            <button onclick="deleteDeal(${data})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف"><i class="fas fa-trash text-xs"></i></button>
                            <button onclick="quickClose(${data}, 'closed_won')" class="action-icon bg-emerald-50 text-emerald-500 hover:bg-emerald-100" title="إغلاق فائز"><i class="fas fa-trophy text-xs"></i></button>
                            <button onclick="quickClose(${data}, 'closed_lost')" class="action-icon bg-red-50 text-red-400 hover:bg-red-100" title="إغلاق خاسر"><i class="fas fa-times-circle text-xs"></i></button>
                        </div>`;
                }}
            ],
            // Buttons placed under the search bar
            dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf><"mb-4 text-right"B>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>',
            buttons: [
                { extend: 'excel', text: '<i class="fas fa-file-excel ml-1 text-green-600"></i> تصدير إكسل', className: 'btn-export no-print' },
                { extend: 'print', text: '<i class="fas fa-print ml-1 text-gray-600"></i> طباعة', className: 'btn-export no-print' }
            ],
            order: [[6, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]],
            initComplete: function() {
                try { this.api().columns.adjust(); } catch(e) {}
            }
        });
    }

    // Initial Load
    loadDeals(function(data){ updateView(data); });

    // View Toggle
    $('#btnTableView, #btnKanbanView').on('click', function(){
        if($(this).attr('id') === 'btnTableView') {
            $('#dealsTable').show(); $('#kanbanBoard').hide();
            $('#btnTableView').addClass('bg-white shadow-sm text-[#1e4a76]').removeClass('text-gray-500');
            $('#btnKanbanView').removeClass('bg-white shadow-sm text-[#1e4a76]').addClass('text-gray-500');
        } else {
            $('#dealsTable').hide(); $('#kanbanBoard').removeClass('hidden').show();
            $('#btnKanbanView').addClass('bg-white shadow-sm text-[#1e4a76]').removeClass('text-gray-500');
            $('#btnTableView').removeClass('bg-white shadow-sm text-[#1e4a76]').addClass('text-gray-500');
        }
        updateView(applyFilters(allDeals));
    });

    // Filters Events
    $('#btnRefresh, #filterStage, #filterMin, #filterMax, #filterAssigned, #filterCloseDate').on('click change keyup', function(){
        updateView(applyFilters(allDeals));
    });

    // Bulk Actions
    $('#runBulk').on('click', function(){ 
        const action = $('#bulkAction').val(); 
        const ids = $('.bulk-check:checked').map(function(){ return $(this).data('id'); }).get(); 
        if (!action || !ids.length) { showToast('اختر إجراء وحدد العناصر أولاً', 'error'); return; } 
        let payload = { action: action, ids: ids, csrf_token: CSRF_TOKEN }; 
        if (action==='change_stage') { const ns = prompt('أدخل قيمة المرحلة الجديدة (مثال: proposal)'); if (!ns) return; payload.value = ns; } 
        if (action==='assign_to') { const uid = prompt('أدخل رقم المعرف للمستخدم المسؤول'); if (!uid) return; payload.value = uid; }
        fetch(BULK_URL, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) })
            .then(async r => {
                const resp = await parseJSONResponse(r);
                if (resp) { if (resp.success) { showToast(resp.message||'تم التنفيذ', 'success'); loadDeals(data => updateView(applyFilters(data))); } else showToast(resp.message||'فشل', 'error'); }
                else { console.error('Invalid JSON from BULK_URL: (unparsable)'); }
            }).catch(e=> { console.error('Network error BULK_URL:', e); }); 
    });

    // Expose helpers globally so functions outside the ready block can use them
    window.loadDeals = loadDeals;
    window.updateView = updateView;
    window.applyFilters = applyFilters;
});

function deleteDeal(id) {
    if (!confirm('هل أنت متأكد من حذف الصفقة؟')) return;
    fetch(DELETE_URL, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN }) })
        .then(async r => {
            const resp = await parseJSONResponse(r);
            if (resp) { if (resp.success) { showToast(resp.message || 'تم الحذف', 'success'); loadDeals(data => updateView(applyFilters(data))); } else { showToast(resp.message || 'فشل الحذف', 'error'); } }
            else { console.error('Invalid JSON from DELETE_URL: (unparsable)'); }
        })
        .catch(e => { console.error('Network error DELETE_URL:', e); });
}

function quickClose(id, stage) {
    if(!confirm(`هل تريد إغلاق هذه الصفقة كـ ${stage === 'closed_won' ? 'فائزة' : 'خاسرة'}؟`)) return;
    fetch(BULK_URL, { 
        method: 'POST', 
        headers: {'Content-Type':'application/json'}, 
        body: JSON.stringify({ action: 'change_stage', ids: [id], value: stage, csrf_token: CSRF_TOKEN }) 
    })
    .then(async r => {
        const resp = await parseJSONResponse(r);
        if (resp) { if (resp.success) { showToast('تم تحديث المرحلة', 'success'); loadDeals(data => updateView(applyFilters(data))); } else showToast(resp.message || 'فشل التحديث', 'error'); }
        else { console.error('Invalid JSON from BULK_URL (quickClose): (unparsable)'); }
    })
    .catch(e => { console.error('Network error BULK_URL (quickClose):', e); });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>