<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!check_permission('documents', 'upload')) {
        echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية للرفع']); exit;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) { echo json_encode(['success'=>false,'message'=>'رمز CSRF غير صالح']); exit; }

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'لا يوجد ملف مرفوع']); exit;
    }

    $related_type = $_POST['related_type'] ?? null;
    $related_id = $_POST['related_id'] ?? null;
    $description = trim($_POST['description'] ?? '');

    $res = upload_document($_FILES['file'], $related_type, $related_id, $description);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('documents/upload.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء الرفع']);
}

?>
