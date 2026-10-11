<?php
  $modal_elecs = get_all_elections($conn);
  $curr_active_elec = get_active_election($conn);
  $curr_active_id = (int)($curr_active_elec['id'] ?? 0);
?>
<!-- Add -->
<div class="modal fade" id="addnew">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b>Add New Voter</b></h4>
            </div>
            <form class="form-horizontal" method="POST" action="voters_add.php" enctype="multipart/form-data">
              <?php echo csrf_field(); ?>
              <div class="modal-body">
                <div class="form-group">
                    <label for="firstname" class="col-sm-3 control-label">Firstname</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="firstname" name="firstname" placeholder="Enter first name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="lastname" class="col-sm-3 control-label">Lastname</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Enter last name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="phone" class="col-sm-3 control-label">Phone</label>
                    <div class="col-sm-9">
                      <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g. 0244123456" required>
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">
                        <i class="fa fa-info-circle text-primary"></i> The phone number serves as the voter's login password and receives the access OTP.
                      </span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="voters_id" class="col-sm-3 control-label">Voter's ID</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="voters_id" name="voters_id" placeholder="Leave blank to auto-generate unique Voter ID">
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">Optional custom ID (e.g. STU2026001). Auto-generated if left blank.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Eligible Election(s)</label>
                    <div class="col-sm-9">
                      <div style="margin-bottom: 8px;">
                        <button type="button" class="btn btn-default btn-xs" id="add_select_all_elecs"><i class="fa fa-check-square-o"></i> Select All</button>
                        <button type="button" class="btn btn-default btn-xs" id="add_unassign_all_elecs" style="margin-left: 5px;"><i class="fa fa-square-o"></i> Unassign All</button>
                      </div>
                      <?php foreach($modal_elecs as $me): ?>
                        <div class="checkbox" style="padding-top: 0;">
                          <label>
                            <input type="checkbox" name="elections[]" value="<?php echo (int)$me['id']; ?>" <?php echo ((int)$me['id'] === $curr_active_id) ? 'checked' : ''; ?>>
                            <b><?php echo escape_html($me['title']); ?></b> 
                            <span class="label label-<?php echo ($me['status'] === 'active') ? 'success' : 'default'; ?>" style="font-size: 10px;"><?php echo strtoupper($me['status']); ?></span>
                          </label>
                        </div>
                      <?php endforeach; ?>
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">Check the election(s) this voter is permitted to vote in.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="photo" class="col-sm-3 control-label">Photo</label>
                    <div class="col-sm-9">
                      <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
                <button type="submit" class="btn btn-primary btn-flat" name="add"><i class="fa fa-save"></i> Save</button>
              </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit -->
<div class="modal fade" id="edit">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b>Edit Voter Details</b></h4>
            </div>
            <form class="form-horizontal" method="POST" action="voters_edit.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" class="id" name="id">
              <input type="hidden" name="elections_submitted" value="1">
              <div class="modal-body">
                <div class="form-group">
                    <label for="edit_voters_id" class="col-sm-3 control-label">Voter's ID</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="edit_voters_id" name="voters_id" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_firstname" class="col-sm-3 control-label">Firstname</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_lastname" class="col-sm-3 control-label">Lastname</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_phone" class="col-sm-3 control-label">Phone</label>
                    <div class="col-sm-9">
                      <input type="tel" class="form-control" id="edit_phone" name="phone" placeholder="e.g. 0244123456" required>
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">
                        <i class="fa fa-info-circle text-primary"></i> Serves as password and receives voting login OTP.
                      </span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Eligible Election(s)</label>
                    <div class="col-sm-9" id="edit_elections_container">
                      <div style="margin-bottom: 8px;">
                        <button type="button" class="btn btn-default btn-xs" id="edit_select_all_elecs"><i class="fa fa-check-square-o"></i> Select All</button>
                        <button type="button" class="btn btn-default btn-xs" id="edit_unassign_all_elecs" style="margin-left: 5px;"><i class="fa fa-square-o"></i> Unassign All</button>
                      </div>
                      <?php foreach($modal_elecs as $me): ?>
                        <div class="checkbox" style="padding-top: 0;">
                          <label>
                            <input type="checkbox" class="edit-election-cb" name="elections[]" value="<?php echo (int)$me['id']; ?>" id="edit_el_<?php echo (int)$me['id']; ?>">
                            <b><?php echo escape_html($me['title']); ?></b>
                            <span class="label label-<?php echo ($me['status'] === 'active') ? 'success' : 'default'; ?>" style="font-size: 10px;"><?php echo strtoupper($me['status']); ?></span>
                          </label>
                        </div>
                      <?php endforeach; ?>
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">Specify which elections this voter is allowed to vote in.</span>
                    </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
                <button type="submit" class="btn btn-success btn-flat" name="edit"><i class="fa fa-check-square-o"></i> Update</button>
              </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete -->
<div class="modal fade" id="delete">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b>Deleting...</b></h4>
            </div>
            <form class="form-horizontal" method="POST" action="voters_delete.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" class="id" name="id">
              <div class="modal-body">
                <div class="text-center">
                    <p>DELETE VOTER</p>
                    <h2 class="bold fullname"></h2>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
                <button type="submit" class="btn btn-danger btn-flat" name="delete"><i class="fa fa-trash"></i> Delete</button>
              </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Photo -->
<div class="modal fade" id="edit_photo">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><span class="fullname"></span></b></h4>
            </div>
            <form class="form-horizontal" method="POST" action="voters_photo.php" enctype="multipart/form-data">
              <?php echo csrf_field(); ?>
              <input type="hidden" class="id" name="id">
              <div class="modal-body">
                <div class="form-group">
                    <label for="photo" class="col-sm-3 control-label">Photo</label>
                    <div class="col-sm-9">
                      <input type="file" id="photo" name="photo" accept="image/*" required>
                    </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
                <button type="submit" class="btn btn-success btn-flat" name="upload"><i class="fa fa-check-square-o"></i> Update</button>
              </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Import CSV -->
<div class="modal fade" id="import_csv">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><i class="fa fa-file-excel-o text-green"></i> Bulk Import Voters from CSV</b></h4>
            </div>
            <form class="form-horizontal" method="POST" action="voters_import.php" enctype="multipart/form-data">
              <?php echo csrf_field(); ?>
              <div class="modal-body">
                <div class="callout callout-info" style="font-size: 13px;">
                  <h4><i class="fa fa-info-circle"></i> CSV File Format Requirements</h4>
                  <p>Your CSV should contain columns in the following order: <br>
                  <code>Firstname, Lastname, Phone, [VoterID]</code></p>
                  <ul>
                    <li><b>Firstname & Lastname:</b> Required.</li>
                    <li><b>Phone:</b> Required (used as the voter's password and receives login OTP via SMS).</li>
                    <li><b>VoterID:</b> Optional. If omitted, a unique alphanumeric ID will be auto-generated.</li>
                  </ul>
                  <p>A downloadable credentials roster with login credentials will be generated after import.</p>
                </div>
                <div class="form-group">
                    <label for="import_election_id" class="col-sm-3 control-label">Target Election</label>
                    <div class="col-sm-9">
                      <select class="form-control" name="import_election_id" id="import_election_id">
                        <option value="0">-- All Active Elections --</option>
                        <?php foreach($modal_elecs as $me): ?>
                          <option value="<?php echo (int)$me['id']; ?>" <?php echo ($me['id'] == $curr_active_id) ? 'selected' : ''; ?>>
                            <?php echo escape_html($me['title']); ?> (<?php echo strtoupper($me['status']); ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <span class="help-block" style="font-size: 12px; margin-bottom: 0; color: #64748b;">Assign imported voters to this specific election, or select All Active Elections.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="csv_file" class="col-sm-3 control-label">Select CSV File</label>
                    <div class="col-sm-9">
                      <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                      <div class="checkbox">
                        <label>
                          <input type="checkbox" name="has_header" value="1" checked> First row is column header (skip row 1)
                        </label>
                      </div>
                    </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Cancel</button>
                <button type="submit" class="btn btn-success btn-flat" name="import"><i class="fa fa-upload"></i> Process & Import Voters</button>
              </div>
            </form>
        </div>
    </div>
</div>