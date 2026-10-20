<?php
// Notifications System
// Prevent direct access
if (!defined('CRM_CONFIG')) {
    require_once __DIR__ . '/../config/config.php';
}

// Function to create notification
function create_notification($user_id, $title, $message, $type = 'info', $related_type = null, $related_id = null) {
    try {
        $db = get_db();
        
        $notification_data = [
            'user_id' => $user_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_type' => $related_type,
            'related_id' => $related_id
        ];
        
        $notification_id = $db->insert('notifications', $notification_data);
        
        // Log notification creation
        log_audit('notification_created', 'notifications', $notification_id, "Notification created for user $user_id: $title");
        
        return $notification_id;
        
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

// Function to get user notifications
function get_user_notifications($user_id = null, $unread_only = false, $limit = 10) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    try {
        $db = get_db();
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT $limit";
        
        return $db->fetchAll($sql, $params);
        
    } catch (Exception $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

// Function to mark notification as read
function mark_notification_read($notification_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    try {
        $db = get_db();
        
        $updated = $db->update('notifications', 
            ['is_read' => 1], 
            'id = ? AND user_id = ?', 
            [$notification_id, $user_id]
        );
        
        if ($updated) {
            log_audit('notification_read', 'notifications', $notification_id, "Notification marked as read");
        }
        
        return $updated;
        
    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

// Function to mark all notifications as read for user
function mark_all_notifications_read($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    try {
        $db = get_db();
        
        $updated = $db->update('notifications', 
            ['is_read' => 1], 
            'user_id = ? AND is_read = 0', 
            [$user_id]
        );
        
        if ($updated) {
            log_audit('notifications_read_all', 'notifications', null, "All notifications marked as read for user $user_id");
        }
        
        return $updated;
        
    } catch (Exception $e) {
        error_log("Mark all notifications read error: " . $e->getMessage());
        return false;
    }
}

// Function to get unread notification count
function get_unread_notification_count($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    try {
        $db = get_db();
        
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $result = $db->fetch($sql, [$user_id]);
        
        return $result['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Get unread notification count error: " . $e->getMessage());
        return 0;
    }
}

// Function to delete notification
function delete_notification($notification_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    
    try {
        $db = get_db();
        
        $deleted = $db->delete('notifications', 'id = ? AND user_id = ?', [$notification_id, $user_id]);
        
        if ($deleted) {
            log_audit('notification_deleted', 'notifications', $notification_id, "Notification deleted");
        }
        
        return $deleted;
        
    } catch (Exception $e) {
        error_log("Delete notification error: " . $e->getMessage());
        return false;
    }
}

// Function to send email notification (basic implementation)
function send_email_notification($to_email, $subject, $message, $from_email = null) {
    if ($from_email === null) {
        $from_email = get_setting('company_email', 'noreply@crm.com');
    }

    $html_message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject) . '</title>' .
        '<style>body{font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.6}.container{max-width:600px;margin:0 auto;padding:20px}.header{background:#2563eb;color:#fff;padding:20px;text-align:center}.content{padding:20px;background:#f9fafb}.footer{padding:20px;text-align:center;font-size:12px;color:#666}</style>' .
        '</head><body><div class="container"><div class="header"><h2>' . APP_NAME . '</h2></div><div class="content">' . nl2br(htmlspecialchars($message)) . '</div><div class="footer"><p>هذه الرسالة أرسلت من نظام ' . APP_NAME . '</p></div></div></body></html>';

    // Prefer sending via SMTP using configured constants. This implementation uses a lightweight SMTP client
    // implemented with stream sockets and supports SSL/TLS and AUTH LOGIN.
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 25;
    $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : '';

    // Helper to read full SMTP response (handles multi-line)
    $get_response = function($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            // lines that have a space at pos 3 are last lines of a response
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $transport = $host;
    if ($encryption === 'ssl') {
        $transport = 'ssl://' . $host;
    }

    $timeout = 30;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        error_log("send_email_notification: Unable to connect to SMTP {$host}:{$port} - {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($fp, $timeout);
    $res = $get_response($fp);

    // EHLO
    $localhost = gethostname() ?: 'localhost';
    fwrite($fp, "EHLO {$localhost}\r\n");
    $res = $get_response($fp);

    // STARTTLS if requested and not using implicit SSL
    if ($encryption === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        $res = $get_response($fp);
        if (stripos($res, '220') === 0) {
            $ok = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($ok !== true) {
                error_log("send_email_notification: Failed to enable TLS on SMTP connection");
                fclose($fp);
                return false;
            }
            // EHLO again after TLS
            fwrite($fp, "EHLO {$localhost}\r\n");
            $res = $get_response($fp);
        }
    }

    // AUTH if credentials provided
    if (!empty($username)) {
        fwrite($fp, "AUTH LOGIN\r\n");
        $res = $get_response($fp);
        fwrite($fp, base64_encode($username) . "\r\n");
        $res = $get_response($fp);
        fwrite($fp, base64_encode($password) . "\r\n");
        $res = $get_response($fp);
        if (stripos($res, '235') !== 0 && stripos($res, '235') === false) {
            // 235 indicates successful auth
            // allow some servers to reply with 235 anywhere in response
            if (stripos($res, '235') === false) {
                error_log("send_email_notification: SMTP auth failed: {$res}");
                fclose($fp);
                return false;
            }
        }
    }

    // MAIL FROM / RCPT TO
    fwrite($fp, "MAIL FROM:<{$from_email}>\r\n");
    $res = $get_response($fp);
    if (stripos($res, '250') !== 0) {
        error_log("send_email_notification: MAIL FROM rejected: {$res}");
    }

    fwrite($fp, "RCPT TO:<{$to_email}>\r\n");
    $res = $get_response($fp);
    if (stripos($res, '250') !== 0 && stripos($res, '251') !== 0) {
        error_log("send_email_notification: RCPT TO rejected: {$res}");
        fclose($fp);
        return false;
    }

    fwrite($fp, "DATA\r\n");
    $res = $get_response($fp);
    if (stripos($res, '354') !== 0 && stripos($res, '354') === false) {
        error_log("send_email_notification: DATA command rejected: {$res}");
        fclose($fp);
        return false;
    }

    $headers = [];
    $headers[] = "From: {$from_email}";
    $headers[] = "To: {$to_email}";
    $headers[] = "Subject: {$subject}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "X-Mailer: PHP/" . phpversion();

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $html_message . "\r\n.\r\n";
    fwrite($fp, $data);
    $res = $get_response($fp);

    // QUIT
    fwrite($fp, "QUIT\r\n");
    $get_response($fp);
    fclose($fp);

    if (stripos($res, '250') === 0) {
        error_log("send_email_notification OK: to={$to_email} subject={$subject}");
        return true;
    } else {
        error_log("send_email_notification failed (SMTP): to={$to_email} subject={$subject} response=" . trim($res));

        // Fallback: try PHP's mail() which on many cPanel hosts uses the local MTA (Exim)
        // Build headers for HTML mail
        $fallback_from = $from_email;
        if (empty($fallback_from) || !validate_email($fallback_from)) {
            $host = $_SERVER['HTTP_HOST'] ?? parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost';
            $fallback_from = 'noreply@' . preg_replace('/^www\./i', '', $host);
        }

        $plain_message = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_message));
        $headers_arr = [];
        $headers_arr[] = "From: {$fallback_from}";
        $headers_arr[] = "MIME-Version: 1.0";
        $headers_arr[] = "Content-type: text/html; charset=UTF-8";
        $headers_arr[] = "X-Mailer: PHP/" . phpversion();
        $headers_str = implode("\r\n", $headers_arr);

        $mail_ok = false;
        try {
            $mail_ok = @mail($to_email, $subject, $html_message, $headers_str);
        } catch (Exception $e) {
            error_log('send_email_notification fallback mail() exception: ' . $e->getMessage());
            $mail_ok = false;
        }

        if ($mail_ok) {
            error_log("send_email_notification OK (mail fallback): to={$to_email} subject={$subject} from={$fallback_from}");
            return true;
        }

        error_log("send_email_notification failed (all methods): to={$to_email} subject={$subject}");
        return false;
    }
}

// Function to create notification for new task
function notify_new_task($task_id, $assigned_to_id) {
    try {
        $db = get_db();
        
        // Get task details
        $sql = "SELECT t.*, u.full_name as creator_name 
                FROM tasks t 
                JOIN users u ON t.created_by = u.id 
                WHERE t.id = ?";
        $task = $db->fetch($sql, [$task_id]);
        
        if ($task) {
            $title = 'مهمة جديدة';
            $message = "لقد تم تعيينك مهمة جديدة: {$task['title']}\n";
            $message .= "من قبل: {$task['creator_name']}\n";
            if ($task['due_date']) {
                $message .= "تاريخ الاستحقاق: " . format_date($task['due_date']) . "\n";
            }
            if ($task['description']) {
                $message .= "الوصف: {$task['description']}";
            }
            
            // Create database notification
            create_notification($assigned_to_id, $title, $message, 'info', 'task', $task_id);
            
            // Send email notification if enabled
            $user = get_user_info($assigned_to_id);
            if ($user && get_setting('email_notifications', true)) {
                send_email_notification($user['email'], $title, $message);
            }
        }
        
    } catch (Exception $e) {
        error_log("Notify new task error: " . $e->getMessage());
    }
}

// Function to notify about task status change
function notify_task_status_changed($task_id, $user_id = null, $old_status = null, $new_status = null) {
    try {
        $db = get_db();

        // Get task details
        $sql = "SELECT t.*, u.full_name as creator_name FROM tasks t JOIN users u ON t.created_by = u.id WHERE t.id = ?";
        $task = $db->fetch($sql, [$task_id]);

        if ($task) {
            $title = 'تحديث حالة المهمة';
            $message = "تم تغيير حالة المهمة: {$task['title']}\n";
            $message .= "من: " . ($old_status ?: '-') . " إلى: " . ($new_status ?: '-') . "\n";
            $message .= "من قبل: {$task['creator_name']}\n";
            if ($task['due_date']) {
                $message .= "تاريخ الاستحقاق: " . format_date($task['due_date']) . "\n";
            }

            // Notify assigned user if exists
            $target_user = $user_id ?: ($task['assigned_to'] ?? null);
            if ($target_user) {
                create_notification($target_user, $title, $message, 'info', 'task', $task_id);
                $user = get_user_info($target_user);
                if ($user && get_setting('email_notifications', true)) {
                    send_email_notification($user['email'], $title, $message);
                }
            }

            // Optionally notify managers for critical transitions (e.g., to cancelled or completed)
            if (in_array($new_status, ['cancelled', 'completed'])) {
                notify_managers($title, $message, 'task', $task_id);
            }
        }
    } catch (Exception $e) {
        error_log("Notify task status changed error: " . $e->getMessage());
    }
}

// Function to create notification for new deal
function notify_new_deal($deal_id, $assigned_to_id = null) {
    try {
        $db = get_db();
        
        // Get deal details
        $sql = "SELECT d.*, c.first_name, c.last_name, u.full_name as creator_name 
                FROM deals d 
                JOIN contacts c ON d.contact_id = c.id 
                JOIN users u ON d.created_by = u.id 
                WHERE d.id = ?";
        $deal = $db->fetch($sql, [$deal_id]);
        
        if ($deal) {
            $title = 'صفقة جديدة';
            $message = "تم إنشاء صفقة جديدة: {$deal['title']}\n";
            $message .= "العميل: {$deal['first_name']} {$deal['last_name']}\n";
            $message .= "من قبل: {$deal['creator_name']}\n";
            if ($deal['amount']) {
                $message .= "القيمة: " . format_currency($deal['amount']) . "\n";
            }
            $message .= "المرحلة: " . get_stage_name_arabic($deal['stage']);
            
            // Notify assigned user if specified
            if ($assigned_to_id) {
                create_notification($assigned_to_id, $title, $message, 'success', 'deal', $deal_id);
                
                // Send email notification
                $user = get_user_info($assigned_to_id);
                if ($user && get_setting('email_notifications', true)) {
                    send_email_notification($user['email'], $title, $message);
                }
            }
            
            // Notify managers
            notify_managers($title, $message, 'deal', $deal_id);
        }
        
    } catch (Exception $e) {
        error_log("Notify new deal error: " . $e->getMessage());
    }
}

// Function to create notification for new ticket
function notify_new_ticket($ticket_id) {
    try {
        $db = get_db();
        
        // Get ticket details
        $sql = "SELECT t.*, c.first_name, c.last_name, u.full_name as creator_name 
                FROM tickets t 
                LEFT JOIN contacts c ON t.contact_id = c.id 
                JOIN users u ON t.created_by = u.id 
                WHERE t.id = ?";
        $ticket = $db->fetch($sql, [$ticket_id]);
        
        if ($ticket) {
            $title = 'تذكرة دعم جديدة';
            $message = "تم إنشاء تذكرة دعم جديدة: {$ticket['subject']}\n";
            $message .= "من قبل: {$ticket['creator_name']}\n";
            if ($ticket['contact_id']) {
                $message .= "العميل: {$ticket['first_name']} {$ticket['last_name']}\n";
            }
            $message .= "الأولوية: " . get_priority_name_arabic($ticket['priority']) . "\n";
            $message .= "الوصف: {$ticket['description']}";
            
            // Notify assigned user if specified
            if ($ticket['assigned_to']) {
                create_notification($ticket['assigned_to'], $title, $message, 'warning', 'ticket', $ticket_id);
                
                // Send email notification
                $user = get_user_info($ticket['assigned_to']);
                if ($user && get_setting('email_notifications', true)) {
                    send_email_notification($user['email'], $title, $message);
                }
            }
            
            // Notify managers
            notify_managers($title, $message, 'ticket', $ticket_id);
        }
        
    } catch (Exception $e) {
        error_log("Notify new ticket error: " . $e->getMessage());
    }
}

// Function to notify all managers
function notify_managers($title, $message, $related_type = null, $related_id = null) {
    try {
        $db = get_db();
        
        // Get all managers
        $sql = "SELECT id FROM users WHERE role = 'manager' AND is_active = 1";
        $managers = $db->fetchAll($sql);
        
        foreach ($managers as $manager) {
            create_notification($manager['id'], $title, $message, 'info', $related_type, $related_id);
        }
        
    } catch (Exception $e) {
        error_log("Notify managers error: " . $e->getMessage());
    }
}

// Helper function to get stage name in Arabic
function get_stage_name_arabic($stage) {
    $stages = [
        'prospecting' => 'استكشاف',
        'qualification' => 'تأهيل',
        'proposal' => 'عرض سعر',
        'negotiation' => 'مفاوضات',
        'closed_won' => 'مكتمل',
        'closed_lost' => 'فقد'
    ];
    
    return $stages[$stage] ?? $stage;
}

// Helper function to get priority name in Arabic
function get_priority_name_arabic($priority) {
    $priorities = [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'عالي',
        'urgent' => 'عاجل'
    ];
    
    return $priorities[$priority] ?? $priority;
}

// Function to render notifications dropdown
function render_notifications_dropdown() {
    $user_id = get_current_user_id();
    $unread_count = get_unread_notification_count($user_id);
    $notifications = get_user_notifications($user_id, false, 5);
    
    $html = '<div class="relative">';
    $html .= '<button id="notificationToggle" class="relative p-2 text-white hover:text-white/90 focus:outline-none focus:ring-2 focus:ring-white/20 rounded-lg">';
    $html .= '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>';
    $html .= '</svg>';
    
    if ($unread_count > 0) {
        $html .= '<span class="notif-badge absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">' . $unread_count . '</span>';
    } else {
        $html .= '<span class="notif-badge absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full hidden">0</span>';
    }
    
    $html .= '</button>';
    
    $html .= '<div id="notificationsDropdown" class="dropdown-panel absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">';
    $html .= '<div class="p-4 border-b border-gray-200">';
    $html .= '<h3 class="text-lg font-semibold text-gray-900">الإشعارات</h3>';
    if ($unread_count > 0) {
        $html .= '<button onclick="markAllNotificationsRead()" class="text-sm text-blue-600 hover:text-blue-800">تعيين الكل كمقروء</button>';
    }
    $html .= '</div>';
    
    $html .= '<div class="max-h-96 overflow-y-auto">';
    
    if (empty($notifications)) {
        $html .= '<div class="p-4 text-center text-gray-500">لا توجد إشعارات</div>';
    } else {
        foreach ($notifications as $notification) {
            $read_class = $notification['is_read'] ? 'bg-gray-50' : 'bg-blue-50';
            $html .= '<div class="p-4 border-b border-gray-100 ' . $read_class . ' hover:bg-gray-100 cursor-pointer" onclick="viewNotification(' . $notification['id'] . ')">';
            $html .= '<div class="flex items-start">';
            $html .= '<div class="flex-1">';
            $html .= '<h4 class="text-sm font-semibold text-gray-900">' . htmlspecialchars($notification['title']) . '</h4>';
            $html .= '<p class="text-sm text-gray-600 mt-1">' . htmlspecialchars($notification['message']) . '</p>';
            $html .= '<p class="text-xs text-gray-500 mt-2">' . time_ago($notification['created_at']) . '</p>';
            $html .= '</div>';
            if (!$notification['is_read']) {
                $html .= '<div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    $html .= '<div class="p-4 border-t border-gray-200">';
    $html .= '<a href="' . base_url('modules/notifications.php') . '" class="text-sm text-blue-600 hover:text-blue-800">عرض جميع الإشعارات</a>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>