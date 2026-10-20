<?php
// Global Search Page
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/config/config.php';
}

// Require login
require_login();

// Include required files
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notifications.php';

// Set page variables
$page_title = 'البحث الشامل - ' . APP_NAME;
$current_module = 'search';

// Get search parameters
$query = isset($_GET['q']) ? clean_input($_GET['q']) : '';
$type = isset($_GET['type']) ? clean_input($_GET['type']) : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get current user info
$user_id = get_current_user_id();
$user_role = get_current_user_role();
$db = get_db();

// Initialize results array
$results = [
    'contacts' => [],
    'deals' => [],
    'tasks' => [],
    'tickets' => [],
    'total' => 0
];

// Perform search if query is provided
if (!empty($query)) {
    try {
        // Search Contacts
        if (check_permission('contacts', 'view', $user_role) && ($type === 'all' || $type === 'contacts')) {
            $contacts_sql = "SELECT id, first_name, last_name, email, phone, company, type, created_at 
                            FROM contacts 
                            WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)
                            ORDER BY created_at DESC 
                            LIMIT $limit OFFSET $offset";
            
            $search_term = "%$query%";
            $results['contacts'] = $db->fetchAll($contacts_sql, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        }
        
        // Search Deals
        if (check_permission('deals', 'view', $user_role) && ($type === 'all' || $type === 'deals')) {
            $deals_sql = "SELECT d.*, c.first_name, c.last_name 
                         FROM deals d 
                         LEFT JOIN contacts c ON d.contact_id = c.id 
                         WHERE (d.title LIKE ? OR d.description LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)
                         ORDER BY d.created_at DESC 
                         LIMIT $limit OFFSET $offset";
            
            $search_term = "%$query%";
            $results['deals'] = $db->fetchAll($deals_sql, [$search_term, $search_term, $search_term, $search_term]);
        }
        
        // Search Tasks
        if (check_permission('tasks', 'view', $user_role) && ($type === 'all' || $type === 'tasks')) {
            $tasks_sql = "SELECT t.*, u.full_name as assigned_name 
                         FROM tasks t 
                         LEFT JOIN users u ON t.assigned_to = u.id 
                         WHERE (t.title LIKE ? OR t.description LIKE ?)
                         ORDER BY t.created_at DESC 
                         LIMIT $limit OFFSET $offset";
            
            $search_term = "%$query%";
            $results['tasks'] = $db->fetchAll($tasks_sql, [$search_term, $search_term]);
        }
        
        // Search Tickets
        if (check_permission('tickets', 'view', $user_role) && ($type === 'all' || $type === 'tickets')) {
            $tickets_sql = "SELECT t.*, c.first_name, c.last_name, u.full_name as assigned_name 
                       FROM tickets t 
                       LEFT JOIN contacts c ON t.contact_id = c.id 
                       LEFT JOIN users u ON t.assigned_to = u.id 
                       WHERE (t.subject LIKE ? OR t.description LIKE ? OR CAST(t.id AS CHAR) LIKE ?)
                       ORDER BY t.created_at DESC 
                       LIMIT $limit OFFSET $offset";
            
            $search_term = "%$query%";
            $results['tickets'] = $db->fetchAll($tickets_sql, [$search_term, $search_term, $search_term]);
        }

        // Calculate total results
        $results['total'] = count($results['contacts']) + count($results['deals']) + count($results['tasks']) + count($results['tickets']);
    } catch (Exception $e) {
        error_log('Global search error: ' . $e->getMessage());
        // Show the exception message to the developer (since display_errors is enabled in config during development)
        $_SESSION['error'] = 'حدث خطأ أثناء البحث: ' . $e->getMessage();
        // leave results empty so page still loads
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<style>
    .search-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .search-filters {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 0.5rem 1rem;
        border: 2px solid #e2e8f0;
        background: white;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .filter-btn:hover {
        border-color: #1e4a76;
        background: #f8fafc;
    }
    
    .filter-btn.active {
        background: #1e4a76;
        color: white;
        border-color: #1e4a76;
    }
    
    .result-section {
        margin-bottom: 3rem;
    }
    
    .result-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .result-item:hover {
        border-color: #1e4a76;
        box-shadow: 0 4px 12px rgba(30, 74, 118, 0.1);
        transform: translateY(-1px);
    }
    
    .result-type-icon {
        width: 40px;
        height: 40px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    
    .contact-icon { background: #dbeafe; color: #1e40af; }
    .deal-icon { background: #dcfce7; color: #166534; }
    .task-icon { background: #fef3c7; color: #92400e; }
    .ticket-icon { background: #fee2e2; color: #991b1b; }
    
    .highlight {
        background: #fef3c7;
        padding: 0.1rem 0.2rem;
        border-radius: 0.2rem;
        font-weight: 600;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem;
        color: #64748b;
    }
    
    .search-stats {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="search-container">
    <!-- Search Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-[#1e4a76]/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-search text-[#1e4a76] text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">البحث الشامل</h1>
                <p class="text-gray-500 text-sm">ابحث في جهات الاتصال، الصفقات، المهام والتذاكر</p>
            </div>
        </div>
        
        <!-- Search Form -->
        <form method="GET" class="flex gap-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" 
                       name="q" 
                       value="<?php echo htmlspecialchars($query); ?>"
                       placeholder="اكتب كلمات البحث..." 
                       class="w-full pr-12 pl-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#1e4a76] focus:border-transparent"
                       autofocus>
            </div>
            <button type="submit" class="px-6 py-3 bg-[#1e4a76] text-white rounded-xl hover:bg-[#163d63] transition-colors">
                <i class="fas fa-search ml-2"></i>
                بحث
            </button>
        </form>
    </div>
    
    <?php if (!empty($query)): ?>
        <!-- Search Statistics -->
        <div class="search-stats">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-gray-600">نتائج البحث عن: </span>
                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($query); ?></span>
                </div>
                <div class="text-gray-500">
                    <?php echo $results['total']; ?> نتيجة
                </div>
            </div>
        </div>
        
        <!-- Search Filters -->
        <div class="search-filters">
            <button class="filter-btn <?php echo $type === 'all' ? 'active' : ''; ?>" 
                    onclick="window.location.href='?q=<?php echo urlencode($query); ?>&type=all'">
                <i class="fas fa-globe ml-2"></i>الكل
            </button>
            <?php if (check_permission('contacts', 'view', $user_role)): ?>
            <button class="filter-btn <?php echo $type === 'contacts' ? 'active' : ''; ?>" 
                    onclick="window.location.href='?q=<?php echo urlencode($query); ?>&type=contacts'">
                <i class="fas fa-users ml-2"></i>جهات الاتصال
            </button>
            <?php endif; ?>
            <?php if (check_permission('deals', 'view', $user_role)): ?>
            <button class="filter-btn <?php echo $type === 'deals' ? 'active' : ''; ?>" 
                    onclick="window.location.href='?q=<?php echo urlencode($query); ?>&type=deals'">
                <i class="fas fa-handshake ml-2"></i>الصفقات
            </button>
            <?php endif; ?>
            <?php if (check_permission('tasks', 'view', $user_role)): ?>
            <button class="filter-btn <?php echo $type === 'tasks' ? 'active' : ''; ?>" 
                    onclick="window.location.href='?q=<?php echo urlencode($query); ?>&type=tasks'">
                <i class="fas fa-tasks ml-2"></i>المهام
            </button>
            <?php endif; ?>
            <?php if (check_permission('tickets', 'view', $user_role)): ?>
            <button class="filter-btn <?php echo $type === 'tickets' ? 'active' : ''; ?>" 
                    onclick="window.location.href='?q=<?php echo urlencode($query); ?>&type=tickets'">
                <i class="fas fa-life-ring ml-2"></i>التذاكر
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Search Results -->
        <?php if ($results['total'] > 0): ?>
            
            <!-- Contacts Results -->
            <?php if (!empty($results['contacts']) && ($type === 'all' || $type === 'contacts')): ?>
            <div class="result-section">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-users text-blue-600 ml-2"></i>
                    جهات الاتصال (<?php echo count($results['contacts']); ?>)
                </h3>
                <?php foreach ($results['contacts'] as $contact): ?>
                <div class="result-item" onclick="window.location.href='modules/contacts/edit.php?id=<?php echo $contact['id']; ?>'">
                    <div class="flex items-start gap-4">
                        <div class="result-type-icon contact-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 mb-1">
                                <?php echo highlight_search($contact['first_name'] . ' ' . $contact['last_name'], $query); ?>
                            </h4>
                            <p class="text-gray-600 text-sm mb-2">
                                <?php if ($contact['company']): ?>
                                    <?php echo highlight_search($contact['company'], $query); ?>
                                <?php endif; ?>
                            </p>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                <?php if ($contact['email']): ?>
                                    <span><i class="fas fa-envelope ml-1"></i><?php echo highlight_search($contact['email'], $query); ?></span>
                                <?php endif; ?>
                                <?php if ($contact['phone']): ?>
                                    <span><i class="fas fa-phone ml-1"></i><?php echo highlight_search($contact['phone'], $query); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-tag ml-1"></i><?php echo $contact['type'] === 'lead' ? 'عميل محتمل' : 'عميل حالي'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Deals Results -->
            <?php if (!empty($results['deals']) && ($type === 'all' || $type === 'deals')): ?>
            <div class="result-section">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-handshake text-green-600 ml-2"></i>
                    الصفقات (<?php echo count($results['deals']); ?>)
                </h3>
                <?php foreach ($results['deals'] as $deal): ?>
                <div class="result-item" onclick="window.location.href='modules/deals/edit.php?id=<?php echo $deal['id']; ?>'">
                    <div class="flex items-start gap-4">
                        <div class="result-type-icon deal-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 mb-1">
                                <?php echo highlight_search($deal['title'], $query); ?>
                            </h4>
                            <p class="text-gray-600 text-sm mb-2">
                                <?php if ($deal['contact_id']): ?>
                                    العميل: <?php echo htmlspecialchars($deal['first_name'] . ' ' . $deal['last_name']); ?>
                                <?php endif; ?>
                            </p>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                <?php if ($deal['amount']): ?>
                                    <span><i class="fas fa-dollar-sign ml-1"></i><?php echo format_currency($deal['amount']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-flag ml-1"></i><?php echo get_deal_stage_label($deal['stage']); ?></span>
                                <span><i class="fas fa-calendar ml-1"></i><?php echo format_date($deal['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Tasks Results -->
            <?php if (!empty($results['tasks']) && ($type === 'all' || $type === 'tasks')): ?>
            <div class="result-section">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-tasks text-yellow-600 ml-2"></i>
                    المهام (<?php echo count($results['tasks']); ?>)
                </h3>
                <?php foreach ($results['tasks'] as $task): ?>
                <div class="result-item" onclick="window.location.href='modules/tasks/edit.php?id=<?php echo $task['id']; ?>'">
                    <div class="flex items-start gap-4">
                        <div class="result-type-icon task-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 mb-1">
                                <?php echo highlight_search($task['title'], $query); ?>
                            </h4>
                            <?php if ($task['description']): ?>
                            <p class="text-gray-600 text-sm mb-2">
                                <?php echo highlight_search(substr($task['description'], 0, 100) . '...', $query); ?>
                            </p>
                            <?php endif; ?>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                <span><i class="fas fa-flag ml-1"></i><?php echo get_status_label($task['status']); ?></span>
                                <span><i class="fas fa-exclamation-triangle ml-1"></i><?php echo get_priority_label($task['priority']); ?></span>
                                <?php if ($task['assigned_name']): ?>
                                    <span><i class="fas fa-user ml-1"></i><?php echo htmlspecialchars($task['assigned_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($task['due_date']): ?>
                                    <span><i class="fas fa-calendar ml-1"></i><?php echo format_date($task['due_date']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Tickets Results -->
            <?php if (!empty($results['tickets']) && ($type === 'all' || $type === 'tickets')): ?>
            <div class="result-section">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-life-ring text-red-600 ml-2"></i>
                    التذاكر (<?php echo count($results['tickets']); ?>)
                </h3>
                <?php foreach ($results['tickets'] as $ticket): ?>
                <div class="result-item" onclick="window.location.href='modules/tickets/edit.php?id=<?php echo $ticket['id']; ?>'">
                    <div class="flex items-start gap-4">
                        <div class="result-type-icon ticket-icon">
                            <i class="fas fa-life-ring"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 mb-1">
                                #<?php echo $ticket['id']; ?> - <?php echo highlight_search($ticket['subject'], $query); ?>
                            </h4>
                            <?php if ($ticket['description']): ?>
                            <p class="text-gray-600 text-sm mb-2">
                                <?php echo highlight_search(substr($ticket['description'], 0, 100) . '...', $query); ?>
                            </p>
                            <?php endif; ?>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                                <span><i class="fas fa-flag ml-1"></i><?php echo get_status_label($ticket['status']); ?></span>
                                <span><i class="fas fa-exclamation-triangle ml-1"></i><?php echo get_priority_label($ticket['priority']); ?></span>
                                <?php if ($ticket['first_name'] || $ticket['last_name']): ?>
                                    <span><i class="fas fa-user ml-1"></i><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($ticket['assigned_name']): ?>
                                    <span><i class="fas fa-user-tie ml-1"></i><?php echo htmlspecialchars($ticket['assigned_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Results -->
            <div class="no-results">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">لا توجد نتائج</h3>
                <p class="text-gray-500">لم يتم العثور على نتائج لـ "<?php echo htmlspecialchars($query); ?>"</p>
                <p class="text-gray-400 text-sm mt-2">جرب استخدام كلمات مختلفة أو تحقق من التهجئة</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Initial Search State -->
        <div class="text-center py-12">
            <div class="w-20 h-20 bg-[#1e4a76]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search text-[#1e4a76] text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">ابحث عن أي شيء</h3>
            <p class="text-gray-500">استخدم مربع البحث أعلاه للعثور على جهات الاتصال، الصفقات، المهام أو التذاكر</p>
            <div class="mt-6 text-sm text-gray-400">
                <p><i class="fas fa-lightbulb ml-2"></i>نصائح للبحث:</p>
                <ul class="mt-2 space-y-1">
                    <li>استخدم الأسماء، عناوين البريد الإلكتروني، أرقام الهواتف</li>
                    <li>ابحث عن مواضيع الصفقات أو المهام</li>
                    <li>استخدم أرقام التذاكر للبحث المباشر</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-focus search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput && searchInput.value) {
            searchInput.value = '';
            searchInput.focus();
        }
    }
});
</script>

<?php
// Helper function to highlight search terms
function highlight_search($text, $search_term) {
    if (empty($search_term)) return $text;
    
    $words = explode(' ', $search_term);
    foreach ($words as $word) {
        if (strlen($word) > 2) {
            $text = preg_replace('/(' . preg_quote($word, '/') . ')/i', '<span class="highlight">$1</span>', $text);
        }
    }
    return $text;
}

// Helper functions for labels
function get_deal_stage_label($stage) {
    $stages = [
        'prospecting' => 'استكشاف',
        'qualification' => 'تأهيل',
        'proposal' => 'عرض سعر',
        'negotiation' => 'مفاوضات',
        'closed_won' => 'مكتمل',
        'closed_lost' => 'فقد'
    ];
    return $stages[$stage] ?? $stage;
}

function get_status_label($status) {
    $statuses = [
        'pending' => 'في الانتظار',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'open' => 'مفتوح',
        'resolved' => 'محلول',
        'closed' => 'مغلق'
    ];
    return $statuses[$status] ?? $status;
}

function get_priority_label($priority) {
    $priorities = [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'مرتفع',
        'urgent' => 'عاجل'
    ];
    return $priorities[$priority] ?? $priority;
}

require_once __DIR__ . '/templates/footer.php';
?>