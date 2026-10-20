<?php
// Core Utility Functions
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Include database functions
require_once __DIR__ . '/../config/db.php';

// Function to log audit trail
function log_audit($action, $table_name, $record_id, $description) {
    try {
        $db = get_db();
        
        $audit_data = [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'table_name' => $table_name,
            'record_id' => $record_id,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        return $db->insert('audit_log', $audit_data);
        
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

// Function to clean input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to format date
function format_date($date, $format = null) {
    if ($format === null) {
        $format = DATE_FORMAT;
    }
    
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

// Function to format datetime
function format_datetime($datetime, $format = null) {
    if ($format === null) {
        $format = DATETIME_FORMAT;
    }
    
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}

// Function to get relative time
function time_ago($datetime) {
    if (empty($datetime)) {
        return '';
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'منذ لحظات';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return 'منذ ' . $minutes . ' دقائق';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return 'منذ ' . $hours . ' ساعات';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return 'منذ ' . $days . ' أيام';
    } else {
        return format_date($datetime);
    }
}

// Function to generate random string
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Function to paginate results
function paginate($query, $params = [], $page = 1, $per_page = null) {
    if ($per_page === null) {
        $per_page = ITEMS_PER_PAGE;
    }
    
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $per_page;
    
    try {
        $db = get_db();
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM ($query) as count_query";
        $count_result = $db->fetch($count_query, $params);
        $total = $count_result['total'] ?? 0;
        
        // Get paginated results
        $paginated_query = $query . " LIMIT $per_page OFFSET $offset";
        $results = $db->fetchAll($paginated_query, $params);
        
        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
            'has_next' => $page < ceil($total / $per_page),
            'has_prev' => $page > 1
        ];
        
    } catch (Exception $e) {
        error_log("Pagination error: " . $e->getMessage());
        return [
            'data' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => $per_page,
            'total_pages' => 0,
            'has_next' => false,
            'has_prev' => false
        ];
    }
}

// Function to render pagination links
function render_pagination($pagination_data, $base_url) {
    $current_page = $pagination_data['page'];
    $total_pages = $pagination_data['total_pages'];
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav class="flex items-center justify-between">';
    $html .= '<div class="flex-1 flex justify-between sm:hidden">';
    
    // Previous link for mobile
    if ($pagination_data['has_prev']) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">السابق</a>';
    }
    
    // Next link for mobile
    if ($pagination_data['has_next']) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . '" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">التالي</a>';
    }
    
    $html .= '</div>';
    $html .= '<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">';
    $html .= '<div>';
    $html .= '<p class="text-sm text-gray-700">';
    $html .= 'عرض <span class="font-medium">' . (($current_page - 1) * $pagination_data['per_page'] + 1) . '</span>';
    $html .= ' إلى <span class="font-medium">' . min($current_page * $pagination_data['per_page'], $pagination_data['total']) . '</span>';
    $html .= ' من <span class="font-medium">' . $pagination_data['total'] . '</span> نتيجة';
    $html .= '</p>';
    $html .= '</div>';
    $html .= '<div>';
    
    // Desktop pagination
    $html .= '<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">';
    
    // Previous button
    if ($pagination_data['has_prev']) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . '" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">السابق</a>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<a href="' . $base_url . '?page=1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
        if ($start_page > 2) {
            $html .= '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active_class = $i == $current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
        $html .= '<a href="' . $base_url . '?page=' . $i . '" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $active_class . '">' . $i . '</a>';
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
        }
        $html .= '<a href="' . $base_url . '?page=' . $total_pages . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($pagination_data['has_next']) {
        $html .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . '" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">التالي</a>';
    }
    
    $html .= '</nav>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</nav>';
    
    return $html;
}

// Function to get status badge HTML
function get_status_badge($status, $type = 'default') {
    $badges = [
        'default' => [
            'active' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">نشط</span>',
            'inactive' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">غير نشط</span>',
            'pending' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">في الانتظار</span>',
            'completed' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">مكتمل</span>',
            'in_progress' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">قيد التنفيذ</span>',
            'cancelled' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">ملغي</span>',
            'on_hold' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">معلق</span>',
            'planning' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">تخطيط</span>',
        ],
        'priority' => [
            'low' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">منخفض</span>',
            'medium' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">متوسط</span>',
            'high' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">عالي</span>',
            'urgent' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">عاجل</span>',
        ],
        'contact_type' => [
            'lead' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">عميل محتمل</span>',
            'client' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">عميل حالي</span>',
        ],
        'deal_stage' => [
            'prospecting' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">استكشاف</span>',
            'qualification' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">تأهيل</span>',
            'proposal' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">عرض سعر</span>',
            'negotiation' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">مفاوضات</span>',
            'closed_won' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">مكتمل</span>',
            'closed_lost' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">فقد</span>',
        ],
        'ticket_status' => [
            'open' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">مفتوح</span>',
            'in_progress' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">قيد المعالجة</span>',
            'resolved' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">حل</span>',
            'closed' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">مغلق</span>',
        ]
    ];
    
    return $badges[$type][$status] ?? $status;
}

// Function to format currency
function format_currency($amount, $currency = 'جنيه مصري') {
    return number_format($amount, 2) . ' ' . $currency;
}

// Function to get file size in human readable format
function format_file_size($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Function to sanitize filename
function sanitize_filename($filename) {
    // Remove any character that is not alphanumeric, space, hyphen, or underscore
    $filename = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $filename);
    // Replace multiple spaces with single space
    $filename = preg_replace('/\s+/', ' ', $filename);
    // Trim spaces from beginning and end
    $filename = trim($filename);
    return $filename;
}

// Function to get current URL
function get_current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . '://' . $host . $uri;
}

// Function to generate CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token is valid (not expired - 1 hour)
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Function to display flash messages
function display_flash_messages() {
    $messages = '';
    $flash_for_js = [];
    
    if (isset($_SESSION['success'])) {
        $messages .= '<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">';
        $messages .= htmlspecialchars($_SESSION['success']);
        $messages .= '</div>';
        $flash_for_js[] = ['type' => 'success', 'message' => $_SESSION['success']];
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $messages .= '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">';
        $messages .= htmlspecialchars($_SESSION['error']);
        $messages .= '</div>';
        $flash_for_js[] = ['type' => 'error', 'message' => $_SESSION['error']];
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $messages .= '<div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4">';
        $messages .= htmlspecialchars($_SESSION['warning']);
        $messages .= '</div>';
        $flash_for_js[] = ['type' => 'warning', 'message' => $_SESSION['warning']];
        unset($_SESSION['warning']);
    }
    
    if (isset($_SESSION['info'])) {
        $messages .= '<div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4">';
        $messages .= htmlspecialchars($_SESSION['info']);
        $messages .= '</div>';
        $flash_for_js[] = ['type' => 'info', 'message' => $_SESSION['info']];
        unset($_SESSION['info']);
    }

    // Also expose flash messages to JS in a safe JSON form for unified toasts
    if (!empty($flash_for_js)) {
        $json = json_encode($flash_for_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $messages .= "<script>window.__FLASH_MESSAGES = $json; </script>";
    }

    // If there are import errors from CSV/XLS import, show them as a detailed warning list
    if (!empty($_SESSION['import_errors'])) {
        $errors = $_SESSION['import_errors'];
        unset($_SESSION['import_errors']);
        $messages .= '<div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4">';
        $messages .= '<strong>أخطاء في الاستيراد:</strong><ul style="margin-top:8px;">';
        foreach ($errors as $err) {
            $messages .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $messages .= '</ul></div>';
    }

    return $messages;
}

// Function to export data to CSV
function export_to_csv($data, $filename, $headers = []) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 display in Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers if provided
    if (!empty($headers)) {
        fputcsv($output, $headers);
    }
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
?>