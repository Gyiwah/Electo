<?php 
include 'includes/session.php';
require_once __DIR__ . '/../includes/sms.php';

$sms_config = get_sms_config();
?>
<?php include 'includes/header.php'; ?>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/menubar.php'; ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-envelope-o"></i> SMS & OTP Gateway Settings
      </h1>
      <ol class="breadcrumb">
        <li><a href="home.php"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">SMS Settings</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <?php
        if(isset($_SESSION['error'])){
          echo "
            <div class='alert alert-danger alert-dismissible'>
              <button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>
              <h4><i class='icon fa fa-warning'></i> Error!</h4>
              ".escape_html($_SESSION['error'])."
            </div>
          ";
          unset($_SESSION['error']);
        }
        if(isset($_SESSION['success'])){
          echo "
            <div class='alert alert-success alert-dismissible'>
              <button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>
              <h4><i class='icon fa fa-check'></i> Success!</h4>
              ".escape_html($_SESSION['success'])."
            </div>
          ";
          unset($_SESSION['success']);
        }
      ?>

      <div class="row">
        <!-- Configuration Form -->
        <div class="col-md-7">
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-cogs"></i> Gateway Configuration</h3>
            </div>
            <form class="form-horizontal" method="POST" action="sms_save.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="save_settings">
              <div class="box-body" style="padding: 20px;">
                
                <div class="form-group">
                  <label for="sms_provider" class="col-sm-3 control-label">SMS Provider</label>
                  <div class="col-sm-9">
                    <select class="form-control" id="sms_provider" name="sms_provider" required>
                      <option value="simulation" <?php echo ($sms_config['sms_provider'] === 'simulation') ? 'selected' : ''; ?>>Local Simulation / Dev Mode (No cost, on-screen display)</option>
                      <option value="arkesel" <?php echo ($sms_config['sms_provider'] === 'arkesel') ? 'selected' : ''; ?>>Arkesel (Ghana)</option>
                      <option value="hubtel" <?php echo ($sms_config['sms_provider'] === 'hubtel') ? 'selected' : ''; ?>>Hubtel (Ghana)</option>
                      <option value="mnotify" <?php echo ($sms_config['sms_provider'] === 'mnotify') ? 'selected' : ''; ?>>mNotify (Ghana)</option>
                      <option value="termii" <?php echo ($sms_config['sms_provider'] === 'termii') ? 'selected' : ''; ?>>Termii (Nigeria / Ghana)</option>
                      <option value="twilio" <?php echo ($sms_config['sms_provider'] === 'twilio') ? 'selected' : ''; ?>>Twilio (International)</option>
                      <option value="generic" <?php echo ($sms_config['sms_provider'] === 'generic') ? 'selected' : ''; ?>>Custom Generic HTTP Webhook API</option>
                    </select>
                    <span class="help-block" style="font-size: 12px; margin-bottom: 0;">Select your live SMS delivery service or choose Local Simulation for testing.</span>
                  </div>
                </div>

                <div class="form-group">
                  <label for="sms_sender_id" class="col-sm-3 control-label">Sender ID</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" maxlength="11" value="<?php echo escape_html($sms_config['sms_sender_id']); ?>" placeholder="e.g. GAFVote" required>
                    <span class="help-block" style="font-size: 12px; margin-bottom: 0;">Up to 11 alphanumeric characters displayed on voter's phone as SMS sender.</span>
                  </div>
                </div>

                <div class="form-group">
                  <label for="sms_api_key" class="col-sm-3 control-label">API Key / Token</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" value="<?php echo escape_html($sms_config['sms_api_key']); ?>" placeholder="Enter API Key from SMS provider">
                  </div>
                </div>

                <div class="form-group" id="group_secret" style="<?php echo in_array($sms_config['sms_provider'], ['hubtel', 'twilio']) ? '' : 'display:none;'; ?>">
                  <label for="sms_api_secret" class="col-sm-3 control-label">API Secret / Auth Token</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="sms_api_secret" name="sms_api_secret" value="<?php echo escape_html($sms_config['sms_api_secret'] ?? ''); ?>" placeholder="API Secret (Hubtel / Twilio Auth Token)">
                  </div>
                </div>

                <div class="form-group" id="group_url" style="<?php echo ($sms_config['sms_provider'] === 'generic') ? '' : 'display:none;'; ?>">
                  <label for="sms_api_url" class="col-sm-3 control-label">API Endpoint URL</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="sms_api_url" name="sms_api_url" value="<?php echo escape_html($sms_config['sms_api_url'] ?? ''); ?>" placeholder="https://api.sms.com/send?to={phone}&msg={message}&from={sender}">
                    <span class="help-block" style="font-size: 12px; margin-bottom: 0;">Supports placeholders: <code>{phone}</code>, <code>{message}</code>, and <code>{sender}</code>.</span>
                  </div>
                </div>

                <div class="form-group">
                  <label for="otp_expiry_minutes" class="col-sm-3 control-label">OTP Expiry</label>
                  <div class="col-sm-9">
                    <div class="input-group">
                      <input type="number" min="1" max="60" class="form-control" id="otp_expiry_minutes" name="otp_expiry_minutes" value="<?php echo (int)($sms_config['otp_expiry_minutes'] ?? 10); ?>" required>
                      <span class="input-group-addon">Minutes</span>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="otp_simulation_mode" value="1" <?php echo (!empty($sms_config['otp_simulation_mode']) && $sms_config['otp_simulation_mode'] == '1') ? 'checked' : ''; ?>>
                        <b>Enable On-Screen Demo OTP Helper</b>
                        <p class="text-muted" style="font-size: 12px; margin: 4px 0 0 0;">
                          Displays a helpful on-screen banner with the sent code during testing/demo. Disable in strict production mode once live SMS is funded.
                        </p>
                      </label>
                    </div>
                  </div>
                </div>

                <hr style="margin: 20px 0; border-top: 1px solid #e2e8f0;">
                
                <div class="form-group">
                  <div class="col-sm-offset-3 col-sm-9">
                    <h4 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 5px 0;">
                      <i class="fa fa-google text-danger"></i> Google Workspace Single Sign-On (SSO)
                    </h4>
                    <p class="text-muted" style="font-size: 12px; margin-bottom: 12px;">
                      Allows voters to select their active <code>@st.uew.edu.gh</code> Google account logged in on their device.
                    </p>
                  </div>
                </div>

                <div class="form-group">
                  <label for="google_client_id" class="col-sm-3 control-label">Google Client ID</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="google_client_id" name="google_client_id" value="<?php echo escape_html(get_google_config()['google_client_id'] ?? ''); ?>" placeholder="e.g. 123456789-abcdefghijk.apps.googleusercontent.com">
                    <span class="help-block" style="font-size: 12px; margin-bottom: 0;">
                      From Google Cloud Console &rarr; APIs & Services &rarr; Credentials (Web application). Ensure Authorized JavaScript origins include your domain (e.g. <code>http://localhost</code>).
                    </span>
                  </div>
                </div>

              </div>
              <div class="box-footer">
                <button type="submit" class="btn btn-primary btn-flat"><i class="fa fa-save"></i> Save Settings</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Test SMS & Live Outbox Log -->
        <div class="col-md-5">
          <!-- Test SMS Box -->
          <div class="box box-success">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-paper-plane"></i> Send Test SMS</h3>
            </div>
            <form class="form-horizontal" method="POST" action="sms_save.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="send_test_sms">
              <div class="box-body" style="padding: 20px;">
                <p style="font-size: 13px; color: #475569;">
                  Dispatch a real test SMS message to verify your API credentials and sender ID.
                </p>
                <div class="form-group">
                  <label for="test_phone" class="col-sm-4 control-label">Recipient Phone</label>
                  <div class="col-sm-8">
                    <input type="text" class="form-control" id="test_phone" name="test_phone" placeholder="e.g. 0244123456" required>
                  </div>
                </div>
                <div class="form-group">
                  <label for="test_message" class="col-sm-4 control-label">Message</label>
                  <div class="col-sm-8">
                    <textarea class="form-control" id="test_message" name="test_message" rows="3" required>Test verification from GAFVote Election System. System is operational!</textarea>
                  </div>
                </div>
              </div>
              <div class="box-footer">
                <button type="submit" class="btn btn-success btn-flat btn-block"><i class="fa fa-send"></i> Dispatch Test SMS</button>
              </div>
            </form>
          </div>

          <!-- Quick Info / Recent Logs -->
          <div class="box box-default">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-history"></i> Recent SMS Outbox Log</h3>
              <div class="box-tools pull-right">
                <a href="sms_save.php?action=clear_log&csrf_token=<?php echo escape_html(get_csrf_token()); ?>" class="btn btn-xs btn-default"><i class="fa fa-trash"></i> Clear</a>
              </div>
            </div>
            <div class="box-body" style="padding: 10px; max-height: 250px; overflow-y: auto; background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 11px; border-radius: 4px;">
              <?php
                if (file_exists(SMS_LOG_FILE)) {
                    $log_content = @file_get_contents(SMS_LOG_FILE);
                    $lines = array_filter(explode("\n", trim($log_content)));
                    $recent = array_slice($lines, -15);
                    if (!empty($recent)) {
                        foreach (array_reverse($recent) as $line) {
                            echo htmlspecialchars($line) . "<br>";
                        }
                    } else {
                        echo "<span style='color: #94a3b8;'>No SMS logs recorded yet.</span>";
                    }
                } else {
                    echo "<span style='color: #94a3b8;'>No SMS logs recorded yet.</span>";
                }
              ?>
            </div>
          </div>
        </div>

      </div>
    </section>   
  </div>
    
  <?php include 'includes/footer.php'; ?>
</div>
<?php include 'includes/scripts.php'; ?>
<script>
$(function(){
  $('#sms_provider').on('change', function(){
    var val = $(this).val();
    if(val === 'hubtel' || val === 'twilio'){
      $('#group_secret').slideDown(200);
    } else {
      $('#group_secret').slideUp(200);
    }

    if(val === 'generic'){
      $('#group_url').slideDown(200);
    } else {
      $('#group_url').slideUp(200);
    }
  });
});
</script>
</body>
</html>
