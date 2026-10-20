<?php
if (!defined('CRM_CONFIG')) require_once __DIR__ . '/../../config/config.php';

require_login();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/auth.php';

 $id = intval($_GET['id'] ?? 0);
if (!$id) { redirect('modules/tickets/'); }

require_permission('tickets', 'view');
 $db = get_db();
 $ticket = $db->fetch('SELECT t.*, c.first_name, c.last_name, u.full_name as assigned_name, cr.full_name as created_name FROM tickets t LEFT JOIN contacts c ON t.contact_id = c.id LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users cr ON t.created_by = cr.id WHERE t.id = ?', [$id]);
if (!$ticket) { redirect('modules/tickets/'); }
if (!can_access_record('tickets', $id, 'view')) { $_SESSION['error']='غير مصرح بالوصول لهذه التذكرة.'; redirect('modules/tickets/'); }

// Mappers for Arabic translation and Styling
 $statusMap = [
    'open' => ['text' => 'مفتوحة', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20'],
    'in_progress' => ['text' => 'قيد التنفيذ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20'],
    'resolved' => ['text' => 'محلولة', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'],
    'closed' => ['text' => 'مغلقة', 'class' => 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20']
];
 $priorityMap = [
    'low' => ['text' => 'منخفضة', 'class' => 'bg-gray-50 text-gray-600 ring-1 ring-gray-500/20'],
    'medium' => ['text' => 'متوسطة', 'class' => 'bg-blue-50 text-[#1e4a76] ring-1 ring-[#1e4a76]/20'],
    'high' => ['text' => 'عالية', 'class' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20'],
    'urgent' => ['text' => 'عاجلة', 'class' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20']
];

// Handle reply POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) { $_SESSION['error'] = 'رمز CSRF غير صالح.'; redirect("modules/tickets/view.php?id={$id}"); }
    $message = clean_input($_POST['reply'] ?? '');
    if (!empty($message)) {
        $reply_id = $db->insert('ticket_replies', [ 'ticket_id' => $id, 'message' => $message, 'is_internal' => 0, 'created_by' => get_current_user_id(), 'created_at' => date('Y-m-d H:i:s') ]);
        if ($reply_id) {
            if (!empty($_FILES['attachments'])) {
                $uploadDir = __DIR__ . '/../../uploads/tickets/' . $id;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                foreach ($_FILES['attachments']['name'] as $idx => $name) {
                    if (empty($name)) continue;
                    $tmp = $_FILES['attachments']['tmp_name'][$idx];
                    $size = intval($_FILES['attachments']['size'][$idx]);
                    $type = $_FILES['attachments']['type'][$idx] ?? '';
                    if ($size > 5 * 1024 * 1024) continue;
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safe = bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $safe;
                    if (move_uploaded_file($tmp, $dest)) {
                        $db->insert('ticket_attachments', [
                            'ticket_id' => $id, 'reply_id' => $reply_id, 'filename' => $name,
                            'path' => 'uploads/tickets/' . $id . '/' . $safe, 'mime' => $type,
                            'size' => $size, 'uploaded_by' => get_current_user_id(), 'uploaded_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            log_audit('reply', 'ticket_replies', $reply_id, 'Replied to ticket');
            if (!empty($ticket['assigned_to'])) create_notification($ticket['assigned_to'], 'رد جديد على التذكرة', $message, 'info', 'ticket', $id);
            // Send email to contact if configured (external reply)
            try {
                if (!empty($ticket['contact_id']) && get_setting('notify_contact_on_reply', true)) {
                    $c = $db->fetch('SELECT email, first_name FROM contacts WHERE id = ?', [$ticket['contact_id']]);
                    if ($c && !empty($c['email']) && validate_email($c['email'])) {
                        $title = 'رد على تذكرتك #' . $id;
                        $mailMsg = "مرحباً " . ($c['first_name'] ?: '') . "\n\nتم الرد على تذكرتك بعنوان: {$ticket['subject']}\n\n" . strip_tags($message) . "\n\nعرض التذكرة: " . base_url("modules/tickets/view.php?id={$id}");
                        send_email_notification($c['email'], $title, $mailMsg);
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to send ticket reply email: ' . $e->getMessage());
            }
            $_SESSION['success'] = 'تم إرسال الرد.';
        }
        redirect("modules/tickets/view.php?id={$id}");
    }
}

// fetch replies and attachments
 $replies = $db->fetchAll('SELECT r.*, u.full_name as user_name FROM ticket_replies r LEFT JOIN users u ON r.created_by = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC', [$id]);
 $ticket_attachments = [];
 $reply_attachments = [];
try {
    $ticket_attachments = $db->fetchAll('SELECT * FROM ticket_attachments WHERE ticket_id = ? AND reply_id IS NULL', [$id]);
} catch (Exception $e) { $ticket_attachments = []; }
foreach ($replies as $rr) {
    try {
        $reply_attachments[$rr['id']] = $db->fetchAll('SELECT * FROM ticket_attachments WHERE reply_id = ?', [$rr['id']]);
    } catch (Exception $e) { $reply_attachments[$rr['id']] = []; }
}

require_permission('tickets', 'view');
require_once __DIR__ . '/../../templates/header.php';
?>

<!-- Custom Scrollbar & Chat Style -->
<style>
    .chat-container { max-height: 500px; overflow-y: auto; }
    .chat-container::-webkit-scrollbar { width: 6px; }
    .chat-container::-webkit-scrollbar-track { background: transparent; }
    .chat-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .chat-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<!-- Page Header - Premium Gradient Style -->
<div class="relative bg-gradient-to-l from-[#1e4a76] to-[#0f2a47] rounded-3xl shadow-xl p-8 mb-8 text-white overflow-hidden">
    <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 bg-[#4a7db8]/20 rounded-full translate-y-1/2 -translate-x-1/4 blur-xl"></div>
    
    <div class="relative z-10">
        <div class="flex items-center mb-4">
            <a href="index.php" class="text-blue-200 hover:text-white transition mr-3 text-sm font-semibold">
                <i class="fas fa-arrow-right ml-1"></i> العودة للقائمة
            </a>
        </div>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full">#TCK-<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></span>
                    <?php 
                    $s_key = $ticket['status'] ?? 'open';
                    $s_cfg = $statusMap[$s_key] ?? ['text' => $s_key];
                    ?>
                    <span class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full"><?php echo $s_cfg['text']; ?></span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                <p class="text-blue-200/80 text-sm font-medium mt-2">
                    <i class="fas fa-user ml-1"></i> <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?> 
                    <span class="mx-2 opacity-50">•</span> 
                    <i class="fas fa-calendar ml-1"></i> <?php echo format_date($ticket['created_at']); ?>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="edit.php?id=<?php echo $id; ?>" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-xl transition flex items-center text-sm">
                    <i class="fas fa-pen ml-2 text-xs"></i> تعديل
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Chat & Reply Section -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Original Ticket Description -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-gradient-to-br from-[#1e4a76] to-[#4a7db8] rounded-full flex-shrink-0 flex items-center justify-center text-white font-bold shadow-md text-sm">
                    <?php echo mb_substr(htmlspecialchars($ticket['created_name'] ?? 'م'), 0, 1); ?>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h4 class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($ticket['created_name'] ?? 'المنشئ'); ?></h4>
                        <span class="text-xs text-gray-400 font-medium">أنشأ التذكرة • <?php echo format_datetime($ticket['created_at']); ?></span>
                    </div>
                    <div class="text-gray-600 text-sm leading-relaxed bg-gray-50 p-4 rounded-xl mt-2 border border-gray-100/50">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                    <?php if (!empty($ticket_attachments)): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php foreach ($ticket_attachments as $a): ?>
                                <a href="/<?php echo htmlspecialchars($a['path']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-[#1e4a76]/5 hover:text-[#1e4a76] hover:border-[#1e4a76]/20 transition">
                                    <i class="fas fa-paperclip ml-2 text-gray-400"></i> <?php echo htmlspecialchars($a['filename']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conversation Thread -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 flex items-center">
                    <i class="fas fa-comments text-[#1e4a76] ml-2"></i> المحادثة
                </h3>
                <span class="text-xs bg-gray-100 px-3 py-1 rounded-full font-bold text-gray-500"><?php echo count($replies); ?> ردود</span>
            </div>
            
            <div class="p-6 chat-container space-y-6 bg-gray-50/50">
                <?php if (empty($replies)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-comment-dots text-gray-300 text-xl"></i>
                        </div>
                        <p class="text-gray-400 font-semibold text-sm">لا توجد ردود بعد</p>
                        <p class="text-gray-300 text-xs mt-1">كن أول من يرد على هذه التذكرة</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($replies as $r): ?>
                    <?php $isMe = $r['created_by'] == get_current_user_id(); ?>
                    <div class="flex items-start gap-4 <?php echo !$isMe ? '' : 'flex-row-reverse'; ?>">
                        <div class="w-9 h-9 <?php echo $isMe ? 'bg-emerald-500' : 'bg-gradient-to-br from-[#1e4a76] to-[#4a7db8]'; ?> rounded-full flex-shrink-0 flex items-center justify-center text-white font-bold shadow text-xs">
                            <?php echo mb_substr(htmlspecialchars($r['user_name'] ?? 'م'), 0, 1); ?>
                        </div>
                        <div class="flex-1 <?php echo !$isMe ? '' : 'text-left'; ?>">
                            <div class="flex items-center gap-2 mb-1 <?php echo !$isMe ? '' : 'justify-end'; ?>">
                                <span class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($r['user_name'] ?? 'مستخدم'); ?></span>
                                <span class="text-[11px] text-gray-400 font-medium"><?php echo format_datetime($r['created_at']); ?></span>
                            </div>
                            <div class="inline-block bg-white p-4 rounded-xl shadow-sm border border-gray-100/80 text-gray-700 text-sm leading-relaxed max-w-full lg:max-w-[80%]">
                                <?php echo nl2br(htmlspecialchars($r['message'])); ?>
                                <?php if (!empty($reply_attachments[$r['id']])): ?>
                                    <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-2">
                                        <?php foreach ($reply_attachments[$r['id']] as $a): ?>
                                            <a href="/<?php echo htmlspecialchars($a['path']); ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-blue-50 hover:text-[#1e4a76] transition">
                                                <i class="fas fa-paperclip ml-2 text-gray-400"></i> <?php echo htmlspecialchars($a['filename']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Form -->
            <div class="p-6 bg-white border-t border-gray-100">
                <form method="POST" action="" id="replyForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <textarea name="reply" rows="3" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#1e4a76] focus:border-[#1e4a76] transition text-sm placeholder-gray-400" placeholder="اكتب ردك هنا..." required></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">
                                <i class="fas fa-paperclip ml-2 text-gray-400"></i> إرفاق ملف
                                <input type="file" name="attachments[]" multiple class="hidden" />
                            </label>
                        </div>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-l from-[#1e4a76] to-[#2d5f96] text-white font-bold rounded-xl hover:shadow-lg hover:shadow-[#1e4a76]/20 transition flex items-center text-sm">
                            <i class="fas fa-paper-plane ml-2"></i> إرسال الرد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Details -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Ticket Status & Details -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-info-circle text-[#1e4a76] ml-2"></i> تفاصيل التذكرة
                </h3>
            </div>
            <div class="p-5 divide-y divide-gray-100">
                <!-- Status -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">الحالة</span>
                    <?php 
                    $s_key = $ticket['status'] ?? 'open';
                    $s_cfg = $statusMap[$s_key] ?? ['text' => $s_key, 'class' => 'bg-gray-50 text-gray-700'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?php echo $s_cfg['class']; ?>">
                        <?php echo $s_cfg['text']; ?>
                    </span>
                </div>
                <!-- Priority -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">الأولوية</span>
                    <?php 
                    $p_key = $ticket['priority'] ?? 'low';
                    $p_cfg = $priorityMap[$p_key] ?? ['text' => $p_key, 'class' => 'bg-gray-50 text-gray-700'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?php echo $p_cfg['class']; ?>">
                        <?php echo $p_cfg['text']; ?>
                    </span>
                </div>
                <!-- Assignee -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">المسؤول</span>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-[#1e4a76]/10 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-[8px] text-[#1e4a76]"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'غير معين'); ?></span>
                    </div>
                </div>
                <!-- Created By -->
                <div class="flex items-center justify-between py-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">أُنشئت بواسطة</span>
                    <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($ticket['created_name'] ?? '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions (Placeholder for future status updates) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100/80 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center text-sm">
                    <i class="fas fa-bolt text-amber-500 ml-2"></i> إجراءات سريعة
                </h3>
            </div>
            <div class="p-5 space-y-2">
                <?php if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed'): ?>
                    <button onclick="window.location.href='edit.php?id=<?php echo $id; ?>&status=resolved'" class="w-full px-4 py-2.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl hover:bg-emerald-100 transition text-sm flex items-center justify-center gap-2">
                        <i class="fas fa-check-circle text-xs"></i> إغلاق كمحلولة
                    </button>
                    <button onclick="window.location.href='edit.php?id=<?php echo $id; ?>&status=closed'" class="w-full px-4 py-2.5 bg-gray-50 text-gray-700 font-bold rounded-xl hover:bg-gray-100 transition text-sm flex items-center justify-center gap-2">
                        <i class="fas fa-lock text-xs"></i> إغلاق التذكرة
                    </button>
                <?php else: ?>
                    <button onclick="window.location.href='edit.php?id=<?php echo $id; ?>&status=open'" class="w-full px-4 py-2.5 bg-blue-50 text-[#1e4a76] font-bold rounded-xl hover:bg-blue-100 transition text-sm flex items-center justify-center gap-2">
                        <i class="fas fa-lock-open text-xs"></i> إعادة فتح التذكرة
                    </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Auto-scroll chat to bottom -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatContainer = document.querySelector('.chat-container');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    });
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>