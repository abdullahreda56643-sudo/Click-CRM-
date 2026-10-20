<?php
// Simple test script to send an email using the application's notification sender.
// Provide minimal server variables when run from CLI so config can initialize safely.
if (php_sapi_name() === 'cli') {
    if (empty($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = 'localhost';
    if (empty($_SERVER['SERVER_PORT'])) $_SERVER['SERVER_PORT'] = 80;
    if (empty($_SERVER['DOCUMENT_ROOT'])) $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..');
    if (empty($_SERVER['HTTPS'])) $_SERVER['HTTPS'] = 'off';
    if (empty($_SERVER['REQUEST_METHOD'])) $_SERVER['REQUEST_METHOD'] = 'GET';
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notifications.php';

$to = null;
// Allow GET param or CLI arg
if (php_sapi_name() === 'cli') {
    global $argv;
    if (isset($argv[1])) $to = $argv[1];
} else {
    if (!empty($_GET['to'])) $to = $_GET['to'];
}

if (empty($to)) {
    $msg = "Usage: provide ?to=you@example.com in the browser or run `php scripts/send_test_email.php you@example.com`\n";
    echo $msg;
    error_log("send_test_email: no recipient provided");
    exit(1);
}

$subject = 'CRM Test Email — اختبار إرسال البريد';
$body = "هذه رسالة اختبارية أُرسلت من نظام " . APP_NAME . "\n\nوقت الإرسال: " . date(DATETIME_FORMAT);

$ok = send_email_notification($to, $subject, $body);
if ($ok) {
    echo "OK: Email sent to {$to}\n";
    error_log("send_test_email: OK to={$to}");
    exit(0);
} else {
    echo "FAILED: See logs/error.log for details\n";
    error_log("send_test_email: FAILED to={$to}");
    exit(2);
}

?>