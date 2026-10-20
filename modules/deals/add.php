<?php
// Add deal
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/auth.php';

 $page_title = 'إضافة صفقة - ' . APP_NAME;
 $current_module = 'deals';
 $page_header = 'إضافة صفقة جديدة';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validate_csrf_token($token)) {
		$_SESSION['error'] = 'رمز CSRF غير صالح.';
		redirect('modules/deals/add.php');
	}

	$title = clean_input($_POST['title'] ?? '');
	$contact_id = intval($_POST['contact_id'] ?? 0);
	$amount = floatval($_POST['amount'] ?? 0);
	$stage = clean_input($_POST['stage'] ?? 'prospecting');
	$expected_close_date = clean_input($_POST['expected_close_date'] ?? null);
	$probability = intval($_POST['probability'] ?? 0);
	$description = clean_input($_POST['description'] ?? '');
	$assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;

	if (empty($title) || !$contact_id) {
		$_SESSION['error'] = 'العنوان وجهة الاتصال مطلوبان.';
		redirect('modules/deals/add.php');
	}

	try {
		$db = get_db();
		// validate assigned_to exists or is null
		if (!empty($assigned_to)) {
			$u = $db->fetch('SELECT id FROM users WHERE id = ?', [$assigned_to]);
			if (!$u) { $_SESSION['error'] = 'المستخدم المحدد غير صالح.'; redirect('modules/deals/add.php'); }
		}
		$data = [
			'title' => $title,
			'contact_id' => $contact_id,
			'amount' => $amount ?: null,
			'stage' => $stage,
			'expected_close_date' => $expected_close_date ?: null,
			'probability' => $probability,
			'description' => $description,
			'assigned_to' => $assigned_to,
			'created_by' => get_current_user_id(),
			'created_at' => date('Y-m-d H:i:s')
		];

		$deal_id = $db->insert('deals', $data);
		if ($deal_id) {
			log_audit('create', 'deals', $deal_id, 'Created new deal');
			// Notify assigned user (if any) and managers
			notify_new_deal($deal_id, $assigned_to);
			$_SESSION['success'] = 'تم إنشاء الصفقة بنجاح.';
			redirect('modules/deals/');
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
		$_SESSION['error'] = 'حدث خطأ أثناء إنشاء الصفقة.';
		redirect('modules/deals/add.php');
	}
}

require_once __DIR__ . '/../../templates/header.php';

// Fetch contacts and users for selects
 $contacts = [];
 $users = [];
try { $db = get_db(); $contacts = $db->fetchAll('SELECT id, first_name, last_name FROM contacts ORDER BY first_name'); } catch (Exception $e) { $contacts = []; }
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }
?>

<style>
	.form-input-advanced { transition: all 0.3s ease; }
	.form-input-advanced:focus { border-color: #1e4a76 !important; box-shadow: 0 0 0 4px rgba(30,74,118,0.08) !important; }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="mb-8 pb-6 border-b border-gray-100">
		<h3 class="text-2xl font-bold text-[#1e4a76]"><?php echo $page_header; ?></h3>
		<p class="text-gray-400 text-sm mt-2">أدخل تفاصيل الصفقة. الحقول المميزة بـ <span class="text-rose-500">*</span> مطلوبة.</p>
	</div>

	<form method="POST" action="" id="dealForm">
		<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600">العنوان <span class="text-rose-500">*</span></label>
			<input type="text" name="title" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" required />
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600">تعيين إلى</label>
			<select name="assigned_to" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
				<option value="">غير معين</option>
				<?php foreach ($users as $u): ?>
					<option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600">جهة الاتصال <span class="text-rose-500">*</span></label>
				<select name="contact_id" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="">اختر جهة الاتصال</option>
					<?php foreach ($contacts as $c): ?>
						<option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name'])); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">المبلغ</label>
				<input type="number" step="0.01" name="amount" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" placeholder="0.00" />
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600">المرحلة</label>
				<select name="stage" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="prospecting">اكتشاف</option>
					<option value="qualification">تأهيل</option>
					<option value="proposal">عرض</option>
					<option value="negotiation">تفاوض</option>
					<option value="closed_won">مغلقة - فائزة</option>
					<option value="closed_lost">مغلقة - خاسرة</option>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">تاريخ الإغلاق المتوقع</label>
				<input type="date" name="expected_close_date" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">الاحتمال (%)</label>
				<input type="number" name="probability" min="0" max="100" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
			</div>
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600">الوصف</label>
			<textarea name="description" rows="5" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl"></textarea>
		</div>

		<div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
			<a href="index.php" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200">إلغاء</a>
			<button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">حفظ الصفقة</button>
		</div>
	</form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

