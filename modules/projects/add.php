<?php
// Add project
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

// Permission check
require_permission('projects', 'add');

 $page_title = 'إضافة مشروع - ' . APP_NAME;
 $current_module = 'projects';
 $page_header = 'إضافة مشروع جديد';

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
		redirect('modules/projects/add.php');
	}

	try {
		$db = get_db();
		$current_user = get_current_user_id();
		$data = [
			'name' => $name,
			'description' => $description,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'status' => $status,
			'assigned_to' => $assigned_to,
			'created_by' => $current_user,
			'created_at' => date('Y-m-d H:i:s')
		];

		$project_id = $db->insert('projects', $data);
		if ($project_id) {
			log_audit('create', 'projects', $project_id, 'Created new project');
			if (!empty($assigned_to)) {
				$title = 'تم تعيين مشروع جديد';
				$message = "تم تعيين مشروع: {$name}\n";
				create_notification($assigned_to, $title, $message, 'info', 'project', $project_id);
				$user = get_user_info($assigned_to);
				if ($user && get_setting('email_notifications', true)) {
					send_email_notification($user['email'], $title, $message);
				}
			}
			$_SESSION['success'] = 'تم إنشاء المشروع بنجاح.';
			redirect('modules/projects/');
		}

	} catch (Exception $e) {
		error_log($e->getMessage());
		$_SESSION['error'] = 'حدث خطأ أثناء إنشاء المشروع.';
		redirect('modules/projects/add.php');
	}
}

require_once __DIR__ . '/../../templates/header.php';

// Fetch users for assign-to select
 $users = [];
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }

// status options
 $statuses = ['planning' => 'تخطيط','in_progress' => 'قيد التنفيذ','completed' => 'مكتمل','on_hold' => 'متوقف','cancelled' => 'ملغى'];

?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="mb-8 pb-6 border-b border-gray-100">
		<h3 class="text-2xl font-bold text-[#1e4a76] flex items-center gap-3">
			<div class="bg-[#1e4a76]/10 p-2.5 rounded-xl">
				<i class="fas fa-folder-plus text-[#1e4a76] text-lg"></i>
			</div>
			<?php echo $page_header; ?>
		</h3>
		<p class="text-gray-400 text-sm mt-2 mr-14">أدخل بيانات المشروع. الحقول المميزة بـ <span class="text-rose-500">*</span> مطلوبة.</p>
	</div>

	<form method="POST" action="" id="projectForm">
		<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">اسم المشروع <span class="text-rose-500">*</span></label>
			<input type="text" name="name" required class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">الوصف</label>
			<textarea name="description" rows="4" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium resize-none" placeholder="وصف المشروع"></textarea>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">تاريخ البدء</label>
				<input type="date" name="start_date" class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">تاريخ الانتهاء</label>
				<input type="date" name="end_date" class="w-full pr-4 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600 block mb-1.5">الحالة</label>
				<select name="status" class="w-full pr-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
					<?php foreach ($statuses as $k => $v): ?>
						<option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600 block mb-1.5">تعيين إلى</label>
			<select name="assigned_to" class="w-full pr-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
				<option value="">بدون تعيين</option>
				<?php foreach ($users as $u): ?>
					<option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
			<a href="index.php" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors">إلغاء</a>
			<button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold hover:bg-[#163d63] transition-colors shadow-lg shadow-[#1e4a76]/20">حفظ المشروع</button>
		</div>
	</form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

