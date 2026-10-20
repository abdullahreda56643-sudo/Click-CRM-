<?php
// Serve deals import template as a downloadable file
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();

$file = __DIR__ . '/import_template.xls';
if (!file_exists($file)) {
    http_response_code(404);
    echo 'Template not found.';
    exit;
}

$basename = 'deals_import_template.xls';
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $basename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
