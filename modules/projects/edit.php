<?php
// Edit project
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

// Permission check
require_permission('projects', 'edit');

 $page_title = 'تعديل مشروع - ' . APP_NAME;
 $current_module = 'projects';

 $id = intval($_GET['id'] ?? 0);
if (!$id) {
	$_SESSION['error'] = 'معرّف غير صالح.';
	redirect('modules/projects/');
}

 $db = get_db();
 $project = $db->fetch('SELECT * FROM projects WHERE id = ?', [$id]);
if (!$project) {
	$_SESSION['error'] = 'المشروع غير موجود.';
	redirect('modules/projects/');
}

// Access check
if (!can_access_record('projects', $id, 'view')) {
	$_SESSION['error'] = 'ليس لديك صلاحية عرض هذا المشروع أو تعديله.';
	redirect('modules/projects/');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validate_csrf_token($token)) {
		$_SESSION['error'] = 'رمز CSRF غير صالح.';
		redirect('modules/projects/');
	}

	$name = clean_input($_POST['name'] ?? '');
	$description = clean_input($_POST['description'] ?? '');
	$start_date = clean_input($_POST['start_date'] ?? '') ?: null;
	$end_date = clean_input($_POST['end_date'] ?? '') ?: null;
	$status = clean_input($_POST['status'] ?? 'planning');
	$assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;

	if (empty($name)) {
		$_SESSION['error'] = 'اسم المشروع مطلوب.';
		redirect('modules/projects/edit.php?id=' . $id);
	}

	try {
		$data = [
			'name' => $name,
			'description' => $description,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'status' => $status,
			'assigned_to' => $assigned_to,
			'updated_at' => date('Y-m-d H:i:s')
		];

		$updated = $db->update('projects', $data, 'id = ?', [$id]);
		if ($updated) {
			log_audit('update', 'projects', $id, 'Updated project');
			$old_assigned = $project['assigned_to'] ?? null;
			if (!empty($assigned_to) && $assigned_to != $old_assigned) {
				$title = 'تم تعيين مشروع لك';
				$message = "تم تعيين مشروع: {$name}\n";
				create_notification($assigned_to, $title, $message, 'info', 'project', $id);
				$user = get_user_info($assigned_to);
				if ($user && get_setting('email_notifications', true)) {
					send_email_notification($user['email'], $title, $message);
				}
			}
			$_SESSION['success'] = 'تم تحديث المشروع بنجاح.';
			redirect('modules/projects/view.php?id=' . $id);
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
		$_SESSION['error'] = 'فشل تحديث المشروع.';
		redirect('modules/projects/edit.php?id=' . $id);
	}
}

require_once __DIR__ . '/../../templates/header.php';

// Fetch users
 $users = [];
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }

 $statuses = ['planning' => 'تخطيط','in_progress' => 'قيد التنفيذ','completed' => 'مكتمل','on_hold' => 'متوقف','cancelled' => 'ملغى'];
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="mb-8 pb-6 border-b border-gray-100">
		<h3 class="text-2xl font-bold text-[#1e4a76] flex items-center gap-3">
			<div class="bg-[#1e4a76]/10 p-2.5 rounded-xl">
				<i class="fas fa-folder-open text-[#1e4a76] text-lg"></i>
			</div>
			تعديل المشروع
		</h3>
		<p class="text-gray-400 text-sm mt-2 mr-14">تحديث بيانات المشروع.</p>
	</div>

	<form method="POST" action="" id="projectForm">
		<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">اسم المشروع <span class="text-rose-500">*</span></label>
			<input type="text" name="name" required value="<?php echo htmlspecialchars($project['name'] ?? ''); ?>" class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">الوصف</label>
			<textarea name="description" rows="4" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium resize-none"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">تاريخ البدء</label>
				<input type="date" name="start_date" value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>" class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">تاريخ الانتهاء</label>
				<input type="date" name="end_date" value="<?php echo htmlspecialchars($project['end_date'] ?? ''); ?>" class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">الحالة</label>
				<select name="status" class="w-full pr-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
					<?php foreach ($statuses as $k => $v): ?>
						<option value="<?php echo $k; ?>" <?php echo (($project['status'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">تعيين إلى</label>
			<select name="assigned_to" class="w-full pr-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
				<option value="">بدون تعيين</option>
				<?php foreach ($users as $u): ?>
					<option value="<?php echo $u['id']; ?>" <?php echo (($project['assigned_to'] ?? '') == $u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
			<a href="view.php?id=<?php echo $id; ?>" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors">إلغاء</a>
			<button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold hover:bg-[#163d63] transition-colors shadow-lg shadow-[#1e4a76]/20">حفظ التغييرات</button>
		</div>
	</form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

