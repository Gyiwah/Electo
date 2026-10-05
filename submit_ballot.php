<?php
	include 'includes/session.php';
	include 'includes/slugify.php';

	if(isset($_POST['vote'])){
		if(!verify_csrf_token()){
			$_SESSION['error'][] = 'Security token invalid or expired. Please try submitting again.';
			safe_redirect('home.php');
		}

		$voter_id = (int)$voter['id'];
		$election_id = (int)($_SESSION['voter_election_id'] ?? 0);
		if($election_id <= 0 || !is_voter_eligible_for_election($conn, $voter_id, $election_id)){
			$_SESSION['error'][] = 'Select an election you are eligible to vote in.';
			safe_redirect('elections_select.php');
		}
		$active_election = get_active_election($conn, $election_id);
		$schedule = get_election_schedule_status($active_election);
		if(!$schedule['is_open']){
			$_SESSION['error'][] = $schedule['message'];
			safe_redirect('home.php');
		}

		// Double-voting check against voter_ballot_audit table
		$check = $conn->execute_query(
			"SELECT id FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1",
			[$election_id, $voter_id]
		);
		if($check->num_rows > 0){
			$_SESSION['error'][] = 'You have already voted in this election.';
			safe_redirect('home.php');
		}

		// Count submitted votes excluding CSRF and button fields
		$submitted_positions = array_filter($_POST, function($key){
			return !in_array($key, ['vote', 'csrf_token'], true);
		}, ARRAY_FILTER_USE_KEY);

		if(empty($submitted_positions)){
			$_SESSION['error'][] = 'Please vote for at least one candidate.';
			safe_redirect('home.php');
		}

		$_SESSION['post'] = $_POST;
		$positions_query = $conn->execute_query(
			"SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC",
			[$election_id]
		);
		$has_error = false;
		$votes_to_insert = [];

		while($pos = $positions_query->fetch_assoc()){
			$slug = slugify($pos['description']);
			$pos_id = (int)$pos['id'];
			$max_vote = (int)$pos['max_vote'];
			$candidate_count = (int)$conn->execute_query("SELECT COUNT(*) AS cnt FROM candidates WHERE position_id = ?", [$pos_id])->fetch_assoc()['cnt'];

			if(isset($_POST[$slug]) && !empty($_POST[$slug])){
				$selections = $_POST[$slug];

				if($candidate_count === 1){
					$single_candidate_id = (int)$conn->execute_query("SELECT id FROM candidates WHERE position_id = ? LIMIT 1", [$pos_id])->fetch_assoc()['id'];
					$choice = is_string($selections) ? $selections : '';
					if($choice === 'no'){
						$votes_to_insert[] = ['candidate_id' => 0, 'position_id' => $pos_id];
					} elseif($choice === 'yes:' . $single_candidate_id){
						$votes_to_insert[] = ['candidate_id' => $single_candidate_id, 'position_id' => $pos_id];
					} else {
						$has_error = true;
						$_SESSION['error'][] = 'Choose Yes or No for ' . escape_html($pos['description']) . '.';
					}
				} elseif($max_vote > 1){
					if(!is_array($selections)){
						$selections = [$selections];
					}
					$selections = array_unique($selections);

					if(count($selections) > $max_vote){
						$has_error = true;
						$_SESSION['error'][] = 'You can only choose up to ' . $max_vote . ' candidates for ' . escape_html($pos['description']) . '.';
					} else {
						foreach($selections as $cid){
							$cid = (int)$cid;
							$valid_cand = $conn->execute_query("SELECT id FROM candidates WHERE id = ? AND position_id = ?", [$cid, $pos_id]);
							if($valid_cand->num_rows > 0){
								$votes_to_insert[] = ['candidate_id' => $cid, 'position_id' => $pos_id];
							} else {
								$has_error = true;
								$_SESSION['error'][] = 'Invalid candidate selected for ' . escape_html($pos['description']) . '.';
							}
						}
					}
				} else {
					$cid = (int)$selections;
					$valid_cand = $conn->execute_query("SELECT id FROM candidates WHERE id = ? AND position_id = ?", [$cid, $pos_id]);
					if($valid_cand->num_rows > 0){
						$votes_to_insert[] = ['candidate_id' => $cid, 'position_id' => $pos_id];
					} else {
						$has_error = true;
						$_SESSION['error'][] = 'Invalid candidate selected for ' . escape_html($pos['description']) . '.';
					}
				}
			}
		}

		if(empty($votes_to_insert) && !$has_error){
			$_SESSION['error'][] = 'Please select at least one valid candidate.';
			$has_error = true;
		}

		if(!$has_error){
			// Generate cryptographic ballot receipt token
			$receipt_token = 'VOTE-' . strtoupper(bin2hex(random_bytes(6)));

			// Atomic database transaction
			$conn->begin_transaction();
			try {
				// Re-verify double-voting inside transaction to prevent race conditions
				$recheck = $conn->execute_query(
					"SELECT id FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1 FOR UPDATE",
					[$election_id, $voter_id]
				);
				if($recheck->num_rows > 0){
					throw new Exception('You have already voted in this election.');
				}

				// 1. Record voter participation (Audit Log)
				$conn->execute_query(
					"INSERT INTO voter_ballot_audit (election_id, voter_id, ballot_token) VALUES (?, ?, ?)",
					[$election_id, $voter_id, $receipt_token]
				);

				// 2. Cast anonymous votes into the ballot box (0 for voters_id guarantees secret ballot)
				$insert_stmt = $conn->prepare("INSERT INTO votes (election_id, voters_id, candidate_id, position_id, ballot_token) VALUES (?, 0, ?, ?, ?)");
				foreach($votes_to_insert as $vote_row){
					$insert_stmt->bind_param('iiis', $election_id, $vote_row['candidate_id'], $vote_row['position_id'], $receipt_token);
					$insert_stmt->execute();
				}

				// 3. System Audit Log
				log_audit($conn, 'voter', $voter_id, 'BALLOT_CAST', "Voter cast ballot with receipt $receipt_token for election $election_id");

				$conn->commit();
				unset($_SESSION['post']);
				$_SESSION['ballot_receipt'] = $receipt_token;
				$_SESSION['success'] = 'Ballot Submitted Successfully! Your Official Verification Receipt Code is: ' . $receipt_token;
			} catch (Exception $e) {
				$conn->rollback();
				$_SESSION['error'][] = 'Transaction error: ' . $e->getMessage();
			}
		}
	} else {
		$_SESSION['error'][] = 'Select candidates to vote first';
	}

	safe_redirect('home.php');
?>