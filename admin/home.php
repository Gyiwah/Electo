<?php include 'includes/session.php'; ?>
<?php include 'includes/slugify.php'; ?>
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
        Dashboard
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Dashboard</li>
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
      <?php
        // Selected election handling
        if(isset($_GET['election_id']) && (int)$_GET['election_id'] > 0){
            $_SESSION['admin_election_id'] = (int)$_GET['election_id'];
        }
        $selected_id = isset($_SESSION['admin_election_id']) ? (int)$_SESSION['admin_election_id'] : null;
        $active_election = get_active_election($conn, $selected_id);
        $election_id = (int)$active_election['id'];
        $_SESSION['admin_election_id'] = $election_id;
        $election_title = $active_election['title'] ?? 'Current Election';
        $all_elections = get_all_elections($conn);
        $schedule = get_election_schedule_status($active_election);

        // Turnout count strictly for the selected election
        $turnout_q = $conn->execute_query(
          "SELECT COUNT(DISTINCT voter_id) as cnt FROM voter_ballot_audit WHERE election_id = ?",
          [$election_id]
        );
        $voters_voted = (int)$turnout_q->fetch_assoc()['cnt'];

        // Eligible voters for the selected election
        $total_voters = get_election_eligible_voters_count($conn, $election_id);

        $turnout_pct = ($total_voters > 0) ? round(($voters_voted / $total_voters) * 100, 1) : 0;

        // Count of positions & candidates strictly for the selected election
        $pos_cnt = (int)$conn->execute_query("SELECT COUNT(*) as cnt FROM positions WHERE election_id = ?", [$election_id])->fetch_assoc()['cnt'];
        $cand_cnt = (int)$conn->execute_query("SELECT COUNT(c.id) as cnt FROM candidates c INNER JOIN positions p ON p.id = c.position_id WHERE p.election_id = ?", [$election_id])->fetch_assoc()['cnt'];
      ?>

      <!-- Active / Selected Election Banner -->
      <div class="row">
        <div class="col-xs-12">
          <div class="callout callout-info" style="border-left-color: #00c0ef; background-color: #fff !important; color: #333 !important; border: 1px solid #e1e8ed; border-left: 5px solid #0073b7; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 18px 20px;">
            <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
              <div class="col-md-7 col-sm-12">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                  <h4 style="margin: 0; color: #0073b7; font-weight: 800; font-size: 19px;">
                    <i class="fa fa-flag"></i> <?php echo escape_html($election_title); ?>
                  </h4>
                  <span class="label label-<?php echo $schedule['badge']; ?>" style="font-size: 11px; padding: 3px 8px; border-radius: 4px;">
                    <i class="fa fa-circle"></i> <?php echo escape_html($schedule['label']); ?>
                  </span>
                </div>
                <p style="margin: 0; color: #475569; font-size: 13px;">
                  <span><i class="fa fa-clock-o text-muted"></i> <?php echo escape_html($schedule['message']); ?></span>
                  &nbsp;&bull;&nbsp; 
                  <span>Turnout: <b style="color: #0f172a;"><?php echo $turnout_pct; ?>%</b> (<?php echo $voters_voted; ?> of <?php echo $total_voters; ?> eligible voters)</span>
                </p>
              </div>

              <div class="col-md-5 col-sm-12 text-right" style="margin-top: 10px;">
                <!-- Election Switcher Control -->
                <div style="display: inline-block; margin-right: 8px; text-align: left;">
                  <select id="election_switcher" class="form-control input-sm" style="font-weight: 600; border-radius: 4px; border: 1px solid #cbd5e1; height: 32px; background: #f8fafc;" onchange="location.href='home.php?election_id=' + this.value">
                    <?php foreach($all_elections as $el): ?>
                      <option value="<?php echo (int)$el['id']; ?>" <?php echo ($el['id'] == $election_id) ? 'selected' : ''; ?>>
                        <?php echo ($el['id'] == $election_id) ? '&check; ' : ''; ?><?php echo escape_html($el['title']); ?> (<?php echo strtoupper($el['status']); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <a href="elections.php" class="btn btn-primary btn-sm btn-flat" style="background: #075985 !important; color: #ffffff !important; border: 1px solid #075985 !important; font-weight: 700; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.15);"><i class="fa fa-cog"></i> Manage Elections</a>
                <a href="votes.php?election_id=<?php echo $election_id; ?>" class="btn btn-sm btn-flat" style="background: #0f766e !important; color: #ffffff !important; border: 1px solid #115e59 !important; font-weight: 700; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.15);"><i class="fa fa-list" style="color: #ffffff;"></i> View Ledger</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Small boxes (Stat box for Selected Election) -->
      <div class="row">
        <div class="col-lg-3 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-aqua" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div class="inner">
              <h3><?php echo $pos_cnt; ?></h3>
              <p>No. of Positions</p>
            </div>
            <div class="icon">
              <i class="fa fa-tasks"></i>
            </div>
            <a href="positions.php?election_id=<?php echo $election_id; ?>" class="small-box-footer">Positions for this election <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-green" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div class="inner">
              <h3><?php echo $cand_cnt; ?></h3>
              <p>No. of Candidates</p>
            </div>
            <div class="icon">
              <i class="fa fa-black-tie"></i>
            </div>
            <a href="candidates.php?election_id=<?php echo $election_id; ?>" class="small-box-footer">Candidates for this election <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-yellow" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div class="inner">
              <h3><?php echo $total_voters; ?></h3>
              <p>Total Eligible Voters</p>
            </div>
            <div class="icon">
              <i class="fa fa-users"></i>
            </div>
            <a href="voters.php?election_id=<?php echo $election_id; ?>" class="small-box-footer">Voters roster <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-xs-6">
          <!-- small box -->
          <div class="small-box bg-red" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div class="inner">
              <h3><?php echo $voters_voted; ?></h3>
              <p>Turnout: <?php echo $turnout_pct; ?>% Voted</p>
            </div>
            <div class="icon">
              <i class="fa fa-check-square-o"></i>
            </div>
            <a href="votes.php?election_id=<?php echo $election_id; ?>" class="small-box-footer">View ballots cast <i class="fa fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <!-- ./col -->
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div style="display: flex; justify-content: space-between; align-items: center; margin: 15px 0 20px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; font-weight: 700; font-size: 20px; color: #0f172a;">
              <i class="fa fa-bar-chart text-primary"></i> Live Results: <span style="color: #2563eb;"><?php echo escape_html($election_title); ?></span>
            </h3>
            <div>
              <a href="../projector.php?election_id=<?php echo $election_id; ?>" target="_blank" class="btn btn-primary btn-sm btn-flat" style="border-radius: 4px; margin-right: 5px;"><i class="fa fa-television"></i> Projector Mode</a>
              <a href="print.php?election_id=<?php echo $election_id; ?>" target="_blank" class="btn btn-success btn-sm btn-flat" style="border-radius: 4px;"><span class="glyphicon glyphicon-print"></span> Print Report</a>
              <a href="votes_export.php?election_id=<?php echo $election_id; ?>" class="btn btn-info btn-sm btn-flat" style="border-radius: 4px; margin-left: 5px;"><i class="fa fa-file-excel-o"></i> CSV Export</a>
            </div>
          </div>
        </div>
      </div>

      <?php
        $positions_res = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);
        $chart_configs = [];
        $inc = 2;

        if($positions_res->num_rows === 0){
          echo "
            <div class='callout callout-info text-center' style='background: #ffffff !important; border-left: 5px solid #3b82f6; border: 1px solid #e2e8f0; border-radius: 10px; padding: 40px 20px; margin: 20px 0;'>
              <i class='fa fa-info-circle fa-3x text-primary' style='margin-bottom: 12px;'></i>
              <h4 style='font-weight: 700; color: #0f172a; margin-bottom: 8px;'>No Positions Configured Yet</h4>
              <p style='color: #64748b; font-size: 14px; max-width: 500px; margin: 0 auto 16px auto;'>
                No voting positions or candidates have been added to <b>".escape_html($election_title)."</b> yet.
              </p>
              <a href='positions.php' class='btn btn-primary btn-flat' style='border-radius: 6px; font-weight: 600;'><i class='fa fa-plus'></i> Configure Positions</a>
            </div>
          ";
        }

        while($row = $positions_res->fetch_assoc()){
          $pos_id = (int)$row['id'];
          $pos_desc = $row['description'];
          $slug = slugify($pos_desc . '-' . $pos_id);

          // Fetch candidates and votes strictly for this position and election
          $cquery = $conn->execute_query(
            "SELECT c.id, c.lastname, c.firstname, COUNT(v.id) AS vote_count 
             FROM candidates c 
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = ?
             WHERE c.position_id = ? 
             GROUP BY c.id 
             ORDER BY vote_count DESC, c.lastname ASC",
            [$election_id, $pos_id]
          );

          $carray = [];
          $varray = [];
          $candidates_list = [];
          $pos_total_votes = 0;

          while($crow = $cquery->fetch_assoc()){
            $cnt = (int)$crow['vote_count'];
            $pos_total_votes += $cnt;
            $carray[] = $crow['lastname'] . ', ' . substr($crow['firstname'], 0, 1) . '.';
            $varray[] = $cnt;
            $candidates_list[] = [
              'name' => $crow['firstname'] . ' ' . $crow['lastname'],
              'votes' => $cnt
            ];
          }

          if(count($carray) === 1){
            $carray[0] = 'Yes: ' . $carray[0];
            $candidates_list[0]['name'] = 'Yes: ' . $candidates_list[0]['name'];
            $no_votes = (int)$conn->execute_query(
              "SELECT COUNT(*) AS cnt FROM votes WHERE candidate_id = 0 AND position_id = ? AND election_id = ?",
              [$pos_id, $election_id]
            )->fetch_assoc()['cnt'];
            $pos_total_votes += $no_votes;
            if($no_votes > $varray[0]){
              array_unshift($carray, 'No');
              array_unshift($varray, $no_votes);
              array_unshift($candidates_list, ['name' => 'No', 'votes' => $no_votes]);
            } else {
              $carray[] = 'No';
              $varray[] = $no_votes;
              $candidates_list[] = ['name' => 'No', 'votes' => $no_votes];
            }
          }

          // Responsive height calculated from candidate count
          $cand_count = count($carray);
          $chart_height = max(180, $cand_count * 45);

          $chart_configs[] = [
            'slug' => $slug,
            'labels' => $carray,
            'data' => $varray,
            'height' => $chart_height
          ];

          $inc = ($inc == 2) ? 1 : $inc + 1; 
          if($inc == 1) echo "<div class='row'>";
          ?>
            <div class='col-sm-6'>
              <div class='box box-solid' style="border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 24px; border: 1px solid #e2e8f0;">
                <div class='box-header with-border' style="padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                  <h4 class='box-title' style="font-weight: 700; color: #0f172a; margin: 0;">
                    <i class="fa fa-bar-chart text-primary" style="margin-right: 6px;"></i> <?php echo escape_html($pos_desc); ?>
                  </h4>
                  <span class="label label-primary" style="font-size: 11px; padding: 4px 10px; border-radius: 20px;">
                    <?php echo $pos_total_votes; ?> Total Votes
                  </span>
                </div>
                <div class='box-body' style="padding: 20px;">
                  <!-- Responsive Canvas Chart Container -->
                  <div class='chart-responsive-box' style='height: <?php echo $chart_height; ?>px;'>
                    <canvas id='<?php echo $slug; ?>'></canvas>
                  </div>

                  <!-- Mobile-First Tally Breakdown List -->
                  <div class="mobile-tally-list">
                    <?php foreach($candidates_list as $idx => $cand): 
                      $pct = ($pos_total_votes > 0) ? round(($cand['votes'] / $pos_total_votes) * 100, 1) : 0;
                    ?>
                      <div class="mobile-tally-item">
                        <div class="mobile-tally-meta">
                          <span class="mobile-tally-candidate">
                            <?php if($idx === 0 && $cand['votes'] > 0): ?>
                              <i class="fa fa-trophy text-warning" style="margin-right: 4px;"></i>
                            <?php endif; ?>
                            <?php echo escape_html($cand['name']); ?>
                          </span>
                          <span class="mobile-tally-count">
                            <?php echo $cand['votes']; ?> votes (<?php echo $pct; ?>%)
                          </span>
                        </div>
                        <div class="mobile-tally-bar">
                          <div class="mobile-tally-fill <?php echo ($idx === 0 && $cand['votes'] > 0) ? 'leader-fill' : ''; ?>" style="width: <?php echo $pct; ?>%;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php
          if($inc == 2) echo "</div>";  
        }
        if($inc == 1) echo "<div class='col-sm-6'></div></div>";
      ?>

      </section>
      <!-- right col -->
    </div>
  	<?php include 'includes/footer.php'; ?>

</div>
<!-- ./wrapper -->

<?php include 'includes/scripts.php'; ?>
<script>
$(function(){
  var activeCharts = [];

  <?php foreach($chart_configs as $cfg): ?>
  (function(){
    var slug = '<?php echo $cfg['slug']; ?>';
    var canvasEl = document.getElementById(slug);
    if (!canvasEl) return;

    var barChartCanvas = canvasEl.getContext('2d');
    var barChart = new Chart(barChartCanvas);
    var barChartData = {
      labels  : <?php echo json_encode($cfg['labels']); ?>,
      datasets: [
        {
          label               : 'Votes',
          fillColor           : 'rgba(37, 99, 235, 0.85)',
          strokeColor         : 'rgba(30, 58, 138, 1)',
          pointColor          : '#2563eb',
          pointStrokeColor    : '#1e3a8a',
          pointHighlightFill  : '#fff',
          pointHighlightStroke: '#2563eb',
          data                : <?php echo json_encode($cfg['data']); ?>
        }
      ]
    };

    var barChartOptions = {
      scaleBeginAtZero        : true,
      scaleShowGridLines      : true,
      scaleGridLineColor      : 'rgba(0,0,0,.04)',
      scaleGridLineWidth      : 1,
      scaleShowHorizontalLines: true,
      scaleShowVerticalLines  : true,
      barShowStroke           : true,
      barStrokeWidth          : 1,
      barValueSpacing         : 4,
      barDatasetSpacing       : 1,
      responsive              : true,
      maintainAspectRatio     : false
    };

    var myChart = barChart.HorizontalBar(barChartData, barChartOptions);
    activeCharts.push(myChart);
  })();
  <?php endforeach; ?>

  // Debounced Window Resize & Orientation Change listener
  var resizeTimer;
  $(window).on('resize orientationchange', function(){
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function(){
      activeCharts.forEach(function(chart){
        if (chart && typeof chart.resize === 'function') {
          chart.resize();
        }
      });
    }, 150);
  });
});
</script>
</body>
</html>
