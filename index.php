<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';

if(isset($_SESSION['admin'])){
    safe_redirect('admin/home.php');
}

if(isset($_SESSION['voter'])){
    safe_redirect('home.php');
}

$initial_role = (isset($_GET['role']) && strtolower($_GET['role']) === 'admin') ? 'admin' : 'voter';
$initial_tab = $_GET['tab'] ?? ($initial_role === 'admin' ? 'id_password' : 'id_password');
if(!in_array($initial_tab, ['id_password', 'phone_only', 'google'])){
    $initial_tab = 'id_password';
}

$google_config = get_google_config();
$google_client_id = $google_config['google_client_id'] ?? '';
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="dist/css/login-ui.css">
<script src="https://accounts.google.com/gsi/client" async defer></script>

<body class="hold-transition login-page">

<!-- Ambient glowing light orbs -->
<div class="auth-ambient-orb auth-ambient-orb-1"></div>
<div class="auth-ambient-orb auth-ambient-orb-2"></div>

<div class="auth-viewport">

    <!-- Outer Floating Glassmorphic Canvas Stage -->
    <div class="auth-canvas">

        <!-- Outer Carousel Navigation Chevrons (< and >) -->
        <button type="button" class="canvas-nav-arrow arrow-left" id="prevHeroSlide" title="Previous highlight">
            <i class="fa fa-chevron-left"></i>
        </button>
        <button type="button" class="canvas-nav-arrow arrow-right" id="nextHeroSlide" title="Next highlight">
            <i class="fa fa-chevron-right"></i>
        </button>

        <div class="auth-grid">

            <!-- =========================================================
                 LEFT COLUMN: Floating White Sign-In Card
                 ========================================================= -->
            <div class="auth-card-container">
                <div class="auth-card">

                    <!-- Top Segmented Header Tabs (matching reference UI) -->
                    <div class="auth-tabs-header">
                        <button type="button" class="auth-tab-btn <?php echo ($initial_tab === 'id_password') ? 'active' : ''; ?>" data-tab="id_password" id="tab_btn_id_pass">
                            <i class="fa fa-id-card-o"></i> User ID
                        </button>
                        <button type="button" class="auth-tab-btn <?php echo ($initial_tab === 'phone_only') ? 'active' : ''; ?>" data-tab="phone_only" id="tab_btn_phone">
                            <i class="fa fa-phone"></i> Phone Number
                        </button>
                        <button type="button" class="auth-tab-btn <?php echo ($initial_tab === 'google') ? 'active' : ''; ?>" data-tab="google" id="tab_btn_google">
                            <i class="fa fa-google"></i> Google
                        </button>
                    </div>

                    <!-- Card Body -->
                    <div class="auth-card-body">

                        <!-- Brand Crest & Header -->
                        <div class="card-brand-header">
                            <div class="brand-crest">
                                <img src="favicon/android-chrome-192x192.png" alt="Electo Logo">
                            </div>
                            <div class="brand-title-wrap">
                                <span class="brand-name">ELECTO</span>
                                <span class="brand-tagline">Certified Voting Suite</span>
                            </div>
                            <div style="margin-left: auto;">
                                <span class="label <?php echo ($initial_role === 'admin') ? 'label-warning' : 'label-info'; ?>" id="role_status_badge" style="border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                    <?php echo ($initial_role === 'admin') ? 'Official / Admin' : 'Voter Access'; ?>
                                </span>
                            </div>
                        </div>

                        <h2 class="auth-title" id="auth_main_heading">Sign In</h2>

                        <!-- Display Error Flash Notice if present -->
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger" style="border-radius: 12px; font-size: 12.5px; padding: 10px 14px; margin-bottom: 16px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; display: flex; align-items: flex-start; gap: 8px;">
                                <i class="fa fa-exclamation-circle" style="margin-top: 2px;"></i>
                                <div style="flex: 1;"><?php echo escape_html($_SESSION['error']); ?></div>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <!-- Unified Authentication Form -->
                        <form action="login.php" method="POST" id="unifiedLoginForm">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="login_method" id="login_method" value="<?php echo escape_html($initial_tab); ?>">
                            <input type="hidden" name="role" id="login_role" value="<?php echo escape_html($initial_role); ?>">

                            <!-- -------------------------------------------------------------
                                 TAB PANE 1: User ID and Password
                                 ------------------------------------------------------------- -->
                            <div class="tab-pane-content" id="pane_id_password" style="<?php echo ($initial_tab === 'id_password') ? '' : 'display: none;'; ?>">
                                <div class="input-field-group">
                                    <label for="identifier" id="identifier_label">
                                        <?php echo ($initial_role === 'admin') ? 'Admin Username' : 'Login / Voter ID'; ?>
                                    </label>
                                    <div class="field-with-icon">
                                        <span class="field-icon-badge" id="identifier_icon">
                                            <i class="fa <?php echo ($initial_role === 'admin') ? 'fa-user-secret' : 'fa-id-card'; ?>"></i>
                                        </span>
                                        <input type="text" id="identifier" name="identifier" class="modern-input" 
                                               placeholder="<?php echo ($initial_role === 'admin') ? 'Enter administrative username' : 'e.g. 5251570429 or Username'; ?>" 
                                               autocomplete="username">
                                    </div>
                                </div>

                                <div class="input-field-group">
                                    <label for="password" id="password_label">
                                        <?php echo ($initial_role === 'admin') ? 'Access Password' : 'Password'; ?>
                                    </label>
                                    <div class="field-with-icon">
                                        <span class="field-icon-badge">
                                            <i class="fa fa-lock"></i>
                                        </span>
                                        <input type="password" id="password" name="password" class="modern-input" 
                                               placeholder="<?php echo ($initial_role === 'admin') ? 'Enter your secret password' : 'Enter your registered password'; ?>" 
                                               autocomplete="current-password">
                                        <button type="button" class="field-toggle-btn" id="toggle_password" title="Show/Hide password">
                                            <i class="fa fa-eye" id="toggle_eye"></i>
                                        </button>
                                    </div>
                                    <div class="field-helper-note" id="id_pass_helper_note" style="<?php echo ($initial_role === 'admin') ? 'display: none;' : ''; ?>">
                                        <i class="fa fa-shield text-info"></i> A 6-digit OTP will be dispatched to your registered phone number upon credential verification.
                                    </div>
                                </div>
                            </div>

                            <!-- -------------------------------------------------------------
                                 TAB PANE 2: Phone Number Only
                                 ------------------------------------------------------------- -->
                            <div class="tab-pane-content" id="pane_phone_only" style="<?php echo ($initial_tab === 'phone_only') ? '' : 'display: none;'; ?>">
                                <div class="input-field-group">
                                    <label for="phone_number">Registered Phone Number</label>
                                    <div class="field-with-icon">
                                        <span class="field-icon-badge">
                                            <i class="fa fa-phone"></i>
                                        </span>
                                        <input type="tel" id="phone_number" name="phone_number" class="modern-input" 
                                               placeholder="e.g. 0556045462 or 0539150181" autocomplete="tel">
                                    </div>
                                </div>
                            </div>

                            <!-- -------------------------------------------------------------
                                 TAB PANE 3: Google (Active Logged-In Account Verification)
                                 ------------------------------------------------------------- -->
                            <div class="tab-pane-content" id="pane_google" style="<?php echo ($initial_tab === 'google') ? '' : 'display: none;'; ?>">
                                <div class="google-auth-box">
                                    <p class="google-auth-intro">
                                        Sign in with your verified institutional Google account (<b>@st.uew.edu.gh</b>)
                                    </p>

                                    <!-- Hidden token field for cryptographically verified Google JWT -->
                                    <input type="hidden" name="google_token" id="google_token" value="">

                                    <!-- Google Sign-In Button Container -->
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 18px 0 14px;">
                                        <div id="gsi_button_target"></div>
                                        
                                        <!-- Interactive Branded Google Button -->
                                        <button type="button" class="btn-google-sso" id="btnTriggerGoogleSSO" style="width: 100%; max-width: 320px;">
                                            <svg viewBox="0 0 48 48" width="22" height="22">
                                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                            </svg>
                                            <span id="google_btn_label">Sign in with Google Account</span>
                                        </button>
                                    </div>

                                    <?php if(empty($google_client_id)): ?>
                                        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 11px 13px; font-size: 11.5px; color: #92400e; margin-bottom: 12px; text-align: left; line-height: 1.45;">
                                            <i class="fa fa-info-circle" style="color: #d97706; margin-right: 4px;"></i>
                                            <strong>Admin Setup:</strong> Google Client ID is not yet configured. Set it in the <a href="javascript:void(0)" onclick="$('#toggleAdminBtn').click()" style="color: #0284c7; font-weight: 700; text-decoration: underline;">Admin Panel</a> to enable Google Single Sign-On. Voters can also vote using <strong>Voter ID & Password</strong> or <strong>Phone Number</strong>.
                                        </div>
                                    <?php endif; ?>

                                    <div class="google-security-notice" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; font-size: 11.5px; color: #166534; line-height: 1.5; text-align: left;">
                                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                                            <i class="fa fa-shield" style="font-size: 15px; margin-top: 1px; color: #16a34a;"></i>
                                            <div>
                                                <strong>Active Session Required:</strong><br>
                                                You must be actively logged into your official UEW Google account on this device. Manual email entry is disabled to prevent unauthorized account access.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- -------------------------------------------------------------
                                 Simulated reCAPTCHA Widget (Exact match to reference UI)
                                 ------------------------------------------------------------- -->
                            <div class="recaptcha-widget" id="recaptchaWidget">
                                <label class="recaptcha-inner" for="recaptcha_check">
                                    <div class="recaptcha-checkbox-wrapper">
                                        <input type="checkbox" id="recaptcha_check" name="recaptcha_check" checked required>
                                        <span class="recaptcha-box-indicator">
                                            <i class="fa fa-check check-mark"></i>
                                        </span>
                                    </div>
                                    <span class="recaptcha-text">I'm not a robot</span>
                                </label>
                                <div class="recaptcha-badge">
                                    <svg class="recaptcha-logo" viewBox="0 0 24 24" width="26" height="26">
                                        <path fill="#1a73e8" d="M12 2A10 10 0 0 0 2 12c0 3.31 1.61 6.24 4.1 8.04l1.45-1.45A7.95 7.95 0 0 1 4 12a8 8 0 1 1 8 8c-1.39 0-2.69-.36-3.82-.99l-1.5 1.5A9.94 9.94 0 0 0 12 22a10 10 0 0 0 10-10A10 10 0 0 0 12 2z"/>
                                        <path fill="#4285f4" d="M12 6a6 6 0 1 0 6 6h-2a4 4 0 1 1-4-4V6z"/>
                                    </svg>
                                    <span class="recaptcha-terms">reCAPTCHA</span>
                                </div>
                            </div>

                            <!-- Terms of Service Agreement Checkbox -->
                            <div class="terms-agreement-row">
                                <label class="terms-label" for="terms_agreed">
                                    <input type="checkbox" id="terms_agreed" name="terms_agreed" value="1" checked required>
                                    <span class="terms-custom-check"><i class="fa fa-check"></i></span>
                                    <span class="terms-text">I agree to Electo <a href="#termsModal" data-toggle="modal">Terms of Use & Voting Charter</a></span>
                                </label>
                            </div>

                            <!-- Vibrant Gradient Submit Button (pill shaped matching reference) -->
                            <button type="submit" name="login" id="main_submit_btn" class="btn-gradient-auth">
                                <span id="btn_spinner" style="display:none;"><i class="fa fa-circle-o-notch fa-spin"></i> </span>
                                <span id="btn_text">
                                    <?php 
                                        if($initial_tab === 'google'){
                                            echo 'Sign In with Google';
                                        } elseif($initial_tab === 'phone_only'){
                                            echo 'Send Verification OTP';
                                        } elseif($initial_role === 'admin'){
                                            echo 'Sign In to Command Center';
                                        } else {
                                            echo 'Sign In';
                                        }
                                    ?>
                                </span>
                            </button>
                        </form>

                        <!-- Card Bottom Bar -->
                        <div class="auth-card-footer">
                            <a href="projector.php" target="_blank" class="footer-link">
                                <i class="fa fa-television"></i> Live Projector
                            </a>
                            <a href="javascript:void(0)" id="toggleAdminBtn" class="footer-link">
                                <i class="fa fa-lock"></i> 
                                <span id="admin_toggle_text"><?php echo ($initial_role === 'admin') ? 'Switch to Voter' : 'Official / Admin'; ?></span>
                            </a>
                        </div>

                    </div>
                </div>
            </div>

            <!-- =========================================================
                 RIGHT COLUMN: Hero Showcase & Isometric Illustration
                 ========================================================= -->
            <div class="auth-hero-container">



                <!-- Hero Headline -->
                <h1 class="hero-headline" id="heroHeadline">
                    Welcome to the Electo <br>
                    <span class="hero-headline-accent">digital voting platform</span>
                </h1>

                <p class="hero-tagline" id="heroTagline">
                    The certified electronic voting suite with multi-factor authentication, live transparent audits, and zero-knowledge privacy.
                </p>

                <!-- Isometric Hero Artwork Scene -->
                <div class="hero-scene-wrap">
                    <img src="dist/img/login_hero.jpg" alt="Electo Digital Voting Platform" class="hero-isometric-image">
                </div>

                <!-- Feature Chips below scene -->
                <div class="hero-features-row">
                    <div class="feature-pill"><i class="fa fa-shield text-success"></i> 256-Bit Encrypted</div>
                    <div class="feature-pill"><i class="fa fa-bolt text-warning"></i> Certified Live Tallies</div>
                    <div class="feature-pill"><i class="fa fa-check-circle text-info"></i> Secret Ballot Charter</div>
                </div>

            </div>

        </div>

    </div>

    <!-- System Footer Copyright -->
    <div style="text-align: center; margin-top: 20px; font-size: 12.5px; color: #64748b; font-weight: 500;">
        <strong>Electo Voting System &copy; <?php echo date('Y'); ?> <span style="color: #0284c7; font-weight: 700;">GAF Tech</span></strong> &bull; All Rights Reserved
    </div>

</div>

<!-- =========================================================
     MODAL: Terms of Use & Voting Charter
     ========================================================= -->
<div class="modal fade" id="termsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 520px;">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #0284c7; color: #ffffff; border-radius: 16px 16px 0 0; padding: 18px 24px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800;"><i class="fa fa-shield"></i> Electo Terms & Voting Charter</h4>
            </div>
            <div class="modal-body" style="padding: 24px; font-size: 13.5px; line-height: 1.6; color: #334155;">
                <h5 style="font-weight: 700; color: #0f172a; margin-top: 0;">1. Secret Ballot Guarantee</h5>
                <p>Your vote choices are cryptographically decoupled from your personal identity upon submission. No administrator, election official, or observer can correlate your ballot selections back to your Voter ID.</p>

                <h5 style="font-weight: 700; color: #0f172a;">2. One Voter, One Ballot</h5>
                <p>Authentication credentials (Voter ID, registered phone number OTP, or verified Google Institutional Account) permit access strictly to authorized elections. Once a ballot is cast, participation is recorded in a tamper-evident audit ledger.</p>

                <h5 style="font-weight: 700; color: #0f172a;">3. Certified Transparency</h5>
                <p>All election results and certified turnout metrics are calculated exclusively against assigned, eligible voters.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 14px 24px;">
                <button type="button" class="btn btn-primary btn-flat" data-dismiss="modal" style="border-radius: 8px; font-weight: 700; padding: 8px 20px;">I Understand & Agree</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>

<script>
$(function(){
    var currentTab = '<?php echo $initial_tab; ?>';
    var currentRole = '<?php echo $initial_role; ?>';

    // Slide highlights array for the (< and >) canvas arrows
    var heroSlides = [
        {
            headline: 'Welcome to the Electo <br><span class="hero-headline-accent">digital voting platform</span>',
            tagline: 'The certified electronic voting suite with multi-factor authentication, live transparent audits, and zero-knowledge privacy.'
        },
        {
            headline: 'Certified Turnout <br><span class="hero-headline-accent">audited in real-time</span>',
            tagline: 'Live projector screen broadcasts verified participation and turnout computed directly from assigned eligible voters.'
        },
        {
            headline: 'Cryptographic <br><span class="hero-headline-accent">secret ballot integrity</span>',
            tagline: 'Multi-factor SMS OTP authentication and Google Institutional SSO ensure tamper-proof, one-voter-one-ballot protection.'
        }
    ];
    var currentSlideIdx = 0;

    $('#nextHeroSlide').on('click', function(){
        currentSlideIdx = (currentSlideIdx + 1) % heroSlides.length;
        applyHeroSlide(currentSlideIdx);
    });

    $('#prevHeroSlide').on('click', function(){
        currentSlideIdx = (currentSlideIdx - 1 + heroSlides.length) % heroSlides.length;
        applyHeroSlide(currentSlideIdx);
    });

    function applyHeroSlide(idx){
        $('#heroHeadline').html(heroSlides[idx].headline);
        $('#heroTagline').text(heroSlides[idx].tagline);
    }

    // Tab Switcher Click Handler
    $('.auth-tab-btn').on('click', function(e){
        e.preventDefault();
        var tab = $(this).data('tab');
        switchTab(tab);
    });

    function switchTab(tab){
        currentTab = tab;
        $('.auth-tab-btn').removeClass('active');
        $('[data-tab="' + tab + '"]').addClass('active');
        $('#login_method').val(tab);

        // Hide all panes
        $('.tab-pane-content').hide();

        if(tab === 'id_password'){
            $('#pane_id_password').fadeIn(200);
            $('#identifier').focus();
            updateSubmitButtonText();
            if(window.history.replaceState){
                window.history.replaceState(null, null, currentRole === 'admin' ? '?role=admin&tab=id_password' : 'index.php?tab=id_password');
            }
        } else if(tab === 'phone_only'){
            $('#pane_phone_only').fadeIn(200);
            $('#phone_number').focus();
            $('#btn_text').text('Send Verification OTP');
            if(window.history.replaceState){
                window.history.replaceState(null, null, 'index.php?tab=phone_only');
            }
        } else if(tab === 'google'){
            $('#pane_google').fadeIn(200);
            $('#google_identity').focus();
            $('#btn_text').text('Sign In with Google');
            if(window.history.replaceState){
                window.history.replaceState(null, null, 'index.php?tab=google');
            }
        }
    }

    function updateSubmitButtonText(){
        if(currentTab === 'id_password'){
            if(currentRole === 'admin'){
                $('#btn_text').text('Sign In to Command Center');
            } else {
                $('#btn_text').text('Sign In');
            }
        } else if(currentTab === 'phone_only'){
            $('#btn_text').text('Send Verification OTP');
        } else if(currentTab === 'google'){
            $('#btn_text').text('Sign In with Google');
        }
    }

    // Official / Admin Mode Switcher
    $('#toggleAdminBtn').on('click', function(e){
        e.preventDefault();
        if(currentRole === 'admin'){
            setRole('voter');
        } else {
            setRole('admin');
        }
    });

    function setRole(role){
        currentRole = role;
        $('#login_role').val(role);

        if(role === 'admin'){
            // Switch to ID & Password tab for admin
            switchTab('id_password');
            $('#role_status_badge').removeClass('label-info').addClass('label-warning').text('Official / Admin');
            $('#identifier_label').text('Admin Username');
            $('#identifier').attr('placeholder', 'Enter administrative username');
            $('#identifier_icon i').removeClass('fa-id-card').addClass('fa-user-secret');
            $('#password_label').text('Access Password');
            $('#password').attr('placeholder', 'Enter your secret password');
            $('#id_pass_helper_note').hide();
            $('#admin_toggle_text').text('Switch to Voter');
            updateSubmitButtonText();
            if(window.history.replaceState){
                window.history.replaceState(null, null, '?role=admin&tab=id_password');
            }
        } else {
            $('#role_status_badge').removeClass('label-warning').addClass('label-info').text('Voter Access');
            $('#identifier_label').text('Login / Voter ID');
            $('#identifier').attr('placeholder', 'e.g. 5251570429 or Username');
            $('#identifier_icon i').removeClass('fa-user-secret').addClass('fa-id-card');
            $('#password_label').text('Password');
            $('#password').attr('placeholder', 'Enter your registered password');
            $('#id_pass_helper_note').show();
            $('#admin_toggle_text').text('Official / Admin');
            updateSubmitButtonText();
            if(window.history.replaceState){
                window.history.replaceState(null, null, 'index.php?tab=' + currentTab);
            }
        }
    }

    // Toggle Password Visibility
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

    // reCAPTCHA widget checkbox click animation
    $('#recaptcha_check').on('change', function(){
        var boxIndicator = $('.recaptcha-box-indicator');
        if($(this).is(':checked')){
            boxIndicator.html('<i class="fa fa-spinner fa-spin" style="font-size:12px; color:#1a73e8;"></i>');
            setTimeout(function(){
                boxIndicator.html('<i class="fa fa-check check-mark" style="opacity:1; transform:scale(1);"></i>');
            }, 300);
        }
    });

    // =========================================================
    // Active Google Authentication Handling (GIS)
    // =========================================================
    var GOOGLE_CLIENT_ID = '<?php echo escape_html($google_client_id); ?>';

    function initGoogleAuth(){
        if(GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id){
            try {
                google.accounts.id.initialize({
                    client_id: GOOGLE_CLIENT_ID,
                    callback: handleGoogleCredentialResponse,
                    context: 'signin',
                    ux_mode: 'popup',
                    auto_select: false,
                    cancel_on_tap_outside: true
                });
                google.accounts.id.renderButton(
                    document.getElementById("gsi_button_target"),
                    { theme: "outline", size: "large", type: "standard", shape: "pill", text: "signin_with", logo_alignment: "left", width: 280 }
                );
                $('#btnTriggerGoogleSSO').hide();
            } catch(e){
                console.warn('Google Identity Services init notice:', e);
            }
        }
    }

    // Attempt GIS initialization on page load
    $(window).on('load', function(){
        initGoogleAuth();
    });
    setTimeout(initGoogleAuth, 600);

    // Global callback from Google when user authenticates active session
    window.handleGoogleCredentialResponse = function(response){
        if(response && response.credential){
            $('#google_token').val(response.credential);
            $('#login_method').val('google');
            $('#btn_spinner').show();
            $('#btn_text').text('Verifying active Google session...');
            $('#unifiedLoginForm').submit();
        }
    };

    // Google Sign-In button click
    $('#btnTriggerGoogleSSO').on('click', function(e){
        e.preventDefault();
        if(GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id){
            google.accounts.id.prompt();
        } else {
            alert('Google Single Sign-On requires a Google Client ID configured in the Admin Panel.\n\nManual email entry is disabled to prevent account impersonation. Please vote using your Voter ID & Password or Phone Number.');
        }
    });

    // Form submit validation & loader
    $('#unifiedLoginForm').on('submit', function(e){
        if(!$('#recaptcha_check').is(':checked')){
            e.preventDefault();
            alert('Please confirm you are not a robot.');
            return false;
        }
        if(!$('#terms_agreed').is(':checked')){
            e.preventDefault();
            alert('Please accept the Electo Terms of Voting & Charter to continue.');
            return false;
        }

        var method = $('#login_method').val();
        if(method === 'id_password'){
            var idVal = $.trim($('#identifier').val());
            var passVal = $.trim($('#password').val());
            if(idVal.length === 0 || passVal.length === 0){
                e.preventDefault();
                alert('Please enter both your User ID and password.');
                return false;
            }
        } else if(method === 'phone_only'){
            var phoneVal = $.trim($('#phone_number').val());
            if(phoneVal.length === 0){
                e.preventDefault();
                alert('Please enter your registered phone number.');
                return false;
            }
        } else if(method === 'google'){
            var token = $('#google_token').val();
            if(!token){
                e.preventDefault();
                if(GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id){
                    google.accounts.id.prompt();
                } else {
                    alert('You must be actively logged into your official UEW Google account (@st.uew.edu.gh) on this device.\n\nManual email entry is permanently disabled to prevent account impersonation.');
                }
                return false;
            }
        }

        $('#btn_spinner').show();
        $('#btn_text').text('Authenticating...');
    });
});
</script>

</body>
</html>