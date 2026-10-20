<?php
// Projects list page
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

 $page_title = 'المشاريع - ' . APP_NAME;
 $current_module = 'projects';


require_permission('projects', 'view');
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
		white-space: nowrap !important;
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

	/* Clean Badges for Types */
	.type-badge {
		display: inline-block;
		padding: 4px 10px;
		border-radius: 6px;
		font-size: 0.75rem;
		font-weight: 600;
	}

	/* Action Buttons */
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

	/* Ensure table stretches properly */
	#projectsTable {
		width: 100% !important;
	}

	/* Print Styles */
	@media print {
		body * { visibility: visible; }
		.no-print, .no-print * { display: none !important; }
		.dataTables_length, .dataTables_filter, .dt-buttons, .dataTables_info, .dataTables_paginate { display: none !important; }
		.bg-white, .rounded-2xl { box-shadow: none !important; border: none !important; background: transparent !important; }
		#projectsTable { width: 100% !important; border-collapse: collapse !important; }
		#projectsTable thead th, #projectsTable tbody td { border: 1px solid #ddd !important; padding: 6px !important; }
		#projectsTable thead th { background: #f8fafc !important; }
		header, footer, nav, .sidebar { display: none !important; }
	}
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 flex items-center justify-between">
	<div>
		<h2 class="text-xl font-bold text-[#1e4a76]">قائمة المشاريع</h2>
		<p class="text-sm text-gray-500">إدارة المشاريع ومتابعة الحالات</p>
	</div>
	<div class="flex items-center gap-2">
		<a href="add.php" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63] transition shadow-sm shadow-[#1e4a76]/20">
			<i class="fas fa-plus ml-1"></i> مشروع جديد
		</a>
	</div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
	<table class="w-full" id="projectsTable">
		<thead>
			<tr>
				<th>المشروع</th>
				<th>تاريخ البدء</th>
				<th>تاريخ الانتهاء</th>
				<th>الحالة</th>
				<th>المسؤول</th>
				<th>تاريخ الإنشاء</th>
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

$(document).ready(function() {
	if ($.fn.DataTable.isDataTable('#projectsTable')) {
		$('#projectsTable').DataTable().destroy();
	}

	const table = $('#projectsTable').DataTable({
		language: {
			search: "", 
			searchPlaceholder: "ابحث عن مشروع أو وصف...",
			lengthMenu: 'عرض _MENU_ سجلاً',
			info: 'عرض _START_ إلى _END_ من _TOTAL_ مدخل',
			infoEmpty: 'عرض 0 إلى 0 من 0 مدخل',
			infoFiltered: '(منتقاة من _MAX_ إجمالي السجلات)',
			zeroRecords: 'لا توجد سجلات مطابقة',
			emptyTable: 'لا توجد بيانات متاحة في الجدول',
			paginate: {
				first: 'الأول',
				previous: 'السابق',
				next: 'التالي',
				last: 'الأخير'
			}
		},
		processing: true,
		serverSide: true,
		ajax: {
			url: 'list.php?ajax=1',
			type: 'GET'
		},
		columns: [
			{ 
				data: 'name',
				render: function(data, type, row) {
					return `<div class="font-semibold text-gray-800">${escapeHtml(data || '-')}</div><div class="text-sm text-gray-500">${escapeHtml(row.description ? (row.description.substr(0,120)) : '')}</div>`;
				}
			},
			{ data: 'start_date', render: function(data){ return data ? `<span class="text-gray-700">${escapeHtml(data)}</span>` : '<span class="text-gray-400">-</span>'; } },
			{ data: 'end_date', render: function(data){ return data ? `<span class="text-gray-700">${escapeHtml(data)}</span>` : '<span class="text-gray-400">-</span>'; } },
			{ 
				data: 'status',
				render: function(data) {
					const map = { 'planning':'تخطيط','in_progress':'قيد التنفيذ','completed':'مكتمل','on_hold':'متوقف','cancelled':'ملغى' };
					return `<span class="type-badge bg-gray-100 text-gray-700">${escapeHtml(map[data] || data)}</span>`;
				}
			},
			{ data: 'assigned_name', render: function(data){ return `<div class="text-gray-700">${escapeHtml(data || '-')}</div>`; } },
			{ data: 'created_at', render: function(data){ if(!data) return '<span class="text-gray-400">-</span>'; const dateOnly = data.split(' ')[0]; return `<span class="text-gray-500 text-sm">${escapeHtml(dateOnly)}</span>`; } },
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
							<button onclick="deleteProject(${data})" class="action-icon bg-rose-50 text-rose-500 hover:bg-rose-100" title="حذف">
								<i class="fas fa-trash text-xs"></i>
							</button>
						</div>
					`;
				}
			}
		],
		dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf><"mb-4 text-right"B>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>',
		buttons: [
			{
				extend: 'print',
				text: '<i class="fas fa-print ml-1 text-gray-600"></i> طباعة',
				className: 'btn-export no-print',
				exportOptions: {
					columns: [0, 1, 2, 3, 4, 5]
				},
				customize: function (win) {
					var title = '<div style="text-align:center; margin-bottom:20px;"><h2 style="margin:0;padding:0;font-size:18px;">' +
						'<?php echo addslashes(APP_NAME); ?> - قائمة المشاريع' + '</h2>' +
						'<div style="font-size:12px;margin-top:6px;">تاريخ الطباعة: ' + new Date().toLocaleString() + '</div></div>';
					try {
						$(win.document.body).css({'font-size': '12px', 'direction': 'rtl', 'text-align': 'right'});
						$(win.document.body).prepend(title);
						$(win.document.body).find('.dt-buttons, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate, .no-print').remove();
						$(win.document.body).find('table').css({'width':'100%','border-collapse':'collapse'});
						$(win.document.body).find('table thead th').css({'border':'1px solid #ddd','padding':'8px','background':'#f8fafc','color':'#333'});
						$(win.document.body).find('table tbody td').css({'border':'1px solid #eee','padding':'8px','color':'#333'});
						var style = win.document.createElement('style');
						style.innerHTML = '@page { size: A4 portrait; margin: 15mm; } body { font-family: Arial, sans-serif; }';
						win.document.head.appendChild(style);
					} catch(e) { console.warn('print customize failed', e); }
				}
			}
		],
		order: [[5, 'desc']],
		pageLength: 10,
		lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "الكل"]],
		initComplete: function() { try { this.api().columns.adjust(); } catch(e) {} }
	});
});

function deleteProject(id){
	if(!confirm('هل أنت متأكد من حذف هذا المشروع؟ هذا الإجراء لا يمكن التراجع عنه.')) return;
	$.post('delete.php', { id:id, csrf_token: CSRF_TOKEN }, function(resp){ try{ var j = typeof resp === 'string' ? JSON.parse(resp) : resp; if(j.success){ location.reload(); } else { alert(j.error || 'فشل الحذف'); } }catch(e){ alert('خطأ في الاستجابة'); } });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
