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
        Election Management
        <small>Multi-Election Lifecycle & Status Control</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="home.php"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Elections</li>
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
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <a href="#addnew_election" data-toggle="modal" class="btn btn-primary btn-sm btn-flat"><i class="fa fa-plus"></i> New Election</a>
              <div class="box-tools pull-right">
                <span class="text-muted"><i class="fa fa-check-circle text-success"></i> Multiple elections can run concurrently.</span>
              </div>
            </div>
            <div class="box-body">
              <div class="table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                  <thead>
                    <th>#</th>
                    <th>Election Title</th>
                    <th>Status</th>
                    <th>Voting Schedule</th>
                    <th>Turnout &amp; Ballots</th>
                    <th>Total Selections</th>
                    <th>Quick Status</th>
                    <th>Actions</th>
                  </thead>
                  <tbody>
                    <?php
                      $sql = "SELECT e.*, 
                                (SELECT COUNT(DISTINCT voter_id) FROM voter_ballot_audit WHERE election_id = e.id) as ballots_cast,
                                (SELECT COUNT(DISTINCT voter_id) FROM voter_elections WHERE election_id = e.id) as eligible_voters,
                                (SELECT COUNT(*) FROM votes WHERE election_id = e.id) as total_votes
                              FROM elections e 
                              ORDER BY (e.status = 'active') DESC, e.id DESC";
                      $next_schedule_refresh_at = null;
                      $query = $conn->query($sql);
                      while($row = $query->fetch_assoc()){
                        $eligible_count = (int)$row['eligible_voters'];
                        $ballots_count = (int)$row['ballots_cast'];
                        $turnout_rate = ($eligible_count > 0) ? round(($ballots_count / $eligible_count) * 100, 1) : 0;
                        $schedule_state = get_election_schedule_status($row);
                        if($schedule_state['state'] === 'scheduled' && !empty($row['start_time'])){
                          $transition_at = strtotime($row['start_time']);
                        } elseif($schedule_state['is_open'] && !empty($row['end_time'])){
                          $transition_at = strtotime($row['end_time']);
                        } else {
                          $transition_at = null;
                        }
                        if($transition_at !== null && $transition_at > time() && ($next_schedule_refresh_at === null || $transition_at < $next_schedule_refresh_at)){
                          $next_schedule_refresh_at = $transition_at;
                        }
                        $status_badge = '';
                        switch($row['status']){
                          case 'active':
                            $status_badge = '<span class="label label-success"><i class="fa fa-check-circle"></i> ACTIVE</span>';
                            break;
                          case 'draft':
                            if($schedule_state['state'] === 'scheduled'){
                              $status_badge = '<span class="label label-info"><i class="fa fa-clock-o"></i> UPCOMING</span>';
                            } elseif($schedule_state['is_open']){
                              $status_badge = '<span class="label label-success"><i class="fa fa-check-circle"></i> ACTIVE (SCHEDULED)</span>';
                            } elseif($schedule_state['state'] === 'ended'){
                              $status_badge = '<span class="label label-danger"><i class="fa fa-lock"></i> ENDED</span>';
                            } else {
                              $status_badge = '<span class="label label-warning"><i class="fa fa-pencil"></i> DRAFT</span>';
                            }
                            break;
                          case 'closed':
                            $status_badge = '<span class="label label-danger"><i class="fa fa-lock"></i> CLOSED</span>';
                            break;
                          case 'archived':
                            $status_badge = '<span class="label label-default"><i class="fa fa-archive"></i> ARCHIVED</span>';
                            break;
                        }

                        $schedule = '';
                        if(!empty($row['start_time'])){
                          $schedule .= '<small><i class="fa fa-clock-o text-green"></i> ' . date('M d, Y h:i A', strtotime($row['start_time'])) . '</small><br>';
                        } else {
                          $schedule .= '<small class="text-muted">Start: Immediate</small><br>';
                        }
                        if(!empty($row['end_time'])){
                          $schedule .= '<small><i class="fa fa-clock-o text-red"></i> ' . date('M d, Y h:i A', strtotime($row['end_time'])) . '</small>';
                        } else {
                          $schedule .= '<small class="text-muted">End: Manual</small>';
                        }

                        // Quick status button
                        $quick_status_btn = '';
                        if($row['status'] === 'active' || $schedule_state['is_open']){
                          $quick_status_btn = '
                            <form method="POST" action="elections_status.php" style="display:inline;">
                              '.csrf_field().'
                              <input type="hidden" name="id" value="'.(int)$row['id'].'">
                              <input type="hidden" name="status" value="closed">
                              <button type="submit" name="set_status" class="btn btn-warning btn-xs btn-flat" title="Close Voting"><i class="fa fa-lock"></i> Close</button>
                            </form>
                          ';
                        } elseif($row['status'] !== 'archived' && $schedule_state['state'] !== 'ended') {
                          $quick_status_btn = '
                            <form method="POST" action="elections_status.php" style="display:inline;">
                              '.csrf_field().'
                              <input type="hidden" name="id" value="'.(int)$row['id'].'">
                              <input type="hidden" name="status" value="active">
                              <button type="submit" name="set_status" class="btn btn-success btn-xs btn-flat" title="Make Active"><i class="fa fa-play"></i> Activate</button>
                            </form>
                          ';
                        } elseif($schedule_state['state'] === 'ended') {
                          $quick_status_btn = '<span class="label label-default">Schedule ended</span>';
                        }

                        echo "
                          <tr>
                            <td>".(int)$row['id']."</td>
                            <td>
                              <b>".escape_html($row['title'])."</b>
                              ".(!empty($row['description']) ? "<br><small class='text-muted'>".escape_html($row['description'])."</small>" : "")."
                            </td>
                            <td>".$status_badge."</td>
                            <td>".$schedule."</td>
                            <td>
                              <span class='badge bg-blue'>".$ballots_count." / ".$eligible_count."</span><br>
                              <small class='text-muted'><b>".$turnout_rate."%</b> Turnout</small>
                            </td>
                            <td><span class='badge bg-purple'>".(int)$row['total_votes']." Votes</span></td>
                            <td>".$quick_status_btn."</td>
                            <td>
                              <button class='btn btn-success btn-sm edit btn-flat' data-id='".(int)$row['id']."' title='Edit Election'><i class='fa fa-edit'></i></button>
                              <button class='btn btn-danger btn-sm delete btn-flat' data-id='".(int)$row['id']."' data-name='".escape_html($row['title'])."' title='Delete / Archive'><i class='fa fa-trash'></i></button>
                            </td>
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
  <?php include 'includes/elections_modal.php'; ?>
</div>
<?php include 'includes/scripts.php'; ?>
<?php if($next_schedule_refresh_at !== null): ?>
<script>window.setTimeout(function(){ window.location.reload(); }, <?php echo max(1000, min(2147483647, ($next_schedule_refresh_at - time() + 1) * 1000)); ?>);</script>
<?php endif; ?>
<script>
$(function(){
  $(document).on('click', '.edit', function(e){
    e.preventDefault();
    $('#edit_election').modal('show');
    var id = $(this).data('id');
    getRow(id);
  });

  $(document).on('click', '.delete', function(e){
    e.preventDefault();
    $('#delete_election').modal('show');
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('.elec_id').val(id);
    $('.elec_name').html(name);
  });
});

function getRow(id){
  $.ajax({
    type: 'POST',
    url: 'elections_row.php',
    data: {id:id},
    dataType: 'json',
    success: function(response){
      $('.elec_id').val(response.id);
      $('#edit_title').val(response.title);
      $('#edit_description').val(response.description);
      $('#edit_status').val(response.status);
      $('#edit_start_time').val(response.start_time);
      $('#edit_end_time').val(response.end_time);
    }
  });
}
</script>
</body>
</html>
