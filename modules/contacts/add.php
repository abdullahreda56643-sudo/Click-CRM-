<?php
// Add contact
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../../config/config.php';
}

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

// Permission check
require_permission('contacts', 'add');

 $page_title = 'إضافة جهة اتصال - ' . APP_NAME;
 $current_module = 'contacts';
 $page_header = 'إضافة جهة اتصال جديدة';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $_SESSION['error'] = 'رمز CSRF غير صالح.';
        redirect('modules/contacts/');
    }

    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name  = clean_input($_POST['last_name'] ?? '');
    $email      = clean_input($_POST['email'] ?? '');
    $phone      = clean_input($_POST['phone'] ?? '');
    $company    = clean_input($_POST['company'] ?? '');
    $type       = clean_input($_POST['type'] ?? 'lead');
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;
    $notes      = clean_input($_POST['notes'] ?? '');

    if (empty($first_name)) {
        $_SESSION['error'] = 'الاسم الأول مطلوب.';
        redirect('modules/contacts/add.php');
    }

    if (!empty($email) && !validate_email($email)) {
        $_SESSION['error'] = 'البريد الإلكتروني غير صالح.';
        redirect('modules/contacts/add.php');
    }

    try {
        $db = get_db();
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'type' => $type,
            'assigned_to' => $assigned_to,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $contact_id = $db->insert('contacts', $data);
        if ($contact_id) {
            log_audit('create', 'contacts', $contact_id, 'Created new contact');
            // Notify assigned user if any
            if (!empty($assigned_to)) {
                $title = 'تم تعيين جهة اتصال جديدة';
                $message = "لقد تم تعيين جهة اتصال: {$first_name} {$last_name}\n";
                if (!empty($company)) $message .= "الشركة: {$company}\n";
                create_notification($assigned_to, $title, $message, 'info', 'contact', $contact_id);
                $user = get_user_info($assigned_to);
                if ($user && get_setting('email_notifications', true)) {
                    send_email_notification($user['email'], $title, $message);
                }
            }
            $_SESSION['success'] = 'تم إنشاء جهة الاتصال بنجاح.';
            redirect('modules/contacts/');
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = 'حدث خطأ أثناء إنشاء جهة الاتصال.';
        redirect('modules/contacts/add.php');
    }
}

require_once __DIR__ . '/../../templates/header.php';

// Fetch users for assign-to select
 $users = [];
try { $users = get_all_users(); } catch (Exception $e) { $users = []; }
?>

<style>
    /* Custom Input Focus & Transition */
    .form-input-advanced {
        transition: all 0.3s ease;
    }
    .form-input-advanced:focus {
        border-color: #1e4a76 !important;
        box-shadow: 0 0 0 4px rgba(30, 74, 118, 0.08) !important;
        background-color: #ffffff !important;
    }
    /* Custom Select Arrow */
    select.form-input-advanced {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: left 0.75rem center;
        background-size: 1.2rem;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        padding-left: 2.5rem;
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-4xl mx-auto">
    
    <!-- Header -->
    <div class="mb-8 pb-6 border-b border-gray-100">
        <h3 class="text-2xl font-bold text-[#1e4a76] flex items-center gap-3">
            <div class="bg-[#1e4a76]/10 p-2.5 rounded-xl">
                <i class="fas fa-user-plus text-[#1e4a76] text-lg"></i>
            </div>
            <?php echo $page_header; ?>
        </h3>
        <p class="text-gray-400 text-sm mt-2 mr-14">أدخل بيانات العميل أو العميل المحتمل بدقة. الحقول المميزة بـ <span class="text-rose-500">*</span> مطلوبة.</p>
    </div>

    <form method="POST" action="" id="contactForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- Section 1: Personal Info -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-id-card text-gray-400"></i> المعلومات الشخصية
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">الاسم الأول <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <input type="text" name="first_name" required class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="مثال: أحمد" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">اسم العائلة</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-users text-sm"></i>
                        </div>
                        <input type="text" name="last_name" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="مثال: الحربي" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Contact Info -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-address-book text-gray-400"></i> معلومات التواصل
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">البريد الإلكتروني</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-envelope text-sm"></i>
                        </div>
                        <input type="email" name="email" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="email@example.com" dir="ltr" style="text-align: right;" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">رقم الهاتف</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-phone text-sm"></i>
                        </div>
                        <input type="text" name="phone" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="+966 5X XXX XXXX" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Business Info -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-briefcase text-gray-400"></i> معلومات العمل
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">الشركة</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-building text-sm"></i>
                        </div>
                        <input type="text" name="company" class="form-input-advanced w-full pr-11 pl-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="اسم الشركة" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">تصنيف العميل</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-tag text-sm"></i>
                        </div>
                        <select name="type" class="form-input-advanced w-full pr-11 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
                            <option value="lead">عميل محتمل (Lead)</option>
                            <option value="client">عميل حالي (Client)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-600 block mb-1.5">تعيين إلى</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                            <i class="fas fa-user-tie text-sm"></i>
                        </div>
                        <select name="assigned_to" class="form-input-advanced w-full pr-11 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium">
                            <option value="">بدون تعيين</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Details -->
        <div class="mb-8">
            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-sticky-note text-gray-400"></i> ملاحظات إضافية
            </h4>
            <div>
                <textarea name="notes" rows="4" class="form-input-advanced w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800 font-medium resize-none" placeholder="أضف أي ملاحظات تتعلق بجهة الاتصال هنا..."></textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="index.php" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl font-semibold hover:bg-gray-200 transition-colors">
                إلغاء الأمر
            </a>
            <button type="submit" class="px-8 py-2.5 bg-[#1e4a76] text-white rounded-xl font-bold hover:bg-[#163d63] transition-colors shadow-lg shadow-[#1e4a76]/20 flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                حفظ جهة الاتصال
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>