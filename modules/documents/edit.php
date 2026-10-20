<?php
// Edit document metadata (description, related_type, related_id)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$id = $id === false || $id === null ? 0 : (int)$id;
if (!$id) {
    $_SESSION['error'] = 'معرّف غير صحيح.';
    redirect('modules/documents/');
}

$document = get_document($id);
if (!$document) {
    $_SESSION['error'] = 'المستند غير موجود.';
    redirect('modules/documents/');
}

// Only admin or uploader may edit metadata (project requirement)
if (!(get_current_user_role() === 'admin' || get_current_user_id() === (int)($document['uploaded_by'] ?? 0))) {
    $_SESSION['error'] = 'ليس لديك صلاحية تعديل هذا الملف.';
    redirect('modules/documents/view.php?id=' . $id);
}

$page_title = 'تعديل المستند - ' . APP_NAME;
$current_module = 'documents';

require_once __DIR__ . '/../../templates/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $errors[] = 'رمز CSRF غير صالح.';
    }

    $description = trim($_POST['description'] ?? '');
    $related_type = $_POST['related_type'] ?? null;
    $related_id = isset($_POST['related_id']) && $_POST['related_id'] !== '' ? intval($_POST['related_id']) : null;

    if (empty($errors)) {
        $db = get_db();
        $data = [
            'description' => $description,
            'related_type' => $related_type,
            'related_id' => $related_id
        ];

        try {
            $updated = $db->update('documents', $data, 'id = ?', [$id]);
            if ($updated) {
                log_audit('document_updated', 'documents', $id, "Metadata updated for {$document['original_name']}");
                $_SESSION['success'] = 'تم حفظ التعديلات.';
                redirect('modules/documents/view.php?id=' . $id);
            } else {
                $errors[] = 'فشل في حفظ التعديلات.';
            }
        } catch (Exception $e) {
            error_log('Document edit error: ' . $e->getMessage());
            $errors[] = 'حدث خطأ أثناء حفظ التعديلات.';
        }
    }
}

function safeTxt($s) { return htmlspecialchars($s ?? ''); }

?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl mx-auto">
    <h3 class="text-lg font-semibold mb-4">تعديل بيانات المستند</h3>

    <?php if (!empty($errors)): ?>
        <div class="bg-rose-50 border border-rose-100 p-3 rounded mb-4 text-rose-700">
            <?php foreach($errors as $err) echo '<div>'.safeTxt($err).'</div>'; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>" />

        <div class="mb-4">
            <label class="block text-sm font-medium">اسم الملف</label>
            <div class="mt-2 font-semibold"><?php echo safeTxt($document['original_name']); ?></div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">الوصف</label>
            <textarea name="description" class="mt-2 w-full" rows="4"><?php echo safeTxt($document['description']); ?></textarea>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">مرتبط ب</label>
            <select name="related_type" class="mt-2">
                <option value="" <?php if (empty($document['related_type'])) echo 'selected'; ?>>بدون</option>
                <option value="contact" <?php if ($document['related_type']=='contact') echo 'selected'; ?>>جهة اتصال</option>
                <option value="deal" <?php if ($document['related_type']=='deal') echo 'selected'; ?>>صفقة</option>
                <option value="project" <?php if ($document['related_type']=='project') echo 'selected'; ?>>مشروع</option>
                <option value="ticket" <?php if ($document['related_type']=='ticket') echo 'selected'; ?>>تذكرة</option>
            </select>
            <input type="text" name="related_id" placeholder="معرّف السجل" value="<?php echo safeTxt($document['related_id']); ?>" class="mt-2" />
        </div>

        <div class="flex justify-end gap-2">
            <a href="view.php?id=<?php echo $document['id']; ?>" class="px-4 py-2 bg-gray-100 rounded">إلغاء</a>
            <button type="submit" class="px-4 py-2 bg-[#1e4a76] text-white rounded">حفظ</button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
