<?php
// Server-side export to Excel for contacts
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';

// Composer autoload (PhpSpreadsheet)
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Check for PhpSpreadsheet
if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "PhpSpreadsheet is not installed.\nRun: composer require phpoffice/phpspreadsheet\n";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $db = get_db();
    // Apply access filtering
    $current_user = get_current_user_id();
    $access_where = get_accessible_records('contacts');
    $params = [];
    $where_sql = '';
    if ($access_where) {
        $where_sql = ' ' . $access_where;
        $q = substr_count($where_sql, '?');
        for ($i = 0; $i < $q; $i++) $params[] = $current_user;
    }
    $sql = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.type, u.full_name as assigned_name, c.created_at FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id" . ($where_sql ?: '') . " ORDER BY c.created_at DESC";
    $rows = $db->fetchAll($sql, $params);

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setCreator(APP_NAME)->setTitle(APP_NAME . ' Contacts Export');
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('جهات الاتصال');

    // Build data array (header + rows) and write with fromArray for compatibility
    $headers = ['الاسم', 'البريد الإلكتروني', 'الهاتف', 'الشركة', 'النوع', 'المسؤول', 'تاريخ الإنشاء'];
    $data = [];
    $data[] = $headers;
    foreach ($rows as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $type_label = ($r['type'] ?? '') === 'client' ? 'عميل' : 'عميل محتمل';
        $data[] = [
            $name,
            $r['email'] ?? '',
            $r['phone'] ?? '',
            $r['company'] ?? '',
            $type_label,
            $r['assigned_name'] ?? '',
            $r['created_at'] ?? ''
        ];
    }

    // Write all data starting at A1
    $sheet->fromArray($data, NULL, 'A1');

    // Styling: header, column widths, autosize fallback
    $lastRow = max(1, count($data));

    // Header style
    $sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDDDDD');
    $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Column widths (preferred) then autosize for others
    $sheet->getColumnDimension('A')->setWidth(28); // name
    $sheet->getColumnDimension('B')->setWidth(30); // email
    $sheet->getColumnDimension('C')->setWidth(18); // phone
    $sheet->getColumnDimension('D')->setWidth(22); // company
    $sheet->getColumnDimension('E')->setWidth(14); // type
    $sheet->getColumnDimension('F')->setWidth(20); // assigned
    $sheet->getColumnDimension('G')->setWidth(20); // created_at

    // Right-to-left and alignment for Arabic content
    $sheet->getStyle('A1:G' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->setRightToLeft(true);

    // Borders for all cells
    $sheet->getStyle('A1:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFBBBBBB');

    // Date format for Created At column (G)
    if ($lastRow >= 2) {
        $sheet->getStyle('G2:G' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);
    }

    // Auto-filter and freeze header
    $sheet->setAutoFilter('A1:G' . $lastRow);
    $sheet->freezePane('A2');

    // Alternate row shading for readability
    for ($r = 2; $r <= $lastRow; $r++) {
        if ($r % 2 === 0) {
            $sheet->getStyle('A' . $r . ':G' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9F9F9');
        }
    }

    $filename = preg_replace('/[^A-Za-z0-9\\-_]/', '_', APP_NAME) . '_contacts.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log('Export Excel error: ' . $e->getMessage());
    http_response_code(500);
    echo 'خطأ في تصدير الملف. راجع سجل الأخطاء.';
}

?>
