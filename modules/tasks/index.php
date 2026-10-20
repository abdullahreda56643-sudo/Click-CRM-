<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

 $page_title = 'المهام - ' . APP_NAME;
 $current_module = 'tasks';

require_permission('tasks', 'view');
// Server-side endpoint for DataTables
$db = get_db();
$current_user = get_current_user_id();
$access_where = get_accessible_records('tasks');

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // DataTables server-side params
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = $_GET['search']['value'] ?? '';
    $order_col = $_GET['order'][0]['column'] ?? 1;
    $order_dir = ($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    // Map column index to DB column (accounting for checkbox column at index 0)
    $cols = [
        0 => 't.id',
        1 => 't.title',
        2 => 't.due_date',
        3 => 't.priority',
        4 => 'u.full_name',
        5 => 't.status',
        6 => 't.created_at'
    ];
    $order_by = $cols[$order_col] ?? 't.due_date';

    // Build where and params for access control
    $where = '';
    $params = [];
    if ($access_where) {
        $where = ' ' . $access_where; // e.g. WHERE (assigned_to = ? OR created_by = ?)
        $qmarks = substr_count($where, '?');
        for ($i = 0; $i < $qmarks; $i++) $params[] = $current_user;
    }

    // Total records
    $total_sql = "SELECT COUNT(*) as cnt FROM tasks t" . ($where ? str_replace('WHERE', ' WHERE', $where) : '');
    $total_result = $db->fetch($total_sql, $params);
    $recordsTotal = intval($total_result['cnt'] ?? 0);

    // Filtering
    $filter_sql = "SELECT t.*, u.full_name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id" . ($where ?: '');
    $filter_params = $params;
    if (!empty($search)) {
        $filter_sql .= $where ? ' AND ' : ' WHERE ';
        $filter_sql .= "(t.title LIKE ? OR t.description LIKE ? OR u.full_name LIKE ? )";
        $sparam = "%{$search}%";
        $filter_params = array_merge($filter_params, [$sparam, $sparam, $sparam]);
    }

    // Count filtered
    $count_sql = "SELECT COUNT(*) as cnt FROM (" . $filter_sql . ") tmp";
    $count_result = $db->fetch($count_sql, $filter_params);
    $recordsFiltered = intval($count_result['cnt'] ?? 0);

    // Add ordering and limit
    $filter_sql .= " ORDER BY {$order_by} {$order_dir} LIMIT ?, ?";
    $filter_params[] = $start;
    $filter_params[] = $length;

    $rows = $db->fetchAll($filter_sql, $filter_params);

    // Output JSON
    $out = [
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ];
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

require_once __DIR__ . '/../../templates/header.php';
// load users for assign dropdown in bulk UI
$users = $db->fetchAll('SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name');
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

    /* Custom Badges & Buttons */
    .status-badge, .priority-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
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
    }
    .action-icon:hover {
        transform: translateY(-1px);
    }

    /* Print Styles */
    @media print {
        body * { visibility: visible; }
        .no-print, .no-print * { display: none !important; }
        .dataTables_length, .dataTables_filter, .dt-buttons, .dataTables_info, .dataTables_paginate { display: none !important; }
        .bg-white, .rounded-2xl { box-shadow: none !important; border: none !important; background: transparent !important; }
        #tasksTable { width: 100% !important; border-collapse: collapse !important; }
        #tasksTable thead th, #tasksTable tbody td { border: 1px solid #ddd !important; padding: 6px !important; }
        #tasksTable thead th { background: #f8fafc !important; }
        header, footer, nav, .sidebar { display: none !important; }
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-bold text-[#1e4a76]">قائمة المهام</h2>
        <p class="text-sm text-gray-500">إدارة وتتبع المهام والأنشطة اليومية</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="add.php" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63] transition shadow-sm shadow-[#1e4a76]/20">
            <i class="fas fa-plus ml-1"></i> إضافة مهمة جديدة
        </a>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <button id="selectAllBtn" class="px-3 py-2 bg-gray-100 rounded-lg">تحديد الكل</button>
            <div class="flex items-center gap-2">
                <button id="bulkChangeStatusBtn" class="px-3 py-2 bg-blue-50 text-blue-700 rounded-lg">تغيير الحالة</button>
                <button id="bulkAssignBtn" class="px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg">تعيين</button>
                <button id="bulkDeleteBtn" class="px-3 py-2 bg-rose-50 text-rose-700 rounded-lg">حذف</button>
            </div>
        </div>
        
    </div>

    <table class="w-full" id="tasksTable">
        <thead>
            <tr>
                <th><input type="checkbox" id="masterCheckbox" /></th>
                <th>العنوان</th>
                <th>تاريخ الاستحقاق</th>
                <th>الأولوية</th>
                <th>المسؤول</th>
                <th>الحالة</th>
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
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Users for assign dropdown
const usersList = <?php echo json_encode($users); ?> || [];

// Status and Priority Configs
const statusConfig = {
    pending: { label: 'معلقة', bg: 'bg-gray-100', text: 'text-gray-700' },
    in_progress: { label: 'قيد التنفيذ', bg: 'bg-blue-100', text: 'text-blue-700' },
    completed: { label: 'مكتملة', bg: 'bg-green-100', text: 'text-green-700' },
    cancelled: { label: 'ملغاة', bg: 'bg-red-100', text: 'text-red-700' }
};

const priorityConfig = {
    high: { label: 'عالية', bg: 'bg-red-100', text: 'text-red-700' },
    medium: { label: 'متوسطة', bg: 'bg-yellow-100', text: 'text-yellow-700' },
    low: { label: 'منخفضة', bg: 'bg-blue-100', text: 'text-blue-700' }
};

 $(document).ready(function() {
    const table = $('#tasksTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: 'index.php?ajax=1',
            type: 'GET'
        },
        language: {
            search: "", 
            searchPlaceholder: "ابحث عن مهمة...",
            lengthMenu: 'عرض _MENU_ سجلاً',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل',
            infoEmpty: 'عرض 0 إلى 0 من 0 مدخل',
            zeroRecords: 'لا توجد سجلات مطابقة',
            emptyTable: 'لا توجد بيانات متاحة في الجدول',
            paginate: {
                first: 'الأول',
                previous: 'السابق',
                next: 'التالي',
                last: 'الأخير'
            }
        },
        columns: [
            {
                data: 'id', orderable: false, searchable: false,
                render: function(data) { return `<input type="checkbox" class="row-checkbox" value="${data}" />`; }
            },
            { 
                data: 'title', 
                render: function(data, type, row) {
                    const icon = row.status === 'completed' ? '<i class="fas fa-check-circle text-green-500 ml-2"></i>' : '';
                    return `<div class="font-semibold text-gray-800">${icon}${escapeHtml(data)}</div>`;
                }
            },
            { 
                data: 'due_date', 
                render: function(data) {
                    if (!data) return '<span class="text-gray-400">-</span>';
                    // Simple highlight for overdue tasks (if date is past and not completed)
                    return `<span class="text-gray-600 text-sm">${escapeHtml(data)}</span>`;
                }
            },
            { 
                data: 'priority', 
                render: function(data) {
                    const cfg = priorityConfig[data] || { label: data, bg: 'bg-gray-100', text: 'text-gray-700' };
                    return `<span class="priority-badge ${cfg.bg} ${cfg.text}">${cfg.label}</span>`;
                }
            },
            { 
                data: 'assigned_name', 
                render: function(data) {
                    return data ? `<span class="text-gray-700">${escapeHtml(data)}</span>` : '<span class="text-gray-400">غير معين</span>';
                }
            },
            { 
                data: 'status', 
                render: function(data) {
                    const cfg = statusConfig[data] || { label: data, bg: 'bg-gray-100', text: 'text-gray-700' };
                    return `<span class="status-badge ${cfg.bg} ${cfg.text}">${cfg.label}</span>`;
                }
            },
            { 
                data: 'id', 
                orderable: false, 
                searchable: false,
                render: function(data, type, row) {
                    return `
                        <div class="flex items-center gap-1">
                            <a href="view.php?id=${data}" class="action-icon bg-blue-50 text-[#1e4a76] hover:bg-blue-100" title="عرض">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="edit.php?id=${data}" class="action-icon bg-yellow-50 text-yellow-600 hover:bg-yellow-100" title="تعديل">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button onclick="deleteTask(${data})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        // Buttons placed under the search bar
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf><"mb-4 text-right"B>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>',
        buttons: [
            {
                extend: 'print',
                text: '<i class="fas fa-print ml-1 text-gray-600"></i> طباعة',
                className: 'btn-export no-print',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                }
            }
        ],
        order: [[1, 'asc']], // Default sort by due date ascending (nearest first)
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]],
        initComplete: function() { try { this.api().columns.adjust(); } catch(e) {} },
        drawCallback: function() {
            // ensure master checkbox behavior
            $('#masterCheckbox').prop('checked', false);
        }
    });

    // Master checkbox
    $(document).on('change', '#masterCheckbox', function() {
        const checked = $(this).is(':checked');
        $('#tasksTable').find('.row-checkbox').prop('checked', checked);
    });

    // Select All button
    $('#selectAllBtn').on('click', function() {
        const anyUnchecked = $('#tasksTable').find('.row-checkbox:not(:checked)').length > 0;
        $('#tasksTable').find('.row-checkbox').prop('checked', anyUnchecked);
    });

    function getSelectedIds() {
        const ids = [];
        $('#tasksTable').find('.row-checkbox:checked').each(function() { ids.push(parseInt($(this).val())); });
        return ids;
    }

    async function postBulk(action, value, ids) {
        if (!ids || ids.length === 0) { alert('الرجاء اختيار عناصر أولاً'); return; }
        const payload = { action: action, value: value, ids: ids, csrf_token: CSRF_TOKEN };
        const resp = await fetch('bulk.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const j = await resp.json();
        if (j.success) { alert(j.message); table.ajax.reload(); } else { alert(j.message || 'خطأ'); }
    }

    // Bulk handlers
    $('#bulkChangeStatusBtn').on('click', function() {
        const ids = getSelectedIds();
        const val = prompt('أدخل الحالة الجديدة (pending, in_progress, completed, cancelled):', 'pending');
        if (val) postBulk('change_status', val, ids);
    });

    $('#bulkAssignBtn').on('click', function() {
        const ids = getSelectedIds();
        // simple prompt - allow entering user id; for convenience, show list
        let opts = usersList.map(u => `${u.id}: ${u.full_name}`).join('\n');
        const val = prompt('أدخل معرف المستخدم لتعيينه:\n' + opts);
        if (val) postBulk('assign_to', parseInt(val), ids);
    });

    $('#bulkDeleteBtn').on('click', function() {
        if (!confirm('هل أنت متأكد من حذف العناصر المحددة؟')) return;
        const ids = getSelectedIds();
        postBulk('delete', null, ids);
    });
});

// JavaScript-driven Delete Function for better UX
function deleteTask(id) {
    if (!confirm('هل أنت متأكد من حذف هذه المهمة؟')) return;
    
    // Using standard redirect for simplicity since original code used GET for delete.
    // If you have a POST endpoint for delete, use fetch() like in contacts/deals.
    window.location.href = `delete.php?id=${id}&csrf_token=${CSRF_TOKEN}`;
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>