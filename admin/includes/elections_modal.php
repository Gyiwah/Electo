<!-- Add Election -->
<div class="modal fade" id="addnew_election">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><i class="fa fa-plus-circle text-primary"></i> Create New Election</b></h4>
            </div>
            <div class="modal-body">
              <form class="form-horizontal" method="POST" action="elections_add.php">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="title" class="col-sm-3 control-label">Election Title</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="title" name="title" placeholder="e.g. 2026 Student Council General Election" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description" class="col-sm-3 control-label">Description</label>
                    <div class="col-sm-9">
                      <textarea class="form-control" id="description" name="description" rows="3" placeholder="Election scope, guidelines, or department..."></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label for="status" class="col-sm-3 control-label">Initial Status</label>
                    <div class="col-sm-9">
                      <select class="form-control" id="status" name="status" required>
                        <option value="draft">Draft (scheduled elections open at start time)</option>
                        <option value="active">Active (opens at scheduled start time)</option>
                        <option value="closed">Closed (Voting Ended)</option>
                      </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="start_time" class="col-sm-3 control-label">Voting Opens</label>
                    <div class="col-sm-9">
                      <input type="datetime-local" class="form-control" id="start_time" name="start_time">
                      <small class="text-muted">Times use UTC, matching this PC. A draft with a start time opens automatically then.</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="end_time" class="col-sm-3 control-label">Voting Closes</label>
                    <div class="col-sm-9">
                      <input type="datetime-local" class="form-control" id="end_time" name="end_time">
                      <small class="text-muted">Optional: Leave blank for manual closing.</small>
                    </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">Report Signatories</label>
                  <div class="col-sm-9">
                    <?php for($signatory = 1; $signatory <= 3; $signatory++): ?>
                      <div class="row" style="margin-bottom: 8px;">
                        <div class="col-xs-6"><input type="text" class="form-control" name="signatory_<?php echo $signatory; ?>_name" placeholder="Signatory <?php echo $signatory; ?> name"></div>
                        <div class="col-xs-6"><input type="text" class="form-control" name="signatory_<?php echo $signatory; ?>_role" placeholder="Role / title"></div>
                      </div>
                    <?php endfor; ?>
                    <small class="text-muted">Printed on this election’s certified results.</small>
                  </div>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Cancel</button>
              <button type="submit" class="btn btn-primary btn-flat" name="add"><i class="fa fa-save"></i> Create Election</button>
              </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Election -->
<div class="modal fade" id="edit_election">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><i class="fa fa-edit text-info"></i> Edit Election Configuration</b></h4>
            </div>
            <div class="modal-body">
              <form class="form-horizontal" method="POST" action="elections_edit.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" class="elec_id" name="id">
                <div class="form-group">
                    <label for="edit_title" class="col-sm-3 control-label">Election Title</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_description" class="col-sm-3 control-label">Description</label>
                    <div class="col-sm-9">
                      <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_status" class="col-sm-3 control-label">Status</label>
                    <div class="col-sm-9">
                      <select class="form-control" id="edit_status" name="status" required>
                        <option value="draft">Draft (scheduled elections open at start time)</option>
                        <option value="active">Active (opens at scheduled start time)</option>
                        <option value="closed">Closed (Voting Ended)</option>
                        <option value="archived">Archived (Read Only)</option>
                      </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_start_time" class="col-sm-3 control-label">Voting Opens</label>
                    <div class="col-sm-9">
                      <input type="datetime-local" class="form-control" id="edit_start_time" name="start_time">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_end_time" class="col-sm-3 control-label">Voting Closes</label>
                    <div class="col-sm-9">
                      <input type="datetime-local" class="form-control" id="edit_end_time" name="end_time">
                    </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-3 control-label">Report Signatories</label>
                  <div class="col-sm-9">
                    <?php for($signatory = 1; $signatory <= 3; $signatory++): ?>
                      <div class="row" style="margin-bottom: 8px;">
                        <div class="col-xs-6"><input type="text" class="form-control" id="edit_signatory_<?php echo $signatory; ?>_name" name="signatory_<?php echo $signatory; ?>_name" placeholder="Signatory <?php echo $signatory; ?> name"></div>
                        <div class="col-xs-6"><input type="text" class="form-control" id="edit_signatory_<?php echo $signatory; ?>_role" name="signatory_<?php echo $signatory; ?>_role" placeholder="Role / title"></div>
                      </div>
                    <?php endfor; ?>
                    <small class="text-muted">Printed on this election’s certified results.</small>
                  </div>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Cancel</button>
              <button type="submit" class="btn btn-success btn-flat" name="edit"><i class="fa fa-check-square-o"></i> Update Settings</button>
              </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Election -->
<div class="modal fade" id="delete_election">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><i class="fa fa-trash text-danger"></i> Delete Election</b></h4>
            </div>
            <div class="modal-body">
              <form class="form-horizontal" method="POST" action="elections_delete.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" class="elec_id" name="id">
                <div class="text-center">
                    <p style="font-size: 16px;">Are you sure you want to delete this election?</p>
                    <h3 class="bold elec_name text-danger"></h3>
                    <p class="text-warning"><i class="fa fa-warning"></i> Elections with recorded votes cannot be permanently deleted to preserve democratic audit trails. They will be archived instead.</p>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Cancel</button>
              <button type="submit" class="btn btn-danger btn-flat" name="delete"><i class="fa fa-trash"></i> Delete / Archive</button>
              </form>
            </div>
        </div>
    </div>
</div>
