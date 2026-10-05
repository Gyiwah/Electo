<?php
require_once 'includes/security.php';

if(isset($_SESSION['admin'])){
    safe_redirect('admin/home.php');
}

if(isset($_SESSION['voter'])){
    safe_redirect('home.php');
}

$initial_role = (isset($_GET['role']) && strtolower($_GET['role']) === 'admin') ? 'admin' : 'voter';
?>
<?php include 'includes/header.php'; ?>
<body class="hold-transition login-page">
<div class="login-box" style="width: 460px; max-width: 94%;">
  	<div class="login-logo" style="margin-bottom: 20px;">
      	<img src="favicon/android-chrome-192x192.png" alt="Electo Logo" style="width: 72px; height: 72px; background: #ffffff; padding: 4px; border-radius: 14px; margin: 0 auto 10px auto; display: block; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
      	<b style="font-size: 28px; color: #ffffff;">Electo</b>
  	</div>
  
  	<div class="login-box-body" style="padding: 30px 35px;">
        <!-- Role Switcher Segmented Control -->
        <div class="role-switcher-container" style="background: #f1f5f9; padding: 4px; border-radius: 12px; display: flex; margin-bottom: 25px; border: 1px solid #e2e8f0;">
            <button type="button" class="role-tab-btn <?php echo ($initial_role === 'voter') ? 'active' : ''; ?>" id="tab_voter" data-role="voter" style="flex: 1; border: none; padding: 10px 14px; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s ease;">
                <i class="fa fa-check-square-o"></i> Voter Sign-In
            </button>
            <button type="button" class="role-tab-btn <?php echo ($initial_role === 'admin') ? 'active' : ''; ?>" id="tab_admin" data-role="admin" style="flex: 1; border: none; padding: 10px 14px; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s ease;">
                <i class="fa fa-lock"></i> Official / Admin
            </button>
        </div>

    	<form action="login.php" method="POST" id="unifiedLoginForm">
			<?php echo csrf_field(); ?>
            <input type="hidden" name="role" id="login_role" value="<?php echo $initial_role; ?>">

      		<div class="form-group has-feedback" style="margin-bottom: 18px;">
      			<label for="identifier" id="identifier_label" style="font-size: 13px; font-weight: 600; color: #334155;">
                    <?php echo ($initial_role === 'admin') ? 'Admin Username' : 'Voter ID'; ?>
                </label>
        		<input type="text" id="identifier" class="form-control" name="identifier" 
                       placeholder="<?php echo ($initial_role === 'admin') ? 'Enter administrative username' : 'Enter your official Voter ID'; ?>" 
                       required autocomplete="username">
        		<span class="fa <?php echo ($initial_role === 'admin') ? 'fa-user-secret' : 'fa-id-card'; ?> form-control-feedback" id="identifier_icon" style="top: 25px;"></span>
      		</div>

			<div class="form-group has-feedback" style="margin-bottom: 18px;">
				<label for="password" id="password_label" style="font-size: 13px; font-weight: 600; color: #334155;">
                    <?php echo ($initial_role === 'admin') ? 'Access Password' : 'Phone Number (Password)'; ?>
                </label>
                <div style="position: relative;">
				    <input type="password" id="password" class="form-control" name="password" 
                           placeholder="<?php echo ($initial_role === 'admin') ? 'Enter your secret password' : 'Enter your registered phone number'; ?>" 
                           required autocomplete="current-password" style="padding-right: 40px;">
				    <span class="fa fa-lock form-control-feedback" id="password_icon" style="top: 0; right: 35px;"></span>
                    <button type="button" id="toggle_password" style="position: absolute; right: 10px; top: 12px; background: none; border: none; color: #94a3b8; cursor: pointer;">
                        <i class="fa fa-eye" id="toggle_eye"></i>
                    </button>
                </div>
                <div id="otp_flow_notice" style="margin-top: 6px; font-size: 12px; color: #64748b; <?php echo ($initial_role === 'admin') ? 'display: none;' : ''; ?>">
                    <i class="fa fa-shield text-primary"></i> An SMS verification code (OTP) will be sent to your phone.
                </div>
			</div>

      		<div style="margin-top: 22px;">
          		<button type="submit" class="btn btn-primary btn-block btn-flat" name="login" id="submit_btn" style="height: 48px; font-weight: 700; font-size: 15px; border-radius: 8px;">
          			<i class="fa <?php echo ($initial_role === 'admin') ? 'fa-sign-in' : 'fa-paper-plane-o'; ?>" id="submit_icon"></i> 
                    <span id="submit_text"><?php echo ($initial_role === 'admin') ? 'Sign In to Election Command' : 'Authenticate & Send OTP'; ?></span>
          		</button>
      		</div>
    	</form>

        <div style="margin-top: 25px; padding-top: 18px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
            <a href="projector.php" target="_blank" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                <i class="fa fa-television"></i> Live Projector Screen
            </a>
            <span class="text-muted" style="font-size: 12px;">
                <i class="fa fa-shield text-success"></i> Secret Ballot
            </span>
        </div>
  	</div>

  	<?php
  		if(isset($_SESSION['error'])){
  			echo "
                <div class='callout callout-danger toast-flash text-center mt20' style='border-radius: 8px; box-shadow: 0 4px 15px rgba(239,68,68,0.25); background: #fee2e2; border-color: #ef4444; color: #991b1b;'>
			  		<p style='margin:0; font-weight: 600;'><i class='fa fa-exclamation-circle'></i> ".escape_html($_SESSION['error'])."</p> 
			  	</div>
  			";
  			unset($_SESSION['error']);
  		}
  	?>
</div>

<div style="text-align: center; margin-bottom: 25px; font-size: 13px; color: #94a3b8;">
    <strong>Copyright &copy; <?php echo date('Y'); ?> <span style="color: #60a5fa; font-weight: 700;">GAF Tech</span></strong> &bull; All rights reserved
</div>

<style>
.role-tab-btn {
    background: transparent;
    color: #64748b;
}
.role-tab-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
.role-tab-btn[data-role="admin"].active {
    color: #1e3a8a;
}
.role-tab-btn[data-role="voter"].active {
    color: #2563eb;
}
</style>

<?php include 'includes/scripts.php' ?>
<script>
$(function(){
    // Role switcher handler
    $('.role-tab-btn').on('click', function(e){
        e.preventDefault();
        var role = $(this).data('role');
        setRole(role);
    });

    function setRole(role){
        $('.role-tab-btn').removeClass('active');
        if(role === 'admin'){
            $('#tab_admin').addClass('active');
            $('#login_role').val('admin');
            $('#identifier_label').text('Admin Username');
            $('#identifier').attr('placeholder', 'Enter administrative username');
            $('#identifier_icon').removeClass('fa-id-card').addClass('fa-user-secret');
            $('#password_label').text('Access Password');
            $('#password').attr('placeholder', 'Enter your secret password');
            $('#otp_flow_notice').hide();
            $('#submit_icon').removeClass('fa-paper-plane-o').addClass('fa-sign-in');
            $('#submit_text').text('Sign In to Election Command');
            if (window.history.replaceState) {
                window.history.replaceState(null, null, '?role=admin');
            }
        } else {
            $('#tab_voter').addClass('active');
            $('#login_role').val('voter');
            $('#identifier_label').text('Voter ID');
            $('#identifier').attr('placeholder', 'Enter your official Voter ID');
            $('#identifier_icon').removeClass('fa-user-secret').addClass('fa-id-card');
            $('#password_label').text('Phone Number (Password)');
            $('#password').attr('placeholder', 'Enter your registered phone number');
            $('#otp_flow_notice').show();
            $('#submit_icon').removeClass('fa-sign-in').addClass('fa-paper-plane-o');
            $('#submit_text').text('Authenticate & Send OTP');
            if (window.history.replaceState) {
                window.history.replaceState(null, null, 'index.php');
            }
        }
    }

    // Toggle password visibility
    $('#toggle_password').on('click', function(){
        var passInput = $('#password');
        var eyeIcon = $('#toggle_eye');
        if(passInput.attr('type') === 'password'){
            passInput.attr('type', 'text');
            eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passInput.attr('type', 'password');
            eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>
</body>
</html>