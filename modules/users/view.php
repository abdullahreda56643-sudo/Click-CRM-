<?php
// View user profile (admin/manager view)
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users', 'view');

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) { $_SESSION['error']='المستخدم غير موجود.'; redirect('modules/users/'); }

$user = get_user_info($user_id);
if (!$user) { $_SESSION['error']='المستخدم غير موجود.'; redirect('modules/users/'); }

$page_title = 'ملف المستخدم - ' . APP_NAME;
$current_module = 'users';
$page_header = 'عرض المستخدم';
require_once __DIR__ . '/../../templates/header.php';

// Local helper for avatar color
function getAvatarColor($string) {
    $colors = ['#1e4a76', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#2563eb'];
    $s = (string) ($string ?? '');
    if ($s === '') return $colors[0];
    $hash = crc32($s);
    $index = (int) ($hash % count($colors));
    if ($index < 0) $index = abs($index) % count($colors);
    return $colors[$index];
}

$displayName = trim($user['full_name'] ?? $user['username']);
$initials = mb_substr($user['full_name'] ?? $user['username'], 0, 1);
$avatarColor = getAvatarColor($displayName);

?>

<style>
    .profile-card { transition: all 0.3s ease; }
    .profile-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); transform: translateY(-2px); }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-5xl mx-auto mt-14 mb-12">

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-6 border-b border-gray-100 mb-6">
        <div class="flex items-center gap-5">
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-3xl font-bold shadow-lg flex-shrink-0" style="background-color: <?php echo $avatarColor; ?>">
                <?php echo htmlspecialchars(mb_strtoupper($initials)); ?>
            </div>
            <div>
                <h3 class="text-3xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($displayName); ?></h3>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-gray-500 font-medium flex items-center gap-1.5">
                        <i class="fas fa-user text-xs text-gray-400"></i> <?php echo htmlspecialchars($user['username']); ?>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-50 text-gray-700">
                        <?php echo htmlspecialchars($user['role'] ?? '-'); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            <a href="index.php" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors flex items-center gap-2">
                <i class="fas fa-arrow-right text-sm"></i> العودة
            </a>
            <?php if (check_permission('users','edit')): ?>
            <a href="edit.php?id=<?php echo $user_id; ?>" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-semibold hover:bg-amber-600 transition-colors shadow-sm shadow-amber-500/20 flex items-center gap-2">
                <i class="fas fa-pen-to-square text-sm"></i> تعديل
            </a>
            <?php endif; ?>
            <?php if (check_permission('users','delete')): ?>
            <button onclick="deleteUser(<?php echo $user_id; ?>)" class="px-4 py-2.5 bg-white border-2 border-rose-200 text-rose-600 rounded-xl font-semibold hover:bg-rose-50 transition-colors flex items-center gap-2">
                <i class="fas fa-trash-can text-sm"></i> حذف
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-user text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">الاسم الكامل</p>
                <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></div>
            </div>
        </div>

        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-envelope text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">البريد الإلكتروني</p>
                <?php if (!empty($user['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-gray-800 font-semibold hover:text-[#1e4a76] transition-colors"><?php echo htmlspecialchars($user['email']); ?></a>
                <?php else: ?>
                    <p class="text-gray-300 font-medium">غير محدد</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-user-shield text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">الدور</p>
                <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($user['role'] ?? '-'); ?></div>
            </div>
        </div>

        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-toggle-on text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">نشط</p>
                <div class="text-gray-800 font-semibold"><?php echo $user['is_active']? '<span class="text-green-600">نعم</span>' : '<span class="text-gray-400">لا</span>'; ?></div>
            </div>
        </div>

        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-calendar-plus text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">تاريخ الإنشاء</p>
                <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($user['created_at']); ?></div>
            </div>
        </div>

        <?php if (!empty($user['updated_at'])): ?>
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0"><i class="fas fa-edit text-lg"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">آخر تعديل</p>
                <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($user['updated_at']); ?></div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
function deleteUser(id) {
    if (!confirm('هل أنت متأكد من حذف المستخدم؟ لا يمكن التراجع عن هذا الإجراء.')) return;
    fetch('delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN })
    }).then(r=>r.json()).then(resp=>{
        if (resp.success) {
            showToast(resp.message || 'تم الحذف', 'success');
            window.location = 'index.php';
        } else {
            showToast(resp.message || 'فشل الحذف', 'error');
        }
    }).catch(err=>{ console.error(err); showToast('خطأ بالشبكة', 'error'); });
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
