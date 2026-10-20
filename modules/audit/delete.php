<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept JSON POST
$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);
$token = $input['csrf_token'] ?? '';

if (!validate_csrf_token($token)) {
    echo json_encode(['success' => false, 'message' => 'رمز CSRF غير صالح']);
    exit;
}

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'معرّف غير صالح']);
    exit;
}

try {
    require_permission('audit', 'delete');
    $db = get_db();
    $deleted = $db->delete('audit_log', 'id = ?', [$id]);
    if ($deleted) {
        echo json_encode(['success' => true, 'message' => 'تم حذف السجل بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل الحذف']);
    }
} catch (Exception $e) {
    error_log('audit/delete.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ داخلي']);
}

?>
