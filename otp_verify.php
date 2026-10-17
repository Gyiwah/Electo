<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';
require_once 'includes/sms.php';

// If already authenticated into voter portal, redirect to ballot
if(isset($_SESSION['voter'])){
    safe_redirect('home.php');
}

// Ensure there is an active pending authentication
if(!isset($_SESSION['pending_voter_auth'])){
    safe_redirect('index.php');
}

$pending = &$_SESSION['pending_voter_auth'];
$sms_config = get_sms_config();
$show_simulation_banner = (!empty($sms_config['otp_simulation_mode']) && $sms_config['otp_simulation_mode'] == '1')
                          || ($sms_config['sms_provider'] === 'simulation');

// Handle Cancel Action
if(isset($_GET['cancel']) && $_GET['cancel'] == '1'){
    unset($_SESSION['pending_voter_auth']);
    safe_redirect('index.php');
}

// Handle Resend Action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend'){
    if(!verify_csrf_token()){
        $_SESSION['otp_error'] = 'Security validation failed. Please try again.';
        safe_redirect('otp_verify.php');
    }

    $time_since_last = time() - (int)($pending['sent_at'] ?? 0);
    if($time_since_last < 20){
        $wait = 20 - $time_since_last;
        $_SESSION['otp_error'] = "Please wait {$wait} seconds before requesting a new code.";
        safe_redirect('otp_verify.php');
    }

    $exp_minutes = (int)($sms_config['otp_expiry_minutes'] ?? 10);
    $new_otp = (string)random_int(100000, 999999);
    $new_expiry = date('Y-m-d H:i:s', time() + ($exp_minutes * 60));

    // Update database
    $conn->execute_query("UPDATE voters SET otp_code = ?, otp_expiry = ? WHERE id = ?", [$new_otp, $new_expiry, $pending['voter_id']]);

    // Update session
    $pending['otp_code'] = $new_otp;
    $pending['expires_at'] = time() + ($exp_minutes * 60);
    $pending['sent_at'] = time();
    $pending['attempts'] = 0;

    // Send SMS
    $sms_res = send_otp_sms($pending['phone'], $new_otp, $pending['firstname']);
    $_SESSION['last_sms_result'] = $sms_res;

    log_audit($conn, 'voter', $pending['voter_id'], 'OTP_RESENT', "New login OTP dispatched to voter '{$pending['voters_id']}'.");
    $_SESSION['otp_success'] = 'A fresh 6-digit verification code has been dispatched to your phone.';
    safe_redirect('otp_verify.php');
}

// Handle OTP Verification
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])){
    if(!verify_csrf_token()){
        $_SESSION['otp_error'] = 'Security validation failed. Please try again.';
        safe_redirect('otp_verify.php');
    }

    $entered_otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if(empty($entered_otp) || strlen($entered_otp) !== 6){
        $_SESSION['otp_error'] = 'Please enter the complete 6-digit verification code.';
        safe_redirect('otp_verify.php');
    }

    // Check attempts limit
    if(($pending['attempts'] ?? 0) >= 5){
        $_SESSION['otp_error'] = 'Too many failed verification attempts. Please click Resend Code to receive a new one.';
        safe_redirect('otp_verify.php');
    }

    // Check expiration
    if(time() > $pending['expires_at']){
        $_SESSION['otp_error'] = 'Your verification code has expired. Please click Resend Code below.';
        safe_redirect('otp_verify.php');
    }

    // Verify against DB and session
    $db_voter_query = $conn->execute_query(
        "SELECT id, voters_id, otp_code, otp_expiry FROM voters WHERE id = ? LIMIT 1",
        [$pending['voter_id']]
    );
    $db_voter = $db_voter_query->fetch_assoc();

    $code_matches = false;
    if($db_voter && !empty($db_voter['otp_code'])){
        $code_matches = hash_equals((string)$db_voter['otp_code'], (string)$entered_otp);
    }
    if(!$code_matches && !empty($pending['otp_code'])){
        $code_matches = hash_equals((string)$pending['otp_code'], (string)$entered_otp);
    }

    if($code_matches){
        // Verification succeeded!
        // Clear OTP from database
        $conn->execute_query("UPDATE voters SET otp_code = NULL, otp_expiry = NULL WHERE id = ?", [$pending['voter_id']]);

        // Elevate session to fully authenticated voter
        session_regenerate_id(true);
        $_SESSION['voter'] = $pending['voter_id'];
        $voter_id_str = $pending['voters_id'];
        $voter_db_id = $pending['voter_id'];

        unset($_SESSION['pending_voter_auth']);
        get_csrf_token(); // Regenerate CSRF for authenticated session

        log_audit($conn, 'voter', $voter_db_id, 'LOGIN_SUCCESS', "Voter '{$voter_id_str}' successfully verified OTP and entered voting portal.");

        $eligible_elections = get_voter_eligible_elections($conn, $voter_db_id);
        if(count($eligible_elections) > 1){
            safe_redirect('elections_select.php');
        } elseif(count($eligible_elections) === 1){
            $_SESSION['voter_election_id'] = (int)$eligible_elections[0]['id'];
            safe_redirect('home.php');
        } else {
            safe_redirect('elections_select.php');
        }
    } else {
        $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
        $remaining = 5 - $pending['attempts'];
        log_audit($conn, 'voter', $pending['voter_id'], 'OTP_FAIL', "Incorrect OTP attempt ({$pending['attempts']}/5) for voter '{$pending['voters_id']}'.");
        
        if($remaining > 0){
            $_SESSION['otp_error'] = "Incorrect verification code. {$remaining} attempts remaining.";
        } else {
            $_SESSION['otp_error'] = 'Too many failed attempts. Please click Resend Code.';
        }
        safe_redirect('otp_verify.php');
    }
}
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="dist/css/login-ui.css">
<body class="hold-transition login-page">
<div class="auth-ambient-orb auth-ambient-orb-1"></div>
<div class="auth-ambient-orb auth-ambient-orb-2"></div>
<div class="login-box" style="width: 460px; max-width: 95%; position: relative; z-index: 1;">
  	<div class="login-logo" style="margin-bottom: 20px; text-align: center;">
  		<img src="favicon/android-chrome-192x192.png" alt="Electo Logo" style="width: 64px; height: 64px; background: #ffffff; padding: 4px; border-radius: 14px; margin: 0 auto 10px auto; display: block; box-shadow: 0 4px 15px rgba(2, 132, 199, 0.25);">
  		<b style="font-size: 26px; color: #0284c7; letter-spacing: -0.5px;">Electo</b>
  	</div>
  
  	<div class="login-box-body" style="padding: 30px 35px; border-radius: 20px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); background: #ffffff; border: 1px solid #f1f5f9;">
        
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 12px; border: 2px solid #bfdbfe;">
                <i class="fa fa-shield"></i>
            </div>
            <h3 style="margin: 0 0 6px 0; font-weight: 800; color: #0f172a; font-size: 22px;">Security Verification</h3>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Welcome, <b><?php echo escape_html($pending['firstname'] . ' ' . $pending['lastname']); ?></b> 
                (<span style="font-family: monospace; color: #2563eb; font-weight: 700;"><?php echo escape_html($pending['voters_id']); ?></span>)
            </p>
            <p style="margin-top: 6px; color: #475569; font-size: 13px;">
                Enter the 6-digit code sent via SMS to <br>
                <b style="font-family: monospace; font-size: 14px; color: #0f172a; background: #f1f5f9; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <?php echo escape_html(get_masked_phone($pending['phone'])); ?>
                </b>
            </p>
        </div>

        <?php if($show_simulation_banner && !empty($pending['otp_code'])): ?>
            <div class="alert alert-info" style="border-radius: 10px; background: #f0f9ff; border: 1px solid #7dd3fc; color: #0369a1; padding: 12px 14px; margin-bottom: 20px; font-size: 13px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <i class="fa fa-mobile-phone" style="font-size: 18px; vertical-align: middle;"></i> 
                        <b>Test Mode / SMS OTP:</b> 
                        <code id="demo_otp_code" style="font-size: 17px; font-weight: 800; letter-spacing: 2px; color: #0369a1; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">
                            <?php echo escape_html($pending['otp_code']); ?>
                        </code>
                    </div>
                    <button type="button" class="btn btn-xs btn-primary" onclick="autoFillOtp('<?php echo escape_html($pending['otp_code']); ?>')" style="font-weight: 700; border-radius: 4px;">
                        <i class="fa fa-paste"></i> Fill Code
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php
            if(isset($_SESSION['otp_error'])){
                echo "
                    <div class='callout callout-danger toast-flash' style='border-radius: 8px; padding: 10px 14px; margin-bottom: 20px; background: #fee2e2; border-color: #ef4444; color: #991b1b; font-size: 13px;'>
                        <p style='margin:0; font-weight: 600;'><i class='fa fa-exclamation-circle'></i> ".escape_html($_SESSION['otp_error'])."</p> 
                    </div>
                ";
                unset($_SESSION['otp_error']);
            }
            if(isset($_SESSION['otp_success'])){
                echo "
                    <div class='callout callout-success toast-flash' style='border-radius: 8px; padding: 10px 14px; margin-bottom: 20px; background: #dcfce7; border-color: #22c55e; color: #166534; font-size: 13px;'>
                        <p style='margin:0; font-weight: 600;'><i class='fa fa-check-circle'></i> ".escape_html($_SESSION['otp_success'])."</p> 
                    </div>
                ";
                unset($_SESSION['otp_success']);
            }
        ?>

    	<form action="otp_verify.php" method="POST" id="otpForm">
			<?php echo csrf_field(); ?>
            <input type="hidden" name="verify_otp" value="1">

      		<div class="form-group" style="margin-bottom: 22px; text-align: center;">
      			<label for="otp" style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">
                    6-DIGIT VERIFICATION CODE
                </label>
        		<input type="text" id="otp" name="otp" class="form-control" 
                       maxlength="6" 
                       inputmode="numeric" 
                       pattern="[0-9]*" 
                       autocomplete="one-time-code" 
                       placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" 
                       required 
                       autofocus 
                       style="font-size: 28px; font-family: 'Inter', monospace; letter-spacing: 12px; text-align: center; height: 56px; border-radius: 10px; border: 2px solid #93c5fd; background: #f8fafc; font-weight: 700; color: #1e3a8a; box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);">
      		</div>

      		<div style="margin-top: 20px;">
          		<button type="submit" class="btn btn-primary btn-block btn-flat" id="verify_btn" style="height: 50px; font-weight: 700; font-size: 16px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; box-shadow: 0 4px 14px rgba(37,99,235,0.35);">
          			<i class="fa fa-unlock-alt"></i> Verify & Access Portal
          		</button>
      		</div>
    	</form>

        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
            <!-- Resend Form -->
            <form action="otp_verify.php" method="POST" style="margin: 0; display: inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="resend">
                <button type="submit" id="resend_btn" class="btn btn-link" style="padding: 0; font-weight: 600; color: #2563eb; text-decoration: none; font-size: 13px;">
                    <i class="fa fa-refresh"></i> <span id="resend_text">Resend Code</span>
                </button>
            </form>

            <a href="otp_verify.php?cancel=1" style="color: #64748b; font-weight: 600; text-decoration: none; font-size: 13px;">
                <i class="fa fa-arrow-left"></i> Cancel / Sign In
            </a>
        </div>
  	</div>

    <div style="text-align: center; margin-top: 20px; font-size: 13px; color: #94a3b8;">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <span style="color: #60a5fa; font-weight: 700;">GAF Tech</span></strong> &bull; All rights reserved
    </div>
</div>

<?php include 'includes/scripts.php' ?>
<script>
function autoFillOtp(code){
    $('#otp').val(code).focus();
}

$(function(){
    // Only allow numbers in OTP field
    $('#otp').on('input', function(){
        this.value = this.value.replace(/[^0-9]/g, '');
        if(this.value.length === 6){
            // Auto submit when 6 digits are complete
            $('#otpForm').submit();
        }
    });

    // 20-second countdown for resend button
    var countdown = 20;
    var resendBtn = $('#resend_btn');
    var resendText = $('#resend_text');
    
    resendBtn.prop('disabled', true).css('opacity', '0.6');
    resendText.text('Resend Code in ' + countdown + 's');

    var timer = setInterval(function(){
        countdown--;
        if(countdown <= 0){
            clearInterval(timer);
            resendBtn.prop('disabled', false).css('opacity', '1');
            resendText.text('Resend Code');
        } else {
            resendText.text('Resend Code in ' + countdown + 's');
        }
    }, 1000);
});
</script>
</body>
</html>
