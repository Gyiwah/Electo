<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';
require_once 'includes/sms.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Security validation failed. Please try logging in again.';
        safe_redirect('index.php');
    }

    $identifier = trim($_POST['identifier'] ?? $_POST['voter'] ?? $_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'voter');

    if(empty($identifier) || empty($password)){
        $_SESSION['error'] = ($role === 'admin') 
            ? 'Please enter both your admin username and password.' 
            : 'Please enter both your Voter ID and phone number (password).';
        safe_redirect($role === 'admin' ? 'index.php?role=admin' : 'index.php');
    }

    $clean_phone = normalize_phone_number($password);

    // Role-specific primary authentication
    if($role === 'admin'){
        // 1. Authenticate Admin
        $query = $conn->execute_query("SELECT * FROM admin WHERE username = ? LIMIT 1", [$identifier]);
        if($query->num_rows > 0){
            $admin = $query->fetch_assoc();
            if(password_verify($password, $admin['password'])){
                session_regenerate_id(true);
                $_SESSION['admin'] = $admin['id'];
                get_csrf_token();
                log_audit($conn, 'admin', $admin['id'], 'LOGIN_SUCCESS', "Admin '{$admin['username']}' logged in successfully.");
                safe_redirect('admin/home.php');
            }
        }

        // Smart Fallback: Check if it's actually a voter credential
        $vquery = $conn->execute_query("SELECT * FROM voters WHERE voters_id = ? LIMIT 1", [$identifier]);
        if($vquery->num_rows > 0){
            $voter = $vquery->fetch_assoc();
            $voter_norm_phone = normalize_phone_number($voter['phone'] ?? '');
            
            $valid_pass = password_verify($password, $voter['password']) 
                       || password_verify($clean_phone, $voter['password'])
                       || (!empty($voter_norm_phone) && $voter_norm_phone === $clean_phone);

            if($valid_pass){
                // Route to OTP verification
                initiate_voter_otp($conn, $voter, $clean_phone);
            }
        }

        log_audit($conn, 'admin', null, 'LOGIN_FAIL', "Failed administrator login attempt for '$identifier'.");
        $_SESSION['error'] = 'Invalid administrator credentials.';
        safe_redirect('index.php?role=admin');

    } else {
        // 1. Authenticate Voter (Voter ID + Phone as Password)
        $vquery = $conn->execute_query("SELECT * FROM voters WHERE voters_id = ? LIMIT 1", [$identifier]);
        if($vquery->num_rows > 0){
            $voter = $vquery->fetch_assoc();
            $voter_norm_phone = normalize_phone_number($voter['phone'] ?? '');

            $valid_pass = password_verify($password, $voter['password']) 
                       || password_verify($clean_phone, $voter['password'])
                       || (!empty($voter_norm_phone) && $voter_norm_phone === $clean_phone);

            if($valid_pass){
                // Valid credentials! Dispatch OTP and redirect to OTP verification screen
                initiate_voter_otp($conn, $voter, $clean_phone);
            }
        }

        // Smart Fallback: Check if an administrator used the voter tab
        $aquery = $conn->execute_query("SELECT * FROM admin WHERE username = ? LIMIT 1", [$identifier]);
        if($aquery->num_rows > 0){
            $admin = $aquery->fetch_assoc();
            if(password_verify($password, $admin['password'])){
                session_regenerate_id(true);
                $_SESSION['admin'] = $admin['id'];
                get_csrf_token();
                log_audit($conn, 'admin', $admin['id'], 'LOGIN_SUCCESS', "Admin '{$admin['username']}' logged in via unified portal.");
                safe_redirect('admin/home.php');
            }
        }

        log_audit($conn, 'voter', null, 'LOGIN_FAIL', "Failed voter login attempt for ID: '$identifier'.");
        $_SESSION['error'] = 'Invalid Voter ID or phone number.';
        safe_redirect('index.php');
    }
} else {
    $_SESSION['error'] = 'Please enter your login credentials.';
    safe_redirect('index.php');
}

/**
 * Generate and dispatch 6-digit OTP to voter phone and redirect to verification screen
 */
function initiate_voter_otp($conn, $voter, $fallback_phone = '') {
    $phone = !empty($voter['phone']) ? $voter['phone'] : $fallback_phone;
    $phone = normalize_phone_number($phone);

    // If voter record was missing phone, store it now
    if(empty($voter['phone']) && !empty($phone)){
        $conn->execute_query("UPDATE voters SET phone = ? WHERE id = ?", [$phone, $voter['id']]);
        $voter['phone'] = $phone;
    }

    $sms_config = get_sms_config();
    $exp_minutes = (int)($sms_config['otp_expiry_minutes'] ?? 10);
    $otp = (string)random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', time() + ($exp_minutes * 60));

    // Save OTP to database
    $conn->execute_query("UPDATE voters SET otp_code = ?, otp_expiry = ? WHERE id = ?", [$otp, $expiry, $voter['id']]);

    // Save pending authentication session (does not grant portal access yet!)
    $_SESSION['pending_voter_auth'] = [
        'voter_id'   => (int)$voter['id'],
        'voters_id'  => $voter['voters_id'],
        'firstname'  => $voter['firstname'],
        'lastname'   => $voter['lastname'],
        'phone'      => $phone,
        'expires_at' => time() + ($exp_minutes * 60),
        'otp_code'   => $otp,
        'attempts'   => 0,
        'sent_at'    => time()
    ];

    // Dispatch SMS
    $sms_res = send_otp_sms($phone, $otp, $voter['firstname']);
    $_SESSION['last_sms_result'] = $sms_res;

    log_audit($conn, 'voter', $voter['id'], 'OTP_SENT', "Login OTP dispatched to voter '{$voter['voters_id']}' at {$phone}.");

    safe_redirect('otp_verify.php');
}
?>