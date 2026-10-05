<?php
include 'includes/session.php';
include 'includes/header.php';

$voter_id = (int)$voter['id'];
$eligible_elections = get_voter_eligible_elections($conn, $voter_id);
$next_schedule_refresh_at = null;
foreach($eligible_elections as $eligible_election){
  $schedule = get_election_schedule_status($eligible_election);
  if($schedule['state'] === 'scheduled' && !empty($eligible_election['start_time'])){
    $transition_at = strtotime($eligible_election['start_time']);
  } elseif($schedule['is_open'] && !empty($eligible_election['end_time'])){
    $transition_at = strtotime($eligible_election['end_time']);
  } else {
    $transition_at = null;
  }
  if($transition_at !== null && $transition_at > time() && ($next_schedule_refresh_at === null || $transition_at < $next_schedule_refresh_at)){
    $next_schedule_refresh_at = $transition_at;
  }
}
?>
<body class="hold-transition skin-blue layout-top-nav">
<div class="wrapper">

  <?php include 'includes/navbar.php'; ?>

  <div class="content-wrapper">
    <div class="container" style="max-width: 960px; padding-top: 30px; padding-bottom: 50px;">
      
      <!-- Welcome & Instructions -->
      <div style="margin-bottom: 25px; text-align: center;">
        <h2 style="font-weight: 800; color: #0f172a; margin: 0 0 8px 0; letter-spacing: -0.5px;">
          Available Elections
        </h2>
        <p style="color: #64748b; font-size: 15px; margin: 0 auto; max-width: 550px;">
          Select the election you wish to participate in from your eligible roster below.
        </p>
      </div>

      <?php
        if(isset($_SESSION['error'])){
          echo "
            <div class='alert alert-danger alert-dismissible' style='border-radius: 8px;'>
              <button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>
              <h4><i class='icon fa fa-warning'></i> Notice</h4>
              ".(is_array($_SESSION['error']) ? implode('<br>', array_map('escape_html', $_SESSION['error'])) : escape_html($_SESSION['error']))."
            </div>
          ";
          unset($_SESSION['error']);
        }
        if(isset($_SESSION['success'])){
          echo "
            <div class='alert alert-success alert-dismissible' style='border-radius: 8px;'>
              <button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>
              <h4><i class='icon fa fa-check'></i> Success!</h4>
              ".escape_html($_SESSION['success'])."
            </div>
          ";
          unset($_SESSION['success']);
        }
      ?>

      <?php if(empty($eligible_elections)): ?>
        <div class="box box-solid text-center" style="border-radius: 12px; padding: 50px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #ffffff;">
          <div style="width: 70px; height: 70px; background: #f1f5f9; color: #64748b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 15px;">
            <i class="fa fa-calendar-times-o"></i>
          </div>
          <h3 style="font-weight: 700; color: #1e293b; margin: 0 0 10px 0;">No Active Elections Assigned</h3>
          <p style="color: #64748b; font-size: 14px; max-width: 480px; margin: 0 auto 20px auto;">
            There are currently no active elections configured for your voter profile. If you believe this is an error, please contact the election administrator.
          </p>
          <a href="logout.php" class="btn btn-default btn-flat" style="border-radius: 6px; font-weight: 600;"><i class="fa fa-sign-out"></i> Sign Out</a>
        </div>
      <?php else: ?>
        <div class="row">
          <?php foreach($eligible_elections as $elec): 
            $eid = (int)$elec['id'];
            $schedule = get_election_schedule_status($elec);
            
            // Check voter participation in this specific election
            $voted_check = $conn->execute_query(
              "SELECT id, ballot_token, voted_at FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1",
              [$eid, $voter_id]
            );
            $has_voted = ($voted_check->num_rows > 0);
            $audit_info = $has_voted ? $voted_check->fetch_assoc() : null;

            // Position & candidate counts
            $pos_count = (int)$conn->execute_query("SELECT COUNT(*) as cnt FROM positions WHERE election_id = ?", [$eid])->fetch_assoc()['cnt'];
            $cand_count = (int)$conn->execute_query("SELECT COUNT(c.id) as cnt FROM candidates c INNER JOIN positions p ON p.id = c.position_id WHERE p.election_id = ?", [$eid])->fetch_assoc()['cnt'];
          ?>
            <div class="col-md-6 col-sm-12" style="margin-bottom: 25px;">
              <div class="box box-solid" style="border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; background: #ffffff;">
                
                <!-- Card Header -->
                <div style="padding: 20px 22px; border-bottom: 1px solid #f1f5f9; background: <?php echo $has_voted ? '#f8fafc' : '#ffffff'; ?>;">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; line-height: 1.3;">
                      <?php echo escape_html($elec['title']); ?>
                    </h3>
                    <span class="label label-<?php echo $schedule['badge']; ?>" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; white-space: nowrap;">
                      <i class="fa fa-circle"></i> <?php echo escape_html($schedule['label']); ?>
                    </span>
                  </div>
                  <?php if(!empty($elec['description'])): ?>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 13px; line-height: 1.4;">
                      <?php echo escape_html($elec['description']); ?>
                    </p>
                  <?php endif; ?>
                </div>

                <!-- Card Body -->
                <div style="padding: 18px 22px; flex: 1;">
                  <div style="display: flex; gap: 20px; margin-bottom: 15px; font-size: 13px; color: #475569;">
                    <div><i class="fa fa-tasks text-primary"></i> <b><?php echo $pos_count; ?></b> Positions</div>
                    <div><i class="fa fa-black-tie text-green"></i> <b><?php echo $cand_count; ?></b> Candidates</div>
                  </div>

                  <div style="font-size: 12px; color: #64748b; background: #f8fafc; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <i class="fa fa-clock-o text-muted"></i> <?php echo escape_html($schedule['message']); ?>
                  </div>

                  <?php if($has_voted): ?>
                    <div style="margin-top: 14px; padding: 10px 12px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; font-size: 12px; color: #065f46;">
                      <i class="fa fa-check-circle" style="color: #059669;"></i> <b>Ballot Certified:</b> Voted on <?php echo date('M d, Y h:i A', strtotime($audit_info['voted_at'])); ?>
                      <br><small style="color: #047857; font-family: monospace; font-size: 11px;">Token: <?php echo escape_html($audit_info['ballot_token']); ?></small>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Card Footer / Actions -->
                <div style="padding: 15px 22px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                  <?php if($has_voted): ?>
                    <span class="text-success" style="font-size: 13px; font-weight: 700;">
                      <i class="fa fa-check"></i> Already Voted
                    </span>
                    <a href="home.php?election_id=<?php echo $eid; ?>" class="btn btn-default btn-sm btn-flat" style="border-radius: 6px; font-weight: 600;">
                      <i class="fa fa-shield text-success"></i> View Receipt
                    </a>
                  <?php elseif($schedule['is_open']): ?>
                    <span class="text-muted" style="font-size: 12px;">Ready for your vote</span>
                    <a href="home.php?election_id=<?php echo $eid; ?>" class="btn btn-primary btn-sm btn-flat" style="border-radius: 6px; font-weight: 700; padding: 7px 16px;">
                      <i class="fa fa-check-square-o"></i> Cast Ballot &rarr;
                    </a>
                  <?php elseif($schedule['state'] === 'scheduled'): ?>
                    <span class="text-muted" style="font-size: 12px;"><i class="fa fa-hourglass-start"></i> Upcoming</span>
                    <button class="btn btn-default btn-sm btn-flat" disabled style="border-radius: 6px; font-weight: 600;">
                      <i class="fa fa-clock-o"></i> Not Open Yet
                    </button>
                  <?php else: ?>
                    <span class="text-muted" style="font-size: 12px;"><i class="fa fa-lock"></i> Closed</span>
                    <button class="btn btn-default btn-sm btn-flat" disabled style="border-radius: 6px; font-weight: 600;">
                      <i class="fa fa-lock"></i> Voting Ended
                    </button>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
</div>
<?php include 'includes/scripts.php'; ?>
<?php if($next_schedule_refresh_at !== null): ?>
<script>window.setTimeout(function(){ window.location.reload(); }, <?php echo max(1000, min(2147483647, ($next_schedule_refresh_at - time() + 1) * 1000)); ?>);</script>
<?php endif; ?>
</body>
</html>
