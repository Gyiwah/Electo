<!-- Reset Votes Modal -->
<div class="modal fade" id="reset">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-red">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><i class="fa fa-warning"></i> <b>Reset Ballot Box & Tallies</b></h4>
            </div>
            <form method="POST" action="votes_reset.php" class="form-horizontal">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                  <div class="callout callout-danger" style="margin-bottom: 20px;">
                      <h4><i class="fa fa-exclamation-triangle"></i> High-Risk Administrative Action</h4>
                      <p>Resetting will delete cast ballots and participation logs for the selected election. This action is permanently recorded in the system audit logs.</p>
                  </div>

                  <div class="form-group">
                    <label for="reset_election_id" class="col-sm-4 control-label">Target Election:</label>
                    <div class="col-sm-8">
                      <select name="election_id" id="reset_election_id" class="form-control" required>
                        <option value="current">Selected Election Only (Recommended)</option>
                        <option value="all">ALL Elections (Complete Purge)</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="admin_password" class="col-sm-4 control-label">Admin Password:</label>
                    <div class="col-sm-8">
                      <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Enter your current password to confirm" required autocomplete="current-password">
                      <small class="text-muted">Password verification is required to authorize ballot box resets.</small>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Cancel</button>
                  <button type="submit" name="reset" class="btn btn-danger btn-flat"><i class="fa fa-trash"></i> Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>