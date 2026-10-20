<?php
// Reports index
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('reports', 'view');



require_once __DIR__ . '/../../templates/header.php';

// fetch users for filters
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }
?>

<style>
    /* =========================================
       CUSTOM REPORT TABLE STYLING
       ========================================= */
    #reportTable { width: 100% !important; border-collapse: collapse; }
    #reportTable th, #reportTable td { 
        padding: 14px 16px; 
        border-bottom: 1px solid #f1f5f9; 
        text-align: right; 
        font-size: 0.875rem; 
        color: #334155; 
        border: none !important;
    }
    #reportTable th { 
        background: #f8fafc; 
        border-bottom: 2px solid #e2e8f0; 
        font-weight: 600; 
        color: #64748b; 
        font-size: 0.8rem; 
        text-transform: uppercase; 
        letter-spacing: 0.03em; 
        white-space: nowrap;
    }
    #reportTable tbody tr:hover { 
        background-color: #f8fafc !important; 
    }
    
    .form-input-advanced { 
        border: 1px solid #e2e8f0; 
        border-radius: 10px; 
        padding: 0.5rem 1rem; 
        background: #f8fafc; 
        outline: none; 
        transition: all 0.2s; 
        font-size: 0.875rem;
        appearance: none;
    }
    .form-input-advanced:focus { 
        border-color: #1e4a76 !important; 
        box-shadow: 0 0 0 3px rgba(30,74,118,0.1) !important; 
        background: #fff !important; 
    }
</style>

<!-- Page Header - Premium Gradient Style -->
<div class="relative bg-gradient-to-l from-[#1e4a76] to-[#0f2a47] rounded-3xl shadow-xl p-8 mb-8 text-white overflow-hidden">
    <!-- Decorative Shapes -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-[#4a7db8]/20 rounded-full translate-y-1/2 -translate-x-1/4 blur-xl"></div>
    
    <div class="relative z-10">
        <div class="flex items-center mb-3">
            <div class="w-2 h-8 bg-white/80 rounded-full ml-3"></div>
            <h1 class="text-3xl font-extrabold tracking-tight">تقارير النظام</h1>
        </div>
        <p class="text-blue-200/80 text-base font-medium mr-5">إنشاء وتصدير تقارير مفصلة لتحليل أداء النظام والبيانات</p>
    </div>
</div>

<!-- Filter Card -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 p-6 mb-8">
    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
        <div class="w-10 h-10 bg-[#1e4a76]/10 rounded-xl flex items-center justify-center text-[#1e4a76]">
            <i class="fas fa-sliders-h"></i>
        </div>
        <div>
            <h3 class="font-bold text-gray-800">خيارات التقرير</h3>
            <p class="text-xs text-gray-500">حدد المعايير المطلوبة ثم اضغط على توليد التقرير</p>
        </div>
    </div>

    <form id="reportForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">نوع التقرير</label>
                <select name="report_type" class="form-input-advanced w-full py-3">
                    <option value="contacts_summary">ملخص جهات الاتصال</option>
                    <option value="deals_by_status">الصفقات حسب الحالة</option>
                    <option value="tasks_overdue">المهام المتأخرة</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">من تاريخ</label>
                <input type="date" name="from_date" class="form-input-advanced w-full py-3" />
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">إلى تاريخ</label>
                <input type="date" name="to_date" class="form-input-advanced w-full py-3" />
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">تعيين إلى</label>
                <select name="assigned_to" class="form-input-advanced w-full py-3">
                    <option value="">كل المستخدمين</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-50">
            <a href="#" id="exportBtn" class="hidden px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition flex items-center gap-2 text-sm shadow-sm">
                <i class="fas fa-file-excel text-emerald-500"></i> تصدير إكسل
            </a>
            <button type="button" id="generateBtn" class="px-6 py-2.5 bg-gradient-to-l from-[#1e4a76] to-[#2d5f96] text-white rounded-xl font-bold hover:shadow-lg hover:shadow-[#1e4a76]/20 transition flex items-center gap-2 text-sm">
                <i class="fas fa-cogs"></i> توليد التقرير
            </button>
        </div>
    </form>
</div>

<!-- Report Container -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 p-6 overflow-hidden">
    <div id="reportContainer">
        <!-- Empty State -->
        <div class="text-center py-16" id="emptyState">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chart-pie text-gray-300 text-3xl"></i>
            </div>
            <p class="text-gray-400 font-bold text-lg">لم يتم توليد تقرير بعد</p>
            <p class="text-gray-300 text-sm mt-1">اختر نوع التقرير والفلاتر ثم اضغط "توليد التقرير"</p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
 $(function(){
    $('#generateBtn').on('click', function(){
        const data = $('#reportForm').serialize();
        const btn = $(this);
        
        // Set Loading State
        btn.prop('disabled', true).html('<i class="fas fa-sync-alt fa-spin ml-2"></i> جاري التوليد...');
        $('#emptyState').remove();
        $('#reportContainer').html(`
            <div class="text-center py-16">
                <div class="w-16 h-16 bg-[#1e4a76]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-spinner fa-spin text-[#1e4a76] text-2xl"></i>
                </div>
                <p class="text-gray-500 font-semibold">جاري معالجة البيانات...</p>
            </div>
        `);

        $.post('generate.php', data, function(resp){
            // Reset Button
            btn.prop('disabled', false).html('<i class="fas fa-cogs"></i> توليد التقرير');

            if (!resp || !resp.columns) { 
                $('#reportContainer').html(`
                    <div class="text-center py-16">
                        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-400 text-2xl"></i>
                        </div>
                        <p class="text-red-500 font-semibold">فشل توليد التقرير، تأكد من البيانات المدخلة</p>
                    </div>
                `); 
                return; 
            }

            // Build Modern Table
            let html = `
            <div class="overflow-x-auto">
                <div class="mb-4 flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-table text-[#1e4a76]"></i> نتائج التقرير
                    </h3>
                    <span class="text-xs bg-gray-100 px-3 py-1 rounded-full font-bold text-gray-500">${resp.data.length} سجل</span>
                </div>
                <table class="w-full" id="reportTable">
                    <thead><tr>`;
            
            resp.columns.forEach(c => { 
                html += `<th>${c.label}</th>`; 
            });
            
            html += `</tr></thead><tbody>`;
            
            if(resp.data.length === 0) {
                html += `<tr><td colspan="${resp.columns.length}" class="text-center py-8 text-gray-400 font-medium">لا توجد بيانات مطابقة للفلاتر المحددة</td></tr>`;
            } else {
                resp.data.forEach(r => {
                    html += '<tr>';
                    resp.columns.forEach(c => { 
                        const val = r[c.key] ?? '-';
                        // Basic numeric check for alignment
                        const isNum = !isNaN(parseFloat(val)) && isFinite(val);
                        html += `<td class="${isNum ? 'font-semibold' : ''}">${val}</td>`; 
                    });
                    html += '</tr>';
                });
            }
            
            html += '</tbody></table></div>';
            
            $('#reportContainer').html(html);
            $('#exportBtn').removeClass('hidden').attr('href', 'export.php?'+data);
            
        }, 'json').fail(function(){ 
            // Reset Button
            btn.prop('disabled', false).html('<i class="fas fa-cogs"></i> توليد التقرير');
            
            $('#reportContainer').html(`
                <div class="text-center py-16">
                    <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-wifi text-red-400 text-2xl"></i>
                    </div>
                    <p class="text-red-500 font-semibold">حدث خطأ في الاتصال بالخادم</p>
                    <p class="text-gray-400 text-sm mt-1">يرجى المحاولة مرة أخرى لاحقاً</p>
                </div>
            `); 
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>