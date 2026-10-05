<?php include 'includes/session.php'; ?>
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
        Security & Audit Logs
        <small>System Compliance, Authentication & Ballot Audit Trail</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="home.php"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Audit Logs</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <?php
        // Filter options
        $filter_action = trim($_GET['action'] ?? '');
        $filter_type = trim($_GET['user_type'] ?? '');

        $where = [];
        $params = [];

        if(!empty($filter_action)){
            $where[] = "action = ?";
            $params[] = $filter_action;
        }
        if(!empty($filter_type)){
            $where[] = "user_type = ?";
            $params[] = $filter_type;
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Query logs
        $sql = "SELECT * FROM audit_logs $where_sql ORDER BY id DESC LIMIT 500";
        $logs_q = !empty($params) ? $conn->execute_query($sql, $params) : $conn->query($sql);

        // Stats
        $total_cnt = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'];
        $ballots_cnt = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE action = 'BALLOT_CAST'")->fetch_assoc()['c'];
        $today_cnt = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
      ?>

      <!-- Summary Stats Cards -->
      <div class="row">
        <div class="col-md-4 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-shield"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Audit Events</span>
              <span class="info-box-number"><?php echo number_format($total_cnt); ?></span>
            </div>
          </div>
        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check-square-o"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Ballots Cast Tracked</span>
              <span class="info-box-number"><?php echo number_format($ballots_cnt); ?></span>
            </div>
          </div>
        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-calendar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Events Recorded Today</span>
              <span class="info-box-number"><?php echo number_format($today_cnt); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-list"></i> System Event Ledger</h3>
              <div class="box-tools pull-right">
                <a href="audit_logs_export.php" class="btn btn-success btn-sm btn-flat"><i class="fa fa-file-excel-o"></i> Export Audit Ledger (CSV)</a>
              </div>
            </div>

            <!-- Filter Toolbar -->
            <div class="box-body" style="border-bottom: 1px solid #f4f4f4; background: #fafafa;">
              <form method="GET" class="form-inline">
                <div class="form-group" style="margin-right: 15px;">
                  <label for="user_type" style="margin-right: 5px;">User Type:</label>
                  <select name="user_type" id="user_type" class="form-control input-sm">
                    <option value="">All Actors</option>
                    <option value="admin" <?php echo ($filter_type === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="voter" <?php echo ($filter_type === 'voter') ? 'selected' : ''; ?>>Voter</option>
                    <option value="system" <?php echo ($filter_type === 'system') ? 'selected' : ''; ?>>System</option>
                  </select>
                </div>

                <div class="form-group" style="margin-right: 15px;">
                  <label for="action" style="margin-right: 5px;">Action:</label>
                  <select name="action" id="action" class="form-control input-sm">
                    <option value="">All Action Types</option>
                    <option value="BALLOT_CAST" <?php echo ($filter_action === 'BALLOT_CAST') ? 'selected' : ''; ?>>BALLOT_CAST</option>
                    <option value="LOGIN_SUCCESS" <?php echo ($filter_action === 'LOGIN_SUCCESS') ? 'selected' : ''; ?>>LOGIN_SUCCESS</option>
                    <option value="LOGIN_FAIL" <?php echo ($filter_action === 'LOGIN_FAIL') ? 'selected' : ''; ?>>LOGIN_FAIL</option>
                    <option value="ELECTION_CREATE" <?php echo ($filter_action === 'ELECTION_CREATE') ? 'selected' : ''; ?>>ELECTION_CREATE</option>
                    <option value="ELECTION_UPDATE" <?php echo ($filter_action === 'ELECTION_UPDATE') ? 'selected' : ''; ?>>ELECTION_UPDATE</option>
                    <option value="ELECTION_STATUS_CHANGE" <?php echo ($filter_action === 'ELECTION_STATUS_CHANGE') ? 'selected' : ''; ?>>ELECTION_STATUS_CHANGE</option>
                    <option value="VOTERS_BULK_IMPORT" <?php echo ($filter_action === 'VOTERS_BULK_IMPORT') ? 'selected' : ''; ?>>VOTERS_BULK_IMPORT</option>
                  </select>
                </div>

                <button type="submit" class="btn btn-primary btn-sm btn-flat"><i class="fa fa-filter"></i> Filter</button>
                <a href="audit_logs.php" class="btn btn-default btn-sm btn-flat">Reset</a>
              </form>
            </div>

            <div class="box-body">
              <div class="table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                    <th>#</th>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                  </thead>
                  <tbody>
                    <?php
                      while($row = $logs_q->fetch_assoc()){
                        $badge_class = 'label-default';
                        if(strpos($row['action'], 'BALLOT') !== false){
                          $badge_class = 'label-success';
                        } elseif(strpos($row['action'], 'SUCCESS') !== false){
                          $badge_class = 'label-info';
                        } elseif(strpos($row['action'], 'FAIL') !== false || strpos($row['action'], 'DELETE') !== false){
                          $badge_class = 'label-danger';
                        } elseif(strpos($row['action'], 'ELECTION') !== false){
                          $badge_class = 'label-warning';
                        }

                        $actor_label = '';
                        switch($row['user_type']){
                          case 'admin':
                            $actor_label = '<span class="badge bg-purple"><i class="fa fa-user-secret"></i> Admin #'.(int)$row['user_id'].'</span>';
                            break;
                          case 'voter':
                            $actor_label = '<span class="badge bg-blue"><i class="fa fa-user"></i> Voter #'.(int)$row['user_id'].'</span>';
                            break;
                          default:
                            $actor_label = '<span class="badge bg-gray"><i class="fa fa-server"></i> System</span>';
                            break;
                        }

                        echo "
                          <tr>
                            <td>".(int)$row['id']."</td>
                            <td><small>".date('M d, Y - h:i:s A', strtotime($row['created_at']))."</small></td>
                            <td>".$actor_label."</td>
                            <td><span class='label ".$badge_class."'>".escape_html($row['action'])."</span></td>
                            <td>".escape_html($row['details'])."</td>
                            <td><code>".escape_html($row['ip_address'] ?? 'N/A')."</code></td>
                          </tr>
                        ";
                      }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>   
  </div>
    
  <?php include 'includes/footer.php'; ?>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
