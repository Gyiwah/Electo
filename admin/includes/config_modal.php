<!-- Config -->
<div class="modal fade" id="config">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><i class="fa fa-pencil-square-o text-primary"></i> Edit Election Title</b></h4>
            </div>
            <?php
              $modal_election = get_active_election($conn);
              $modal_title = $modal_election['title'] ?? 'Election';
              $modal_eid = (int)$modal_election['id'];
              $all_elecs = get_all_elections($conn);
            ?>
            <form class="form-horizontal" method="POST" action="config_save.php?return=<?php echo urlencode(basename($_SERVER['PHP_SELF'])); ?>">
              <?php echo csrf_field(); ?>
              <div class="modal-body">
                <div class="form-group">
                  <label for="config_election_id" class="col-sm-3 control-label">Election</label>
                  <div class="col-sm-9">
                    <select class="form-control" id="config_election_id" name="election_id" required>
                      <?php foreach($all_elecs as $el): ?>
                        <option value="<?php echo (int)$el['id']; ?>" data-title="<?php echo escape_html($el['title']); ?>" <?php echo ($el['id'] == $modal_eid) ? 'selected' : ''; ?>>
                          <?php echo escape_html($el['title']); ?> (<?php echo strtoupper($el['status']); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label for="config_title" class="col-sm-3 control-label">Election Title</label>
                  <div class="col-sm-9">
                    <input type="text" class="form-control" id="config_title" name="title" value="<?php echo escape_html($modal_title); ?>" required>
                    <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">
                      Updates the official title of the selected election across the entire platform.
                    </span>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
                <button type="submit" class="btn btn-success btn-flat" name="save"><i class="fa fa-save"></i> Save Title</button>
              </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var sel = document.getElementById('config_election_id');
  var inp = document.getElementById('config_title');
  if(sel && inp){
    sel.addEventListener('change', function(){
      var opt = sel.options[sel.selectedIndex];
      if(opt && opt.getAttribute('data-title')){
        inp.value = opt.getAttribute('data-title');
      }
    });
  }
});
</script>