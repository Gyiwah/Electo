<?php
/**
 * SMS Gateway & OTP Dispatch Service
 * Supports multiple providers: Simulation (Local/Dev), Arkesel, Hubtel, mNotify, Termii, Twilio, and Generic HTTP Webhook.
 */

if (!defined('SMS_LOG_FILE')) {
    define('SMS_LOG_FILE', __DIR__ . '/../sms_outbox.log');
}

/**
 * Retrieve SMS Gateway Configuration from config.ini with safe defaults
 * @return array
 */
function get_sms_config() {
    $ini_path = __DIR__ . '/../admin/config.ini';
    $config = [
        'election_title'      => 'Voting System',
        'sms_provider'        => 'simulation', // 'simulation', 'arkesel', 'hubtel', 'mnotify', 'termii', 'twilio', 'generic'
        'sms_sender_id'       => 'GAFVote',
        'sms_api_key'         => '',
        'sms_api_secret'      => '',
        'sms_api_url'         => '',
        'otp_expiry_minutes'  => 10,
        'otp_simulation_mode' => '1', // 1: display demo OTP banner for testing, 0: production silent
    ];

    if (file_exists($ini_path)) {
        $parsed = @parse_ini_file($ini_path, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            $config = array_merge($config, $parsed);
        }
    }

    return $config;
}

/**
 * Normalize phone number to standard format
 * @param string $phone
 * @return string
 */
function normalize_phone_number($phone) {
    $phone = trim((string)$phone);
    // Remove spaces, hyphens, parentheses, and dots
    $clean = preg_replace('/[^\d+]/', '', $phone);
    return $clean;
}

/**
 * Format phone number to international format (defaulting to +233 Ghana if local 0XX format)
 * @param string $phone
 * @param string $default_country_code Default '233'
 * @return string
 */
function format_phone_international($phone, $default_country_code = '233') {
    $clean = normalize_phone_number($phone);
    if (str_starts_with($clean, '+')) {
        return ltrim($clean, '+');
    }
    if (str_starts_with($clean, '00')) {
        return substr($clean, 2);
    }
    if (str_starts_with($clean, '0') && strlen($clean) === 10) {
        return $default_country_code . substr($clean, 1);
    }
    return $clean;
}

/**
 * Return masked phone representation (e.g. 024••••123)
 * @param string $phone
 * @return string
 */
function get_masked_phone($phone) {
    $clean = normalize_phone_number($phone);
    $len = strlen($clean);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    $visible_end = 3;
    $visible_start = ($len > 8) ? 3 : 2;
    $start = substr($clean, 0, $visible_start);
    $end = substr($clean, -$visible_end);
    $masked = $start . str_repeat('•', max(3, $len - $visible_start - $visible_end)) . $end;
    return $masked;
}

/**
 * Core SMS sender dispatching to chosen gateway
 * 
 * @param string $phone Recipient phone number
 * @param string $message Text message content
 * @return array ['success' => bool, 'message' => string, 'provider' => string, 'details' => mixed]
 */
function send_sms($phone, $message) {
    $config = get_sms_config();
    $provider = strtolower(trim($config['sms_provider'] ?? 'simulation'));
    $sender = trim($config['sms_sender_id'] ?: 'GAFVote');
    $phone_norm = normalize_phone_number($phone);
    $phone_intl = format_phone_international($phone_norm);

    $log_entry = sprintf("[%s] [%s] To: %s | Msg: %s\n", date('Y-m-d H:i:s'), strtoupper($provider), $phone_norm, $message);
    @file_put_contents(SMS_LOG_FILE, $log_entry, FILE_APPEND);

    // If simulation mode or empty API key, run in simulated mode
    if ($provider === 'simulation' || (empty($config['sms_api_key']) && !in_array($provider, ['simulation', 'generic']))) {
        return [
            'success'  => true,
            'message'  => 'SMS simulated successfully (Simulation Mode).',
            'provider' => 'simulation',
            'details'  => ['phone' => $phone_norm, 'body' => $message]
        ];
    }

    // Provider 1: Arkesel (Ghana v2 API)
    if ($provider === 'arkesel') {
        $apiKey = $config['sms_api_key'];
        $url = 'https://sms.arkesel.com/api/v2/sms/send';
        $payload = json_encode([
            'sender'     => substr($sender, 0, 11),
            'message'    => $message,
            'recipients' => [$phone_norm]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return ['success' => false, 'message' => 'Arkesel cURL Error: ' . $curl_error, 'provider' => 'arkesel'];
        }

        $res_json = json_decode($response, true);
        $status = strtolower($res_json['status'] ?? '');
        $ok = ($http_code === 200 || $http_code === 201) && ($status === 'success' || empty($status));

        return [
            'success'  => $ok,
            'message'  => $ok ? 'SMS delivered to network via Arkesel' : ($res_json['message'] ?? 'Arkesel dispatch failed'),
            'provider' => 'arkesel',
            'details'  => $res_json
        ];
    }

    // Provider 2: Termii
    if ($provider === 'termii') {
        $url = 'https://api.ng.termii.com/api/sms/send';
        $payload = json_encode([
            'to'      => $phone_intl,
            'from'    => $sender,
            'sms'     => $message,
            'type'    => 'plain',
            'channel' => 'generic',
            'api_key' => $config['sms_api_key']
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $res_json = json_decode($response, true);
        $ok = isset($res_json['code']) && $res_json['code'] == 'ok';

        return [
            'success'  => $ok,
            'message'  => $ok ? 'SMS delivered via Termii' : ($res_json['message'] ?? 'Termii dispatch failed'),
            'provider' => 'termii',
            'details'  => $res_json
        ];
    }

    // Provider 3: Hubtel
    if ($provider === 'hubtel') {
        $clientId = $config['sms_api_key'];
        $clientSecret = $config['sms_api_secret'];
        $url = 'https://sms.hubtel.com/v1/messages/send?' . http_build_query([
            'clientsecret' => $clientSecret,
            'clientid'     => $clientId,
            'from'         => $sender,
            'to'           => $phone_intl,
            'content'      => $message
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $res_json = json_decode($response, true);
        $ok = ($http_code >= 200 && $http_code < 300);

        return [
            'success'  => $ok,
            'message'  => $ok ? 'SMS delivered via Hubtel' : 'Hubtel dispatch returned HTTP ' . $http_code,
            'provider' => 'hubtel',
            'details'  => $res_json
        ];
    }

    // Provider 4: Twilio
    if ($provider === 'twilio') {
        $sid = $config['sms_api_key'];
        $token = $config['sms_api_secret'];
        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'To'   => '+' . $phone_intl,
                'From' => $sender,
                'Body' => $message
            ]),
            CURLOPT_USERPWD        => "$sid:$token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $res_json = json_decode($response, true);
        $ok = ($http_code >= 200 && $http_code < 300);

        return [
            'success'  => $ok,
            'message'  => $ok ? 'SMS delivered via Twilio' : ($res_json['message'] ?? 'Twilio dispatch failed'),
            'provider' => 'twilio',
            'details'  => $res_json
        ];
    }

    // Provider 5: Generic HTTP Webhook / Custom Gateway
    if ($provider === 'generic' && !empty($config['sms_api_url'])) {
        $url = str_replace(
            ['{phone}', '{message}', '{sender}'],
            [urlencode($phone_norm), urlencode($message), urlencode($sender)],
            $config['sms_api_url']
        );
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = ($http_code >= 200 && $http_code < 300);
        return [
            'success'  => $ok,
            'message'  => $ok ? 'SMS delivered via Generic API' : 'Generic API returned HTTP ' . $http_code,
            'provider' => 'generic',
            'details'  => $response
        ];
    }

    // Fallback Simulation
    return [
        'success'  => true,
        'message'  => 'SMS processed via simulation fallback.',
        'provider' => 'simulation',
        'details'  => ['phone' => $phone_norm, 'body' => $message]
    ];
}

/**
 * Format and dispatch an OTP verification code via SMS
 * 
 * @param string $phone
 * @param string $otp
 * @param string $recipient_name
 * @return array
 */
function send_otp_sms($phone, $otp, $recipient_name = '') {
    $config = get_sms_config();
    $sender = $config['sms_sender_id'] ?: 'GAFVote';
    $name_prefix = !empty($recipient_name) ? "Hello " . trim($recipient_name) . ", " : "";
    $expiry_min = (int)($config['otp_expiry_minutes'] ?? 10);

    $message = "{$name_prefix}Your {$sender} verification code is: {$otp}. It expires in {$expiry_min} minutes. Do not share this code with anyone.";
    return send_sms($phone, $message);
}
