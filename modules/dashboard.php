<?php
// Dashboard Page
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Require login
require_login();

// Include required files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Set page variables
 $page_title = 'لوحة التحكم - ' . APP_NAME;
 $current_module = 'dashboard';

// Get dashboard statistics
 $user_id = get_current_user_id();
 $user_role = get_current_user_role();
 $db = get_db();

// Get counts based on user permissions
 $stats = [];

// Contacts count
if (check_permission('contacts', 'view', $user_role)) {
    $contacts_sql = "SELECT COUNT(*) as total, 
                     SUM(CASE WHEN type = 'lead' THEN 1 ELSE 0 END) as leads,
                     SUM(CASE WHEN type = 'client' THEN 1 ELSE 0 END) as clients
                     FROM contacts";
    $contacts_result = $db->fetch($contacts_sql);
    $stats['contacts'] = $contacts_result;
}

// Deals count
if (check_permission('deals', 'view', $user_role)) {
    $deals_sql = "SELECT COUNT(*) as total,
                 SUM(CASE WHEN stage = 'closed_won' THEN 1 ELSE 0 END) as won,
                 SUM(CASE WHEN stage = 'closed_lost' THEN 1 ELSE 0 END) as lost,
                 SUM(CASE WHEN amount IS NOT NULL THEN amount ELSE 0 END) as total_value
                 FROM deals";
    $deals_result = $db->fetch($deals_sql);
    $stats['deals'] = $deals_result;
}

// Tasks count
if (check_permission('tasks', 'view', $user_role)) {
    $tasks_sql = "SELECT COUNT(*) as total,
                 SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                 SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                 FROM tasks";
    $tasks_result = $db->fetch($tasks_sql);
    $stats['tasks'] = $tasks_result;
}

// Tickets count
if (check_permission('tickets', 'view', $user_role)) {
    $tickets_sql = "SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                   SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                   FROM tickets";
    $tickets_result = $db->fetch($tickets_sql);
    $stats['tickets'] = $tickets_result;
}

// Projects count
if (check_permission('projects', 'view', $user_role)) {
    $projects_sql = "SELECT COUNT(*) as total,
                    SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM projects";
    $projects_result = $db->fetch($projects_sql);
    $stats['projects'] = $projects_result;

    // Helper: compute simple growth percentage comparing last 7 days vs previous 7 days
    function compute_weekly_growth($db, $table, $date_col = 'created_at') {
        try {
            $cur_sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$date_col} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $prev_sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$date_col} >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND {$date_col} < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $cur = $db->fetch($cur_sql);
            $prev = $db->fetch($prev_sql);
            $cur_cnt = intval($cur['cnt'] ?? 0);
            $prev_cnt = intval($prev['cnt'] ?? 0);
            if ($prev_cnt === 0) {
                if ($cur_cnt === 0) return 0.0;
                return 100.0; // new activity
            }
            return (($cur_cnt - $prev_cnt) / max(1, $prev_cnt)) * 100.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    // Compute growth percentages for KPI badges
    $contacts_growth = compute_weekly_growth($db, 'contacts', 'created_at');
    $deals_growth = compute_weekly_growth($db, 'deals', 'created_at');
    $tasks_growth = compute_weekly_growth($db, 'tasks', 'created_at');
    $tickets_growth = compute_weekly_growth($db, 'tickets', 'created_at');
}

// Function to translate activity descriptions to Arabic
function translate_activity_to_arabic($description) {
    $translations = [
        'User logged in' => 'قام بتسجيل الدخول',
        'User logged out' => 'قام بتسجيل الخروج',
        'Created new contact' => 'أنشأ جهة اتصال جديدة',
        'Updated contact' => 'حدّث جهة الاتصال',
        'Deleted contact' => 'حذف جهة الاتصال',
        'Created new deal' => 'أنشأ صفقة جديدة',
        'Updated deal' => 'حدّث الصفقة',
        'Deleted deal' => 'حذف الصفقة',
        'Created new task' => 'أنشأ مهمة جديدة',
        'Updated task' => 'حدّث المهمة',
        'Deleted task' => 'حذف المهمة',
        'Created new ticket' => 'أنشأ تذكرة جديدة',
        'Updated ticket' => 'حدّث التذكرة',
        'Deleted ticket' => 'حذف التذكرة',
        'Created new project' => 'أنشأ مشروع جديد',
        'Updated project' => 'حدّث المشروع',
        'Deleted project' => 'حذف المشروع',
        'Uploaded document' => 'رفع مستند',
        'Deleted document' => 'حذف المستند'
    ];
    
    if (isset($translations[$description])) {
        return $translations[$description];
    }
    
    foreach ($translations as $english => $arabic) {
        if (stripos($description, $english) !== false) {
            return $arabic;
        }
    }
    
    return $description;
}

// Render activity description using action/table/description with Arabic fallbacks
function render_activity_description_arabic($activity) {
    $desc = trim($activity['description'] ?? '');
    $action = strtolower($activity['action'] ?? '');
    $table = strtolower($activity['table_name'] ?? '');

    // If explicit description exists, try to translate common phrases
    if ($desc !== '') {
        // generic replacements
        $replacements = [
            'created new' => 'أنشأ',
            'created' => 'أنشأ',
            'added' => 'أضاف',
            'updated' => 'حدّث',
            'deleted' => 'حذف',
            'uploaded' => 'رفع',
            'logged in' => 'قام بتسجيل الدخول',
            'logged out' => 'قام بتسجيل الخروج',
            'assigned' => 'أسند',
            'changed status' => 'غيّر الحالة',
            'replied' => 'ردّ على',
            'sent' => 'أرسل',
            'exported' => 'صدّر',
            'imported' => 'استورد'
        ];

        $nouns = [
            'contact' => 'جهة اتصال',
            'contacts' => 'جهات الاتصال',
            'deal' => 'صفقة',
            'deals' => 'الصفقات',
            'task' => 'مهمة',
            'tasks' => 'المهام',
            'ticket' => 'تذكرة',
            'tickets' => 'التذاكر',
            'project' => 'مشروع',
            'projects' => 'المشاريع',
            'document' => 'مستند',
            'user' => 'مستخدم'
        ];

        $translated = $desc;
        // replace nouns first
        foreach ($nouns as $eng => $ar) {
            $translated = preg_replace('/\b' . preg_quote($eng, '/') . '\b/i', $ar, $translated);
        }
        // replace verbs/phrases
        foreach ($replacements as $eng => $ar) {
            $translated = preg_replace('/' . preg_quote($eng, '/') . '/i', $ar, $translated);
        }

        // If result changed, return it
        if ($translated !== $desc) {
            return $translated;
        }
    }

    // Fallback: build description from action and table
    $action_map = [
        'create' => 'أنشأ',
        'created' => 'أنشأ',
        'add' => 'أضاف',
        'update' => 'حدّث',
        'updated' => 'حدّث',
        'delete' => 'حذف',
        'deleted' => 'حذف',
        'login' => 'قام بتسجيل الدخول',
        'logout' => 'قام بتسجيل الخروج',
        'reply' => 'ردّ على',
        'assign' => 'أسند'
    ];

    $table_map = [
        'contacts' => 'جهة اتصال',
        'contact' => 'جهة اتصال',
        'deals' => 'صفقة',
        'deal' => 'صفقة',
        'tasks' => 'مهمة',
        'task' => 'مهمة',
        'tickets' => 'تذكرة',
        'ticket' => 'تذكرة',
        'projects' => 'مشروع',
        'project' => 'مشروع',
        'documents' => 'مستند',
        'document' => 'مستند',
        'users' => 'مستخدم',
        'user' => 'مستخدم'
    ];

    $a = $action_map[$action] ?? null;
    $t = $table_map[$table] ?? null;

    if ($a && $t) {
        $id = isset($activity['record_id']) && $activity['record_id'] ? ' #' . $activity['record_id'] : '';
        return $a . ' ' . $t . $id;
    }

    // final fallback: return translated known phrases or original description
    if (!empty($desc)) return $desc;
    return 'قام بتنفيذ إجراء';
}

// Get recent activities
 $recent_activities = [];
 $activities_sql = "SELECT al.*, u.full_name as user_name
                  FROM audit_log al
                  LEFT JOIN users u ON al.user_id = u.id
                  ORDER BY al.created_at DESC
                  LIMIT 10";
 $recent_activities = $db->fetchAll($activities_sql);

// Get upcoming tasks
 $upcoming_tasks = [];
if (check_permission('tasks', 'view', $user_role)) {
    $upcoming_tasks_sql = "SELECT t.*, u.full_name as assigned_name
                           FROM tasks t
                           LEFT JOIN users u ON t.assigned_to = u.id
                           WHERE t.status != 'completed' AND t.due_date >= CURDATE()
                           ORDER BY t.due_date ASC, t.priority DESC
                           LIMIT 5";
    $upcoming_tasks = $db->fetchAll($upcoming_tasks_sql);
}

// Get recent deals
 $recent_deals = [];
if (check_permission('deals', 'view', $user_role)) {
    $recent_deals_sql = "SELECT d.*, c.first_name, c.last_name, u.full_name as created_name
                        FROM deals d
                        JOIN contacts c ON d.contact_id = c.id
                        LEFT JOIN users u ON d.created_by = u.id
                        ORDER BY d.created_at DESC
                        LIMIT 5";
    $recent_deals = $db->fetchAll($recent_deals_sql);
}

// Get open tickets
 $open_tickets = [];
if (check_permission('tickets', 'view', $user_role)) {
    $open_tickets_sql = "SELECT t.*, c.first_name, c.last_name, u.full_name as assigned_name
                        FROM tickets t
                        LEFT JOIN contacts c ON t.contact_id = c.id
                        LEFT JOIN users u ON t.assigned_to = u.id
                        WHERE t.status IN ('open', 'in_progress')
                        ORDER BY t.priority DESC, t.created_at ASC
                        LIMIT 5";
    $open_tickets = $db->fetchAll($open_tickets_sql);
}

// --- Compute activity buckets for charts (day / week / month) ---
function get_activity_buckets_day($db, $table, $date_col = 'created_at') {
    $buckets = array_fill(0, 7, 0);
    $ranges = [
        ['start'=>0,'end'=>3],
        ['start'=>4,'end'=>7],
        ['start'=>8,'end'=>11],
        ['start'=>12,'end'=>15],
        ['start'=>16,'end'=>19],
        ['start'=>20,'end'=>23],
        ['start'=>23,'end'=>23]
    ];
    foreach ($ranges as $i => $r) {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE DATE({$date_col}) = CURDATE() AND HOUR({$date_col}) BETWEEN {$r['start']} AND {$r['end']}";
        $res = $db->fetch($sql);
        $buckets[$i] = intval($res['cnt'] ?? 0);
    }
    return $buckets;
}

function get_activity_buckets_week($db, $table, $date_col = 'created_at') {
    $buckets = array_fill(0, 7, 0); // Sunday..Saturday
    for ($dow = 1; $dow <= 7; $dow++) {
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE DAYOFWEEK({$date_col}) = {$dow} AND YEARWEEK({$date_col}, 1) = YEARWEEK(CURDATE(), 1)";
        $res = $db->fetch($sql);
        $buckets[$dow-1] = intval($res['cnt'] ?? 0);
    }
    return $buckets;
}

function get_activity_buckets_month($db, $table, $date_col = 'created_at') {
    $buckets = array_fill(0, 4, 0);
    $start_month = date('Y-m-01');
    for ($w = 0; $w < 4; $w++) {
        $start = date('Y-m-d', strtotime($start_month . " +" . ($w*7) . " days"));
        $end = date('Y-m-d', strtotime($start . " +6 days"));
        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE DATE({$date_col}) BETWEEN '{$start}' AND '{$end}' AND MONTH({$date_col}) = MONTH(CURDATE())";
        $res = $db->fetch($sql);
        $buckets[$w] = intval($res['cnt'] ?? 0);
    }
    return $buckets;
}

// Prepare activity datasets for contacts, deals, tasks
$contacts_day = get_activity_buckets_day($db, 'contacts', 'created_at');
$contacts_week = get_activity_buckets_week($db, 'contacts', 'created_at');
$contacts_month = get_activity_buckets_month($db, 'contacts', 'created_at');

$deals_day = get_activity_buckets_day($db, 'deals', 'created_at');
$deals_week = get_activity_buckets_week($db, 'deals', 'created_at');
$deals_month = get_activity_buckets_month($db, 'deals', 'created_at');

$tasks_day = get_activity_buckets_day($db, 'tasks', 'created_at');
$tasks_week = get_activity_buckets_week($db, 'tasks', 'created_at');
$tasks_month = get_activity_buckets_month($db, 'tasks', 'created_at');

// --- Compute performance KPIs ---
$task_stats = $db->fetch("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM tasks");
$total_tasks = intval($task_stats['total'] ?? 0);
$completed_tasks = intval($task_stats['completed'] ?? 0);
$task_completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100, 1) : 0;

$ticket_total = intval(($db->fetch("SELECT COUNT(*) as total FROM tickets")['total'] ?? 0));
$ticket_resolved = intval(($db->fetch("SELECT COUNT(*) as resolved FROM tickets WHERE status IN ('resolved','closed')")['resolved'] ?? 0));
$customer_satisfaction_rate = $ticket_total > 0 ? round(($ticket_resolved / $ticket_total) * 100, 1) : 0;

$first_reply_within_24 = intval(($db->fetch("SELECT COUNT(DISTINCT t.id) as within24 FROM tickets t JOIN (SELECT ticket_id, MIN(created_at) as first_reply FROM ticket_replies GROUP BY ticket_id) r ON t.id = r.ticket_id WHERE TIMESTAMPDIFF(HOUR, t.created_at, r.first_reply) <= 24")['within24'] ?? 0));
$ticket_response_rate = $ticket_total > 0 ? round(($first_reply_within_24 / $ticket_total) * 100, 1) : 0;

$overall_performance = round(($task_completion_rate * 0.4) + ($customer_satisfaction_rate * 0.4) + ($ticket_response_rate * 0.2), 1);

// JSON for JS
$activity_json = [
    'day' => [
        'labels' => ['00:00','04:00','08:00','12:00','16:00','20:00','24:00'],
        'contacts' => $contacts_day,
        'deals' => $deals_day,
        'tasks' => $tasks_day
    ],
    'week' => [
        'labels' => ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'],
        'contacts' => $contacts_week,
        'deals' => $deals_week,
        'tasks' => $tasks_week
    ],
    'month' => [
        'labels' => ['الأسبوع 1','الأسبوع 2','الأسبوع 3','الأسبوع 4'],
        'contacts' => $contacts_month,
        'deals' => $deals_month,
        'tasks' => $tasks_month
    ]
];

// Include header
require_once __DIR__ . '/../templates/header.php';
?>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-slide-up {
        animation: slideUp 0.5s ease-out forwards;
    }
    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(30, 74, 118, 0.1);
    }
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<!-- Dashboard Header - Elegant White Design -->
<div class="relative bg-gradient-to-br from-white via-white to-gray-50 rounded-xl shadow-md border border-gray-200 p-6 mb-8 overflow-hidden group transition-all duration-300">
    <!-- Decorative Elements -->
    <div class="absolute top-0 left-0 h-1 w-16 bg-gradient-to-r from-[#1e4a76] via-blue-500 to-transparent rounded-br-lg"></div>
    <div class="absolute -bottom-12 -right-12 w-40 h-40 bg-blue-100 rounded-full opacity-20 blur-2xl"></div>
    <div class="absolute top-1/2 right-0 w-px h-12 bg-gradient-to-b from-transparent via-gray-300 to-transparent opacity-40"></div>
    
    <div class="relative z-10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <!-- Left Content -->
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-1.5 h-6 bg-gradient-to-b from-[#1e4a76] to-blue-400 rounded-full"></div>
                    <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">لوحة التحكم</h1>
                </div>
                <p class="text-gray-600 text-sm font-medium ml-5">نظرة عامة على أداء النظام وإدارة العملاء</p>
            </div>
            
            <!-- Right Content - Time & Date -->
            <div class="flex gap-4">
                <!-- Time -->
                <div class="group/time bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-lg p-4 text-center min-w-[110px] hover:border-blue-300 hover:shadow-lg transition-all duration-300 cursor-default">
                    <div class="text-[#1e4a76] text-lg mb-2 font-semibold opacity-80 group-hover/time:opacity-100 transition-opacity">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="text-2xl font-bold text-[#1e4a76] font-mono group-hover/time:scale-110 transition-transform duration-300" id="currentTime">--:--</div>
                    <div class="text-xs text-gray-500 font-semibold mt-2">التوقيت</div>
                </div>
                <!-- Date -->
                <div class="group/date bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-lg p-4 text-center min-w-[110px] hover:border-blue-300 hover:shadow-lg transition-all duration-300 cursor-default">
                    <div class="text-[#1e4a76] text-lg mb-2 font-semibold opacity-80 group-hover/date:opacity-100 transition-opacity">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800 font-mono group-hover/date:scale-110 transition-transform duration-300"><?php echo date('d/m'); ?></div>
                    <div class="text-xs text-gray-500 font-semibold mt-2">اليوم</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Filter & Actions -->
<div class="bg-gradient-to-r from-white via-white to-gray-50 rounded-xl shadow-md border border-gray-200 p-5 mb-8 flex flex-col md:flex-row items-center justify-between gap-4 transition-all duration-300 group hover:shadow-lg">
    <!-- Decorative left accent -->
    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-gradient-to-b from-[#1e4a76] to-blue-400 rounded-r-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    
    <!-- Filter Section -->
    <div class="flex items-center gap-3 w-full md:w-auto">
        <div class="text-sm font-semibold text-gray-700 flex items-center gap-1">
            <i class="fas fa-sliders-h text-[#1e4a76] text-xs"></i>
            <span>الفترة:</span>
        </div>
        <select id="dateRange" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#1e4a76] focus:ring-offset-1 focus:border-transparent transition-all bg-white font-medium text-gray-700 hover:border-gray-400 cursor-pointer shadow-sm">
            <option value="today">اليوم</option>
            <option value="week" selected>هذا الأسبوع</option>
            <option value="month">هذا الشهر</option>
            <option value="quarter">هذا الربع</option>
            <option value="year">هذه السنة</option>
            <option value="custom">مخصص</option>
        </select>
        <div id="customDateRange" class="hidden flex items-center gap-2">
            <input type="date" id="startDate" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#1e4a76] focus:ring-offset-1 bg-white transition-all shadow-sm">
            <span class="text-gray-400 font-light">→</span>
            <input type="date" id="endDate" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#1e4a76] focus:ring-offset-1 bg-white transition-all shadow-sm">
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="flex items-center gap-2 w-full md:w-auto">
        <button onclick="refreshDashboard(this)" class="px-4 py-2 bg-gradient-to-br from-[#1e4a76] to-blue-600 text-white text-sm font-semibold rounded-lg hover:shadow-md hover:from-[#163d63] transition-all duration-200 flex items-center gap-2 active:scale-95">
            <i class="fas fa-sync-alt text-xs"></i>
            <span>تحديث</span>
        </button>
    </div>
</div>

<!-- KPI Cards Section -->
<div class="mb-10">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        
        <!-- Contacts Card -->
        <?php if (check_permission('contacts', 'view', $user_role)): ?>
            <div class="card-hover bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 group">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-14 h-14 bg-[#1e4a76]/10 rounded-2xl flex items-center justify-center text-[#1e4a76] group-hover:bg-[#1e4a76] group-hover:text-white transition-all duration-300">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <?php 
                        $c_dir = $contacts_growth >= 0 ? 'up' : 'down';
                        $c_class = $contacts_growth >= 0 ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
                        $c_icon = $contacts_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                        <span class="flex items-center text-sm font-bold <?php echo $c_class; ?> px-3 py-1 rounded-full">
                            <i class="fas <?php echo $c_icon; ?> text-xs ml-1"></i> <?php echo number_format(abs($contacts_growth), 1); ?>%
                        </span>
                    </div>
                    <h3 class="text-4xl font-extrabold text-gray-900 mb-1"><?php echo number_format($stats['contacts']['total'] ?? 0); ?></h3>
                    <p class="text-sm font-medium text-gray-500 mb-5">جهات الاتصال</p>
                    <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-lg font-bold text-[#1e4a76]"><?php echo $stats['contacts']['leads'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">عملاء محتملين</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-emerald-500"><?php echo $stats['contacts']['clients'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">عملاء حاليين</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo base_url('modules/contacts/'); ?>" class="block bg-gray-50 hover:bg-[#1e4a76]/5 text-center py-3 text-sm font-semibold text-gray-500 hover:text-[#1e4a76] transition-all border-t border-gray-100">
                    إدارة الجهات <i class="fas fa-arrow-left text-xs mr-1"></i>
                </a>
            </div>
        <?php endif; ?>

        <!-- Deals Card -->
        <?php if (check_permission('deals', 'view', $user_role)): ?>
            <div class="card-hover bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 group">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-14 h-14 bg-[#1e4a76]/10 rounded-2xl flex items-center justify-center text-[#1e4a76] group-hover:bg-[#1e4a76] group-hover:text-white transition-all duration-300">
                            <i class="fas fa-handshake text-2xl"></i>
                        </div>
                        <?php 
                        $d_class = $deals_growth >= 0 ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
                        $d_icon = $deals_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                        <span class="flex items-center text-sm font-bold <?php echo $d_class; ?> px-3 py-1 rounded-full">
                            <i class="fas <?php echo $d_icon; ?> text-xs ml-1"></i> <?php echo number_format(abs($deals_growth), 1); ?>%
                        </span>
                    </div>
                    <h3 class="text-4xl font-extrabold text-gray-900 mb-1"><?php echo number_format($stats['deals']['total'] ?? 0); ?></h3>
                    <p class="text-sm font-medium text-gray-500 mb-5">الصفقات</p>
                    <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-lg font-bold text-emerald-500"><?php echo $stats['deals']['won'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">مكتملة</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-red-400"><?php echo $stats['deals']['lost'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">فقدت</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo base_url('modules/deals/'); ?>" class="block bg-gray-50 hover:bg-[#1e4a76]/5 text-center py-3 text-sm font-semibold text-gray-500 hover:text-[#1e4a76] transition-all border-t border-gray-100">
                    إدارة الصفقات <i class="fas fa-arrow-left text-xs mr-1"></i>
                </a>
            </div>
        <?php endif; ?>

        <!-- Tasks Card -->
        <?php if (check_permission('tasks', 'view', $user_role)): ?>
            <div class="card-hover bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 group">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-14 h-14 bg-[#1e4a76]/10 rounded-2xl flex items-center justify-center text-[#1e4a76] group-hover:bg-[#1e4a76] group-hover:text-white transition-all duration-300">
                            <i class="fas fa-tasks text-2xl"></i>
                        </div>
                        <?php 
                        $t_class = $tasks_growth >= 0 ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
                        $t_icon = $tasks_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                        <span class="flex items-center text-sm font-bold <?php echo $t_class; ?> px-3 py-1 rounded-full">
                            <i class="fas <?php echo $t_icon; ?> text-xs ml-1"></i> <?php echo number_format(abs($tasks_growth), 1); ?>%
                        </span>
                    </div>
                    <h3 class="text-4xl font-extrabold text-gray-900 mb-1"><?php echo number_format($stats['tasks']['total'] ?? 0); ?></h3>
                    <p class="text-sm font-medium text-gray-500 mb-5">المهام</p>
                    <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-lg font-bold text-amber-500"><?php echo $stats['tasks']['pending'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">في الانتظار</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-red-400"><?php echo $stats['tasks']['overdue'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">متأخرة</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo base_url('modules/tasks/'); ?>" class="block bg-gray-50 hover:bg-[#1e4a76]/5 text-center py-3 text-sm font-semibold text-gray-500 hover:text-[#1e4a76] transition-all border-t border-gray-100">
                    إدارة المهام <i class="fas fa-arrow-left text-xs mr-1"></i>
                </a>
            </div>
        <?php endif; ?>

        <!-- Tickets Card -->
        <?php if (check_permission('tickets', 'view', $user_role)): ?>
            <div class="card-hover bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 group">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-14 h-14 bg-[#1e4a76]/10 rounded-2xl flex items-center justify-center text-[#1e4a76] group-hover:bg-[#1e4a76] group-hover:text-white transition-all duration-300">
                            <i class="fas fa-life-ring text-2xl"></i>
                        </div>
                        <?php 
                        $ti_class = $tickets_growth >= 0 ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
                        $ti_icon = $tickets_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                        <span class="flex items-center text-sm font-bold <?php echo $ti_class; ?> px-3 py-1 rounded-full">
                            <i class="fas <?php echo $ti_icon; ?> text-xs ml-1"></i> <?php echo number_format(abs($tickets_growth), 1); ?>%
                        </span>
                    </div>
                    <h3 class="text-4xl font-extrabold text-gray-900 mb-1"><?php echo number_format($stats['tickets']['total'] ?? 0); ?></h3>
                    <p class="text-sm font-medium text-gray-500 mb-5">تذاكر الدعم</p>
                    <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-lg font-bold text-[#1e4a76]"><?php echo $stats['tickets']['open'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">مفتوحة</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-emerald-500"><?php echo $stats['tickets']['resolved'] ?? 0; ?></div>
                            <div class="text-xs text-gray-400 font-medium">محلولة</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo base_url('modules/tickets/'); ?>" class="block bg-gray-50 hover:bg-[#1e4a76]/5 text-center py-3 text-sm font-semibold text-gray-500 hover:text-[#1e4a76] transition-all border-t border-gray-100">
                    إدارة التذاكر <i class="fas fa-arrow-left text-xs mr-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Charts Section -->
<div class="mb-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Revenue Chart -->
        <?php if (check_permission('deals', 'view', $user_role)): ?>
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">تحليل الإيرادات</h3>
                        <p class="text-sm text-gray-500 mt-1">مقارنة الأداء الشهري مع الهدف</p>
                    </div>
                    <div class="flex items-center space-x-2 space-x-reverse bg-gray-100 rounded-lg p-1">
                        <button onclick="changeChartType('revenue', 'line')" class="chart-type-btn px-3 py-1.5 text-xs bg-white text-[#1e4a76] rounded-md shadow-sm font-semibold" data-chart="revenue" data-type="line">
                            <i class="fas fa-chart-line ml-1"></i> خطي
                        </button>
                        <button onclick="changeChartType('revenue', 'bar')" class="chart-type-btn px-3 py-1.5 text-xs text-gray-500 rounded-md font-semibold" data-chart="revenue" data-type="bar">
                            <i class="fas fa-chart-bar ml-1"></i> شريطي
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="relative h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <?php
                    // Compute monthly metrics (current vs previous month) for dynamic percentages
                    $cur_month_cond = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
                    $prev_month_cond = "MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

                    // Total value
                    $total_cur = $db->fetch("SELECT SUM(amount) as total FROM deals WHERE amount IS NOT NULL AND $cur_month_cond");
                    $total_prev = $db->fetch("SELECT SUM(amount) as total FROM deals WHERE amount IS NOT NULL AND $prev_month_cond");
                    $total_cur_val = floatval($total_cur['total'] ?? 0);
                    $total_prev_val = floatval($total_prev['total'] ?? 0);
                    if ($total_prev_val > 0) {
                        $total_change = (($total_cur_val - $total_prev_val) / $total_prev_val) * 100.0;
                    } else {
                        $total_change = $total_cur_val > 0 ? 100.0 : 0.0;
                    }

                    // Average deal
                    $avg_cur = $db->fetch("SELECT AVG(amount) as avg FROM deals WHERE amount IS NOT NULL AND $cur_month_cond");
                    $avg_prev = $db->fetch("SELECT AVG(amount) as avg FROM deals WHERE amount IS NOT NULL AND $prev_month_cond");
                    $avg_cur_val = floatval($avg_cur['avg'] ?? 0);
                    $avg_prev_val = floatval($avg_prev['avg'] ?? 0);
                    if ($avg_prev_val > 0) {
                        $avg_change = (($avg_cur_val - $avg_prev_val) / $avg_prev_val) * 100.0;
                    } else {
                        $avg_change = $avg_cur_val > 0 ? 100.0 : 0.0;
                    }

                    // Conversion rate (closed_won / total)
                    $won_cur = $db->fetch("SELECT COUNT(*) as cnt FROM deals WHERE stage = 'closed_won' AND $cur_month_cond");
                    $total_cur_cnt = $db->fetch("SELECT COUNT(*) as cnt FROM deals WHERE $cur_month_cond");
                    $won_prev = $db->fetch("SELECT COUNT(*) as cnt FROM deals WHERE stage = 'closed_won' AND $prev_month_cond");
                    $total_prev_cnt = $db->fetch("SELECT COUNT(*) as cnt FROM deals WHERE $prev_month_cond");
                    $won_cur_cnt = intval($won_cur['cnt'] ?? 0);
                    $total_cur_cnt_val = intval($total_cur_cnt['cnt'] ?? 0);
                    $won_prev_cnt = intval($won_prev['cnt'] ?? 0);
                    $total_prev_cnt_val = intval($total_prev_cnt['cnt'] ?? 0);
                    $rate_cur = $total_cur_cnt_val > 0 ? ($won_cur_cnt / $total_cur_cnt_val) * 100.0 : 0.0;
                    $rate_prev = $total_prev_cnt_val > 0 ? ($won_prev_cnt / $total_prev_cnt_val) * 100.0 : 0.0;
                    if ($rate_prev > 0) {
                        $rate_change = (($rate_cur - $rate_prev) / $rate_prev) * 100.0;
                    } else {
                        $rate_change = $rate_cur > 0 ? 100.0 : 0.0;
                    }

                    // Helper to render badge classes and formatted text
                    function render_change_badge($val) {
                        $sign = $val >= 0 ? '+' : '-';
                        $abs = number_format(abs($val), 0);
                        $cls = $val >= 0 ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
                        return "<span class=\"text-xs font-bold $cls px-2 py-1 rounded-md\">{$sign}{$abs}%</span>";
                    }
                    ?>

                    <div class="mt-6 grid grid-cols-3 gap-4">
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <i class="fas fa-wallet text-[#1e4a76] bg-[#1e4a76]/10 p-2 rounded-lg"></i>
                                <?php echo render_change_badge($total_change); ?>
                            </div>
                            <div class="text-xl font-extrabold text-gray-900 mb-1">
                                <?php echo format_currency($stats['deals']['total_value'] ?? $total_cur_val); ?>
                            </div>
                            <p class="text-xs text-gray-500 font-medium">إجمالي القيمة</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <i class="fas fa-coins text-[#1e4a76] bg-[#1e4a76]/10 p-2 rounded-lg"></i>
                                <?php echo render_change_badge($avg_change); ?>
                            </div>
                            <div class="text-xl font-extrabold text-gray-900 mb-1">
                                <?php echo format_currency($avg_cur_val ?? 0); ?>
                            </div>
                            <p class="text-xs text-gray-500 font-medium">متوسط الصفقة</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <i class="fas fa-percentage text-[#1e4a76] bg-[#1e4a76]/10 p-2 rounded-lg"></i>
                                <?php echo render_change_badge($rate_change); ?>
                            </div>
                            <div class="text-xl font-extrabold text-gray-900 mb-1">
                                <?php echo number_format($rate_cur, 1) . '%'; ?>
                            </div>
                            <p class="text-xs text-gray-500 font-medium">معدل التحويل</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pipeline Chart -->
        <?php if (check_permission('deals', 'view', $user_role)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">مسار الصفقات</h3>
                    <p class="text-sm text-gray-500 mt-1">توزيع المراحل الحالية</p>
                </div>
                <div class="p-6 flex-1 flex flex-col justify-between">
                    <div class="relative h-64 mb-6">
                        <canvas id="pipelineChart"></canvas>
                    </div>
                    <div class="space-y-3">
                        <?php 
                        $stages = ['prospecting' => 'استكشاف', 'qualification' => 'تأهيل', 'proposal' => 'عرض سعر', 'negotiation' => 'مفاوضات', 'closed_won' => 'مكتمل', 'closed_lost' => 'فقد'];
                        $colors = ['bg-[#1e4a76]', 'bg-[#2d5f96]', 'bg-[#4a7db8]', 'bg-[#7ba3d1]', 'bg-emerald-500', 'bg-red-400'];
                        $stage_index = 0;
                        
                        $total_deals_sql = "SELECT COUNT(*) as total FROM deals";
                        $total_result = $db->fetch($total_deals_sql);
                        $has_deals = ($total_result['total'] ?? 0) > 0;
                        
                        foreach ($stages as $stage_key => $stage_name):
                            if ($has_deals) {
                                $stage_sql = "SELECT COUNT(*) as count FROM deals WHERE stage = '$stage_key'";
                                $stage_count = $db->fetch($stage_sql);
                                $count = $stage_count['count'] ?? 0;
                            } else {
                                $sample_counts = ['prospecting' => 8, 'qualification' => 5, 'proposal' => 3, 'negotiation' => 4, 'closed_won' => 12, 'closed_lost' => 2];
                                $count = $sample_counts[$stage_key] ?? 0;
                            }
                        ?>
                            <div class="flex items-center justify-between group">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 <?php echo $colors[$stage_index]; ?> rounded-full ml-3"></div>
                                    <span class="text-sm font-medium text-gray-600 group-hover:text-gray-900 transition-colors"><?php echo $stage_name; ?></span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <span class="text-sm font-bold text-gray-900 w-6 text-left"><?php echo $count; ?></span>
                                    <div class="w-20 bg-gray-100 rounded-full h-2">
                                        <div class="<?php echo $colors[$stage_index]; ?> h-2 rounded-full transition-all" style="width: <?php echo $has_deals ? ($count / max($total_result['total'], 1)) * 100 : 20; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            $stage_index++;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Activity Timeline and Performance -->
<div class="mb-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activity Timeline -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">النشاط والأداء</h3>
                    <p class="text-sm text-gray-500 mt-1">مقارنة الأنشطة عبر الفترات</p>
                </div>
                <div class="flex items-center space-x-2 space-x-reverse bg-gray-100 rounded-lg p-1">
                    <button onclick="changeActivityPeriod('day')" class="activity-period-btn px-3 py-1.5 text-xs text-gray-500 rounded-md font-semibold" data-period="day">يوم</button>
                    <button onclick="changeActivityPeriod('week')" class="activity-period-btn px-3 py-1.5 text-xs bg-white text-[#1e4a76] rounded-md shadow-sm font-semibold" data-period="week">أسبوع</button>
                    <button onclick="changeActivityPeriod('month')" class="activity-period-btn px-3 py-1.5 text-xs text-gray-500 rounded-md font-semibold" data-period="month">شهر</button>
                </div>
            </div>
            <div class="p-6">
                <div class="relative h-80">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">مؤشرات الأداء</h3>
                <p class="text-sm text-gray-500 mt-1">الإنجاز والرضا العام</p>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">إتمام المهام</span>
                        <span class="text-sm font-bold text-emerald-500"><?php echo $task_completion_rate; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-emerald-500 h-2.5 rounded-full" style="width: <?php echo $task_completion_rate; ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">رضا العملاء</span>
                        <span class="text-sm font-bold text-[#1e4a76]"><?php echo $customer_satisfaction_rate; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-[#1e4a76] h-2.5 rounded-full" style="width: <?php echo $customer_satisfaction_rate; ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">استجابة التذاكر</span>
                        <span class="text-sm font-bold text-amber-500"><?php echo $ticket_response_rate; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-amber-500 h-2.5 rounded-full" style="width: <?php echo $ticket_response_rate; ?>%"></div>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold text-gray-700">نمو العملاء</span>
                        <span class="text-sm font-bold text-[#2d5f96]">+<?php echo number_format(($contacts_growth ?? 0),1); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-[#2d5f96] h-2.5 rounded-full" style="width: <?php echo min(100, max(0, abs($contacts_growth))); ?>%"></div>
                    </div>
                </div>
                
                <div class="mt-8 p-5 bg-[#1e4a76] rounded-xl text-white shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-100">معدل الأداء العام</p>
                            <p class="text-xs text-blue-200 mt-1">بناءً على جميع المؤشرات</p>
                        </div>
                        <div class="text-4xl font-extrabold"><?php echo $overall_performance; ?><span class="text-lg">%</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities and Tasks (Timeline Style) -->
<div class="mb-10">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">سجل الأنشطة</h3>
                <a href="modules/audit/" class="text-[#1e4a76] hover:text-[#163d63] text-sm font-semibold transition-colors">
                    عرض الكل <i class="fas fa-arrow-left text-xs mr-1"></i>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-0 max-h-[400px] overflow-y-auto relative">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute top-4 bottom-4 left-7 w-0.5 bg-gray-100"></div>
                    
                    <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-16">
                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-history text-gray-300 text-3xl"></i>
                            </div>
                            <p class="text-gray-400 font-semibold">لا توجد أنشطة حديثة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start mb-6 relative">
                                <div class="flex-shrink-0 w-14 h-14 bg-[#1e4a76]/10 rounded-full flex items-center justify-center z-10 border-4 border-white">
                                    <i class="fas fa-bolt text-[#1e4a76]"></i>
                                </div>
                                <div class="mr-4 bg-gray-50 rounded-xl p-4 flex-1 hover:bg-[#1e4a76]/5 transition-colors border border-gray-100">
                                    <p class="text-sm text-gray-800">
                                        <span class="font-bold text-[#1e4a76]"><?php echo htmlspecialchars($activity['user_name'] ?? 'مستخدم'); ?></span>
                                        <span class="text-gray-600"> <?php echo render_activity_description_arabic($activity); ?></span>
                                    </p>
                                    <div class="text-xs text-gray-400 mt-2 flex items-center">
                                        <i class="fas fa-clock ml-1"></i>
                                        <?php echo time_ago($activity['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <?php if (check_permission('tasks', 'view', $user_role)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">المهام القادمة</h3>
                    <a href="modules/tasks/" class="text-[#1e4a76] hover:text-[#163d63] text-sm font-semibold transition-colors">
                        عرض الكل <i class="fas fa-arrow-left text-xs mr-1"></i>
                    </a>
                </div>
                <div class="p-6">
                    <div class="space-y-4 max-h-[400px] overflow-y-auto">
                        <?php if (empty($upcoming_tasks)): ?>
                            <div class="text-center py-16">
                                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-check-double text-gray-300 text-3xl"></i>
                                </div>
                                <p class="text-gray-400 font-semibold">لا توجد مهام قادمة</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_tasks as $task): ?>
                                <div class="flex items-start p-4 bg-gray-50 rounded-xl hover:bg-white hover:shadow-md transition-all border border-gray-100 group cursor-pointer">
                                    <div class="flex-shrink-0 w-5 h-5 mt-1 rounded-full border-2 <?php echo $task['priority'] == 'high' ? 'border-red-400 bg-red-50' : ($task['priority'] == 'medium' ? 'border-amber-400 bg-amber-50' : 'border-emerald-400 bg-emerald-50'); ?>"></div>
                                    <div class="mr-3 flex-1">
                                        <h4 class="text-sm font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <?php echo get_status_badge($task['status'], 'default'); ?>
                                            <?php echo get_status_badge($task['priority'], 'priority'); ?>
                                        </div>
                                        <div class="flex items-center justify-between text-xs text-gray-400">
                                            <span>
                                                <i class="fas fa-user ml-1"></i>
                                                <?php echo htmlspecialchars($task['assigned_name'] ?? 'غير مسند'); ?>
                                            </span>
                                            <span class="font-medium <?php echo $task['priority'] == 'high' ? 'text-red-500' : 'text-gray-500'; ?>">
                                                <i class="fas fa-calendar ml-1"></i>
                                                <?php echo format_date($task['due_date']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Deals and Open Tickets -->
<div class="mb-10">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Deals -->
        <?php if (check_permission('deals', 'view', $user_role)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">أحدث الصفقات</h3>
                    <a href="modules/deals/" class="text-[#1e4a76] hover:text-[#163d63] text-sm font-semibold transition-colors">
                        المتابعة <i class="fas fa-arrow-left text-xs mr-1"></i>
                    </a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($recent_deals)): ?>
                        <div class="text-center py-16">
                            <i class="fas fa-handshake text-gray-200 text-4xl mb-3"></i>
                            <p class="text-gray-400 font-semibold">لا توجد صفقات حديثة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_deals as $deal): ?>
                            <div class="p-5 hover:bg-gray-50 transition-colors flex items-center justify-between group">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-[#1e4a76]/10 rounded-xl flex items-center justify-center text-[#1e4a76] ml-4 group-hover:bg-[#1e4a76] group-hover:text-white transition-all">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($deal['title']); ?></h4>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-user ml-1"></i> <?php echo htmlspecialchars($deal['first_name'] . ' ' . $deal['last_name']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="text-sm font-extrabold text-gray-900 mb-1"><?php echo format_currency($deal['amount'] ?? 0); ?></div>
                                    <?php echo get_status_badge($deal['stage'], 'deal_stage'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Open Tickets -->
        <?php if (check_permission('tickets', 'view', $user_role)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">تذاكر بحاجة لمتابعة</h3>
                    <a href="modules/tickets/" class="text-[#1e4a76] hover:text-[#163d63] text-sm font-semibold transition-colors">
                        المتابعة <i class="fas fa-arrow-left text-xs mr-1"></i>
                    </a>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php if (empty($open_tickets)): ?>
                        <div class="text-center py-16">
                            <i class="fas fa-life-ring text-gray-200 text-4xl mb-3"></i>
                            <p class="text-gray-400 font-semibold">لا توجد تذاكر مفتوحة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($open_tickets as $ticket): ?>
                            <div class="p-5 hover:bg-gray-50 transition-colors flex items-center justify-between group">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-500 ml-4 group-hover:bg-red-500 group-hover:text-white transition-all">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-user ml-1"></i> <?php echo htmlspecialchars($ticket['first_name'] ?? 'غير محدد' . ' ' . $ticket['last_name'] ?? ''); ?>
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex flex-col items-end gap-1">
                                        <?php echo get_status_badge($ticket['status'], 'ticket_status'); ?>
                                        <?php echo get_status_badge($ticket['priority'], 'priority'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions FAB -->
<div class="fixed bottom-8 left-8 z-50 flex flex-col items-center space-y-4" id="fabContainer">
    <div id="quickActionsMenu" class="flex flex-col-reverse items-center space-y-reverse space-y-3 opacity-0 translate-y-10 invisible transition-all duration-300">
        <?php if (check_permission('contacts', 'add', $user_role)): ?>
            <a href="<?php echo base_url('modules/contacts/add.php'); ?>" class="group flex items-center">
                <span class="mr-3 bg-white text-gray-800 text-sm font-semibold py-2 px-4 rounded-xl shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">جهة اتصال</span>
                <div class="w-12 h-12 bg-[#1e4a76] rounded-full shadow-lg flex items-center justify-center text-white hover:bg-[#163d63] transition-all transform hover:scale-110">
                    <i class="fas fa-user-plus"></i>
                </div>
            </a>
        <?php endif; ?>
        <?php if (check_permission('deals', 'add', $user_role)): ?>
            <a href="<?php echo base_url('modules/deals/add.php'); ?>" class="group flex items-center">
                <span class="mr-3 bg-white text-gray-800 text-sm font-semibold py-2 px-4 rounded-xl shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">صفقة جديدة</span>
                <div class="w-12 h-12 bg-emerald-500 rounded-full shadow-lg flex items-center justify-center text-white hover:bg-emerald-600 transition-all transform hover:scale-110">
                    <i class="fas fa-handshake"></i>
                </div>
            </a>
        <?php endif; ?>
        <?php if (check_permission('tasks', 'add', $user_role)): ?>
            <a href="<?php echo base_url('modules/tasks/add.php'); ?>" class="group flex items-center">
                <span class="mr-3 bg-white text-gray-800 text-sm font-semibold py-2 px-4 rounded-xl shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">مهمة جديدة</span>
                <div class="w-12 h-12 bg-amber-500 rounded-full shadow-lg flex items-center justify-center text-white hover:bg-amber-600 transition-all transform hover:scale-110">
                    <i class="fas fa-tasks"></i>
                </div>
            </a>
        <?php endif; ?>
        <?php if (check_permission('tickets', 'add', $user_role)): ?>
            <a href="<?php echo base_url('modules/tickets/add.php'); ?>" class="group flex items-center">
                <span class="mr-3 bg-white text-gray-800 text-sm font-semibold py-2 px-4 rounded-xl shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">تذكرة جديدة</span>
                <div class="w-12 h-12 bg-red-500 rounded-full shadow-lg flex items-center justify-center text-white hover:bg-red-600 transition-all transform hover:scale-110">
                    <i class="fas fa-life-ring"></i>
                </div>
            </a>
        <?php endif; ?>
    </div>
    <button onclick="toggleQuickActions()" id="mainFab" class="w-16 h-16 bg-[#1e4a76] rounded-full shadow-2xl flex items-center justify-center text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105 focus:outline-none">
        <i class="fas fa-plus text-2xl transition-transform duration-300" id="fabIcon"></i>
    </button>
</div>

<div id="fabOverlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-40 hidden transition-opacity" onclick="toggleQuickActions()"></div>

<script>
// Chart.js Global Config
Chart.defaults.font.family = 'Tajawal, sans-serif';
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(30, 74, 118, 0.9)'; // Primary Dark Blue
Chart.defaults.plugins.tooltip.padding = 14;
Chart.defaults.plugins.tooltip.cornerRadius = 10;
Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold', size: 14 };
Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };

let revenueChart, pipelineChart, activityChart;

// Activity data injected from server
const activityData = <?php echo json_encode($activity_json, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); ?>;

// Revenue Chart
const revenueCtx = document.getElementById('revenueChart');
if (revenueCtx) {
    const context = revenueCtx.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(30, 74, 118, 0.15)');
    gradient.addColorStop(1, 'rgba(30, 74, 118, 0.0)');

    revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
            datasets: [{
                label: 'الإيرادات',
                data: [12000, 19000, 15000, 25000, 22000, 30000],
                borderColor: '#1e4a76',
                backgroundColor: gradient,
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#1e4a76',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'الهدف',
                data: [15000, 15000, 20000, 20000, 25000, 25000],
                borderColor: '#94a3b8',
                backgroundColor: 'transparent',
                borderDash: [8, 4],
                tension: 0.4,
                fill: false,
                borderWidth: 2,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 20 } } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                    ticks: { callback: (value) => value.toLocaleString('ar-EG') + ' ج.م', font: { weight: 'bold' } }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

// Pipeline Chart
const pipelineCtx = document.getElementById('pipelineChart');
if (pipelineCtx) {
    pipelineChart = new Chart(pipelineCtx, {
        type: 'doughnut',
        data: {
            labels: ['استكشاف', 'تأهيل', 'عرض سعر', 'مفاوضات', 'مكتمل', 'فقد'],
            datasets: [{
                data: [<?php 
                    $pipeline_data = [];
                    $stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
                    $total_deals_sql = "SELECT COUNT(*) as total FROM deals";
                    $total_result = $db->fetch($total_deals_sql);
                    $has_deals = ($total_result['total'] ?? 0) > 0;
                    if ($has_deals) {
                        foreach ($stages as $stage) {
                            $stage_sql = "SELECT COUNT(*) as count FROM deals WHERE stage = '$stage'";
                            $stage_result = $db->fetch($stage_sql);
                            $pipeline_data[] = $stage_result['count'] ?? 0;
                        }
                    } else { $pipeline_data = [8, 5, 3, 4, 12, 2]; }
                    echo implode(',', $pipeline_data);
                ?>],
                backgroundColor: [
                    '#1e4a76', '#2d5f96', '#4a7db8', '#7ba3d1', '#10b981', '#ef4444'
                ],
                borderWidth: 4,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });
}

// Activity Chart
const activityCtx = document.getElementById('activityChart');
if (activityCtx) {
    const ctx = activityCtx.getContext('2d');
    activityChart = new Chart(activityCtx, {
        type: 'bar',
        data: {
            labels: activityData.week.labels,
            datasets: [{
                label: 'جهات الاتصال',
                data: activityData.week.contacts,
                backgroundColor: '#1e4a76',
                borderRadius: 6
            }, {
                label: 'الصفقات',
                data: activityData.week.deals,
                backgroundColor: '#4a7db8',
                borderRadius: 6
            }, {
                label: 'المهام',
                data: activityData.week.tasks,
                backgroundColor: '#e2e8f0',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 20 } } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false } },
                x: { grid: { display: false } }
            }
        }
    });
}

// Chart type change function
function changeChartType(chartName, type) {
    const buttons = document.querySelectorAll(`[data-chart="${chartName}"]`);
    buttons.forEach(btn => {
        btn.classList.remove('bg-white', 'text-[#1e4a76]', 'shadow-sm');
        btn.classList.add('text-gray-500');
    });
    
    const activeBtn = document.querySelector(`[data-chart="${chartName}"][data-type="${type}"]`);
    activeBtn.classList.remove('text-gray-500');
    activeBtn.classList.add('bg-white', 'text-[#1e4a76]', 'shadow-sm');
    
    if (chartName === 'revenue' && revenueChart) {
        revenueChart.config.type = type;
        if(type === 'bar') {
            revenueChart.data.datasets[0].borderRadius = 6;
            revenueChart.data.datasets[0].backgroundColor = '#1e4a76';
            revenueChart.data.datasets[1].borderRadius = 6;
            revenueChart.data.datasets[1].backgroundColor = '#94a3b8';
            revenueChart.data.datasets[1].borderDash = [];
        } else {
            const context = revenueCtx.getContext('2d');
            const gradient = context.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(30, 74, 118, 0.15)');
            gradient.addColorStop(1, 'rgba(30, 74, 118, 0.0)');
            revenueChart.data.datasets[0].backgroundColor = gradient;
            revenueChart.data.datasets[1].backgroundColor = 'transparent';
            revenueChart.data.datasets[1].borderDash = [8, 4];
        }
        revenueChart.update();
    }
}

// Activity period change function
function changeActivityPeriod(period) {
    const buttons = document.querySelectorAll('.activity-period-btn');
    buttons.forEach(btn => {
        btn.classList.remove('bg-white', 'text-[#1e4a76]', 'shadow-sm');
        btn.classList.add('text-gray-500');
    });
    
    const activeBtn = document.querySelector(`[data-period="${period}"]`);
    activeBtn.classList.remove('text-gray-500');
    activeBtn.classList.add('bg-white', 'text-[#1e4a76]', 'shadow-sm');
    
    if (activityChart) {
        const dataObj = activityData[period] || activityData.week;
        activityChart.data.labels = dataObj.labels;
        activityChart.data.datasets[0].data = dataObj.contacts;
        activityChart.data.datasets[1].data = dataObj.deals;
        activityChart.data.datasets[2].data = dataObj.tasks;
        activityChart.update();
    }
}

// Date range functionality
document.getElementById('dateRange').addEventListener('change', function() {
    const customRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customRange.classList.remove('hidden');
        customRange.classList.add('flex');
    } else {
        customRange.classList.add('hidden');
        customRange.classList.remove('flex');
        refreshDashboard();
    }
});

// Refresh dashboard function
function refreshDashboard(btn) {
    if(btn) {
        btn.innerHTML = '<i class="fas fa-sync-alt fa-spin ml-2"></i> جارٍ التحديث...';
        btn.disabled = true;
    }
    setTimeout(() => { location.reload(); }, 1000);
}

// Initialize tooltips and Time
document.addEventListener('DOMContentLoaded', function() {
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', hour12: false });
        const el = document.getElementById('currentTime');
        if (el) el.textContent = timeString;
    }
    updateTime();
    setInterval(updateTime, 1000);
});

// Toggle Quick Actions FAB
function toggleQuickActions() {
    const menu = document.getElementById('quickActionsMenu');
    const icon = document.getElementById('fabIcon');
    const overlay = document.getElementById('fabOverlay');
    
    const isOpen = !menu.classList.contains('invisible');
    
    if (isOpen) {
        menu.classList.add('opacity-0', 'translate-y-10', 'invisible');
        menu.classList.remove('opacity-100', 'translate-y-0', 'visible');
        icon.classList.remove('rotate-45');
        overlay.classList.add('hidden');
    } else {
        menu.classList.remove('opacity-0', 'translate-y-10', 'invisible');
        menu.classList.add('opacity-100', 'translate-y-0', 'visible');
        icon.classList.add('rotate-45');
        overlay.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>