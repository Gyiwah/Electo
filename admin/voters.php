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
        Voters List
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Voters</li>
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
        if(isset($_SESSION['imported_credentials']) && !empty($_SESSION['imported_credentials'])){
          $count_cred = count($_SESSION['imported_credentials']);
          ?>
          <div class="callout callout-info" style="border-radius: 4px;">
            <h4><i class="fa fa-key"></i> <?php echo (int)$count_cred; ?> Voter Credentials Generated!</h4>
            <p>You can download the full CSV containing the generated login credentials for distribution to voters.</p>
            <div style="margin-top: 10px;">
              <a href="voters_credentials_download.php" class="btn btn-success btn-flat"><i class="fa fa-download"></i> Download Credentials CSV</a>
              <a href="voters.php?dismiss_credentials=1" class="btn btn-default btn-flat" style="margin-left: 10px;">Dismiss</a>
            </div>
          </div>
          <?php
          if(isset($_GET['dismiss_credentials'])){
            unset($_SESSION['imported_credentials']);
            safe_redirect('voters.php');
          }
        }

        $filter_election = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
        $all_elections = get_all_elections($conn);
        $active_election = get_active_election($conn, $filter_election > 0 ? $filter_election : null);
        $election_id = (int)($active_election['id'] ?? 1);
        $election_title = $active_election['title'] ?? 'Current Election';
      ?>
      <div class="row">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border voters-toolbar">
              <div class="voters-toolbar-actions">
                <a href="#addnew" data-toggle="modal" class="btn btn-primary btn-sm btn-flat"><i class="fa fa-plus"></i> New Voter</a>
                <a href="#import_csv" data-toggle="modal" class="btn btn-success btn-sm btn-flat"><i class="fa fa-file-excel-o"></i> Bulk Import CSV</a>
                <a href="voters_export.php<?php echo ($filter_election > 0) ? '?election_id='.$filter_election : ''; ?>" class="btn btn-info btn-sm btn-flat"><i class="fa fa-download"></i> Export Roster CSV</a>
              </div>
              <div class="voters-toolbar-filter">
                <label for="voter_election_filter"><i class="fa fa-filter text-primary"></i> Filter Election</label>
                <select id="voter_election_filter" class="form-control input-sm" onchange="location.href='voters.php?election_id=' + this.value">
                  <option value="0" <?php echo ($filter_election === 0) ? 'selected' : ''; ?>>-- All Elections &amp; Voters --</option>
                  <?php foreach($all_elections as $el): ?>
                    <option value="<?php echo (int)$el['id']; ?>" <?php echo ($filter_election === (int)$el['id']) ? 'selected' : ''; ?>>
                      <?php echo escape_html($el['title']); ?> (<?php echo strtoupper($el['status']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="box-body">
              <div class="bulk-selection-toolbar">
                <label class="bulk-selection-count"><span id="selected_voter_count">0</span> selected</label>
                <div class="bulk-selection-actions">
                  <button type="button" class="btn btn-primary btn-sm btn-flat" id="bulk_assign_button" disabled><i class="fa fa-calendar-plus-o"></i> Assign Elections</button>
                  <button type="button" class="btn btn-warning btn-sm btn-flat" id="bulk_unassign_button" disabled><i class="fa fa-calendar-times-o"></i> Unassign Elections</button>
                  <button type="button" class="btn btn-danger btn-sm btn-flat" id="bulk_delete_button" disabled><i class="fa fa-trash"></i> Delete Selected</button>
                </div>
              </div>
              <div class="table-responsive">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th class="voter-select-cell"><input type="checkbox" id="select_all_voters" aria-label="Select voters on this page"></th>
                    <th>Voter's ID</th>
                    <th>Firstname</th>
                    <th>Lastname</th>
                    <th>Phone (Password)</th>
                    <th>Eligible Election(s)</th>
                    <th>Photo</th>
                    <th>Turnout Status</th>
                    <th>Tools</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    if($filter_election > 0){
                      $sql = "SELECT v.*, 
                                    (CASE WHEN vba.id IS NOT NULL OR legacy.id IS NOT NULL THEN 1 ELSE 0 END) as has_voted,
                                    vba.voted_at
                              FROM voters v
                              INNER JOIN voter_elections ve ON ve.voter_id = v.id AND ve.election_id = ?
                              LEFT JOIN voter_ballot_audit vba ON vba.voter_id = v.id AND vba.election_id = ?
                              LEFT JOIN votes legacy ON legacy.voters_id = v.id AND legacy.election_id = ?
                              GROUP BY v.id
                              ORDER BY v.lastname ASC, v.firstname ASC";
                      $stmt = $conn->execute_query($sql, [$filter_election, $filter_election, $filter_election]);
                    } else {
                      $sql = "SELECT v.*, 
                                    (CASE WHEN vba.id IS NOT NULL OR legacy.id IS NOT NULL THEN 1 ELSE 0 END) as has_voted,
                                    vba.voted_at
                              FROM voters v
                              LEFT JOIN voter_ballot_audit vba ON vba.voter_id = v.id AND vba.election_id = ?
                              LEFT JOIN votes legacy ON legacy.voters_id = v.id AND legacy.election_id = ?
                              GROUP BY v.id
                              ORDER BY v.lastname ASC, v.firstname ASC";
                      $stmt = $conn->execute_query($sql, [$election_id, $election_id]);
                    }

                    while($row = $stmt->fetch_assoc()){
                      $image = (!empty($row['photo'])) ? '../images/'.escape_html($row['photo']) : '../images/profile.jpg';
                      $status_badge = ($row['has_voted'] == 1) 
                        ? '<span class="label label-success"><i class="fa fa-check"></i> Voted</span>' 
                        : '<span class="label label-default">Not Voted</span>';

                      // Fetch assigned elections for this voter
                      $assigned_q = $conn->execute_query(
                        "SELECT e.id, e.title FROM elections e 
                         INNER JOIN voter_elections ve ON ve.election_id = e.id 
                         WHERE ve.voter_id = ?
                         ORDER BY e.title ASC",
                        [(int)$row['id']]
                      );
                      $elec_badges = '';
                      if($assigned_q->num_rows > 0){
                        while($ar = $assigned_q->fetch_assoc()){
                          $elec_badges .= '<span class="label label-primary" style="margin-right: 3px; display: inline-block; margin-bottom: 2px; font-size: 11px;">' . escape_html($ar['title']) . '</span> ';
                        }
                      } else {
                        $elec_badges = '<span class="label label-default" style="font-size: 11px;">No elections assigned</span>';
                      }

                      echo "
                        <tr>
                          <td class='voter-select-cell'><input type='checkbox' class='voter-select' value='".(int)$row['id']."' aria-label='Select voter ".escape_html($row['voters_id'])."'></td>
                          <td><span class='badge bg-blue' style='font-size: 13px; font-family: monospace; font-weight: 600;'>".escape_html($row['voters_id'])."</span></td>
                          <td><b>".escape_html($row['firstname'])."</b></td>
                          <td><b>".escape_html($row['lastname'])."</b></td>
                          <td><code>".escape_html($row['phone'])."</code></td>
                          <td>".$elec_badges."</td>
                          <td>
                            <img src='".$image."' width='30px' height='30px' style='object-fit: cover; border-radius: 50%;'>
                            <a href='#edit_photo' data-toggle='modal' class='pull-right photo' data-id='".(int)$row['id']."'><span class='fa fa-edit'></span></a>
                          </td>
                          <td>".$status_badge."</td>
                          <td>
                            <button class='btn btn-success btn-sm edit btn-flat' data-id='".(int)$row['id']."'><i class='fa fa-edit'></i> Edit</button>
                            <button class='btn btn-danger btn-sm delete btn-flat' data-id='".(int)$row['id']."'><i class='fa fa-trash'></i> Delete</button>
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
  <?php include 'includes/voters_modal.php'; ?>
  <div class="modal fade" id="bulk_assign_modal" tabindex="-1" role="dialog" aria-labelledby="bulk_assign_title">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="voters_bulk_action.php" id="bulk_assign_form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="assign_elections">
          <input type="hidden" name="return_election_id" value="<?php echo (int)$filter_election; ?>">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="bulk_assign_title"><i class="fa fa-calendar-plus-o text-primary"></i> Assign Elections to Selected Voters</h4>
          </div>
          <div class="modal-body">
            <p id="bulk_assign_summary" style="font-weight: 600;"></p>
            <div class="form-group" style="margin-bottom: 15px;">
              <label>Assignment Mode:</label>
              <div class="radio" style="margin-top: 5px;">
                <label><input type="radio" name="assign_mode" value="replace" checked> <b>Replace current assignments</b> with selected elections</label>
              </div>
              <div class="radio">
                <label><input type="radio" name="assign_mode" value="add"> <b>Add selected elections</b> to existing assignments</label>
              </div>
            </div>
            <div style="margin-bottom: 8px;">
              <label>Select Elections to Assign:</label>
              <div class="pull-right">
                <button type="button" class="btn btn-default btn-xs" id="bulk_assign_select_all">Select All</button>
                <button type="button" class="btn btn-default btn-xs" id="bulk_assign_clear_all">Clear</button>
              </div>
            </div>
            <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; background: #fafafa;">
              <?php foreach($all_elections as $el): ?>
                <div class="checkbox" style="margin: 4px 0;">
                  <label><input type="checkbox" class="bulk-assign-cb" name="election_ids[]" value="<?php echo (int)$el['id']; ?>"> <b><?php echo escape_html($el['title']); ?></b> <span class="label label-<?php echo ($el['status'] === 'active') ? 'success' : 'default'; ?>" style="font-size: 10px;"><?php echo strtoupper(escape_html($el['status'])); ?></span></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="bulk-hidden-voters"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-flat" name="bulk_action"><i class="fa fa-save"></i> Apply Assignments</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bulk_unassign_modal" tabindex="-1" role="dialog" aria-labelledby="bulk_unassign_title">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="voters_bulk_action.php" id="bulk_unassign_form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="return_election_id" value="<?php echo (int)$filter_election; ?>">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="bulk_unassign_title"><i class="fa fa-calendar-times-o text-warning"></i> Unassign Elections from Selected Voters</h4>
          </div>
          <div class="modal-body">
            <p id="bulk_unassign_summary" style="font-weight: 600;"></p>
            <div class="form-group" style="margin-bottom: 15px;">
              <label>Unassignment Scope:</label>
              <div class="radio" style="margin-top: 5px;">
                <label><input type="radio" name="action" value="unassign_elections" checked id="unassign_scope_selected"> <b>Unassign selected election(s)</b> only</label>
              </div>
              <div class="radio">
                <label><input type="radio" name="action" value="unassign_all_elections" id="unassign_scope_all"> <b class="text-danger">Unassign from ALL elections</b> (remove all election eligibility)</label>
              </div>
            </div>
            <div id="unassign_election_list_container">
              <div style="margin-bottom: 8px;">
                <label>Select Elections to Unassign:</label>
                <div class="pull-right">
                  <button type="button" class="btn btn-default btn-xs" id="bulk_unassign_select_all">Select All</button>
                  <button type="button" class="btn btn-default btn-xs" id="bulk_unassign_clear_all">Clear</button>
                </div>
              </div>
              <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; background: #fafafa;">
                <?php foreach($all_elections as $el): ?>
                  <div class="checkbox" style="margin: 4px 0;">
                    <label><input type="checkbox" class="bulk-unassign-cb" name="election_ids[]" value="<?php echo (int)$el['id']; ?>"> <b><?php echo escape_html($el['title']); ?></b> <span class="label label-<?php echo ($el['status'] === 'active') ? 'success' : 'default'; ?>" style="font-size: 10px;"><?php echo strtoupper(escape_html($el['status'])); ?></span></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="bulk-hidden-voters"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning btn-flat" name="bulk_action"><i class="fa fa-times"></i> Unassign Elections</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="modal fade" id="bulk_delete_modal" tabindex="-1" role="dialog" aria-labelledby="bulk_delete_title">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="voters_bulk_action.php" id="bulk_delete_form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="return_election_id" value="<?php echo (int)$filter_election; ?>">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="bulk_delete_title">Delete selected voters?</h4>
          </div>
          <div class="modal-body">
            <p id="bulk_delete_summary"></p>
            <p class="text-danger">This action cannot be undone.</p>
            <div class="bulk-hidden-voters"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-flat" name="bulk_action"><i class="fa fa-trash"></i> Delete Voters</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/scripts.php'; ?>
<script>
$(function(){
  var selectedVoters = {};
  var voterTable = $('#example1').DataTable();

  function selectedVoterIds(){
    return Object.keys(selectedVoters).filter(function(id){ return selectedVoters[id]; });
  }

  function refreshVoterSelection(){
    var selectedIds = selectedVoterIds();
    $('#selected_voter_count').text(selectedIds.length);
    $('#bulk_assign_button, #bulk_unassign_button, #bulk_delete_button').prop('disabled', selectedIds.length === 0);
    var visibleChecks = voterTable.rows({page: 'current'}).nodes().to$().find('.voter-select');
    var selectedVisible = visibleChecks.filter(':checked').length;
    $('#select_all_voters').prop('checked', visibleChecks.length > 0 && selectedVisible === visibleChecks.length);
    $('#select_all_voters').prop('indeterminate', selectedVisible > 0 && selectedVisible < visibleChecks.length);
  }

  function prepareBulkForm(formId, summaryId, countText){
    var ids = selectedVoterIds();
    var form = $(formId);
    form.find('.bulk-hidden-voters').empty();
    ids.forEach(function(id){
      $('<input>', {type: 'hidden', name: 'voter_ids[]', value: id}).appendTo(form.find('.bulk-hidden-voters'));
    });
    $(summaryId).text(countText.replace('{count}', ids.length));
  }

  $('#example1').on('change', '.voter-select', function(){
    var id = String(this.value);
    if(this.checked){ selectedVoters[id] = true; }
    else { delete selectedVoters[id]; }
    refreshVoterSelection();
  });

  $('#select_all_voters').on('change', function(){
    var checked = this.checked;
    voterTable.rows({page: 'current'}).nodes().to$().find('.voter-select').each(function(){
      var id = String(this.value);
      this.checked = checked;
      if(checked){ selectedVoters[id] = true; }
      else { delete selectedVoters[id]; }
    });
    refreshVoterSelection();
  });

  $('#select_all_voters').on('click', function(e){
    e.stopPropagation();
  });

  voterTable.on('draw', function(){
    voterTable.rows({page: 'current'}).nodes().to$().find('.voter-select').each(function(){
      this.checked = !!selectedVoters[String(this.value)];
    });
    refreshVoterSelection();
  });

  $('#bulk_assign_button').on('click', function(){
    prepareBulkForm('#bulk_assign_form', '#bulk_assign_summary', 'Assign elections to {count} selected voter(s).');
    $('#bulk_assign_modal').modal('show');
  });

  $('#bulk_unassign_button').on('click', function(){
    prepareBulkForm('#bulk_unassign_form', '#bulk_unassign_summary', 'Unassign elections from {count} selected voter(s).');
    $('#bulk_unassign_modal').modal('show');
  });

  $('#bulk_assign_select_all').on('click', function(){
    $('.bulk-assign-cb').prop('checked', true);
  });
  $('#bulk_assign_clear_all').on('click', function(){
    $('.bulk-assign-cb').prop('checked', false);
  });

  $('#bulk_unassign_select_all').on('click', function(){
    $('.bulk-unassign-cb').prop('checked', true);
  });
  $('#bulk_unassign_clear_all').on('click', function(){
    $('.bulk-unassign-cb').prop('checked', false);
  });

  $('input[name="action"][type="radio"]').on('change', function(){
    if($('#unassign_scope_all').is(':checked')){
      $('#unassign_election_list_container').slideUp();
    } else {
      $('#unassign_election_list_container').slideDown();
    }
  });

  $(document).on('click', '#add_select_all_elecs', function(){
    $('#addnew input[name="elections[]"]').prop('checked', true);
  });
  $(document).on('click', '#add_unassign_all_elecs', function(){
    $('#addnew input[name="elections[]"]').prop('checked', false);
  });

  $(document).on('click', '#edit_select_all_elecs', function(){
    $('#edit .edit-election-cb').prop('checked', true);
  });
  $(document).on('click', '#edit_unassign_all_elecs', function(){
    $('#edit .edit-election-cb').prop('checked', false);
  });

  $('#bulk_delete_button').on('click', function(){
    prepareBulkForm('#bulk_delete_form', '#bulk_delete_summary', 'You are about to permanently delete {count} selected voters and their election assignments.');
    $('#bulk_delete_modal').modal('show');
  });

  $(document).on('click', '.edit', function(e){
    e.preventDefault();
    $('#edit').modal('show');
    var id = $(this).data('id');
    getRow(id);
  });

  $(document).on('click', '.delete', function(e){
    e.preventDefault();
    $('#delete').modal('show');
    var id = $(this).data('id');
    getRow(id);
  });

  $(document).on('click', '.photo', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    getRow(id);
  });

});

function getRow(id){
  $.ajax({
    type: 'POST',
    url: 'voters_row.php',
    data: {id:id},
    dataType: 'json',
    success: function(response){
      $('.id').val(response.id);
      $('#edit_voters_id').val(response.voters_id);
      $('#edit_firstname').val(response.firstname);
      $('#edit_lastname').val(response.lastname);
      $('#edit_phone').val(response.phone);
      $('.fullname').text(response.firstname+' '+response.lastname);

      $('.edit-election-cb').prop('checked', false);
      if(response.elections && response.elections.length > 0){
        response.elections.forEach(function(eid){
          $('#edit_el_' + eid).prop('checked', true);
        });
      }
    }
  });
}
</script>
</body>
</html>
