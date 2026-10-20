<?php
// Generates a sample Excel file for contacts import
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers and sample rows (use fromArray for compatibility)
$headers = ['first_name','last_name','email','phone','company','position','type','assigned_to'];
$rows = [
    ['Ahmed','Ali','ahmed.ali@example.com','+201234567890','Acme Co.','Sales Manager','عميل',1],
    ['Sara','Mohamed','sara.moh@example.com','+201112223334','Beta Ltd.','Marketing','محتمل',2],
    ['Khaled','Hassan','khaled.h@example.com','+201998877665','Gamma Inc.','Support','عميل',null]
];

// Write header at A1 and rows starting at A2
$sheet->fromArray($headers, NULL, 'A1');
$sheet->fromArray($rows, NULL, 'A2');

$filename = __DIR__ . '/sample_contacts.xlsx';

try {
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="sample_contacts.xlsx"');
    readfile($filename);
    exit;
} catch (Exception $e) {
    echo 'فشل في إنشاء الملف: ' . $e->getMessage();
}
