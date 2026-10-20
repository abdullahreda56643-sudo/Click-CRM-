<?php
// Delete deal (AJAX JSON)
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? ($_POST['id'] ?? 0));
$token = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!validate_csrf_token($token)) {
	echo json_encode(['success' => false, 'message' => 'رمز CSRF غير صالح']);
	exit;
}

if (!$id) {
	echo json_encode(['success' => false, 'message' => 'معرّف غير صالح']);
	exit;
}

try {
	$db = get_db();
	// fetch deal details for notification
	$deal = $db->fetch('SELECT id, title, created_by FROM deals WHERE id = ?', [$id]);
	$deleted = $db->delete('deals', 'id = ?', [$id]);
	if ($deleted) {
		log_audit('delete', 'deals', $id, 'Deleted deal');
		// Notify managers about deletion
		$title = 'حذف صفقة: ' . ($deal['title'] ?? $id);
		$message = 'تم حذف الصفقة: ' . ($deal['title'] ?? $id) . ' من قبل: ' . (get_current_user_id() ? get_user_info(get_current_user_id())['full_name'] : '-') . '.';
		notify_managers($title, $message, 'deal', $id);
		echo json_encode(['success' => true, 'message' => 'تم حذف الصفقة.']);
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'فشل الحذف']);
} catch (Exception $e) {
	error_log($e->getMessage());
	echo json_encode(['success' => false, 'message' => 'خطأ في الخادم']);
}

