<header class="main-header">
  <nav class="navbar navbar-static-top">
    <div class="container">
      <div class="navbar-header">
        <a href="home.php" class="navbar-brand" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px;">
          <img src="favicon/android-chrome-192x192.png" alt="Electo Logo" style="width: 28px; height: 28px; background: #ffffff; padding: 2px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
          <span><b>Electo</b></span>
        </a>
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse">
          <i class="fa fa-bars"></i>
        </button>
      </div>

      <!-- Collect the nav links, forms, and other content for toggling -->
      <div class="collapse navbar-collapse pull-left" id="navbar-collapse">
        <ul class="nav navbar-nav">
          <?php
            if(isset($_SESSION['voter'])){
              echo "
                <li><a href='elections_select.php'><i class='fa fa-th-large'></i> ELECTIONS</a></li>
                <li><a href='home.php'><i class='fa fa-check-square-o'></i> BALLOT</a></li>
                <li><a href='ballot_receipt.php'><i class='fa fa-shield'></i> RECEIPT</a></li>
              ";
            } 
          ?>
        </ul>
      </div>
      <!-- /.navbar-collapse -->
      <!-- Navbar Right Menu -->
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="user user-menu">
            <a href="#">
              <img src="<?php echo (!empty($voter['photo'])) ? 'images/'.escape_html($voter['photo']) : 'images/profile.jpg' ?>" class="user-image" alt="User Image">
              <span class="hidden-xs">
                <?php echo escape_html(($voter['firstname'] ?? '') . ' ' . ($voter['lastname'] ?? '')); ?>
                <?php if(!empty($voter['voters_id'])): ?>
                  <span class="badge" style="margin-left: 6px; background: rgba(255,255,255,0.2); font-family: monospace; font-size: 11px;"><?php echo escape_html($voter['voters_id']); ?></span>
                <?php endif; ?>
              </span>
            </a>
          </li>
          <li><a href="logout.php"><i class="fa fa-sign-out"></i> LOGOUT</a></li>  
        </ul>
      </div>
      <!-- /.navbar-custom-menu -->
    </div>
    <!-- /.container-fluid -->
  </nav>
</header>