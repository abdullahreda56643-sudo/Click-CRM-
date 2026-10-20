<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('modules/dashboard.php');
}

 $error = '';
 $success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        if (authenticate_user($username, $password)) {
            if ($remember) {
                remember_login(get_current_user_id());
            }
            
            // Redirect to intended page or dashboard
            $redirect = $_SESSION['redirect_url'] ?? 'modules/dashboard.php';
            unset($_SESSION['redirect_url']);
            redirect($redirect);
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo APP_NAME; ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts - Arabic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --brand-blue: #1e4a76; --brand-blue-rgb: 30,74,118; }
        body {
            font-family: 'Tajawal', sans-serif;
        }
        
        /* تخصيص حقول الإدخال الشفافة */
        .modern-input {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(209, 213, 219, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modern-input:focus {
            background: rgba(255, 255, 255, 0.75);
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(var(--brand-blue-rgb), 0.12);
        }

        /* تحسين تأثير الزر */
        .btn-modern {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-modern:hover {
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }
        .btn-modern:active {
            transform: translateY(0);
            box-shadow: none;
        }

        /* تخصيص الـ Checkbox ليناسب التصميم */
        .custom-checkbox {
            accent-color: rgba(var(--brand-blue-rgb), 0.9);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* زر باللون التجاري */
        .brand-btn {
            background: var(--brand-blue);
            color: #ffffff;
        }
        .brand-btn:hover {
            background: #163a66;
            box-shadow: 0 10px 25px -5px rgba(var(--brand-blue-rgb), 0.25);
        }
        .brand-btn:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(var(--brand-blue-rgb), 0.14);
        }
    </style>
    <style>
        /* Full-page background image with light blue overlay */
        body {
            background-image: url('<?php echo base_url("assets/img/ChatGPT Image May 16, 2026, 12_08_19 AM.png"); ?>');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
            color: #111827;
        }

        /* blue overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            /* Slightly darker overlay for stronger contrast */
            background: rgba(var(--brand-blue-rgb), 0.75);
            pointer-events: none;
            z-index: 0;
        }

        /* keep the login card above the overlay */
        .max-w-md {
            position: relative;
            z-index: 10;
        }

        /* Make header titles and supportive texts white for contrast */
        .login-header h2 {
            color: #ffffff !important;
        }
        .login-header p {
            color: rgba(255,255,255,0.9) !important;
        }

        /* Footer and small texts inside the login card */
        .max-w-md .text-xs,
        .max-w-md .text-sm,
        .max-w-md .text-gray-500,
        .max-w-md .text-gray-600,
        .max-w-md .text-gray-400 {
            color: rgba(255,255,255,0.85) !important;
        }

        /* Keep input text dark for readability on light inputs */
        .modern-input {
            color: #0f172a; /* dark text for inputs */
        }
        .modern-input::placeholder {
            color: rgba(15,23,42,0.45);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    
    <div class="max-w-md w-full">
        
        <!-- الهيدر -->
        <div class="text-center mb-10 login-header">
                <div class="mx-auto h-16 w-16 flex items-center justify-center rounded-2xl shadow-sm mb-3" style="background: var(--brand-blue); box-shadow: 0 6px 18px rgba(var(--brand-blue-rgb),0.12);">
                    <i class="fas fa-fingerprint text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    <?php echo htmlspecialchars(APP_NAME); ?>
                </h2>
                <p class="mt-2 text-sm text-gray-500 font-medium">
                    نظام <?php echo htmlspecialchars(APP_NAME); ?> — مقدم من Click
                </p>
        </div>
        
        <!-- الفورم (ترانسبيرنت بالكامل - بدون خلفية أو ظل) -->
        <form class="space-y-5" method="POST">
            
            <?php if ($error): ?>
                <div class="bg-red-50/80 border border-red-200/60 text-red-700 px-4 py-3 rounded-xl backdrop-blur-sm">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                        <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50/80 border border-green-200/60 text-green-700 px-4 py-3 rounded-xl backdrop-blur-sm">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <span class="text-sm font-medium"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- حقل اسم المستخدم -->
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4">
                    <i class="fas fa-user text-gray-400 text-sm"></i>
                </div>
                <input id="username" name="username" type="text" required
                       class="modern-input block w-full ps-11 pe-4 py-3.5 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none text-sm"
                       placeholder="اسم المستخدم أو البريد الإلكتروني"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <!-- حقل كلمة المرور -->
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4">
                    <i class="fas fa-lock text-gray-400 text-sm"></i>
                </div>
                <input id="password" name="password" type="password" required
                       class="modern-input block w-full ps-11 pe-4 py-3.5 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none text-sm"
                       placeholder="كلمة المرور">
            </div>

            <!-- خيارات إضافية -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input id="remember" name="remember" type="checkbox" class="custom-checkbox rounded">
                    <span class="text-sm text-gray-600">تذكرني</span>
                </label>
            </div>

            <!-- زر تسجيل الدخول -->
            <div class="pt-2">
                <button type="submit"
                    class="btn-modern brand-btn w-full flex items-center justify-center gap-2 py-3.5 px-4 border border-transparent text-sm font-bold rounded-xl focus:outline-none">
                    <span>تسجيل الدخول</span>
                    <i class="fas fa-arrow-left text-xs"></i>
                </button>
            </div>
        </form>
        
     
        
        <!-- الفوتر -->
        <div class="text-center mt-8">
            <p class="text-xs text-gray-400">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — جميع الحقوق محفوظة
            </p>
        </div>
    </div>
    
    <script>
        // التركيز التلقائي على الحقل الأول
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>