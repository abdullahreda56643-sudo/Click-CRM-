<?php
// Edit deal
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/auth.php';

 $page_title = 'تعديل صفقة - ' . APP_NAME;
 $current_module = 'deals';

 $id = intval($_GET['id'] ?? 0);
if (!$id) {
	$_SESSION['error'] = 'معرّف غير صالح.';
	redirect('modules/deals/');
}

 $db = get_db();
 $deal = $db->fetch('SELECT * FROM deals WHERE id = ?', [$id]);
if (!$deal) {
	$_SESSION['error'] = 'الصفقة غير موجودة.';
	redirect('modules/deals/');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!validate_csrf_token($token)) {
		$_SESSION['error'] = 'رمز CSRF غير صالح.';
		redirect('modules/deals/edit.php?id=' . $id);
	}

	$title = clean_input($_POST['title'] ?? '');
	$contact_id = intval($_POST['contact_id'] ?? 0);
	$amount = floatval($_POST['amount'] ?? 0);
	$stage = clean_input($_POST['stage'] ?? 'prospecting');
	$expected_close_date = clean_input($_POST['expected_close_date'] ?? null);
	$probability = intval($_POST['probability'] ?? 0);
	$description = clean_input($_POST['description'] ?? '');

	if (empty($title) || !$contact_id) {
		$_SESSION['error'] = 'العنوان وجهة الاتصال مطلوبان.';
		redirect('modules/deals/edit.php?id=' . $id);
	}

	try {
		$data = [
			'title' => $title,
			'contact_id' => $contact_id,
			'amount' => $amount ?: null,
			'stage' => $stage,
			'expected_close_date' => $expected_close_date ?: null,
			'probability' => $probability,
			'description' => $description,
			'updated_at' => date('Y-m-d H:i:s')
		];

		$updated = $db->update('deals', $data, 'id = ?', [$id]);
		if ($updated) {
			log_audit('update', 'deals', $id, 'Updated deal');
			// Notify managers about deal update
			$title = 'تحديث في الصفقة: ' . $title;
			$message = "تم تحديث الصفقة (" . $title . ") من قبل: " . (get_current_user_id() ? get_user_info(get_current_user_id())['full_name'] : '-') . ".";
			notify_managers($title, $message, 'deal', $id);
			$_SESSION['success'] = 'تم تحديث الصفقة بنجاح.';
			redirect('modules/deals/view.php?id=' . $id);
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
		$_SESSION['error'] = 'فشل تحديث الصفقة.';
		redirect('modules/deals/edit.php?id=' . $id);
	}
}

require_once __DIR__ . '/../../templates/header.php';

// Fetch contacts and users for selects
 $contacts = [];
 $users = [];
try { $contacts = $db->fetchAll('SELECT id, first_name, last_name FROM contacts ORDER BY first_name'); } catch (Exception $e) { $contacts = []; }
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }
?>

<style>
	.form-input-advanced { transition: all 0.3s ease; }
	.form-input-advanced:focus { border-color: #1e4a76 !important; box-shadow: 0 0 0 4px rgba(30,74,118,0.08) !important; }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="mb-8 pb-6 border-b border-gray-100">
		<h3 class="text-2xl font-bold text-[#1e4a76]">تعديل تفاصيل الصفقة</h3>
		<p class="text-gray-400 text-sm mt-2">قم بتحديث المعلومات المطلوبة. الحقول المميزة بـ <span class="text-rose-500">*</span> مطلوبة.</p>
	</div>

	<form method="POST" action="" id="dealForm">
		<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600">العنوان <span class="text-rose-500">*</span></label>
			<input type="text" name="title" required value="<?php echo htmlspecialchars($deal['title'] ?? ''); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
		</div>

		<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600">جهة الاتصال <span class="text-rose-500">*</span></label>
				<select name="contact_id" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="">اختر جهة الاتصال</option>
					<?php foreach ($contacts as $c): ?>
						<option value="<?php echo $c['id']; ?>" <?php echo ($deal['contact_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name'])); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">المبلغ</label>
				<input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($deal['amount'] ?? ''); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
			<div>
				<label class="text-sm font-semibold text-gray-600">المرحلة</label>
				<select name="stage" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl">
					<option value="prospecting" <?php echo ($deal['stage'] ?? '') === 'prospecting' ? 'selected' : ''; ?>>اكتشاف</option>
					<option value="qualification" <?php echo ($deal['stage'] ?? '') === 'qualification' ? 'selected' : ''; ?>>تأهيل</option>
					<option value="proposal" <?php echo ($deal['stage'] ?? '') === 'proposal' ? 'selected' : ''; ?>>عرض</option>
					<option value="negotiation" <?php echo ($deal['stage'] ?? '') === 'negotiation' ? 'selected' : ''; ?>>تفاوض</option>
					<option value="closed_won" <?php echo ($deal['stage'] ?? '') === 'closed_won' ? 'selected' : ''; ?>>مغلقة - فائزة</option>
					<option value="closed_lost" <?php echo ($deal['stage'] ?? '') === 'closed_lost' ? 'selected' : ''; ?>>مغلقة - خاسرة</option>
				</select>
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">تاريخ الإغلاق المتوقع</label>
				<input type="date" name="expected_close_date" value="<?php echo htmlspecialchars($deal['expected_close_date'] ?? ''); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
			</div>
			<div>
				<label class="text-sm font-semibold text-gray-600">الاحتمال (%)</label>
				<input type="number" name="probability" min="0" max="100" value="<?php echo htmlspecialchars($deal['probability'] ?? 0); ?>" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl" />
			</div>
		</div>

		<div class="mb-6">
			<label class="text-sm font-semibold text-gray-600">الوصف</label>
			<textarea name="description" rows="5" class="form-input-advanced w-full mt-2 p-3 bg-gray-50 border-2 border-gray-200 rounded-xl"><?php echo htmlspecialchars($deal['description'] ?? ''); ?></textarea>
		</div>

		<div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
			<a href="view.php?id=<?php echo $id; ?>" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200">إلغاء</a>
			<button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold">حفظ التغييرات</button>
		</div>
	</form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

