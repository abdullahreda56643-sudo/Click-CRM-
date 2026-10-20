<?php
// View deal details
if (!defined('CRM_CONFIG')) {
	require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';

 $id = intval($_GET['id'] ?? 0);
if (!$id) {
	$_SESSION['error'] = 'معرّف غير صحيح.';
	redirect('modules/deals/');
}

 $db = get_db();
 $deal = $db->fetch('SELECT d.*, c.first_name, c.last_name, u.full_name as created_by_name FROM deals d LEFT JOIN contacts c ON d.contact_id = c.id LEFT JOIN users u ON d.created_by = u.id WHERE d.id = ?', [$id]);
if (!$deal) {
	$_SESSION['error'] = 'الصفقة غير موجودة.';
	redirect('modules/deals/');
}

 $page_title = htmlspecialchars($deal['title'] ?? '') . ' - ' . APP_NAME;
 $current_module = 'deals';
 $page_header = 'عرض الصفقة';

require_once __DIR__ . '/../../templates/header.php';

function formatCurrency($val) { return $val !== null ? number_format((float)$val,2) : '-'; }

 $stage_labels = [
	'prospecting' => 'اكتشاف',
	'qualification' => 'تأهيل',
	'proposal' => 'عرض',
	'negotiation' => 'تفاوض',
	'closed_won' => 'مغلقة - فائزة',
	'closed_lost' => 'مغلقة - خاسرة'
];
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
	<div class="flex items-start justify-between mb-6">
		<div>
			<h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($deal['title']); ?></h3>
			<p class="text-sm text-gray-500"><?php echo $stage_labels[$deal['stage']] ?? $deal['stage']; ?> • احتمال: <?php echo htmlspecialchars($deal['probability']); ?>%</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="index.php" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold">العودة</a>
			<a href="edit.php?id=<?php echo $deal['id']; ?>" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-semibold">تعديل</a>
			<form action="delete.php" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الصفقة؟');" class="inline">
				<input type="hidden" name="id" value="<?php echo $deal['id']; ?>">
				<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
				<button type="submit" class="px-4 py-2.5 bg-white border-2 border-rose-200 text-rose-600 rounded-xl font-semibold">حذف</button>
			</form>
		</div>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
		<div class="p-5 border rounded-xl bg-gray-50">
			<p class="text-xs text-gray-400 uppercase tracking-wider mb-2">جهة الاتصال</p>
			<p class="font-semibold text-gray-800"><?php echo htmlspecialchars(trim($deal['first_name'] . ' ' . $deal['last_name'])); ?></p>
		</div>

		<div class="p-5 border rounded-xl bg-gray-50">
			<p class="text-xs text-gray-400 uppercase tracking-wider mb-2">المبلغ</p>
			<p class="font-semibold text-gray-800"><?php echo formatCurrency($deal['amount']); ?></p>
		</div>

		<div class="p-5 border rounded-xl bg-gray-50">
			<p class="text-xs text-gray-400 uppercase tracking-wider mb-2">تاريخ الإغلاق المتوقع</p>
			<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($deal['expected_close_date'] ?? '-'); ?></p>
		</div>

		<div class="p-5 border rounded-xl bg-gray-50">
			<p class="text-xs text-gray-400 uppercase tracking-wider mb-2">تم الإنشاء بواسطة</p>
			<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($deal['created_by_name'] ?? '-'); ?></p>
		</div>
	</div>

	<?php if (!empty($deal['description'])): ?>
	<div class="bg-white rounded-xl p-5 border-l-4 border-[#1e4a76]">
		<h4 class="text-sm font-bold text-[#1e4a76] mb-3">الوصف</h4>
		<div class="text-gray-700 leading-relaxed whitespace-pre-line"><?php echo nl2br(htmlspecialchars($deal['description'])); ?></div>
	</div>
	<?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

