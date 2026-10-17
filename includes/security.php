<?php
date_default_timezone_set('UTC');

/**
 * Security Helper Functions
 * Provides CSRF protection, input escaping, secure image uploads, session hardening, and safe redirects.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        // Harden session cookie settings before starting
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Generate or retrieve CSRF token for the session
 * @return string
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF input field
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . escape_html(get_csrf_token()) . '">';
}

/**
 * Verify CSRF token from request
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * HTML Escaping shortcut
 * @param mixed $string
 * @return string
 */
function escape_html($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Safe internal redirect with guaranteed script termination
 * @param string $url
 * @param array $allowed_list Optional list of permitted target URLs
 */
function safe_redirect($url, array $allowed_list = []) {
    // If an allow-list is specified, verify target is permitted
    if (!empty($allowed_list)) {
        $target = basename(parse_url($url, PHP_URL_PATH));
        if (!in_array($target, $allowed_list, true)) {
            $url = $allowed_list[0];
        }
    } else {
        // Strip any protocol, CRLF, or host to prevent external open redirect and header injection
        $url = str_replace(["\r", "\n"], '', $url);
        if (preg_match('/^https?:\/\//i', $url) || str_starts_with($url, '//')) {
            $url = 'home.php';
        }
    }
    header('Location: ' . $url);
    exit();
}

/**
 * Secure Image Upload Helper
 * Validates extension, MIME type, image content, and renames with random hex.
 * 
 * @param array $file_array Typically $_FILES['photo']
 * @param string $destination_dir Relative or absolute directory path
 * @param string &$error_out Error message output
 * @param int $max_size Max file size in bytes (default 2MB)
 * @return string|false Generated filename on success, false on failure
 */
function secure_upload_image($file_array, $destination_dir, &$error_out = '', $max_size = 2097152) {
    if (!isset($file_array) || !is_array($file_array)) {
        $error_out = 'No file uploaded.';
        return false;
    }

    if ($file_array['error'] === UPLOAD_ERR_NO_FILE) {
        return ''; // No file selected, optional upload
    }

    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        $error_out = 'File upload failed with error code: ' . $file_array['error'];
        return false;
    }

    if ($file_array['size'] > $max_size) {
        $error_out = 'File size exceeds maximum allowed limit of ' . ($max_size / 1048576) . 'MB.';
        return false;
    }

    $tmp_path = $file_array['tmp_name'];
    if (!is_uploaded_file($tmp_path)) {
        $error_out = 'Invalid upload attempt.';
        return false;
    }

    // Verify extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $original_ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
    if (!in_array($original_ext, $allowed_extensions, true)) {
        $error_out = 'Invalid file extension. Only JPG, PNG, WEBP, and GIF are allowed.';
        return false;
    }

    // Verify MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    $allowed_mimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif']
    ];

    if (!array_key_exists($mime_type, $allowed_mimes) || !in_array($original_ext, $allowed_mimes[$mime_type], true)) {
        $error_out = 'File MIME type mismatch. Only legitimate image files are permitted.';
        return false;
    }

    // Verify image data integrity with getimagesize
    $image_info = @getimagesize($tmp_path);
    if ($image_info === false) {
        $error_out = 'File is not a valid image.';
        return false;
    }

    // Generate random filename
    $destination_dir = rtrim($destination_dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($destination_dir) && !mkdir($destination_dir, 0755, true)) {
        $error_out = 'Unable to create upload directory.';
        return false;
    }

    $new_filename = bin2hex(random_bytes(16)) . '.' . $original_ext;
    $target_filepath = $destination_dir . $new_filename;

    if (!move_uploaded_file($tmp_path, $target_filepath)) {
        $error_out = 'Failed to move uploaded file to destination.';
        return false;
    }

    return $new_filename;
}

/**
 * Retrieve client IP address reliably
 * @return string
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Record an entry into the audit_logs table
 * @param mysqli $conn
 * @param string $user_type 'admin'|'voter'|'system'
 * @param int|null $user_id
 * @param string $action
 * @param string $details
 */
function log_audit($conn, $user_type, $user_id, $action, $details = '') {
    if (!$conn) return;
    $ip = get_client_ip();
    try {
        $conn->execute_query(
            "INSERT INTO audit_logs (user_type, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)",
            [$user_type, $user_id, $action, $details, $ip]
        );
    } catch (Exception $e) {
        error_log('Audit log failure: ' . $e->getMessage());
    }
}

/**
 * Retrieve the selected or active election record
 * Priority: $preferred_id -> session -> latest active -> latest
 * 
 * @param mysqli $conn
 * @param int|null $preferred_id
 * @return array
 */
function get_active_election($conn, $preferred_id = null) {
    if (!$conn) {
        return ['id' => 1, 'title' => 'Election', 'status' => 'active'];
    }

    if ($preferred_id === null) {
        if (!empty($_GET['election_id'])) {
            $preferred_id = (int)$_GET['election_id'];
        } elseif (isset($_SESSION['admin']) && !empty($_SESSION['admin_election_id'])) {
            $preferred_id = (int)$_SESSION['admin_election_id'];
        } elseif (isset($_SESSION['voter']) && !empty($_SESSION['voter_election_id'])) {
            $preferred_id = (int)$_SESSION['voter_election_id'];
        }
    }

    if ($preferred_id > 0) {
        $stmt = $conn->execute_query("SELECT * FROM elections WHERE id = ? LIMIT 1", [$preferred_id]);
        if ($row = $stmt->fetch_assoc()) {
            return $row;
        }
    }

    $res = $conn->query("SELECT * FROM elections WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        return $row;
    }

    $res2 = $conn->query("SELECT * FROM elections ORDER BY id DESC LIMIT 1");
    if ($row2 = $res2->fetch_assoc()) {
        return $row2;
    }

    return ['id' => 1, 'title' => 'General Election', 'status' => 'active'];
}

/**
 * Retrieve all elections
 * @param mysqli $conn
 * @return array
 */
function get_all_elections($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT * FROM elections ORDER BY (status = 'active') DESC, id DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Retrieve all currently active elections
 * @param mysqli $conn
 * @return array
 */
function get_active_elections($conn) {
    if (!$conn) return [];
    $res = $conn->query("SELECT * FROM elections WHERE status = 'active' ORDER BY id DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Determine dynamic real-time schedule status of an election based on start_time and end_time
 * 
 * @param array $election
 * @return array ['state' => string, 'label' => string, 'badge' => string, 'is_open' => bool, 'message' => string]
 */
function get_election_schedule_status($election) {
    $status = $election['status'] ?? 'draft';
    $start = !empty($election['start_time']) ? $election['start_time'] : null;
    $end = !empty($election['end_time']) ? $election['end_time'] : null;
    $scheduled_draft = ($status === 'draft' && $start !== null);

    if ($status === 'draft' && !$scheduled_draft) {
        return [
            'state'   => 'draft',
            'label'   => 'DRAFT',
            'badge'   => 'warning',
            'is_open' => false,
            'message' => 'Election is in draft mode. Voting is disabled.'
        ];
    }

    if ($status === 'closed' || $status === 'archived') {
        return [
            'state'   => 'closed',
            'label'   => 'CLOSED',
            'badge'   => 'danger',
            'is_open' => false,
            'message' => 'Voting has officially ended for this election.'
        ];
    }

    $now = date('Y-m-d H:i:s');
    if ($start && $now < $start) {
        $formatted_start = date('M d, Y \a\t h:i A', strtotime($start));
        return [
            'state'   => 'scheduled',
            'label'   => 'UPCOMING (STARTS ' . date('M d, h:i A', strtotime($start)) . ')',
            'badge'   => 'info',
            'is_open' => false,
            'message' => 'Voting has not started yet. Opens on ' . $formatted_start . '.'
        ];
    }

    if ($end && $now >= $end) {
        $formatted_end = date('M d, Y \a\t h:i A', strtotime($end));
        return [
            'state'   => 'ended',
            'label'   => 'ENDED (CLOSED AT ' . date('M d, h:i A', strtotime($end)) . ')',
            'badge'   => 'danger',
            'is_open' => false,
            'message' => 'Voting time has expired. Closed on ' . $formatted_end . '.'
        ];
    }

    $message = 'Voting is active and accepting ballots.';
    if ($end) {
        $message = 'Voting is open. Closes on ' . date('M d, Y \a\t h:i A', strtotime($end)) . '.';
    }
    if ($scheduled_draft) {
        $message = 'Scheduled voting is open.' . ($end ? ' Closes on ' . date('M d, Y \a\t h:i A', strtotime($end)) . '.' : '');
    }

    return [
        'state'   => 'open',
        'label'   => 'ACTIVE & ACCEPTING VOTES',
        'badge'   => 'success',
        'is_open' => true,
        'message' => $message
    ];
}

/**
 * Retrieve elections that a specific voter is eligible to participate in
 * 
 * @param mysqli $conn
 * @param int $voter_id
 * @return array
 */
function get_voter_eligible_elections($conn, $voter_id) {
    if (!$conn) return [];
    
    // Check if voter has explicit assignments in voter_elections
    $stmt = $conn->execute_query(
        "SELECT e.*, 
                (CASE WHEN vba.id IS NOT NULL THEN 1 ELSE 0 END) AS has_voted,
                vba.voted_at,
                vba.ballot_token
         FROM elections e
         INNER JOIN voter_elections ve ON ve.election_id = e.id AND ve.voter_id = ?
         LEFT JOIN voter_ballot_audit vba ON vba.election_id = e.id AND vba.voter_id = ?
         WHERE e.status = 'active' OR (e.status = 'draft' AND e.start_time IS NOT NULL)
         ORDER BY e.id DESC",
        [$voter_id, $voter_id]
    );

    $elections = $stmt->fetch_all(MYSQLI_ASSOC);

    return $elections;
}

/**
 * Check if a voter is assigned/eligible for a specific election
 * 
 * @param mysqli $conn
 * @param int $voter_id
 * @param int $election_id
 * @return bool
 */
function is_voter_eligible_for_election($conn, $voter_id, $election_id) {
    if (!$conn) return false;
    
    $check = $conn->execute_query("SELECT id FROM voter_elections WHERE voter_id = ? AND election_id = ? LIMIT 1", [$voter_id, $election_id]);
    return ($check->num_rows > 0);
}

/**
 * Retrieve the count of eligible voters registered for a specific election
 * 
 * @param mysqli $conn
 * @param int $election_id
 * @return int
 */
function get_election_eligible_voters_count($conn, $election_id) {
    if (!$conn || $election_id <= 0) return 0;
    $stmt = $conn->execute_query(
        "SELECT COUNT(DISTINCT v.id) as cnt 
         FROM voters v 
         INNER JOIN voter_elections ve ON ve.voter_id = v.id 
         WHERE ve.election_id = ?",
        [$election_id]
    );
    return (int)($stmt->fetch_assoc()['cnt'] ?? 0);
}

/**
 * Retrieve Google OAuth Client Configuration from config.ini
 * @return array
 */
function get_google_config() {
    $ini_path = __DIR__ . '/../admin/config.ini';
    $config = [
        'google_client_id' => '',
    ];
    if (file_exists($ini_path)) {
        $parsed = @parse_ini_file($ini_path, false, INI_SCANNER_RAW);
        if (is_array($parsed) && !empty($parsed['google_client_id'])) {
            $config['google_client_id'] = trim((string)$parsed['google_client_id']);
        }
    }
    return $config;
}

/**
 * Verify Google ID Token (JWT)
 * Ensures the token was genuinely signed by Google for an active session.
 * 
 * @param string $id_token
 * @param string $client_id Optional expected client ID
 * @return array|false Returns decoded token payload on success, false on failure
 */
function verify_google_id_token($id_token, $client_id = '') {
    if (empty($id_token)) {
        return false;
    }

    $parts = explode('.', $id_token);
    if (count($parts) !== 3) {
        return false;
    }

    // Decode payload
    $payload_b64 = strtr($parts[1], '-_', '+/');
    $payload_b64 .= str_repeat('=', (4 - strlen($payload_b64) % 4) % 4);
    $payload_json = base64_decode($payload_b64);
    if (!$payload_json) {
        return false;
    }

    $payload = json_decode($payload_json, true);
    if (!is_array($payload)) {
        return false;
    }

    // Verify basic standard claims
    $valid_issuers = ['accounts.google.com', 'https://accounts.google.com'];
    if (!isset($payload['iss']) || !in_array($payload['iss'], $valid_issuers, true)) {
        return false;
    }

    // Check expiration (with 300 second clock-skew tolerance)
    if (isset($payload['exp']) && ($payload['exp'] + 300) < time()) {
        return false;
    }

    // Check that email is present and marked verified by Google
    if (empty($payload['email'])) {
        return false;
    }
    if (!isset($payload['email_verified']) || ($payload['email_verified'] !== true && $payload['email_verified'] !== 'true' && $payload['email_verified'] !== 1)) {
        return false;
    }

    // If client ID is specified, verify audience
    if (!empty($client_id) && isset($payload['aud']) && $payload['aud'] !== $client_id) {
        return false;
    }

    // If cURL is available, cryptographically verify signature with Google's public tokeninfo endpoint
    if (function_exists('curl_init')) {
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $resp = curl_exec($ch);
        $curl_err = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$curl_err) {
            if ($http_code === 200 && $resp) {
                $online_info = json_decode($resp, true);
                if (is_array($online_info) && !empty($online_info['email'])) {
                    $is_verified = !empty($online_info['email_verified']) && 
                        ($online_info['email_verified'] === true || $online_info['email_verified'] === 'true' || $online_info['email_verified'] === 1);
                    if ($is_verified) {
                        return $online_info;
                    }
                }
            }
            // Google reached and rejected token or unverified email
            return false;
        }
    }

    return $payload;
}
