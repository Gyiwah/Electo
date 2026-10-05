<?php
include 'includes/session.php';
require_once __DIR__ . '/../includes/sms.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_settings') {
    if (!verify_csrf_token()) {
        $_SESSION['error'] = 'Security validation failed. Settings not saved.';
        safe_redirect('sms_settings.php');
    }

    $sms_provider = trim($_POST['sms_provider'] ?? 'simulation');
    $sms_sender_id = trim($_POST['sms_sender_id'] ?? 'GAFVote');
    $sms_api_key = trim($_POST['sms_api_key'] ?? '');
    $sms_api_secret = trim($_POST['sms_api_secret'] ?? '');
    $sms_api_url = trim($_POST['sms_api_url'] ?? '');
    $otp_expiry_minutes = max(1, min(60, (int)($_POST['otp_expiry_minutes'] ?? 10)));
    $otp_simulation_mode = !empty($_POST['otp_simulation_mode']) ? '1' : '0';

    $file = __DIR__ . '/config.ini';
    $existing = file_exists($file) ? (@parse_ini_file($file, false, INI_SCANNER_RAW) ?: []) : [];

    $existing['sms_provider'] = $sms_provider;
    $existing['sms_sender_id'] = $sms_sender_id;
    $existing['sms_api_key'] = $sms_api_key;
    $existing['sms_api_secret'] = $sms_api_secret;
    $existing['sms_api_url'] = $sms_api_url;
    $existing['otp_expiry_minutes'] = $otp_expiry_minutes;
    $existing['otp_simulation_mode'] = $otp_simulation_mode;

    $content = '';
    foreach ($existing as $k => $v) {
        $clean_v = preg_replace('/[\r\n]+/', ' ', (string)$v);
        $clean_v = str_replace('"', '\"', $clean_v);
        $content .= "{$k} = \"{$clean_v}\"\n";
    }

    if (@file_put_contents($file, $content) !== false) {
        log_audit($conn, 'admin', $user['id'], 'SMS_CONFIG_UPDATE', "Updated SMS gateway to provider: $sms_provider.");
        $_SESSION['success'] = 'SMS & OTP Gateway settings updated successfully!';
    } else {
        $_SESSION['error'] = 'Unable to write to configuration file.';
    }

    safe_redirect('sms_settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send_test_sms') {
    if (!verify_csrf_token()) {
        $_SESSION['error'] = 'Security validation failed.';
        safe_redirect('sms_settings.php');
    }

    $phone = trim($_POST['test_phone'] ?? '');
    $message = trim($_POST['test_message'] ?? 'Test message from GAFVote');

    if (empty($phone) || empty($message)) {
        $_SESSION['error'] = 'Phone number and message are required.';
        safe_redirect('sms_settings.php');
    }

    $result = send_sms($phone, $message);

    if ($result['success']) {
        $_SESSION['success'] = "Test SMS dispatched successfully! Result: " . escape_html($result['message']);
    } else {
        $_SESSION['error'] = "SMS delivery failed: " . escape_html($result['message']);
    }

    safe_redirect('sms_settings.php');
}

if ($action === 'clear_log') {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Security token invalid.';
        safe_redirect('sms_settings.php');
    }

    if (file_exists(SMS_LOG_FILE)) {
        @file_put_contents(SMS_LOG_FILE, '');
    }
    $_SESSION['success'] = 'SMS outbox logs cleared.';
    safe_redirect('sms_settings.php');
}

safe_redirect('sms_settings.php');
