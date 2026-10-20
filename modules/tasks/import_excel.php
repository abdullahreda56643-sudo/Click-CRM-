<?php
// Import tasks from Excel/CSV
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/notifications.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('display_errors', 0);
ob_start();

function respond($success, $message, $isJson = false, $data = []) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Import Tasks</title></head><body>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        if (!empty($data['errors'])) { echo '<ul>'; foreach ($data['errors'] as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; echo '</ul>'; }
        echo '<p><a href="' . htmlspecialchars(base_url('modules/tasks/import_excel.php')) . '">عودة</a></p>';
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
        if (in_array($ext, ['xlsx', 'xls']) && !class_exists('ZipArchive')) {
            respond(false, 'ملفات XLSX/XLS تتطلب تفعيل امتداد PHP "zip" (ZipArchive). فعِّل ext-zip أو ارفع ملف CSV.', $isAjax);
        }

        $spreadsheet = IOFactory::load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) respond(false, 'الملف لا يحتوي على بيانات للاستيراد', $isAjax);

        $header = array_shift($rows);
        $map = [];
        foreach ($header as $col => $val) {
            $k = strtolower(trim((string)$val));
            if (in_array($k, ['title', 'subject', 'name'])) $map[$col] = 'title';
            elseif (in_array($k, ['due_date', 'due'])) $map[$col] = 'due_date';
            elseif (in_array($k, ['priority'])) $map[$col] = 'priority';
            elseif (in_array($k, ['assigned_to', 'assigned', 'assigned_user', 'owner'])) $map[$col] = 'assigned_to';
            elseif (in_array($k, ['description', 'notes'])) $map[$col] = 'description';
            else $map[$col] = null;
        }

        $db = get_db();
        $inserted = 0;
        $errors = [];
        $current_user = get_current_user_id();

        foreach ($rows as $rIndex => $row) {
            $rnum = $rIndex + 2;
            $data = ['created_by' => $current_user, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            $hasTitle = false;

            foreach ($row as $col => $val) {
                $field = $map[$col] ?? null;
                if (!$field) continue;
                $val = trim((string)$val);
                if ($field === 'title') { $data['title'] = $val; $hasTitle = true; }
                if ($field === 'due_date') {
                    $date = false;
                    if ($val !== '') {
                        $date = DateTime::createFromFormat('Y-m-d', $val) ?: DateTime::createFromFormat('d/m/Y', $val) ?: DateTime::createFromFormat('m/d/Y', $val);
                    }
                    if ($date) $data['due_date'] = $date->format('Y-m-d');
                }
                if ($field === 'priority') { $data['priority'] = $val; }
                if ($field === 'description') { $data['description'] = $val; }
                if ($field === 'assigned_to') { $data['assigned_to'] = is_numeric($val) ? intval($val) : null; }
            }

            if (!$hasTitle) { $errors[] = "الصف $rnum: لا يوجد عنوان (title)"; continue; }

            // validate assigned_to
            if (!empty($data['assigned_to'])) {
                $u = $db->fetch('SELECT id FROM users WHERE id = ?', [$data['assigned_to']]);
                if (!$u) { $data['assigned_to'] = null; }
            }

            try {
                // Filter data to columns in tasks
                $cols = $db->fetchAll("SHOW COLUMNS FROM tasks");
                $taskCols = array_map(function($c){ return $c['Field']; }, $cols ?: []);
                $data = array_intersect_key($data, array_flip($taskCols));

                $id = $db->insert('tasks', $data);
                if ($id) { $inserted++; log_audit('task_imported', 'tasks', $id, 'Imported from Excel'); if (!empty($data['assigned_to'])) notify_new_task($id, $data['assigned_to']); }
            } catch (Exception $ex) {
                $errors[] = "الصف $rnum: " . $ex->getMessage();
            }
        }

        $buf = ob_get_clean(); if (!empty($buf)) error_log('tasks/import unexpected output: ' . $buf);
        $message = "تم استيراد $inserted مهمة";
        if ($isAjax) respond(true, $message, true, ['inserted' => $inserted, 'errors' => $errors]);
        else {
            if (!empty($errors)) { $_SESSION['warning'] = $message . '. بعض الصفوف لم تُستورد.'; $_SESSION['import_errors'] = $errors; }
            else { $_SESSION['success'] = $message; }
            header('Location: ' . base_url('modules/tasks/index.php'));
            exit;
        }

    } catch (Exception $e) {
        $buf = ob_get_clean(); if (!empty($buf)) error_log('tasks/import error: ' . $buf);
        if ($isAjax) respond(false, 'فشل في معالجة الملف: ' . $e->getMessage(), true);
        else { $_SESSION['error'] = 'فشل في معالجة الملف: ' . $e->getMessage(); header('Location: ' . base_url('modules/tasks/index.php')); exit; }
    }

} else {
    $csrf = generate_csrf_token();
    echo '<!doctype html><html><head><meta charset="utf-8"><title>استيراد المهام</title></head><body>';
    echo '<h3>استيراد مهام من ملف Excel (xlsx, xls, csv)</h3>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required />';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '" />';
    echo '<button type="submit">تحميل واستيراد</button>';
    echo '</form>';
    echo '<p>الصف الأول يجب أن يحتوي رؤوس مثل: <strong>title, due_date, priority, assigned_to, description</strong>.</p>';
    echo '</body></html>';
}

?>
