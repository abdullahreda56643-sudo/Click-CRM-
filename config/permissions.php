<?php

// Permissions Configuration

// Prevent direct access

if (!defined('CRM_CONFIG')) {

    die('Direct access not allowed');

}



// Role-based permissions array

$permissions = [

    'admin' => [

        'dashboard' => ['view'],

        'contacts' => ['view', 'add', 'edit', 'delete'],

        'deals' => ['view', 'add', 'edit', 'delete'],

        'tasks' => ['view', 'add', 'edit', 'delete'],

        'tickets' => ['view', 'add', 'edit', 'delete'],

        'projects' => ['view', 'add', 'edit', 'delete'],

        'documents' => ['view', 'upload', 'delete'],

        'audit' => ['view'],

        'reports' => ['view'],

        

        'users' => ['view', 'add', 'edit', 'delete']

    ],

    'manager' => [

        'dashboard' => ['view'],

        'contacts' => ['view', 'add', 'edit'],

        'deals' => ['view', 'add', 'edit'],

        'tasks' => ['view', 'add', 'edit', 'delete'],

        'tickets' => ['view', 'add', 'edit'],

        'projects' => ['view', 'add', 'edit'],

        'documents' => ['view', 'upload', 'delete'],

        'audit' => ['view'],

        'reports' => ['view'],

        

        'users' => ['view']

    ],

    'user' => [

        'dashboard' => ['view'],

        'contacts' => ['view', 'add'],

        'deals' => ['view', 'add'],

        'tasks' => ['view', 'add', 'edit'],

        'tickets' => ['view', 'add'],

        'projects' => ['view'],

        'documents' => ['view', 'upload'],

        'audit' => [],

        'reports' => ['view'],

        

        'users' => []

    ]

];

// Allow overriding permissions via a JSON file (editable from UI)
$roles_file = __DIR__ . '/roles.json';
if (file_exists($roles_file)) {
    $json = @file_get_contents($roles_file);
    $decoded = @json_decode($json, true);
    if (is_array($decoded)) {
        $permissions = $decoded;
    }
}

// Save permissions to roles.json (returns bool)
function save_permissions(array $perms) {
    $roles_file = __DIR__ . '/roles.json';
    $json = json_encode($perms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    return @file_put_contents($roles_file, $json) !== false;
}



// Function to check if user has permission

function check_permission($module, $action, $user_role = null) {

    global $permissions;

    

    if ($user_role === null) {

        $user_role = get_current_user_role();

    }

    

    // Admin has all permissions

    if ($user_role === 'admin') {

        return true;

    }

    

    // Check if role exists

    if (!isset($permissions[$user_role])) {

        return false;

    }

    

    // Check if module exists for this role

    if (!isset($permissions[$user_role][$module])) {

        return false;

    }

    

    // Check if action is allowed for this module

    return in_array($action, $permissions[$user_role][$module]);

}



// Function to get current user role

function get_current_user_role() {

    if (isset($_SESSION['user_role'])) {

        return $_SESSION['user_role'];

    }

    return 'guest';

}



// Function to get current user ID

function get_current_user_id() {

    if (isset($_SESSION['user_id'])) {

        return $_SESSION['user_id'];

    }

    return null;

}



// Function to check if user is logged in

function is_logged_in() {

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

}



// Function to check if user can access specific record

function can_access_record($table, $record_id, $action = 'view') {

    $user_role = get_current_user_role();

    $user_id = get_current_user_id();

    

    // Admin can access everything

    if ($user_role === 'admin') {

        return true;

    }

    

    // Check basic permission first

    if (!check_permission($table, $action, $user_role)) {

        return false;

    }

    

    // For non-admin users, check ownership or assignment

    try {

        $db = get_db();

        

        switch ($table) {

            case 'contacts':

                $sql = "SELECT assigned_to FROM contacts WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && ($result['assigned_to'] == $user_id || $user_role === 'manager');

                

            case 'deals':

                $sql = "SELECT created_by FROM deals WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && $result['created_by'] == $user_id;

                

            case 'tasks':

                $sql = "SELECT assigned_to, created_by FROM tasks WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && ($result['assigned_to'] == $user_id || $result['created_by'] == $user_id);

                

            case 'tickets':

                $sql = "SELECT assigned_to, created_by FROM tickets WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && ($result['assigned_to'] == $user_id || $result['created_by'] == $user_id);

                

            case 'projects':

                $sql = "SELECT assigned_to, created_by FROM projects WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && ($result['assigned_to'] == $user_id || $result['created_by'] == $user_id);

                

            case 'documents':

                $sql = "SELECT uploaded_by FROM documents WHERE id = ?";

                $result = $db->fetch($sql, [$record_id]);

                return $result && $result['uploaded_by'] == $user_id;

                

            default:

                return false;

        }

    } catch (Exception $e) {

        error_log("Permission check failed: " . $e->getMessage());

        return false;

    }

}



// Function to get user's accessible records

function get_accessible_records($table, $user_id = null, $user_role = null) {

    if ($user_id === null) {

        $user_id = get_current_user_id();

    }

    

    if ($user_role === null) {

        $user_role = get_current_user_role();

    }

    

    // Admin can see all records

    if ($user_role === 'admin') {

        return '';

    }

    

    // Build WHERE clause based on table and user role

    $where_clauses = [];

    

    switch ($table) {

        case 'contacts':

            if ($user_role === 'manager') {

                $where_clauses[] = "assigned_to = ? OR assigned_to IS NULL";

            } else {

                $where_clauses[] = "assigned_to = ?";

            }

            break;

            

        case 'deals':

            $where_clauses[] = "created_by = ?";

            break;

            

        case 'tasks':

            $where_clauses[] = "(assigned_to = ? OR created_by = ?)";

            break;

            

        case 'tickets':

            $where_clauses[] = "(assigned_to = ? OR created_by = ?)";

            break;

            

        case 'projects':

            $where_clauses[] = "(assigned_to = ? OR created_by = ?)";

            break;

            

        case 'documents':

            $where_clauses[] = "uploaded_by = ?";

            break;

            

        default:

            return '';

    }

    

    return empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

}



// Function to get navigation menu items based on user role

function get_navigation_menu($user_role = null) {

    if ($user_role === null) {

        $user_role = get_current_user_role();

    }

    

    $menu = [];

    

    if (check_permission('dashboard', 'view', $user_role)) {

        $menu[] = [

            'name' => 'لوحة التحكم',

            'icon' => 'dashboard',

            'url' => 'modules/dashboard.php',

            'module' => 'dashboard'

        ];

    }

    

    if (check_permission('contacts', 'view', $user_role)) {

        $menu[] = [

            'name' => 'جهات الاتصال',

            'icon' => 'address-book',

            'url' => 'modules/contacts/',

            'module' => 'contacts'

        ];

    }

    

    if (check_permission('deals', 'view', $user_role)) {

        $menu[] = [

            'name' => 'الصفقات',

            'icon' => 'handshake',

            'url' => 'modules/deals/',

            'module' => 'deals'

        ];

    }

    

    if (check_permission('tasks', 'view', $user_role)) {

        $menu[] = [

            'name' => 'المهام',

            'icon' => 'clipboard-list',

            'url' => 'modules/tasks/',

            'module' => 'tasks'

        ];

    }

    

    if (check_permission('tickets', 'view', $user_role)) {

        $menu[] = [

            'name' => 'تذاكر الدعم',

            'icon' => 'headset',

            'url' => 'modules/tickets/',

            'module' => 'tickets'

        ];

    }

    

    if (check_permission('projects', 'view', $user_role)) {

        $menu[] = [

            'name' => 'المشاريع',

            'icon' => 'briefcase',

            'url' => 'modules/projects/',

            'module' => 'projects'

        ];

    }

    

    if (check_permission('documents', 'view', $user_role)) {

        $menu[] = [

            'name' => 'الوثائق',

            'icon' => 'file-alt',

            'url' => 'modules/documents/',

            'module' => 'documents'

        ];

    }

    

    // Audit menu removed: hidden from sidebar per configuration

    

    if (check_permission('reports', 'view', $user_role)) {

        $menu[] = [

            'name' => 'التقارير',

            'icon' => 'chart-bar',

            'url' => 'modules/reports/',

            'module' => 'reports'

        ];

    }

    // Users module
    if (check_permission('users', 'view', $user_role)) {
        $menu[] = [
            'name' => 'المستخدمين',
            'icon' => 'users',
            'url' => 'modules/users/',
            'module' => 'users'
        ];
    }

    

    // Settings module removed

    

    return $menu;

}



// Function to require permission (redirects if no permission)

function require_permission($module, $action) {

    if (!check_permission($module, $action)) {

        $_SESSION['error'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';

        redirect('index.php');

    }

}



// Function to require login

function require_login() {

    if (!is_logged_in()) {

        $_SESSION['error'] = 'يجب تسجيل الدخول للوصول إلى هذه الصفحة';

        redirect('login.php');

    }

}

?>