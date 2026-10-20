<?php
// Delete project (AJAX JSON)
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

// Buffer unexpected output to ensure we always return clean JSON
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_permission('projects', 'delete');

function send_json($data, $http_code = 200) {
	$buf = ob_get_clean();
	if ($buf) {
		error_log("modules/projects/delete.php - unexpected output: " . $buf);
	}
	http_response_code($http_code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE);
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? ($_POST['id'] ?? 0));
$token = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!validate_csrf_token($token)) { send_json(['success'=>false,'message'=>'رمز CSRF غير صالح'], 400); }
if (!$id) { send_json(['success'=>false,'message'=>'معرّف غير صالح'], 400); }

if (!can_access_record('projects', $id, 'delete')) { send_json(['success'=>false,'message'=>'ليس لديك صلاحية حذف هذا المشروع'], 403); }

try {
	$db = get_db();
	$project = $db->fetch('SELECT id, name, assigned_to FROM projects WHERE id = ?', [$id]);
	$deleted = $db->delete('projects', 'id = ?', [$id]);
	if ($deleted) {
		log_audit('delete', 'projects', $id, 'Deleted project');
		if (!empty($project['assigned_to']) && function_exists('create_notification')) {
			create_notification($project['assigned_to'], 'تم حذف مشروع', 'تم حذف المشروع: '.$project['name'], 'info', 'project', $id);
		}
		send_json(['success'=>true,'message'=>'تم حذف المشروع'], 200);
	}
	send_json(['success'=>false,'message'=>'فشل الحذف'], 500);
} catch (Exception $e) {
	error_log('modules/projects/delete.php exception: ' . $e->getMessage());
	send_json(['success'=>false,'message'=>'خطأ في الخادم'], 500);
}

?>
