<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!check_permission('documents', 'delete')) {
        echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية للحذف']); exit;
    }

    // Accept JSON or form POST
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $token = $input['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) { echo json_encode(['success'=>false,'message'=>'رمز CSRF غير صالح']); exit; }

    $document_id = intval($input['id'] ?? 0);
    if (!$document_id) { echo json_encode(['success'=>false,'message'=>'معرّف غير صالح']); exit; }

    $res = delete_uploaded_file($document_id);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('documents/delete.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ في الحذف']);
}

?>
