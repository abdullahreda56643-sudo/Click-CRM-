<?php
// View contact details
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Permission
require_permission('contacts', 'view');

// Validate `id` strictly as an integer to avoid float-to-int precision issues
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$id = $id === false || $id === null ? 0 : (int)$id;
if (!$id) {
    $_SESSION['error'] = 'معرّف غير صحيح.';
    redirect('modules/contacts/');
}

 $db = get_db();
 $contact = $db->fetch('SELECT c.*, u.full_name as assigned_name FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id WHERE c.id = ?', [$id]);
if (!$contact) {
    $_SESSION['error'] = 'جهة الاتصال غير موجودة.';
    redirect('modules/contacts/');
}

// Access check
if (!can_access_record('contacts', $id, 'view')) {
    $_SESSION['error'] = 'ليس لديك صلاحية عرض هذه الجهة.';
    redirect('modules/contacts/');
}

 $page_title = htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) . ' - ' . APP_NAME;
 $current_module = 'contacts';
 $page_header = 'عرض جهة الاتصال';

require_once __DIR__ . '/../../templates/header.php';

// Helper functions for UI
function getAvatarColor($string) {
    $colors = ['#1e4a76', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#2563eb'];
    $s = (string) ($string ?? '');
    if ($s === '') return $colors[0];

    // Use crc32 for a stable 32-bit hash, then map into the colors array safely
    $hash = crc32($s);
    // crc32 can return a large unsigned int; cast and use modulus to keep index in range
    $index = (int) ($hash % count($colors));
    if ($index < 0) $index = abs($index) % count($colors);
    return $colors[$index];
}

 $fullName = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
 $initials = mb_substr($contact['first_name'] ?? '?', 0, 1) . mb_substr($contact['last_name'] ?? '', 0, 1);
 $avatarColor = getAvatarColor($fullName);

 $typeLabel = $contact['type'] == 'client' ? 'عميل' : 'عميل محتمل';
 $badgeStyle = $contact['type'] == 'client' 
    ? ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'] 
    : ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500'];
?>

<style>
    .profile-card {
        transition: all 0.3s ease;
    }
    .profile-card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01) !important;
        transform: translateY(-2px);
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-5xl mx-auto">
    
    <!-- Profile Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pb-8 border-b border-gray-100 mb-8">
        <div class="flex items-center gap-5">
            <!-- Avatar -->
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-3xl font-bold shadow-lg flex-shrink-0" style="background-color: <?php echo $avatarColor; ?>">
                <?php echo htmlspecialchars(strtoupper($initials)); ?>
            </div>
            
            <!-- Name & Meta -->
            <div>
                <h3 class="text-3xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($fullName); ?></h3>
                <div class="flex items-center gap-3 flex-wrap">
                    <?php if (!empty($contact['company'])): ?>
                        <span class="text-gray-500 font-medium flex items-center gap-1.5">
                            <i class="fas fa-building text-xs text-gray-400"></i> <?php echo htmlspecialchars($contact['company']); ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeStyle['bg'] . ' ' . $badgeStyle['text']; ?>">
                        <span class="w-2 h-2 rounded-full <?php echo $badgeStyle['dot']; ?>"></span>
                        <?php echo $typeLabel; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            <a href="index.php" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors flex items-center gap-2">
                <i class="fas fa-arrow-right text-sm"></i> العودة
            </a>
            <a href="edit.php?id=<?php echo $contact['id']; ?>" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-semibold hover:bg-amber-600 transition-colors shadow-sm shadow-amber-500/20 flex items-center gap-2">
                <i class="fas fa-pen-to-square text-sm"></i> تعديل
            </a>
            <form action="delete.php" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف جهة الاتصال؟ لا يمكن التراجع عن هذا الإجراء.');" class="inline">
                <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <button type="submit" class="px-4 py-2.5 bg-white border-2 border-rose-200 text-rose-600 rounded-xl font-semibold hover:bg-rose-50 transition-colors flex items-center gap-2">
                    <i class="fas fa-trash-can text-sm"></i> حذف
                </button>
            </form>
        </div>
    </div>

    <!-- Contact Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        
        <!-- Email Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-envelope text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">البريد الإلكتروني</p>
                <?php if (!empty($contact['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="text-gray-800 font-semibold hover:text-[#1e4a76] transition-colors">
                        <?php echo htmlspecialchars($contact['email']); ?>
                    </a>
                <?php else: ?>
                    <p class="text-gray-300 font-medium">غير محدد</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Phone Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-phone text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">رقم الهاتف</p>
                <?php if (!empty($contact['phone'])): ?>
                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" class="text-gray-800 font-semibold hover:text-[#1e4a76] transition-colors" dir="ltr">
                        <?php echo htmlspecialchars($contact['phone']); ?>
                    </a>
                <?php else: ?>
                    <p class="text-gray-300 font-medium">غير محدد</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned To Card -->
        <div class="profile-card bg-gray-50/50 border border-gray-100 p-5 rounded-xl flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-[#1e4a76] flex-shrink-0">
                <i class="fas fa-user-tie text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">المسؤول</p>
                <?php if (!empty($contact['assigned_name'])): ?>
                    <p class="text-gray-800 font-semibold flex items-center gap-2">
                        <?php 
                            $assignColor = getAvatarColor($contact['assigned_name']);
                            $assignInitial = mb_substr($contact['assigned_name'], 0, 1);
                        ?>
                        <span class="w-6 h-6 rounded-md text-white text-xs flex items-center justify-center font-bold" style="background-color: <?php echo $assignColor; ?>">
                            <?php echo $assignInitial; ?>
                        </span>
                        <?php echo htmlspecialchars($contact['assigned_name']); ?>
                    </p>
                <?php else: ?>
                    <p class="text-gray-300 font-medium">غير معين</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Notes Section -->
    <?php if (!empty($contact['notes'])): ?>
    <div class="bg-blue-50/40 border border-blue-100 rounded-2xl p-6">
        <h4 class="text-sm font-bold text-[#1e4a76] uppercase tracking-wider flex items-center gap-2 mb-4">
            <i class="fas fa-sticky-note text-blue-300"></i> ملاحظات
        </h4>
        <div class="bg-white rounded-xl p-5 border-r-4 border-[#1e4a76] shadow-sm text-gray-700 leading-relaxed whitespace-pre-line font-medium">
            <?php echo nl2br(htmlspecialchars($contact['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>