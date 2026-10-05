<?php include 'includes/session.php'; ?>
<?php
	$voter_id = (int)$voter['id'];
	$eligible_elections = get_voter_eligible_elections($conn, $voter_id);
	$eligible_ids = array_map('intval', array_column($eligible_elections, 'id'));
	if(isset($_GET['election_id'])){
		$requested_election_id = (int)$_GET['election_id'];
		if(!in_array($requested_election_id, $eligible_ids, true)){
			$_SESSION['error'] = ['You are not assigned to that election.'];
			safe_redirect('elections_select.php');
		}
		$_SESSION['voter_election_id'] = $requested_election_id;
	} elseif(!empty($_SESSION['voter_election_id']) && in_array((int)$_SESSION['voter_election_id'], $eligible_ids, true)){
		$_SESSION['voter_election_id'] = (int)$_SESSION['voter_election_id'];
	} elseif(count($eligible_ids) === 1){
		$_SESSION['voter_election_id'] = $eligible_ids[0];
	} else {
		unset($_SESSION['voter_election_id']);
		safe_redirect('elections_select.php');
	}
?>
<?php include 'includes/header.php'; ?>
<body class="hold-transition skin-blue layout-top-nav">
<div class="wrapper">

	<?php include 'includes/navbar.php'; ?>
	 
	  <div class="content-wrapper">
	    <div class="container">

	      <!-- Main content -->
	      <section class="content">
	      	<?php
	      		$active_election = get_active_election($conn, (int)$_SESSION['voter_election_id']);
	      		$title = $active_election['title'] ?? 'ELECTION';
	      		$election_id = (int)($active_election['id'] ?? 1);
	      		$election_status = $active_election['status'] ?? 'active';
	      		$schedule = get_election_schedule_status($active_election);
	      		$next_schedule_refresh_at = null;
	      		if($schedule['state'] === 'scheduled' && !empty($active_election['start_time'])){
	      			$next_schedule_refresh_at = strtotime($active_election['start_time']);
	      		} elseif($schedule['is_open'] && !empty($active_election['end_time'])){
	      			$next_schedule_refresh_at = strtotime($active_election['end_time']);
	      		}
	      	?>
	      	<h1 class="page-header text-center title"><b><?php echo escape_html(strtoupper($title)); ?></b></h1>
	        <div class="row">
	        	<div class="col-sm-10 col-sm-offset-1">
	        		<?php
				        if(isset($_SESSION['error'])){
				        	?>
				        	<div class="alert alert-danger alert-dismissible">
				        		<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
					        	<ul>
					        		<?php
					        			foreach($_SESSION['error'] as $error){
					        				echo "
					        					<li>".escape_html($error)."</li>
					        				";
					        			}
					        		?>
					        	</ul>
					        </div>
				        	<?php
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
				    	// Check voter participation in voter_ballot_audit or legacy votes
				    	$audit_query = $conn->execute_query(
				    		"SELECT id, ballot_token, voted_at FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1",
				    		[$election_id, $voter['id']]
				    	);
				    	$has_voted = false;
				    	$ballot_receipt = $_SESSION['ballot_receipt'] ?? null;
				    	$voted_at = null;

				    	if($audit_query->num_rows > 0){
				    		$has_voted = true;
				    		$audit_row = $audit_query->fetch_assoc();
				    		$ballot_receipt = $ballot_receipt ?: $audit_row['ballot_token'];
				    		$voted_at = $audit_row['voted_at'];
				    	} else {
				    		// Check legacy votes table
	    							$legacy_q = $conn->execute_query("SELECT id FROM votes WHERE voters_id = ? AND election_id = ? LIMIT 1", [$voter['id'], $election_id]);
				    		if($legacy_q->num_rows > 0){
				    			$has_voted = true;
				    		}
				    	}

				    	if($has_voted){
				    		?>
				    		<div class="box box-solid box-success" style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
				    			<div class="box-header with-border" style="background: linear-gradient(135deg, #00a65a, #008d4c); color: #fff; padding: 20px; border-radius: 8px 8px 0 0;">
				    				<div class="text-center">
				    					<i class="fa fa-check-circle" style="font-size: 48px; margin-bottom: 10px;"></i>
				    					<h2 style="margin: 0; font-weight: 700;">Ballot Certified & Cast</h2>
				    					<p style="margin: 5px 0 0 0; opacity: 0.9;">Your vote has been officially counted in the election tally.</p>
				    				</div>
				    			</div>
				    			<div class="box-body" style="padding: 30px;">
				    				<div class="row">
				    					<div class="col-md-6 col-md-offset-3 text-center">
				    						<?php if(!empty($ballot_receipt)): ?>
				    							<div style="background: #f8fafc; border: 2px dashed #00a65a; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
				    								<p style="margin: 0 0 5px 0; color: #64748b; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Cryptographic Receipt Token</p>
				    								<code style="font-size: 20px; font-weight: 700; color: #0f172a; background: transparent; letter-spacing: 2px;"><?php echo escape_html($ballot_receipt); ?></code>
				    								<?php if(!empty($voted_at)): ?>
				    									<div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">
				    										Cast on: <?php echo date('M d, Y - h:i A', strtotime($voted_at)); ?>
				    									</div>
				    								<?php endif; ?>
				    							</div>
				    						<?php endif; ?>

				    						<div class="well well-sm text-left" style="background: #f1f5f9; border: none; border-radius: 6px; font-size: 13px; color: #475569;">
				    							<i class="fa fa-shield text-success"></i> <b>Secret Ballot Guarantee:</b>
				    							Your individual selections have been anonymized into the ballot box. Your identity cannot be traced back to candidate votes, protecting your voter privacy while ensuring cryptographic verifiability.
				    						</div>

				    						<div style="margin-top: 25px;">
				    							<a href="#view" data-toggle="modal" class="btn btn-primary btn-lg btn-flat" style="border-radius: 4px; padding: 10px 25px;">
				    								<i class="fa fa-search"></i> View Receipt Details
				    							</a>
				    							<a href="ballot_receipt.php" class="btn btn-default btn-lg btn-flat" style="border-radius: 4px; padding: 10px 25px; margin-left: 10px;">
				    								<i class="fa fa-print"></i> Receipt Card
				    							</a>
				    						</div>
				    					</div>
				    				</div>
				    			</div>
				    		</div>
				    		<?php
				    	}
	    					elseif($schedule['state'] === 'closed' || $schedule['state'] === 'ended'){
				    		?>
				    		<div class="box box-solid box-warning text-center" style="border-radius: 8px; padding: 40px 20px;">
				    			<i class="fa fa-lock" style="font-size: 48px; color: #f39c12; margin-bottom: 15px;"></i>
				    			<h2 style="font-weight: 700; color: #333;">Voting is Closed</h2>
				    			<p style="font-size: 16px; color: #666; max-width: 600px; margin: 0 auto 20px auto;">
				    				This election is officially closed. Ballots are no longer being accepted. Contact the election administrator for certified results.
				    			</p>
				    		</div>
				    		<?php
				    	}
	    					elseif(!$schedule['is_open']){
				    		?>
				    		<div class="box box-solid box-info text-center" style="border-radius: 8px; padding: 40px 20px;">
				    			<i class="fa fa-clock-o" style="font-size: 48px; color: #00c0ef; margin-bottom: 15px;"></i>
				    			<h2 style="font-weight: 700; color: #333;">Election Not Yet Open</h2>
				    			<p style="font-size: 16px; color: #666; max-width: 600px; margin: 0 auto 20px auto;">
				    				This election is currently in preparation and will open for voting soon. Please check back when voting officially begins.
				    			</p>
				    		</div>
				    		<?php
				    	}
				    	else{
				    		?>
			    			<!-- Voting Ballot -->
						    <form method="POST" id="ballotForm" action="submit_ballot.php">
								<?php echo csrf_field(); ?>
				        		<?php
				        			include 'includes/slugify.php';

				        			$candidate = '';
									$query = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);
									while($row = $query->fetch_assoc()){
										$cquery = $conn->execute_query("SELECT * FROM candidates WHERE position_id = ?", [$row['id']]);
										$candidate_rows = $cquery->fetch_all(MYSQLI_ASSOC);
										$slug = slugify($row['description']);
										if(count($candidate_rows) === 1){
											$unopposed = $candidate_rows[0];
											$yes_value = 'yes:' . (int)$unopposed['id'];
											$posted_value = $_SESSION['post'][$slug] ?? '';
											$yes_checked = ($posted_value === $yes_value) ? 'checked' : '';
											$no_checked = ($posted_value === 'no') ? 'checked' : '';
											$image = !empty($unopposed['photo']) ? 'images/'.escape_html($unopposed['photo']) : 'images/profile.jpg';
											$fullname = escape_html($unopposed['firstname'].' '.$unopposed['lastname']);
											$candidate = '<li class="unopposed-question"><img src="'.$image.'" alt=""><span>Do you approve of <b>'.$fullname.'</b>?</span></li>';
											$candidate .= '<li><label class="candidate-card-touch yes-no-choice '.($yes_checked ? 'selected-card' : '').'"><input type="radio" class="flat-red '.$slug.'" name="'.$slug.'" value="'.$yes_value.'" '.$yes_checked.'><span>Yes</span></label></li>';
											$candidate .= '<li><label class="candidate-card-touch yes-no-choice '.($no_checked ? 'selected-card' : '').'"><input type="radio" class="flat-red '.$slug.'" name="'.$slug.'" value="no" '.$no_checked.'><span>No</span></label></li>';
										} else {
										foreach($candidate_rows as $crow){
											$checked = '';
											if(isset($_SESSION['post'][$slug])){
												$value = $_SESSION['post'][$slug];
												if(is_array($value)){
													foreach($value as $val){
														if($val == $crow['id']) $checked = 'checked';
													}
												} elseif($value == $crow['id']) {
													$checked = 'checked';
												}
											}
											$input = ($row['max_vote'] > 1) ? '<input type="checkbox" class="flat-red '.$slug.'" name="'.$slug.'[]" value="'.(int)$crow['id'].'" '.$checked.'>' : '<input type="radio" class="flat-red '.$slug.'" name="'.$slug.'" value="'.(int)$crow['id'].'" '.$checked.'>';
											$image = (!empty($crow['photo'])) ? 'images/'.escape_html($crow['photo']) : 'images/profile.jpg';
											$candidate .= '
												<li>
													<div class="candidate-card-touch '.($checked ? 'selected-card' : '').'">
														<div style="flex-shrink: 0;">'.$input.'</div>
														<img src="'.$image.'" class="candidate-touch-avatar" alt="'.escape_html($crow['firstname']).'">
														<div class="candidate-touch-info">
															<div class="candidate-touch-name">'.escape_html($crow['firstname'].' '.$crow['lastname']).'</div>
															'.(!empty($crow['platform']) ? '<button type="button" class="btn btn-xs platform" data-platform="'.escape_html($crow['platform']).'" data-fullname="'.escape_html($crow['firstname'].' '.$crow['lastname']).'" style="margin-top: 5px; border-radius: 4px;"><i class="fa fa-align-left"></i> View Platform</button>' : '').'
														</div>
													</div>
												</li>
											';
										}
										}

										$instruct = (count($candidate_rows) === 1) ? '<i class="fa fa-info-circle text-primary"></i> Select Yes or No' : (($row['max_vote'] > 1) ? '<i class="fa fa-info-circle text-primary"></i> You may select up to <b>'.(int)$row['max_vote'].'</b> candidates' : '<i class="fa fa-info-circle text-primary"></i> Select only <b>one</b> candidate');

										echo '
											<div class="row">
												<div class="col-xs-12">
													<div class="box box-solid" id="'.(int)$row['id'].'" style="border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px;">
														<div class="box-header with-border" style="padding: 16px 20px;">
															<h3 class="box-title" style="font-weight: 800; color: #0f172a; font-size: 18px;">'.escape_html($row['description']).'</h3>
															<div class="box-tools pull-right">
																<button type="button" class="btn btn-default btn-xs btn-flat reset" data-desc="'.$slug.'" style="border-radius: 6px; padding: 4px 10px;"><i class="fa fa-refresh"></i> Clear Selection</button>
															</div>
														</div>
														<div class="box-body" style="padding: 20px;">
															<p style="color: #64748b; font-size: 13px; margin-bottom: 12px;">'.$instruct.'</p>
															<div id="candidate_list">
																<ul class="candidate-touch-grid">
																	'.$candidate.'
																</ul>
															</div>
														</div>
													</div>
												</div>
											</div>
										';

										$candidate = '';

									}	

				        		?>
				        		<div class="text-center" style="margin: 40px 0 60px 0;">
					        		<button type="button" class="btn btn-default btn-lg btn-flat" id="preview" style="border-radius: 8px; padding: 12px 30px; font-weight: 700; margin-right: 15px;"><i class="fa fa-file-text"></i> Preview Selections</button> 
					        		<button type="submit" class="btn btn-primary btn-lg btn-flat" name="vote" style="border-radius: 8px; padding: 12px 40px; font-weight: 800; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; box-shadow: 0 4px 15px rgba(37,99,235,0.3);"><i class="fa fa-check-square-o"></i> Submit Secret Ballot</button>
					        	</div>

								<!-- Mobile-First Sticky Action Bar -->
								<div class="mobile-sticky-vote-bar hidden-sm hidden-md hidden-lg">
									<div style="display: flex; align-items: center; gap: 8px;">
										<span id="mobile_vote_count" class="badge" style="background: #2563eb; font-size: 13px; padding: 4px 9px;">0</span>
										<span style="font-size: 12px; color: #475569; font-weight: 700;">Selected</span>
									</div>
									<div style="display: flex; gap: 8px;">
										<button type="button" class="btn btn-default btn-sm btn-mobile-preview" style="font-weight: 700; border-radius: 8px;">
											<i class="fa fa-eye"></i> Preview
										</button>
										<button type="submit" class="btn btn-primary btn-sm" name="vote" style="font-weight: 800; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
											<i class="fa fa-check-square-o"></i> Submit Ballot
										</button>
									</div>
								</div>
				        	</form>
				        	<!-- End Voting Ballot -->
				    		<?php
				    	}

				    ?>

	        	</div>
	        </div>
	      </section>
	     
	    </div>
	  </div>
  
  	<?php include 'includes/footer.php'; ?>
  	<?php include 'includes/ballot_modal.php'; ?>
</div>

<?php include 'includes/scripts.php'; ?>
<script>
$(function(){
	$('.content').iCheck({
		checkboxClass: 'icheckbox_flat-green',
		radioClass: 'iradio_flat-green'
	});

	// Sync card active states and mobile vote count badge
	function updateCardSelections() {
		var totalSelected = 0;
		$('input.flat-red').each(function(){
			var card = $(this).closest('.candidate-card-touch');
			if($(this).is(':checked')){
				card.addClass('selected-card');
				totalSelected++;
			} else {
				card.removeClass('selected-card');
			}
		});
		$('#mobile_vote_count').text(totalSelected);
	}

	// Update on iCheck events
	$('input.flat-red').on('ifChanged', function(){
		updateCardSelections();
	});

	// Tap anywhere on card to select
	$(document).on('click', '.candidate-card-touch', function(e){
		if($(e.target).is('button') || $(e.target).closest('button').length) return;
		if($(e.target).is('ins') || $(e.target).is('input')) return; // let iCheck handle direct clicks

		var inp = $(this).find('input.flat-red');
		if(inp.attr('type') === 'checkbox'){
			inp.iCheck('toggle');
		} else {
			inp.iCheck('check');
		}
	});

	// Reset button handler
	$(document).on('click', '.reset', function(e){
	    e.preventDefault();
	    var desc = $(this).data('desc');
	    $('.'+desc).iCheck('uncheck');
		setTimeout(updateCardSelections, 50);
	});

	$(document).on('click', '.platform', function(e){
		e.preventDefault();
		$('#platform').modal('show');
		var platform = $(this).data('platform');
		var fullname = $(this).data('fullname');
		$('.candidate').html(fullname);
		$('#plat_view').html(platform);
	});

	// Mobile preview trigger
	$(document).on('click', '.btn-mobile-preview', function(e){
		e.preventDefault();
		$('#preview').trigger('click');
	});

	$('#preview').click(function(e){
		e.preventDefault();
		var form = $('#ballotForm').serialize();
		if(form == ''){
			window.showToast('error', 'You must vote for at least one candidate.');
		}
		else{
			$.ajax({
				type: 'POST',
				url: 'preview.php',
				data: form,
				dataType: 'json',
				success: function(response){
					if(response.error){
						var errmsg = '';
						var messages = response.message;
						for (i in messages) {
							errmsg += messages[i]; 
						}
						window.showToast('error', errmsg);
					}
					else{
						$('#preview_modal').modal('show');
						$('#preview_body').html(response.list);
					}
				}
			});
		}
	});

	// Initial selection count
	updateCardSelections();
});
</script>
<?php if($next_schedule_refresh_at !== null && $next_schedule_refresh_at > time()): ?>
<script>window.setTimeout(function(){ window.location.reload(); }, <?php echo max(1000, min(2147483647, ($next_schedule_refresh_at - time() + 1) * 1000)); ?>);</script>
<?php endif; ?>
</body>
</html>