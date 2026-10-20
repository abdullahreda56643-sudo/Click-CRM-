<?php
// Add ticket
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('tickets', 'add');

 $page_title = 'إضافة تذكرة - ' . APP_NAME;
 $current_module = 'tickets';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) { $_SESSION['error']='رمز CSRF غير صالح.'; redirect('modules/tickets/'); }

    $subject = clean_input($_POST['subject'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $contact_id = intval($_POST['contact_id'] ?? 0) ?: null;
    $priority = clean_input($_POST['priority'] ?? 'medium');
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;

    if (empty($subject) || empty($description)) { $_SESSION['error']='الموضوع والوصف مطلوبان.'; redirect('modules/tickets/add.php'); }

    try {
        $db = get_db();
        $data = [
            'subject' => $subject,
            'description' => $description,
            'contact_id' => $contact_id,
            'priority' => $priority,
            'status' => 'open',
            'assigned_to' => $assigned_to,
            'created_by' => get_current_user_id(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('tickets', $data);
        if ($id) {
            // Handle attachments if any
            if (!empty($_FILES['attachments'])) {
                $uploadDir = __DIR__ . '/../../uploads/tickets/' . $id;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                foreach ($_FILES['attachments']['name'] as $idx => $name) {
                    if (empty($name)) continue;
                    $tmp = $_FILES['attachments']['tmp_name'][$idx];
                    $size = intval($_FILES['attachments']['size'][$idx]);
                    $type = $_FILES['attachments']['type'][$idx] ?? '';
                    // basic validation: size < 5MB
                    if ($size > 5 * 1024 * 1024) continue;
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safe = bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $safe;
                    if (move_uploaded_file($tmp, $dest)) {
                        try {
                            $db->insert('ticket_attachments', [
                                'ticket_id' => $id,
                                'reply_id' => null,
                                'filename' => $name,
                                'path' => 'uploads/tickets/' . $id . '/' . $safe,
                                'mime' => $type,
                                'size' => $size,
                                'uploaded_by' => get_current_user_id(),
                                'uploaded_at' => date('Y-m-d H:i:s')
                            ]);
                        } catch (Exception $e) {
                            error_log('Failed to insert ticket_attachment: ' . $e->getMessage());
                        }
                    }
                }
            }
            log_audit('create', 'tickets', $id, 'Created new ticket');
            if (!empty($assigned_to)) {
                create_notification($assigned_to, 'تذكرة جديدة', "تم إنشاء تذكرة: {$subject}", 'info', 'ticket', $id);
            }
            // Email contact if configured
            try {
                if (!empty($contact_id) && get_setting('notify_contact_on_create', true)) {
                    $c = $db->fetch('SELECT email, first_name FROM contacts WHERE id = ?', [$contact_id]);
                    if ($c && !empty($c['email']) && validate_email($c['email'])) {
                        $title = 'تم استلام تذكرتك #' . $id;
                        $message = "مرحباً " . ($c['first_name'] ?: '') . "\n\nتم استلام تذكرتك بعنوان: {$subject}\n\n" . strip_tags($description) . "\n\nللاطّلاع على التذكرة: " . base_url("modules/tickets/view.php?id={$id}");
                        $sent = send_email_notification($c['email'], $title, $message);
                        if (!$sent) {
                            error_log('Ticket create email not sent for ticket ' . $id . ' to ' . $c['email']);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to send ticket create email: ' . $e->getMessage());
            }

            $_SESSION['success'] = 'تم إنشاء التذكرة.';
            redirect('modules/tickets/');
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = 'خطأ أثناء إنشاء التذكرة.';
        redirect('modules/tickets/add.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';

$contacts = [];$users = [];
try { $db = get_db(); $contacts = $db->fetchAll('SELECT id, first_name, last_name FROM contacts ORDER BY first_name'); } catch (Exception $e) { $contacts = []; }
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
    <div class="mb-8 pb-6 border-b border-gray-100">
        <h3 class="text-2xl font-bold text-[#1e4a76]">إنشاء تذكرة جديدة</h3>
        <p class="text-gray-400 text-sm mt-2">أدخل تفاصيل تذكرة الدعم</p>
    </div>

    <form method="POST" action="" id="ticketForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="mb-6">
            <label class="text-sm font-semibold">الموضوع <span class="text-rose-500">*</span></label>
            <input type="text" name="subject" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" required />
        </div>

        <div class="mb-6">
            <label class="text-sm font-semibold">الوصف <span class="text-rose-500">*</span></label>
            <textarea name="description" rows="6" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" required></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div>
                <label class="text-sm font-semibold">الجهة</label>
                <select name="contact_id" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <option value="">بدون</option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold">الأولوية</label>
                <select name="priority" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <option value="low">منخفض</option>
                    <option value="medium" selected>متوسط</option>
                    <option value="high">عالي</option>
                    <option value="urgent">عاجل</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold">تعيين إلى</label>
                <select name="assigned_to" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <option value="">بدون</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="index.php" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold">إلغاء</a>
            <button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">إنشاء التذكرة</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
