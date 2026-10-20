<?php
// AJAX Quick Search
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

// Ensure core helpers are available for AJAX execution
require_once __DIR__ . '/../functions.php';

// Disable display of errors for AJAX responses and buffer output
ini_set('display_errors', 0);
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search query
$query = isset($_GET['q']) ? clean_input($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

// Get current user info
$user_id = get_current_user_id();
$user_role = get_current_user_role();
$db = get_db();

$results = [];

// Search Contacts
if (check_permission('contacts', 'view', $user_role)) {
    $contacts_sql = "SELECT id, first_name, last_name, email, phone, company, type 
                    FROM contacts 
                    WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)
                    ORDER BY first_name, last_name 
                    LIMIT $limit";
    
    $search_term = "%$query%";
    $contacts = $db->fetchAll($contacts_sql, [$search_term, $search_term, $search_term, $search_term]);
    
    foreach ($contacts as $contact) {
        $results[] = [
            'type' => 'contact',
            'id' => $contact['id'],
            'title' => $contact['first_name'] . ' ' . $contact['last_name'],
            'subtitle' => $contact['company'] ?: $contact['email'],
            'url' => base_url('modules/contacts/edit.php?id=' . $contact['id']),
            'icon' => 'fas fa-user',
            'color' => 'blue'
        ];
    }
}

// Search Deals
if (check_permission('deals', 'view', $user_role)) {
    $deals_sql = "SELECT d.id, d.title, d.amount, c.first_name, c.last_name 
                 FROM deals d 
                 LEFT JOIN contacts c ON d.contact_id = c.id 
                 WHERE (d.title LIKE ? OR d.description LIKE ?)
                 ORDER BY d.created_at DESC 
                 LIMIT $limit";
    
    $search_term = "%$query%";
    $deals = $db->fetchAll($deals_sql, [$search_term, $search_term]);
    
    foreach ($deals as $deal) {
        $results[] = [
            'type' => 'deal',
            'id' => $deal['id'],
            'title' => $deal['title'],
            'subtitle' => ($deal['first_name'] ? $deal['first_name'] . ' ' . $deal['last_name'] : '') . 
                         ($deal['amount'] ? ' • ' . format_currency($deal['amount']) : ''),
            'url' => base_url('modules/deals/edit.php?id=' . $deal['id']),
            'icon' => 'fas fa-handshake',
            'color' => 'green'
        ];
    }
}

// Search Tasks
if (check_permission('tasks', 'view', $user_role)) {
    $tasks_sql = "SELECT t.id, t.title, t.status, t.due_date, u.full_name as assigned_name 
                 FROM tasks t 
                 LEFT JOIN users u ON t.assigned_to = u.id 
                 WHERE (t.title LIKE ? OR t.description LIKE ?)
                 ORDER BY t.created_at DESC 
                 LIMIT $limit";
    
    $search_term = "%$query%";
    $tasks = $db->fetchAll($tasks_sql, [$search_term, $search_term]);
    
    foreach ($tasks as $task) {
        $results[] = [
            'type' => 'task',
            'id' => $task['id'],
            'title' => $task['title'],
            'subtitle' => ($task['assigned_name'] ?: 'غير مسند') . 
                         ($task['due_date'] ? ' • ' . format_date($task['due_date']) : ''),
            'url' => base_url('modules/tasks/edit.php?id=' . $task['id']),
            'icon' => 'fas fa-tasks',
            'color' => 'yellow'
        ];
    }
}

// Search Tickets
if (check_permission('tickets', 'view', $user_role)) {
    $tickets_sql = "SELECT t.id, t.subject, t.status, c.first_name, c.last_name 
                   FROM tickets t 
                   LEFT JOIN contacts c ON t.contact_id = c.id 
                   WHERE (t.subject LIKE ? OR t.description LIKE ? OR CAST(t.id AS CHAR) LIKE ?)
                   ORDER BY t.created_at DESC 
                   LIMIT $limit";
    
    $search_term = "%$query%";
    $tickets = $db->fetchAll($tickets_sql, [$search_term, $search_term, $search_term]);
    
    foreach ($tickets as $ticket) {
        $results[] = [
            'type' => 'ticket',
            'id' => $ticket['id'],
            'title' => '#' . $ticket['id'] . ' - ' . $ticket['subject'],
            'subtitle' => ($ticket['first_name'] ? $ticket['first_name'] . ' ' . $ticket['last_name'] : ''),
            'url' => base_url('modules/tickets/edit.php?id=' . $ticket['id']),
            'icon' => 'fas fa-life-ring',
            'color' => 'red'
        ];
    }
}

// Limit total results
$results = array_slice($results, 0, 10);

// Clean accidental output then return JSON
$buf = ob_get_clean();
if (!empty($buf)) error_log('quick_search unexpected output: ' . $buf);
echo json_encode(['results' => $results]);
?>
