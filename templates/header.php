<?php

// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Ensure notifications functions are available (provides get_unread_notification_count)
if (!function_exists('get_unread_notification_count')) {
    require_once __DIR__ . '/../includes/notifications.php';
}

// Set default values for variables if not defined
$page_title       = $page_title       ?? APP_NAME;
$current_module   = $current_module   ?? '';
$page_header      = $page_header      ?? null;
$page_description = $page_description ?? null;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#107dc5',
                            900: '#107dc5',
                        },
                        surface: {
                            DEFAULT: '#0f172a',
                            50:  '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    boxShadow: {
                        'glow':     '0 0 24px rgba(99,102,241,0.25)',
                        'glow-sm':  '0 0 12px rgba(99,102,241,0.18)',
                        'panel':    '0 8px 32px rgba(15,23,42,0.18)',
                        'float':    '0 20px 60px rgba(15,23,42,0.22)',
                        'inner-sm': 'inset 0 1px 0 rgba(255,255,255,0.08)',
                    },
                    backdropBlur: {
                        xs: '2px',
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts - Arabic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ─── Base ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Tajawal', sans-serif;
            background: #f1f5f9;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99,102,241,0.07) 0%, transparent 70%);
            color: #1e293b;
            min-height: 100vh;
        }

        /* ─── Scrollbar ─────────────────────────────────── */
        ::-webkit-scrollbar               { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track         { background: transparent; }
        ::-webkit-scrollbar-thumb         { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover   { background: #94a3b8; }

        /* ─── Top Navbar ────────────────────────────────── */
        .crm-navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 67px;
            background: rgba(30, 74, 118, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 0 0 12px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1), 0 4px 24px rgba(0, 0, 0, 0.2);
            z-index: 50;
                overflow: visible;
        }
        .crm-navbar::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .crm-navbar::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        /* Logo container */
        .logo-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-image {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 8px;
        }

        /* Search input */
        .search-input {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #ffffff;
        }
        .search-input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        /* Icon buttons (nav) */
        .nav-icon-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; height: 40px;
            border-radius: 11px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .nav-icon-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.15);
        }

        /* Avatar */
        .nav-avatar {
            width: 38px; height: 38px;
            border-radius: 11px;
            border: 2px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15), 0 4px 12px rgba(0, 0, 0, 0.3);
            object-fit: cover;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .nav-avatar:hover {
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.25), 0 4px 16px rgba(0, 0, 0, 0.4);
        }

        /* ─── Dropdown Panels ───────────────────────────── */
        .dropdown-panel {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            box-shadow: 0 24px 64px rgba(15,23,42,0.5), 0 0 0 1px rgba(255,255,255,0.04);
            z-index: 9999;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.97);
            transform-origin: top left;
            transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
        }
        .dropdown-panel.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .dropdown-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.02);
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            font-size: 13.5px;
            color: #94a3b8;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background: rgba(30, 74, 118, 0.12);
            color: #c7d2fe;
        }
        .dropdown-item .item-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px;
            color: #818cf8;
            flex-shrink: 0;
        }
        .dropdown-footer {
            padding: 10px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.01);
        }

        /* ─── Sidebar ───────────────────────────────────── */
        .crm-sidebar {
            position: fixed;
            right: 0;
            /* reduce vertical span so sidebar appears shorter and more compact */
            top: 90px;
            bottom: 80px;
            transform: none;
            z-index: 40;
            width: 72px;
            /* limit height to a smaller area while still allowing content centering */
            height: auto;
            max-height: calc(100vh - 170px);
            background: rgba(30, 74, 118, 0.92);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-right: none;
            border-radius: 22px 0 0 22px;
            box-shadow: -8px 0 40px rgba(30, 74, 118, 0.3), -1px 0 0 rgba(255,255,255,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 0;
            transition: width 0.35s cubic-bezier(0.16,1,0.3,1), box-shadow 0.35s ease;
            overflow: visible; /* allow tooltips and items to be visible */
            justify-content: center; /* center nav items in the reduced space */
        }
        .crm-sidebar:hover {
            box-shadow: -10px 0 50px rgba(30, 74, 118, 0.2), -8px 0 30px rgba(30, 74, 118, 0.4);
        }

        /* Sidebar tooltip: ensure consistent placement to the left of the pill */
        .crm-sidebar .nav-pill:hover .nav-tooltip,
        .crm-sidebar .logout-btn:hover .nav-tooltip {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(-50%) translateX(0) !important;
            /* use right-based placement so RTL sidebar positions are stable */
            right: calc(100% + 12px) !important;
            left: auto !important;
        }

        .sidebar-divider {
            width: 32px;
            height: 1px;
            background: rgba(255,255,255,0.08);
            margin: 8px auto;
            flex-shrink: 0;
        }

        /* Sidebar nav scroll */
        .sidebar-nav {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 0;
            width: 100%;
            /* no vertical scroll: show all items within the taller sidebar */
            overflow: visible;
        }

        /* Nav item */
        .nav-pill {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            color: #ffffff;
            transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
            cursor: pointer;
            flex-shrink: 0;
            border: 1px solid transparent;
        }
        .nav-pill:hover {
            background: rgba(30, 74, 118, 0.3);
            border-color: rgba(30, 74, 118, 0.5);
            color: #a5b4fc;
            transform: translateX(-3px);
            box-shadow: 0 0 16px rgba(30, 74, 118, 0.25);
        }
        .nav-pill.active {
            background: linear-gradient(135deg, rgba(30, 74, 118, 0.4) 0%, rgba(42, 95, 143, 0.3) 100%);
            border-color: rgba(30, 74, 118, 0.6);
            color: #c7d2fe;
            box-shadow: 0 0 20px rgba(30, 74, 118, 0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .nav-pill.active::after {
            content: '';
            position: absolute;
            right: -1px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: linear-gradient(180deg, #1e4a76, #2a5f8f);
            border-radius: 3px 0 0 3px;
        }

        /* Tooltip */
        .nav-tooltip {
            position: absolute;
            right: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%) translateX(6px);
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.1);
            color: #e2e8f0;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 12px;
            border-radius: 10px;
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(15,23,42,0.4);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s cubic-bezier(0.16,1,0.3,1);
            pointer-events: none;
            z-index: 9999;
        }
        .nav-tooltip::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: rgba(255,255,255,0.12);
        }
        .nav-tooltip::before {
            content: '';
            position: absolute;
            top: 50%;
            left: calc(100% + 1px);
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: #0f172a;
        }
        .nav-pill:hover .nav-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }

        /* Logout btn */
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: #ffffff;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .logout-btn:hover {
            background: rgba(239,68,68,0.2);
            border-color: rgba(239,68,68,0.4);
            color: #fca5a5;
            box-shadow: 0 0 16px rgba(239,68,68,0.2);
            transform: translateX(-2px);
        }

        /* ─── Page Header ───────────────────────────────── */
        .page-header-bar {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(15,23,42,0.06);
            padding: 20px 28px;
            margin-bottom: 28px;
        }
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }
        .page-desc {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        /* ─── Flash Messages ────────────────────────────── */
        .flash-success {
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.25);
            border-right: 3px solid #10b981;
            color: #065f46;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .flash-error {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-right: 3px solid #ef4444;
            color: #7f1d1d;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        /* ─── Animations ────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up {
            animation: fadeUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes ping-slow {
            75%, 100% { transform: scale(2); opacity: 0; }
        }
        .animate-ping-slow { animation: ping-slow 2s cubic-bezier(0,0,0.2,1) infinite; }

        /* ─── Notification badge pulse ──────────────────── */
        .notif-badge {
            position: absolute;
            top: 7px; right: 7px;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #f43f5e;
            border: 2px solid #ffffff;
        }
        .notif-badge::before {
            content: '';
            position: absolute;
            inset: -3px;
        }
        /* Notifications dropdown placement: center under the bell icon */
        #notificationsDropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 50%;
            right: auto;
            transform: translateX(-50%);
            transform-origin: top center;
            z-index: 60;
            min-width: 18rem; /* keep similar to w-80 (20rem) but allow responsiveness */
        }
    </style>
</head>
<body class="antialiased min-h-screen">

    <!-- ════════════════════════════════════════════════
         TOP NAVIGATION BAR
    ════════════════════════════════════════════════ -->
    <nav class="crm-navbar">
        <div class="h-full px-5 flex items-center justify-between gap-4">

            <!-- ── Logo ── -->
            <div class="logo-container flex items-center gap-3 flex-shrink-0">
                <img src="<?php echo base_url('assets/img/logo.jpg'); ?>" class="logo-image" alt="<?php echo APP_NAME; ?> Logo">
                <div>
                    <h1 class="text-white font-bold text-[20px] leading-tight tracking-wide">
                        <?php echo APP_NAME; ?>
                    </h1>
                    <p class="text-[12px] text-white/90 font-semibold tracking-widest uppercase leading-none mt-0.5">
                        نظام إدارة العملاء
                    </p>
                </div>
            </div>

            <!-- ── Global Search ── -->
            <div class="hidden md:flex flex-1 max-w-xs relative mx-4">
                <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-white/70 text-sm pointer-events-none"></i>
                <form action="<?php echo base_url('global_search.php'); ?>" method="GET" class="w-full">
                    <input type="text"
                           name="q"
                           placeholder="بحث سريع..."
                           class="search-input w-full pr-10 pl-4 py-2.5 text-sm"
                           id="globalSearchInput">
                </form>
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-white font-mono bg-white/20 px-1.5 py-0.5 rounded-md border border-white/30">⌘K</span>
            </div>

            <!-- ── Right Actions ── -->
            <div class="flex items-center gap-2">

                <?php if (is_logged_in()): ?>

                    <!-- Mobile search toggle -->
                    <button class="nav-icon-btn md:hidden" onclick="toggleMobileSearch()">
                        <i class="fas fa-search text-sm"></i>
                    </button>

                    <!-- Notifications (rendered by backend helper) -->
                    <?php echo render_notifications_dropdown(); ?>

                    <!-- Divider -->
                    <div class="w-px h-6 bg-white/8 mx-1"></div>

                    <!-- User Profile -->
                    <div class="relative" id="userContainer">
                        <button id="userMenuBtn"
                            class="flex items-center gap-2.5 focus:outline-none group">
                            <?php
                                $uid = $_SESSION['user_id'] ?? null;
                                $avatarUrl = '';
                                if ($uid) {
                                    $uploadDir = __DIR__ . '/../uploads/avatars';
                                    foreach (['png','jpg','jpeg','gif'] as $ext) {
                                        $f = $uploadDir . '/user_' . $uid . '.' . $ext;
                                        if (file_exists($f)) { 
                                            $avatarUrl = base_url('uploads/avatars/user_' . $uid . '.' . $ext);
                                            // Add file modification time to prevent caching
                                            $avatarUrl .= '?v=' . filemtime($f);
                                            break; 
                                        }
                                    }
                                }
                                if (!$avatarUrl) $avatarUrl = 'https://picsum.photos/seed/' . ($uid ?? 'user') . '/100/100';
                            ?>
                            <img src="<?php echo $avatarUrl; ?>"
                                 class="nav-avatar"
                                 alt="صورة المستخدم">
                            <div class="hidden lg:block text-right">
                                <p class="text-white font-bold text-[14px] leading-tight">
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المستخدم'); ?>
                                </p>
                                <p class="text-white/80 text-[12px] leading-tight mt-0.5">مدير النظام</p>
                            </div>
                            <i class="fas fa-chevron-down text-white/70 text-[10px] hidden lg:block transition-transform group-[.open]:rotate-180"></i>
                        </button>

                        <!-- User Dropdown -->
                        <div id="userDropdown" class="dropdown-panel w-60">
                            <div class="dropdown-header">
                                <div class="flex items-center gap-3">
                                     <img src="<?php echo $avatarUrl; ?>"
                                         class="w-10 h-10 rounded-xl object-cover border border-indigo-500/30"
                                         alt="">
                                    <div>
                                        <p class="text-slate-100 font-semibold text-sm leading-tight">
                                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'المستخدم'); ?>
                                        </p>
                                        <p class="text-slate-500 text-xs truncate mt-0.5 max-w-[140px]">
                                            <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="py-1.5">
                                <a href="<?php echo base_url('modules/profile.php'); ?>" class="dropdown-item">
                                    <div class="item-icon"><i class="fas fa-user-circle"></i></div>
                                    الملف الشخصي
                                </a>
                               
                               
                            </div>

                            <div class="dropdown-footer">
                                <a href="<?php echo base_url('logout.php'); ?>" class="flex items-center gap-3 px-1 py-1.5 text-rose-400 hover:text-rose-300 text-sm font-semibold transition-colors group">
                                    <div class="w-7 h-7 rounded-lg bg-rose-500/10 flex items-center justify-center border border-rose-500/20 group-hover:bg-rose-500/20 transition-colors">
                                        <i class="fas fa-sign-out-alt text-xs"></i>
                                    </div>
                                    تسجيل الخروج
                                </a>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </nav>


    <!-- ════════════════════════════════════════════════
         PROFESSIONAL SIDEBAR
    ════════════════════════════════════════════════ -->
    <?php if (is_logged_in()): ?>

        <aside class="crm-sidebar" id="crmSidebar">

            
            <!-- Navigation -->
            <nav class="sidebar-nav">
                <?php
                $menu_items = get_navigation_menu();
                foreach ($menu_items as $item):
                    $is_active = ($current_module === $item['module']);
                ?>
                    <div class="flex justify-center w-full px-2">
                        <a href="<?php echo base_url($item['url']); ?>"
                           class="nav-pill <?php echo $is_active ? 'active' : ''; ?> relative group">
                            <i class="fas fa-<?php echo $item['icon']; ?> text-base"></i>
                            <span class="nav-tooltip"><?php echo $item['name']; ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-divider"></div>

            <!-- Logout button removed from sidebar -->

        </aside>

    <?php endif; ?>


    <!-- ════════════════════════════════════════════════
         MAIN CONTENT WRAPPER
    ════════════════════════════════════════════════ -->
    <main class="min-h-screen pt-[67px] transition-all duration-300 <?php echo is_logged_in() ? 'pr-[88px]' : 'pr-0'; ?>">

        <div class="px-5 sm:px-8 py-7 max-w-7xl mx-auto">

            <!-- Flash Messages -->
            <?php echo display_flash_messages(); ?>

            <!-- Page Header -->
            <?php if ($page_header && (empty($hide_page_header) && ($current_module ?? '') !== 'contacts')): ?>
                <div class="page-header-bar animate-fade-up flex items-start justify-between gap-4">
                    <div>
                        <h1 class="page-title">
                            <?php echo htmlspecialchars($page_header); ?>
                        </h1>
                        <?php if ($page_description): ?>
                            <p class="page-desc">
                                <?php echo htmlspecialchars($page_description); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <!-- Breadcrumb / actions slot — add per-page if needed -->
                </div>
            <?php endif; ?>

            <!-- DYNAMIC CONTENT START -->
            <!-- محتوى الصفحة يُحقن أدناه عبر include -->

