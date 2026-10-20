<?php
// Roles management (interactive) — formatted to match system styles
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users', 'edit');

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/users/roles.php');
    }

    $json = $_POST['roles_json'] ?? '';
    $decoded = @json_decode($json, true);
    if (!is_array($decoded)) {
        $_SESSION['error'] = 'تنسيق JSON غير صالح.';
        redirect('modules/users/roles.php');
    }

    if (save_permissions($decoded)) {
        $_SESSION['success'] = 'تم حفظ الأدوار.';
        redirect('modules/users/roles.php');
    } else {
        $_SESSION['error'] = 'فشل حفظ الأدوار.';
        redirect('modules/users/roles.php');
    }
}

// Build modules/actions list (base known set)
$known = [
    'dashboard' => ['view'],
    'contacts' => ['view','add','edit','delete'],
    'deals' => ['view','add','edit','delete'],
    'tasks' => ['view','add','edit','delete'],
    'tickets' => ['view','add','edit','delete'],
    'projects' => ['view','add','edit','delete'],
    'documents' => ['view','upload','delete'],
    'reports' => ['view'],
    'users' => ['view','add','edit','delete']
];

$modules = $known;
// merge with any modules found in current $permissions
foreach ($permissions as $r => $mods) {
    foreach ($mods as $m => $acts) {
        if (!isset($modules[$m])) $modules[$m] = array_values($acts);
        else $modules[$m] = array_values(array_unique(array_merge($modules[$m], $acts)));
    }
}

$current_json = json_encode($permissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Labels for display (Arabic)
$module_labels = [
    'dashboard' => 'لوحة التحكم',
    'contacts' => 'جهات الاتصال',
    'deals' => 'الصفقات',
    'tasks' => 'المهام',
    'tickets' => 'تذاكر الدعم',
    'projects' => 'المشاريع',
    'documents' => 'الوثائق',
    'reports' => 'التقارير',
    'users' => 'المستخدمين'
];

// Ensure audit module is hidden from the roles editor even if present in permissions
if (isset($modules['audit'])) unset($modules['audit']);
if (isset($module_labels['audit'])) unset($module_labels['audit']);

$action_labels = [
    'view' => 'عرض',
    'add' => 'إضافة',
    'edit' => 'تعديل',
    'delete' => 'حذف',
    'upload' => 'رفع'
];

$page_title = 'الأدوار والصلاحيات - ' . APP_NAME;
$current_module = 'users';
$page_header = 'إدارة الأدوار والصلاحيات';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-[#1e4a76]"><?php echo $page_header; ?></h2>
            <p class="text-sm text-gray-500">أنشئ أو حدث الأدوار وصلاحيات الوصول للنظام هنا.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200">رجوع</a>
            <button id="btnNewRole" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl font-semibold hover:bg-[#163d63]">دور جديد</button>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-6xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Roles list -->
        <div class="col-span-1">
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 h-full">
                <div class="flex items-center justify-between mb-3">
                    <strong class="text-gray-700">الأدوار</strong>
                </div>
                <div id="rolesList" class="space-y-2 overflow-auto max-h-[60vh]"></div>
            </div>
        </div>

        <!-- Permissions editor -->
        <div class="col-span-1 lg:col-span-3">
            <div class="bg-white border border-gray-100 rounded-xl p-6">
                <form method="POST" id="rolesForm" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="roles_json" id="roles_json_input" value="<?php echo htmlspecialchars($current_json); ?>">

                    <div class="mb-4">
                        <label class="block text-sm font-semibold">اسم الدور</label>
                        <input type="text" id="roleName" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">الصلاحيات</label>
                        <div class="space-y-3 overflow-auto max-h-[55vh] border rounded p-3">
                            <?php foreach ($modules as $mod => $acts): ?>
                                <div class="mb-2">
                                    <div class="font-semibold mb-1 text-gray-700"><?php echo htmlspecialchars($module_labels[$mod] ?? $mod); ?></div>
                                    <div class="flex flex-wrap gap-3">
                                        <?php foreach ($acts as $act): ?>
                                            <label style="min-width:110px;" class="inline-flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" class="perm-checkbox" data-module="<?php echo htmlspecialchars($mod); ?>" data-action="<?php echo htmlspecialchars($act); ?>">
                                                <?php echo htmlspecialchars($action_labels[$act] ?? $act); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" id="btnSelectAll" class="px-4 py-2 bg-gray-100 rounded-xl">تحديد الكل</button>
                        <button type="button" id="btnClearAll" class="px-4 py-2 bg-gray-100 rounded-xl">إلغاء التحديد</button>
                        <button type="button" id="btnDeleteRole" class="px-4 py-2 bg-rose-100 text-rose-600 rounded-xl">حذف الدور</button>
                        <button type="button" id="btnGrantAll" class="px-4 py-2 bg-yellow-500 text-white rounded-xl">منح كل الصلاحيات</button>
                        <button type="submit" class="px-4 py-2 bg-[#1e4a76] text-white rounded-xl">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initial permissions object from PHP
const permissionsObj = <?php echo json_encode($permissions, JSON_UNESCAPED_UNICODE); ?> || {};

function renderRolesList() {
    const container = document.getElementById('rolesList');
    container.innerHTML = '';
    Object.keys(permissionsObj).forEach(role => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'w-full text-right p-3 border rounded-xl bg-white hover:bg-gray-50 flex items-center justify-between';
        btn.innerHTML = `<span class="font-medium">${role}</span><i class="fas fa-chevron-left text-gray-300"></i>`;
        btn.onclick = () => loadRole(role);
        container.appendChild(btn);
    });
}

function loadRole(role) {
    const nameInput = document.getElementById('roleName');
    nameInput.value = role;

    // uncheck all
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);

    const perms = permissionsObj[role] || {};
    Object.keys(perms).forEach(mod => {
        (perms[mod] || []).forEach(act => {
            const selector = `.perm-checkbox[data-module="${CSS.escape(mod)}"][data-action="${CSS.escape(act)}"]`;
            const el = document.querySelector(selector);
            if (el) el.checked = true;
        });
    });
}

document.getElementById('btnNewRole').addEventListener('click', () => {
    const name = prompt('اسم الدور الجديد:');
    if (!name) return;
    if (permissionsObj[name]) { alert('الدور موجود بالفعل'); return; }
    permissionsObj[name] = {};
    renderRolesList();
    loadRole(name);
});

document.getElementById('btnDeleteRole').addEventListener('click', () => {
    const name = document.getElementById('roleName').value.trim();
    if (!name) return alert('اختر دوراً أولاً');
    if (name === 'admin') return alert('لا يمكن حذف دور المشرف.');
    if (!confirm('هل تريد حذف الدور "' + name + '"؟')) return;
    delete permissionsObj[name];
    document.getElementById('roleName').value = '';
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
    renderRolesList();
});

// Select all/clear all handlers
document.getElementById('btnSelectAll').addEventListener('click', () => {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('btnClearAll').addEventListener('click', () => {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
});

// Grant all perms then submit
document.getElementById('btnGrantAll').addEventListener('click', () => {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('rolesForm').dispatchEvent(new Event('submit'));
});

// On submit, build permissions JSON and post
document.getElementById('rolesForm').addEventListener('submit', function(e){
    e.preventDefault();
    const name = document.getElementById('roleName').value.trim();
    if (!name) { alert('اسم الدور مطلوب'); return; }

    // Build permission list for this role
    const rolePerms = {};
    document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
        const mod = cb.getAttribute('data-module');
        const act = cb.getAttribute('data-action');
        if (!rolePerms[mod]) rolePerms[mod] = [];
        if (!rolePerms[mod].includes(act)) rolePerms[mod].push(act);
    });

    // Assign
    permissionsObj[name] = rolePerms;

    // Update hidden input and submit form normally
    const hidden = document.getElementById('roles_json_input');
    hidden.value = JSON.stringify(permissionsObj, null, 4);
    this.submit();
});

// Initial render
renderRolesList();
const first = Object.keys(permissionsObj)[0]; if (first) loadRole(first);
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
