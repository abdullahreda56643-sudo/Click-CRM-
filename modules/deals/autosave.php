<?php
// Autosave draft for deals (session-based)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$input = $_POST;
$user = get_current_user_id();
if (!$user) {
    echo json_encode(['success'=>false,'message'=>'لم يتم تسجيل الدخول']);
    exit;
}

$key = 'deal_draft_' . $user;
$_SESSION[$key] = [ 'data' => $input, 'saved_at' => date('c') ];

echo json_encode(['success'=>true,'message'=>'تم الحفظ مؤقتًا','saved_at'=>$_SESSION[$key]['saved_at']]);

?>
