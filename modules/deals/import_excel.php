<?php
// Import deals from Excel (XLSX/XLS/CSV)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('display_errors', 0);
ob_start();

function respond($success, $message, $isJson = false, $data = []) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Import Deals</title></head><body>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        if (!empty($data['errors'])) {
            echo '<ul>'; foreach ($data['errors'] as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; echo '</ul>';
        }
        echo '<p><a href="' . htmlspecialchars(base_url('modules/deals/import_excel.php')) . '">عودة</a></p>';
        echo '</body></html>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // XLSX/XLS require PHP Zip extension (ZipArchive)
        if (in_array($ext, ['xlsx', 'xls']) && !class_exists('ZipArchive')) {
            respond(false, 'ملفات XLSX/XLS تتطلب تفعيل امتداد PHP "zip" (ZipArchive). فعِّل ext-zip في php.ini ثم أعد تشغيل Apache، أو ارفع ملف CSV بدلاً من XLSX.', $isAjax);
        }

        $spreadsheet = IOFactory::load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            respond(false, 'الملف لا يحتوي على بيانات للاستيراد', $isAjax);
        }

        // Determine header mapping from first row
        $header = array_shift($rows);
        $map = [];
        foreach ($header as $col => $val) {
            $k = strtolower(trim((string)$val));
            if (in_array($k, ['title', 'subject', 'name'])) $map[$col] = 'title';
            elseif (in_array($k, ['contact_email', 'email'])) $map[$col] = 'contact_email';
            elseif (in_array($k, ['amount', 'value'])) $map[$col] = 'amount';
            elseif ($k === 'stage') $map[$col] = 'stage';
            elseif (in_array($k, ['expected_close_date', 'close_date', 'expected_close'])) $map[$col] = 'expected_close_date';
            elseif (in_array($k, ['assigned_to', 'assigned', 'assigned_user', 'owner'])) $map[$col] = 'assigned_to';
            else $map[$col] = null;
        }

        $db = get_db();
        $inserted = 0;
        $errors = [];
        $current_user = get_current_user_id();

        foreach ($rows as $rIndex => $row) {
            $rnum = $rIndex + 2; // spreadsheet row number (header was row 1)
            $data = ['created_by' => $current_user, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            $hasTitle = false;

            foreach ($row as $col => $val) {
                $field = $map[$col] ?? null;
                if (!$field) continue;
                $val = trim((string)$val);
                if ($field === 'title') { $data['title'] = $val; $hasTitle = true; }
                if ($field === 'contact_email') {
                    if ($val !== '') {
                        $contact = $db->fetch('SELECT id FROM contacts WHERE email = ?', [$val]);
                        $data['contact_id'] = $contact['id'] ?? null;
                    }
                }
                if ($field === 'amount') { $data['amount'] = is_numeric($val) ? $val : 0; }
                if ($field === 'stage') { $data['stage'] = $val; }
                if ($field === 'expected_close_date') {
                    $date = false;
                    if ($val !== '') {
                        $date = DateTime::createFromFormat('Y-m-d', $val) ?: DateTime::createFromFormat('d/m/Y', $val) ?: DateTime::createFromFormat('m/d/Y', $val);
                    }
                    if ($date) $data['expected_close_date'] = $date->format('Y-m-d');
                }
                if ($field === 'assigned_to') { $data['assigned_to'] = is_numeric($val) ? intval($val) : null; }
            }

            if (!$hasTitle) { $errors[] = "الصف $rnum: لا يوجد عنوان (title)"; continue; }

            // Ensure contact_id exists: if not found, create a placeholder contact
            if (empty($data['contact_id'])) {
                try {
                    $contactFirst = mb_substr($data['title'] ?? 'Imported Contact', 0, 50);
                    $contactData = [
                        'first_name' => $contactFirst,
                        'last_name' => '(import)',
                        'email' => null,
                        'phone' => null,
                        'company' => null,
                        'position' => null,
                        'type' => 'lead',
                        'assigned_to' => $current_user,
                        'notes' => 'Placeholder contact created during import',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $cid = $db->insert('contacts', $contactData);
                    if ($cid) {
                        $data['contact_id'] = $cid;
                    }
                } catch (Exception $ex) {
                    $errors[] = "الصف $rnum: فشل إنشاء جهة اتصال افتراضية - " . $ex->getMessage();
                    continue;
                }
            }

                try {
                        // Filter $data to only columns that exist in `deals` to avoid unknown column errors
                        $cols = $db->fetchAll("SHOW COLUMNS FROM deals");
                        $dealCols = array_map(function($c){ return $c['Field']; }, $cols ?: []);
                        $data = array_intersect_key($data, array_flip($dealCols));

                        error_log('import_excel inserting deal row ' . $rnum . ' filtered data: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
                        $id = $db->insert('deals', $data);
                        if ($id) { $inserted++; log_audit('deal_imported', 'deals', $id, 'Imported from Excel'); }
                    } catch (Exception $ex) {
                        error_log('import_excel insert error row ' . $rnum . ': ' . $ex->getMessage());
                        $errors[] = "الصف $rnum: " . $ex->getMessage();
                    }
        }

        $buf = ob_get_clean(); if (!empty($buf)) error_log('import_excel unexpected output: ' . $buf);
        $message = "تم استيراد $inserted صفقة";
        if ($isAjax) {
            respond(true, $message, true, ['inserted' => $inserted, 'errors' => $errors]);
        } else {
            if (!empty($errors)) {
                $_SESSION['warning'] = $message . '. بعض الصفوف لم تُستورد.';
                $_SESSION['import_errors'] = $errors;
            } else {
                $_SESSION['success'] = $message;
            }
            header('Location: ' . base_url('modules/deals/index.php'));
            exit;
        }

    } catch (Exception $e) {
        $buf = ob_get_clean(); if (!empty($buf)) error_log('import_excel error: ' . $buf);
        if ($isAjax) {
            respond(false, 'فشل في معالجة الملف: ' . $e->getMessage(), true);
        } else {
            $_SESSION['error'] = 'فشل في معالجة الملف: ' . $e->getMessage();
            header('Location: ' . base_url('modules/deals/index.php'));
            exit;
        }
    }

} else {
    // Simple upload form with download template button
    $csrf = generate_csrf_token();
    $templateUrl = base_url('modules/deals/download_template.php');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>استيراد الصفقات</title></head><body>';
    echo '<h3>استيراد صفقات من ملف Excel (xlsx, xls, csv)</h3>';
    echo '<p>يمكنك تنزيل قالب جاهز للتعبئة ثم رفعه هنا.</p>';
    echo '<p><a href="' . htmlspecialchars($templateUrl) . '" class="btn" download style="display:inline-block;padding:8px 12px;background:#1e4a76;color:#fff;border-radius:6px;text-decoration:none;margin-bottom:12px;">تحميل قالب الاستيراد (Excel)</a></p>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required />';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '" />';
    echo '<div style="margin-top:10px;"><button type="submit" style="padding:8px 12px;background:#16a34a;color:#fff;border-radius:6px;border:none;">تحميل واستيراد</button></div>';
    echo '</form>';
    echo '<p style="margin-top:12px;color:#555;">ملحوظة: يجب أن يحتوي الصف الأول على رؤوس مثل: <strong>title, contact_email, amount, stage, expected_close_date</strong>. يمكن تعديل الأعمدة وفق الحاجة.</p>';
    echo '</body></html>';
}

?>
