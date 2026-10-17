<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';
require_once 'includes/sms.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['login']) || isset($_POST['google_auth']))){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Security validation failed. Please try logging in again.';
        safe_redirect('index.php');
    }

    $login_method = trim($_POST['login_method'] ?? 'id_password');
    $role = trim($_POST['role'] ?? 'voter');

    // -------------------------------------------------------------
    // METHOD 1: USER ID AND PASSWORD (OTP sent to phone if user exists)
    // -------------------------------------------------------------
    if($login_method === 'id_password'){
        $identifier = trim($_POST['identifier'] ?? $_POST['voter'] ?? $_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if(empty($identifier) || empty($password)){
            $_SESSION['error'] = 'Please enter both your User ID and password.';
            safe_redirect('index.php?tab=id_password');
        }

        // 1. Check if designated role is admin
        if($role === 'admin'){
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
            log_audit($conn, 'admin', null, 'LOGIN_FAIL', "Failed administrator login attempt for '$identifier'.");
            $_SESSION['error'] = 'Invalid administrator credentials.';
            safe_redirect('index.php?role=admin&tab=id_password');
        }

        // 2. Fallback check for admin in case official used default voter tab
        $aquery = $conn->execute_query("SELECT * FROM admin WHERE username = ? LIMIT 1", [$identifier]);
        if($aquery->num_rows > 0){
            $admin = $aquery->fetch_assoc();
            if(password_verify($password, $admin['password'])){
                session_regenerate_id(true);
                $_SESSION['admin'] = $admin['id'];
                get_csrf_token();
                log_audit($conn, 'admin', $admin['id'], 'LOGIN_SUCCESS', "Admin '{$admin['username']}' logged in via ID tab.");
                safe_redirect('admin/home.php');
            }
        }

        // 3. Authenticate Voter by Voter ID
        $vquery = $conn->execute_query("SELECT * FROM voters WHERE voters_id = ? LIMIT 1", [$identifier]);
        if($vquery->num_rows > 0){
            $voter = $vquery->fetch_assoc();
            $clean_phone = normalize_phone_number($password);
            $voter_norm_phone = normalize_phone_number($voter['phone'] ?? '');

            $valid_pass = password_verify($password, $voter['password']) 
                       || password_verify($clean_phone, $voter['password'])
                       || (!empty($voter_norm_phone) && $voter_norm_phone === $clean_phone)
                       || ($voter['password'] === $password);

            if($valid_pass){
                // Valid credentials: Send OTP to voter phone and route to OTP screen
                initiate_voter_otp($conn, $voter, $voter['phone'] ?? '');
            } else {
                log_audit($conn, 'voter', $voter['id'], 'LOGIN_FAIL', "Invalid password attempt for Voter ID: '$identifier'.");
                $_SESSION['error'] = 'Invalid password for Voter ID: ' . escape_html($identifier) . '.';
                safe_redirect('index.php?tab=id_password');
            }
        } else {
            log_audit($conn, 'voter', null, 'LOGIN_FAIL', "Voter ID '$identifier' not found in database.");
            $_SESSION['error'] = 'Voter ID "' . escape_html($identifier) . '" not found in the voter register.';
            safe_redirect('index.php?tab=id_password');
        }

    // -------------------------------------------------------------
    // METHOD 2: PHONE NUMBER ONLY (OTP sent to phone if it exists in DB)
    // -------------------------------------------------------------
    } elseif($login_method === 'phone_only'){
        $phone_input = trim($_POST['phone_number'] ?? '');
        if(empty($phone_input)){
            $_SESSION['error'] = 'Please enter your registered phone number.';
            safe_redirect('index.php?tab=phone_only');
        }

        $clean_phone = normalize_phone_number($phone_input);
        
        // Search voters for matching phone number
        $vquery = $conn->execute_query(
            "SELECT * FROM voters WHERE phone = ? OR phone = ? OR RIGHT(phone, 9) = RIGHT(?, 9) LIMIT 1",
            [$clean_phone, $phone_input, $clean_phone]
        );

        if($vquery->num_rows > 0){
            $voter = $vquery->fetch_assoc();
            // Phone exists! Dispatch OTP to this phone
            initiate_voter_otp($conn, $voter, $clean_phone);
        } else {
            log_audit($conn, 'voter', null, 'LOGIN_FAIL', "Failed phone-only login attempt for '$clean_phone'. Number not found.");
            $_SESSION['error'] = 'No registered voter found with phone number: ' . escape_html($phone_input) . '. Please check your number.';
            safe_redirect('index.php?tab=phone_only');
        }

    // -------------------------------------------------------------
    // METHOD 3: GOOGLE (Active Logged-In Google Account Verification)
    // -------------------------------------------------------------
    } elseif($login_method === 'google'){
        $google_token = trim($_POST['google_token'] ?? $_POST['credential'] ?? '');

        // SECURITY: A user cannot just type or send another person's email!
        // The email MUST be actively logged in on this device via Google Identity Services.
        if(empty($google_token)){
            log_audit($conn, 'voter', null, 'LOGIN_GOOGLE_FAIL', "Google login rejected: No active Google session token provided.");
            $_SESSION['error'] = 'Access Denied: You must authenticate using an active institutional Google account logged in on this device. Manual email entry is not permitted.';
            safe_redirect('index.php?tab=google');
        }

        $google_config = get_google_config();
        $client_id = $google_config['google_client_id'] ?? '';

        // Cryptographically verify Google ID Token with Google servers
        $token_payload = verify_google_id_token($google_token, $client_id);
        if(!$token_payload || empty($token_payload['email'])){
            log_audit($conn, 'voter', null, 'LOGIN_GOOGLE_FAIL', "Google OAuth token validation failed.");
            $_SESSION['error'] = 'Google authentication failed: Active Google session could not be verified by Google. Please log into your Google account and try again.';
            safe_redirect('index.php?tab=google');
        }

        $verified_email = strtolower(trim($token_payload['email']));
        $clean_email = preg_replace('/\s+/', '', $verified_email);
        $first_10 = substr($clean_email, 0, 10);
        $suffix = substr($clean_email, 10);

        // Institutional email rule: First 10 digits must be numbers, followed strictly by @st.uew.edu.gh or .st.uew.edu.gh
        if(!preg_match('/^\d{10}$/', $first_10) || ($suffix !== '@st.uew.edu.gh' && $suffix !== '.st.uew.edu.gh')){
            log_audit($conn, 'voter', null, 'LOGIN_GOOGLE_FAIL', 
                "Logged-in Google account '$clean_email' rejected: Must be 10-digit Student ID followed by @st.uew.edu.gh.");
            $_SESSION['error'] = 'Access Denied: Your logged-in Google account ("' . escape_html($clean_email) . '") is not an official UEW Student Account. It must end with ".st.uew.edu.gh" preceded by your 10-digit Student ID.';
            safe_redirect('index.php?tab=google');
        }

        // Match first 10 digits against database voters_id
        $vquery = $conn->execute_query(
            "SELECT * FROM voters WHERE voters_id = ? OR SUBSTRING(voters_id, 1, 10) = ? LIMIT 1",
            [$first_10, $first_10]
        );

        if($vquery->num_rows > 0){
            $voter = $vquery->fetch_assoc();
            
            // Authenticated via active Google Workspace login
            session_regenerate_id(true);
            $_SESSION['voter'] = (int)$voter['id'];
            get_csrf_token();

            log_audit($conn, 'voter', $voter['id'], 'LOGIN_GOOGLE_SUCCESS', 
                "Voter '{$voter['voters_id']}' authenticated via active Google Workspace login ('{$clean_email}').");

            $eligible_elections = get_voter_eligible_elections($conn, $voter['id']);
            if(count($eligible_elections) > 1){
                safe_redirect('elections_select.php');
            } elseif(count($eligible_elections) === 1){
                $_SESSION['voter_election_id'] = (int)$eligible_elections[0]['id'];
                safe_redirect('home.php');
            } else {
                safe_redirect('elections_select.php');
            }
        } else {
            log_audit($conn, 'voter', null, 'LOGIN_GOOGLE_FAIL', 
                "Logged-in Google account '$clean_email' has Student ID '$first_10' not found in database.");
            $_SESSION['error'] = 'Access Denied: The Student ID "' . escape_html($first_10) . '" from your logged-in Google account is not in the voter register.';
            safe_redirect('index.php?tab=google');
        }

    } else {
        $_SESSION['error'] = 'Invalid login method requested.';
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