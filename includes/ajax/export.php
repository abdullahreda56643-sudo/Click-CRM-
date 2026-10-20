<?php
// Export handler (supports module=contacts, format=csv)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../auth.php";

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$format = $_POST['format'] ?? 'csv';
$module = $_POST['module'] ?? '';
$token = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($token)) {
    $_SESSION['error'] = 'رمز CSRF غير صالح.';
    redirect('modules/' . ($module ?: ''));
}

try {
    if ($module === 'contacts' && $format === 'csv') {
        $db = get_db();

        // Fetch contacts with assigned user name
        $sql = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.type, c.assigned_to, u.full_name as assigned_name, c.created_at
                FROM contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                ORDER BY c.created_at DESC";
        $rows = $db->fetchAll($sql);

        // Map type labels
        $type_labels = [ 'lead' => 'عميل محتمل', 'client' => 'عميل' ];

        // Prepare filename
        $date = date('Ymd_His');
        $filename = 'contacts_export_' . $date . '.csv';

        // Send headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Professional header rows
        $company_name = get_setting('company_name', APP_NAME);
        fputcsv($output, ["تقرير جهات الاتصال"]);
        fputcsv($output, ["الجهة المُصدِرة:", $company_name]);
        fputcsv($output, ["تاريخ التصدير:", date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        // Column headers (Arabic)
        $headers = [ 'الرقم', 'الاسم الكامل', 'البريد الإلكتروني', 'الهاتف', 'الشركة', 'النوع', 'المسؤول', 'تاريخ الإنشاء' ];
        fputcsv($output, $headers);

        // Rows
        foreach ($rows as $r) {
            $full_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $type_label = $type_labels[$r['type']] ?? $r['type'];
            $assigned = $r['assigned_name'] ?? '';
            $created = $r['created_at'] ?? '';

            $line = [
                $r['id'],
                $full_name,
                $r['email'] ?? '',
                $r['phone'] ?? '',
                $r['company'] ?? '',
                $type_label,
                $assigned,
                $created
            ];

            fputcsv($output, $line);
        }

        fclose($output);
        exit;
        exit;
    }

    // XLSX export (styled) using PhpSpreadsheet
    if ($module === 'contacts' && $format === 'xlsx') {
        // Check library
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            // Inform user to install dependency
            $_SESSION['error'] = 'مطلوب تثبيت مكتبة PhpSpreadsheet لتصدير XLSX. شغّل: composer require phpoffice/phpspreadsheet';
            redirect('modules/contacts/');
        }

        // Create spreadsheet
        $db = get_db();
        $sql = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.type, c.assigned_to, u.full_name as assigned_name, c.created_at
                FROM contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                ORDER BY c.created_at DESC";
        $rows = $db->fetchAll($sql);

        $type_labels = [ 'lead' => 'عميل محتمل', 'client' => 'عميل' ];

        // Build spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        // Document properties
        $spreadsheet->getProperties()
            ->setCreator(get_setting('company_name', APP_NAME))
            ->setTitle('تصدير جهات الاتصال')
            ->setCreatedAt(new \DateTime());

        $rowNum = 1;
        // Report title (merged)
        $sheet->mergeCells('A'.$rowNum.':H'.$rowNum);
        $sheet->setCellValue('A'.$rowNum, 'تقرير جهات الاتصال');
        $sheet->getStyle('A'.$rowNum)->getFont()->setBold(true)->setSize(16);
        $rowNum++;

        // Company and date
        $sheet->setCellValue('A'.$rowNum, 'الجهة المُصدِرة:');
        $sheet->setCellValue('B'.$rowNum, get_setting('company_name', APP_NAME));
        $rowNum++;
        $sheet->setCellValue('A'.$rowNum, 'تاريخ التصدير:');
        $sheet->setCellValue('B'.$rowNum, date('Y-m-d H:i:s'));
        $rowNum += 2;

        // Headers
        $headers = [ 'الرقم', 'الاسم الكامل', 'البريد الإلكتروني', 'الهاتف', 'الشركة', 'النوع', 'المسؤول', 'تاريخ الإنشاء' ];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$rowNum, $h);
            $col++;
        }

        // Style header row (bold, background color, white text, center)
        $headerRow = $rowNum;
        $headerRange = 'A'.$headerRow.':H'.$headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2B6EA3');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Rows
        $rowNum = $headerRow + 1;
        $bodyStartRow = $rowNum;
        foreach ($rows as $idx => $r) {
            $full_name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $type_label = $type_labels[$r['type']] ?? $r['type'];
            $assigned = $r['assigned_name'] ?? '';
            $created = $r['created_at'] ?? '';

            $sheet->setCellValue('A'.$rowNum, $r['id']);
            $sheet->setCellValue('B'.$rowNum, $full_name);
            $sheet->setCellValue('C'.$rowNum, $r['email'] ?? '');
            $sheet->setCellValue('D'.$rowNum, $r['phone'] ?? '');
            $sheet->setCellValue('E'.$rowNum, $r['company'] ?? '');
            $sheet->setCellValue('F'.$rowNum, $type_label);
            $sheet->setCellValue('G'.$rowNum, $assigned);
            $sheet->setCellValue('H'.$rowNum, $created);

            // Row styling: vertical center and right alignment for RTL text
            $sheet->getStyle('A'.$rowNum.':H'.$rowNum)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            // Alternating row shading for readability
            if (($idx % 2) === 0) {
                $sheet->getStyle('A'.$rowNum.':H'.$rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FBFF');
            }

            $rowNum++;
        }

        // Auto-size columns
        foreach (range('A','H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Thin borders around table
        $tableRange = 'A'.$headerRow.':H'.($rowNum - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setARGB('FFCCCCCC');

        // Freeze header row so body starts below it
        $sheet->freezePane('A'.$bodyStartRow);

        // Output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $date = date('Ymd_His');
        $filename = 'contacts_export_' . $date . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer->save('php://output');
        exit;
    }

    // Unknown module/format -> redirect back with error
    $_SESSION['error'] = 'نوع التصدير أو الوحدة غير مدعوم.';
    redirect('modules/' . ($module ?: ''));

} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء التصدير.';
    redirect('modules/' . ($module ?: ''));
}

?>