<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload.php';

// GET download by id
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$document_id) {
    http_response_code(404); echo 'الملف غير موجود'; exit;
}

$res = download_file($document_id);
if (!$res['success']) {
    http_response_code(403); echo $res['message'] ?? 'غير مصرح'; exit;
}

// serve file
serve_file($res['file_path'], $res['original_name'], $res['file_type']);

?>
