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
        Positions
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Positions</li>
      </ol>
    </section>
    <!-- Main content -->
    <section class="content">
      <?php
        $active_election = get_active_election($conn);
        $selected_election_id = (int)($active_election['id'] ?? 0);
        $_SESSION['admin_election_id'] = $selected_election_id;
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
          <div class="box">
            <div class="box-header with-border">
              <a href="#addnew" data-toggle="modal" class="btn btn-primary btn-sm btn-flat"><i class="fa fa-plus"></i> New</a>
              <span class="text-muted" style="margin-left: 12px;">Election: <strong><?php echo escape_html($active_election['title'] ?? 'Current Election'); ?></strong></span>
            </div>
            <div class="box-body">
              <div class="bulk-selection-toolbar">
                <label class="bulk-selection-count"><span id="selected_position_count">0</span> selected</label>
                <div class="bulk-selection-actions">
                  <button type="button" class="btn btn-danger btn-sm btn-flat" id="bulk_delete_button" disabled><i class="fa fa-trash"></i> Delete Selected</button>
                </div>
              </div>
              <div class="table-responsive">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                  <th class="voter-select-cell"><input type="checkbox" id="select_all_positions" aria-label="Select positions on this page"></th>
                  <th>Description</th>
                  <th>Maximum Vote</th>
                  <th>Tools</th>
                </thead>
                <tbody>
                  <?php
                    $query = $conn->execute_query(
                      "SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC",
                      [$selected_election_id]
                    );
                    while($row = $query->fetch_assoc()){
                      echo "
                        <tr>
                          <td class='voter-select-cell'><input type='checkbox' class='position-select' value='".(int)$row['id']."' aria-label='Select position ".escape_html($row['description'])."'></td>
                          <td>".escape_html($row['description'])."</td>
                          <td>".(int)$row['max_vote']."</td>
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
  <?php include 'includes/positions_modal.php'; ?>
  <div class="modal fade" id="bulk_delete_modal" tabindex="-1" role="dialog" aria-labelledby="bulk_delete_title">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="positions_bulk_action.php" id="bulk_delete_form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="bulk_delete_title">Delete selected positions?</h4>
          </div>
          <div class="modal-body">
            <p id="bulk_delete_summary" style="font-weight: 600;"></p>
            <p class="text-danger">Deleting selected positions will also delete all candidate profiles and votes associated with them. This action cannot be undone.</p>
            <div class="bulk-hidden-positions"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-flat" name="bulk_action"><i class="fa fa-trash"></i> Delete Positions</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/scripts.php'; ?>
<script>
$(function(){
  var selectedPositions = {};
  var positionTable = $('#example1').DataTable({
    "columnDefs": [
      { "orderable": false, "targets": [0, 3] }
    ]
  });

  function selectedPositionIds(){
    return Object.keys(selectedPositions).filter(function(id){ return selectedPositions[id]; });
  }

  function refreshPositionSelection(){
    var selectedIds = selectedPositionIds();
    $('#selected_position_count').text(selectedIds.length);
    $('#bulk_delete_button').prop('disabled', selectedIds.length === 0);
    var visibleChecks = positionTable.rows({page: 'current'}).nodes().to$().find('.position-select');
    var selectedVisible = visibleChecks.filter(':checked').length;
    $('#select_all_positions').prop('checked', visibleChecks.length > 0 && selectedVisible === visibleChecks.length);
    $('#select_all_positions').prop('indeterminate', selectedVisible > 0 && selectedVisible < visibleChecks.length);
  }

  function prepareBulkForm(formId, summaryId, countText){
    var ids = selectedPositionIds();
    var form = $(formId);
    form.find('.bulk-hidden-positions').empty();
    ids.forEach(function(id){
      $('<input>', {type: 'hidden', name: 'position_ids[]', value: id}).appendTo(form.find('.bulk-hidden-positions'));
    });
    $(summaryId).text(countText.replace('{count}', ids.length));
  }

  $('#example1').on('change', '.position-select', function(){
    var id = String(this.value);
    if(this.checked){ selectedPositions[id] = true; }
    else { delete selectedPositions[id]; }
    refreshPositionSelection();
  });

  $('#select_all_positions').on('change', function(){
    var checked = this.checked;
    positionTable.rows({page: 'current'}).nodes().to$().find('.position-select').each(function(){
      var id = String(this.value);
      this.checked = checked;
      if(checked){ selectedPositions[id] = true; }
      else { delete selectedPositions[id]; }
    });
    refreshPositionSelection();
  });

  $('#select_all_positions').on('click', function(e){
    e.stopPropagation();
  });

  positionTable.on('draw', function(){
    positionTable.rows({page: 'current'}).nodes().to$().find('.position-select').each(function(){
      this.checked = !!selectedPositions[String(this.value)];
    });
    refreshPositionSelection();
  });

  $('#bulk_delete_button').on('click', function(){
    prepareBulkForm('#bulk_delete_form', '#bulk_delete_summary', 'You are about to permanently delete {count} selected position(s) and their records.');
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

});

function getRow(id){
  $.ajax({
    type: 'POST',
    url: 'positions_row.php',
    data: {id:id},
    dataType: 'json',
    success: function(response){
      $('.id').val(response.id);
      $('#edit_description').val(response.description);
      $('#edit_max_vote').val(response.max_vote);
      $('.description').text(response.description);
    }
  });
}
</script>
</body>
</html>
