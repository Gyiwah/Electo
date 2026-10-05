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
        Votes
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Votes</li>
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
        $active_election = get_active_election($conn);
        $selected_election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

        // Fetch current election info
        $cur_elec_q = $conn->execute_query("SELECT * FROM elections WHERE id = ?", [$selected_election_id]);
        $cur_election = $cur_elec_q->fetch_assoc();
        $election_title = $cur_election['title'] ?? 'Election';

        // Stats for this election
        $total_votes = $conn->execute_query("SELECT COUNT(*) as c FROM votes WHERE election_id = ?", [$selected_election_id])->fetch_assoc()['c'];
        $unique_ballots = $conn->execute_query("SELECT COUNT(DISTINCT voter_id) as c FROM voter_ballot_audit WHERE election_id = ?", [$selected_election_id])->fetch_assoc()['c'];
      ?>

      <!-- Election Switcher & Actions Toolbar -->
      <div class="row">
        <div class="col-xs-12">
          <div class="box box-solid" style="margin-bottom: 15px;">
            <div class="box-body" style="background: #fafafa;">
              <form method="GET" class="form-inline pull-left" style="margin-bottom: 0;">
                <div class="form-group">
                  <label for="election_id" style="margin-right: 10px;">Select Election:</label>
                  <select name="election_id" id="election_id" class="form-control" onchange="this.form.submit()">
                    <?php
                      $e_list = $conn->query("SELECT id, title, status FROM elections ORDER BY id DESC");
                      while($e = $e_list->fetch_assoc()){
                        $sel = ($e['id'] == $selected_election_id) ? 'selected' : '';
                        $stat_tag = ($e['status'] === 'active') ? ' [ACTIVE]' : ' ('.strtoupper($e['status']).')';
                        echo "<option value='".(int)$e['id']."' $sel>".escape_html($e['title']).$stat_tag."</option>";
                      }
                    ?>
                  </select>
                </div>
              </form>

              <div class="pull-right">
                <a href="votes_export.php?election_id=<?php echo $selected_election_id; ?>" class="btn btn-success btn-sm btn-flat"><i class="fa fa-file-excel-o"></i> Export Results CSV</a>
                <a href="turnout_export.php?election_id=<?php echo $selected_election_id; ?>" class="btn btn-info btn-sm btn-flat" style="margin-left: 5px;"><i class="fa fa-users"></i> Export Turnout CSV</a>
                <a href="print.php?election_id=<?php echo $selected_election_id; ?>" target="_blank" class="btn btn-default btn-sm btn-flat" style="margin-left: 5px;"><i class="fa fa-print"></i> PDF/Print Report</a>
                <a href="#reset" data-toggle="modal" class="btn btn-danger btn-sm btn-flat" style="margin-left: 10px;"><i class="fa fa-refresh"></i> Reset Votes</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Summary Cards -->
      <div class="row">
        <div class="col-md-6 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check-square-o"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Voter Ballots Counted</span>
              <span class="info-box-number"><?php echo number_format($unique_ballots); ?> Certified Ballots</span>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-purple"><i class="fa fa-tasks"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Individual Selections Recorded</span>
              <span class="info-box-number"><?php echo number_format($total_votes); ?> Votes</span>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title">Ballot Box Ledger for: <b><?php echo escape_html($election_title); ?></b></h3>
            </div>
            <div class="box-body">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                  <th>#</th>
                  <th>Position</th>
                  <th>Candidate</th>
                  <th>Ballot Token / Anonymity Status</th>
                </thead>
                <tbody>
                  <?php
                    $sql = "SELECT votes.*, 
                                  candidates.firstname AS canfirst, candidates.lastname AS canlast, 
                                  positions.description,
                                  voters.firstname AS votfirst, voters.lastname AS votlast, voters.voters_id as votcode
                            FROM votes 
                            LEFT JOIN positions ON positions.id = votes.position_id 
                            LEFT JOIN candidates ON candidates.id = votes.candidate_id 
                            LEFT JOIN voters ON voters.id = votes.voters_id 
                            WHERE votes.election_id = ?
                            ORDER BY positions.priority ASC, votes.id DESC";
                    $stmt = $conn->execute_query($sql, [$selected_election_id]);
                    $cnt = 1;
                    while($row = $stmt->fetch_assoc()){
                      if(!empty($row['ballot_token'])){
                        $ballot_badge = '<span class="label label-primary" style="font-family: monospace; font-size: 12px;"><i class="fa fa-shield"></i> ' . escape_html($row['ballot_token']) . '</span> <small class="text-success">(Secret Ballot)</small>';
                      } elseif(!empty($row['votcode'])){
                        $ballot_badge = escape_html($row['votfirst'].' '.$row['votlast']).' <code>('.escape_html($row['votcode']).')</code> <small class="text-muted">(Legacy)</small>';
                      } else {
                        $ballot_badge = '<span class="label label-default">Anonymous Ballot</span>';
                      }

                      echo "
                        <tr>
                          <td>".$cnt++."</td>
                          <td><b>".escape_html($row['description'])."</b></td>
                          <td>".((int)$row['candidate_id'] === 0 ? 'No' : escape_html($row['canfirst'].' '.$row['canlast']))."</td>
                          <td>".$ballot_badge."</td>
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
    </section>   
  </div>
    
  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/votes_modal.php'; ?>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
