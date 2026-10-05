<!-- Preview -->
<div class="modal fade" id="preview_modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title">Vote Preview</h4>
            </div>
            <div class="modal-body">
              <div id="preview_body"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Platform -->
<div class="modal fade" id="platform">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><b><span class="candidate"></span></b></h4>
            </div>
            <div class="modal-body">
                <p id="plat_view"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Ballot / Certified Receipt -->
<div class="modal fade" id="view">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title"><i class="fa fa-certificate text-green"></i> <b>Official Ballot Certification</b></h4>
            </div>
            <div class="modal-body">
              <?php
                if(isset($voter['id'])){
                  $elec_id = (int)($election_id ?? 1);
                  $vba_query = $conn->execute_query(
                    "SELECT ballot_token, voted_at FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1",
                    [$elec_id, $voter['id']]
                  );

                  if($vba_query->num_rows > 0){
                    $vba = $vba_query->fetch_assoc();
                    $token = $vba['ballot_token'];
                    $voted_at = $vba['voted_at'];

                    $count_q = $conn->execute_query("SELECT COUNT(*) as cnt FROM votes WHERE ballot_token = ?", [$token]);
                    $votes_cnt = $count_q->fetch_assoc()['cnt'] ?? 0;
                    ?>
                    <div class="callout callout-success" style="border-radius: 4px;">
                      <h4><i class="fa fa-shield"></i> Ballot Verified & Preserved</h4>
                      <p>Your vote was recorded anonymously in the certified election tally.</p>
                    </div>
                    <table class="table table-bordered">
                      <tr>
                        <th style="width: 40%;">Voter Name</th>
                        <td><?php echo escape_html(($voter['firstname'] ?? '') . ' ' . ($voter['lastname'] ?? '')); ?></td>
                      </tr>
                      <tr>
                        <th>Voter ID</th>
                        <td><code><?php echo escape_html($voter['voters_id'] ?? ''); ?></code></td>
                      </tr>
                      <tr>
                        <th>Verification Token</th>
                        <td><span class="label label-primary" style="font-size: 13px; font-family: monospace;"><?php echo escape_html($token); ?></span></td>
                      </tr>
                      <tr>
                        <th>Recorded Selections</th>
                        <td><span class="badge bg-green"><?php echo (int)$votes_cnt; ?> Positions Voted</span></td>
                      </tr>
                      <tr>
                        <th>Timestamp</th>
                        <td><?php echo date('F j, Y - g:i:s A', strtotime($voted_at)); ?></td>
                      </tr>
                      <tr>
                        <th>Secrecy Guarantee</th>
                        <td><span class="text-success"><i class="fa fa-check-circle"></i> Decoupled (Voter ID = Anonymous)</span></td>
                      </tr>
                    </table>
                    <?php
                  }

                  // Also check for legacy direct votes
                  $sql = "SELECT positions.description, candidates.firstname AS canfirst, candidates.lastname AS canlast 
                          FROM votes 
                          LEFT JOIN candidates ON candidates.id = votes.candidate_id 
                          LEFT JOIN positions ON positions.id = votes.position_id 
                          WHERE votes.voters_id = ? 
                          ORDER BY positions.priority ASC";
                  $query = $conn->execute_query($sql, [$voter['id']]);
                  if($query->num_rows > 0){
                    echo "<h5 style='margin-top:20px; font-weight:bold;'>Legacy Recorded Selections:</h5>";
                    while($row = $query->fetch_assoc()){
                      echo "
                        <div class='row votelist'>
                          <span class='col-sm-4'><span class='pull-right'><b>".escape_html($row['description'])." :</b></span></span> 
                          <span class='col-sm-8'>".escape_html($row['canfirst']." ".$row['canlast'])."</span>
                        </div>
                      ";
                    }
                  }
                }
              ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-flat pull-left" data-dismiss="modal"><i class="fa fa-close"></i> Close</button>
              <a href="ballot_receipt.php" class="btn btn-success btn-flat"><i class="fa fa-print"></i> Printable Receipt Card</a>
            </div>
        </div>
    </div>
</div>

