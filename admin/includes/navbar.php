<header class="main-header">
  <!-- Logo -->
  <a href="home.php" class="logo">
    <!-- mini logo for sidebar mini 50x50 pixels -->
    <span class="logo-mini">
      <img src="../favicon/favicon-32x32.png" alt="Electo" style="width: 26px; height: 26px; background: #ffffff; padding: 2px; border-radius: 6px; vertical-align: middle; box-shadow: 0 1px 3px rgba(0,0,0,0.15);">
    </span>
    <!-- logo for regular state and mobile devices -->
    <span class="logo-lg" style="letter-spacing: -0.3px; display: inline-flex; align-items: center;">
      <img src="../favicon/android-chrome-192x192.png" alt="Electo" style="width: 26px; height: 26px; background: #ffffff; padding: 2px; border-radius: 6px; vertical-align: middle; margin-right: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.2);">
      <b>Electo</b>
    </span>
  </a>
  <!-- Header Navbar: style can be found in header.less -->
  <nav class="navbar navbar-static-top">
    <!-- Sidebar toggle button-->
    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
      <span class="sr-only">Toggle navigation</span>
    </a>

    <div class="navbar-custom-menu">
      <ul class="nav navbar-nav">
        <!-- User Account: style can be found in dropdown.less -->
        <li class="dropdown user user-menu">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <img src="<?php echo (!empty($user['photo'])) ? '../images/'.escape_html($user['photo']) : '../images/profile.jpg'; ?>" class="user-image" alt="User Image">
            <span class="hidden-xs"><?php echo escape_html(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')); ?></span>
          </a>
          <ul class="dropdown-menu">
            <!-- User image -->
            <li class="user-header">
              <img src="<?php echo (!empty($user['photo'])) ? '../images/'.escape_html($user['photo']) : '../images/profile.jpg'; ?>" class="img-circle" alt="User Image">

              <p>
                <?php echo escape_html(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')); ?>
                <small>Member since <?php echo date('M. Y', strtotime($user['created_on'] ?? 'now')); ?></small>
              </p>
            </li>
            <li class="user-footer">
              <div class="pull-left">
                <a href="#profile" data-toggle="modal" class="btn btn-default btn-flat" id="admin_profile">Update</a>
              </div>
              <div class="pull-right">
                <a href="logout.php" class="btn btn-default btn-flat">Sign out</a>
              </div>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>
</header>
<?php include 'includes/profile_modal.php'; ?>