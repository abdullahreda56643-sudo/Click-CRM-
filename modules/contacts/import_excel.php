<?php
// Import contacts from Excel (XLSX/XLS/CSV)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function respond($success, $message, $isJson = true, $data = []) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    } else {
        echo htmlspecialchars($message);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'طريقة الطلب غير مدعومة');
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    respond(false, 'رمز CSRF غير صالح', $isAjax);
}

if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    respond(false, 'لم يتم رفع ملف صالح', $isAjax);
}

$tmp = $_FILES['excel_file']['tmp_name'];
$name = $_FILES['excel_file']['name'] ?? 'upload';
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$allowed = ['xlsx', 'xls', 'csv'];
if (!in_array($ext, $allowed)) {
    respond(false, 'امتداد الملف غير مدعوم. الرجاء استخدام xlsx, xls أو csv', $isAjax);
}

try {
    if (in_array($ext, ['xlsx', 'xls']) && !class_exists('ZipArchive')) {
        respond(false, 'ملفات XLSX/XLS تتطلب تفعيل امتداد PHP "zip" (ZipArchive).', $isAjax);
    }

    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        respond(false, 'الملف لا يحتوي على بيانات للاستيراد', $isAjax);
    }

    $header = array_shift($rows);
    $map = [];
    foreach ($header as $col => $val) {
        $k = strtolower(trim((string)$val));
        if (in_array($k, ['first_name','firstname','first'])) $map[$col] = 'first_name';
        elseif (in_array($k, ['last_name','lastname','last'])) $map[$col] = 'last_name';
        elseif (in_array($k, ['email','email_address'])) $map[$col] = 'email';
        elseif (in_array($k, ['phone','phone_number','mobile'])) $map[$col] = 'phone';
        elseif (in_array($k, ['company','company_name'])) $map[$col] = 'company';
        elseif (in_array($k, ['position','job_title'])) $map[$col] = 'position';
        elseif (in_array($k, ['type','contact_type'])) $map[$col] = 'type';
        elseif (in_array($k, ['assigned_to','owner','assigned'])) $map[$col] = 'assigned_to';
        elseif (in_array($k, ['notes','note','remarks'])) $map[$col] = 'notes';
        else $map[$col] = null;
    }

    $db = get_db();
    $inserted = 0; $errors = [];
    $current_user = get_current_user_id();

    foreach ($rows as $rIndex => $row) {
        $rnum = $rIndex + 2;
        $data = [ 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s') ];
        foreach ($row as $col => $val) {
            $field = $map[$col] ?? null;
            if (!$field) continue;
            $val = trim((string)$val);
            if ($field === 'assigned_to') { $data['assigned_to'] = is_numeric($val) ? intval($val) : null; }
            else { $data[$field] = $val !== '' ? $val : null; }
        }

        // Basic validation: at least first_name or email
        if (empty($data['first_name']) && empty($data['email'])) { $errors[] = "الصف $rnum: مطلوب على الأقل الاسم الأول أو البريد الإلكتروني"; continue; }

        // validate assigned_to if present
        if (!empty($data['assigned_to'])) {
            $u = $db->fetch('SELECT id FROM users WHERE id = ?', [$data['assigned_to']]);
            if (!$u) { $errors[] = "الصف $rnum: معرف المستخدم المعين غير صالح"; $data['assigned_to'] = null; }
        }

        try {
            // prevent duplicate by email if provided
            if (!empty($data['email'])) {
                $existing = $db->fetch('SELECT id FROM contacts WHERE email = ?', [$data['email']]);
                if ($existing) { $errors[] = "الصف $rnum: البريد الإلكتروني {$data['email']} موجود بالفعل (تخطي)"; continue; }
            }

            // filter to contacts columns
            $cols = $db->fetchAll("SHOW COLUMNS FROM contacts");
            $contactCols = array_map(function($c){ return $c['Field']; }, $cols ?: []);
            $insertData = array_intersect_key($data, array_flip($contactCols));
            if (empty($insertData['first_name']) && empty($insertData['last_name']) && empty($insertData['email'])) {
                $errors[] = "الصف $rnum: بيانات غير كافية"; continue;
            }

            $id = $db->insert('contacts', $insertData);
            if ($id) { $inserted++; log_audit('contact_imported', 'contacts', $id, 'Imported from Excel'); }
        } catch (Exception $ex) {
            $errors[] = "الصف $rnum: فشل الإدخال - " . $ex->getMessage();
        }
    }

    $message = "تم استيراد $inserted جهة اتصال" . ($inserted === 1 ? '' : 'ات');
    respond(true, $message, $isAjax, ['inserted' => $inserted, 'errors' => $errors]);

} catch (Exception $e) {
    respond(false, 'فشل في معالجة الملف: ' . $e->getMessage(), $isAjax);
}
