<?php
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!check_permission('users','delete')) {
    echo json_encode(['success'=>false,'message'=>'لا تملك صلاحية الحذف']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);
$token = $input['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    echo json_encode(['success'=>false,'message'=>'رمز CSRF غير صالح']); exit;
}

if (!$id) {
    echo json_encode(['success'=>false,'message'=>'معرّف المستخدم مطلوب']); exit;
}

$res = delete_user($id);
if ($res['success']) {
    echo json_encode(['success'=>true,'message'=>'تم حذف المستخدم']);
} else {
    echo json_encode(['success'=>false,'message'=>$res['message'] ?? 'فشل حذف المستخدم']);
}

?>
