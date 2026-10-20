<?php
// Delete contact (AJAX JSON)
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

// Permission
require_permission('contacts', 'delete');

header('Content-Type: application/json; charset=utf-8');

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

// Access check
if (!can_access_record('contacts', $id, 'delete')) {
	echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية حذف هذه الجهة']);
	exit;
}

try {
	$db = get_db();
	// fetch contact info before deletion for notification
	$contact = $db->fetch('SELECT id, first_name, last_name, assigned_to FROM contacts WHERE id = ?', [$id]);
	$deleted = $db->delete('contacts', 'id = ?', [$id]);
	if ($deleted) {
		log_audit('delete', 'contacts', $id, 'Deleted contact');
		// Notify assigned user and managers
		$name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?: $id;
		$title = 'حذف جهة اتصال: ' . $name;
		$message = 'تم حذف جهة الاتصال: ' . $name . ' من قبل: ' . (get_current_user_id() ? get_user_info(get_current_user_id())['full_name'] : '-') . '.';
		if (!empty($contact['assigned_to'])) {
			create_notification($contact['assigned_to'], $title, $message, 'info', 'contact', $id);
		}
		notify_managers($title, $message, 'contact', $id);
		echo json_encode(['success' => true, 'message' => 'تم حذف جهة الاتصال.']);
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'فشل الحذف']);
} catch (Exception $e) {
	error_log($e->getMessage());
	echo json_encode(['success' => false, 'message' => 'خطأ في الخادم']);
}
