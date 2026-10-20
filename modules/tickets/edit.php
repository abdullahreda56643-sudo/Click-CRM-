<?php
// Edit ticket
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('tickets', 'edit');

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { redirect('modules/tickets/'); }

$db = get_db();
$ticket = $db->fetch('SELECT * FROM tickets WHERE id = ?', [$id]);
if (!$ticket) { redirect('modules/tickets/'); }
if (!can_access_record('tickets', $id, 'edit')) { $_SESSION['error']='غير مصرح'; redirect('modules/tickets/'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validate_csrf_token($token)) { $_SESSION['error']='رمز CSRF غير صالح.'; redirect("modules/tickets/edit.php?id={$id}"); }

	$subject = clean_input($_POST['subject'] ?? '');
	$description = clean_input($_POST['description'] ?? '');
	$contact_id = intval($_POST['contact_id'] ?? 0) ?: null;
	$priority = clean_input($_POST['priority'] ?? 'medium');
	$assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;
	$status = clean_input($_POST['status'] ?? $ticket['status']);

	if (empty($subject) || empty($description)) { $_SESSION['error']='الموضوع والوصف مطلوبان.'; redirect("modules/tickets/edit.php?id={$id}"); }

        try {
        	$update = [ 'subject'=>$subject, 'description'=>$description, 'contact_id'=>$contact_id, 'priority'=>$priority, 'assigned_to'=>$assigned_to, 'status'=>$status, ];
        	$db->update('tickets', $update, 'id = ?', [$id]);
		log_audit('update','tickets',$id,'Updated ticket');
		// attachments
		if (!empty($_FILES['attachments'])) {
			$uploadDir = __DIR__ . '/../../uploads/tickets/' . $id;
			if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
			foreach ($_FILES['attachments']['name'] as $idx => $name) {
				if (empty($name)) continue;
				$tmp = $_FILES['attachments']['tmp_name'][$idx];
				$size = intval($_FILES['attachments']['size'][$idx]);
				$type = $_FILES['attachments']['type'][$idx] ?? '';
				if ($size > 5 * 1024 * 1024) continue;
				$ext = pathinfo($name, PATHINFO_EXTENSION);
				$safe = bin2hex(random_bytes(8)) . '.' . $ext;
				$dest = $uploadDir . '/' . $safe;
				if (move_uploaded_file($tmp, $dest)) {
					try {
						$db->insert('ticket_attachments', [
							'ticket_id'=>$id,'reply_id'=>null,'filename'=>$name,'path'=>'uploads/tickets/'.$id.'/'.$safe,'mime'=>$type,'size'=>$size,'uploaded_by'=>get_current_user_id(),'uploaded_at'=>date('Y-m-d H:i:s')
						]);
					} catch (Exception $e) {
						error_log('Failed to insert ticket_attachment (edit): ' . $e->getMessage());
					}
				}
			}
		}

		if (!empty($assigned_to)) create_notification($assigned_to,'تم تحديث تذكرة','تم تعيين أو تحديث تذكرة','info','ticket',$id);
		$_SESSION['success']='تم تحديث التذكرة.';
		redirect("modules/tickets/view.php?id={$id}");
	} catch (Exception $e) {
		error_log($e->getMessage()); $_SESSION['error']='خطأ أثناء التحديث.'; redirect("modules/tickets/edit.php?id={$id}");
	}
}

// load supporting data
$contacts = [];$users = [];
try { $contacts = $db->fetchAll('SELECT id, first_name, last_name FROM contacts ORDER BY first_name'); } catch (Exception $e) { $contacts = []; }
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="mb-8 pb-6 border-b border-gray-100">
		<h3 class="text-2xl font-bold text-[#1e4a76]">تعديل التذكرة</h3>
	</div>

	<form method="POST" action="" id="ticketEditForm" enctype="multipart/form-data">
		<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
		<input type="hidden" name="id" value="<?php echo $id; ?>">

		<div class="mb-6">
			<label class="text-sm font-semibold">الموضوع <span class="text-rose-500">*</span></label>
			<input type="text" name="subject" value="<?php echo htmlspecialchars($ticket['subject']); ?>" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" required />
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold">الوصف <span class="text-rose-500">*</span></label>
			<textarea name="description" rows="6" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
			<div>
				<label class="text-sm font-semibold">الجهة</label>
				<select name="contact_id" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="">بدون</option>
					<?php foreach ($contacts as $c): ?>
						<option value="<?php echo $c['id']; ?>" <?php if ($ticket['contact_id']==$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold">الأولوية</label>
				<select name="priority" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="low" <?php if ($ticket['priority']=='low') echo 'selected'; ?>>منخفض</option>
					<option value="medium" <?php if ($ticket['priority']=='medium') echo 'selected'; ?>>متوسط</option>
					<option value="high" <?php if ($ticket['priority']=='high') echo 'selected'; ?>>عالي</option>
					<option value="urgent" <?php if ($ticket['priority']=='urgent') echo 'selected'; ?>>عاجل</option>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold">تعيين إلى</label>
				<select name="assigned_to" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="">بدون</option>
					<?php foreach ($users as $u): ?>
						<option value="<?php echo $u['id']; ?>" <?php if ($ticket['assigned_to']==$u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="mb-4">
			<label class="text-sm font-semibold">الحالة</label>
			<select name="status" class="w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
				<option value="open" <?php if ($ticket['status']=='open') echo 'selected'; ?>>مفتوحة</option>
				<option value="in_progress" <?php if ($ticket['status']=='in_progress') echo 'selected'; ?>>قيد العمل</option>
				<option value="resolved" <?php if ($ticket['status']=='resolved') echo 'selected'; ?>>محلول</option>
				<option value="closed" <?php if ($ticket['status']=='closed') echo 'selected'; ?>>مغلقة</option>
			</select>
		</div>

		<div class="mb-4">
			<label class="text-sm font-semibold">مرفقات جديدة</label>
			<input type="file" name="attachments[]" multiple class="mt-2" />
		</div>

		<div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
			<a href="view.php?id=<?php echo $id; ?>" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold">إلغاء</a>
			<button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">حفظ التعديلات</button>
		</div>
	</form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

