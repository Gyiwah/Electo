<?php
	include 'includes/session.php';
	include 'includes/slugify.php';

	header('Content-Type: application/json');
	$output = array('error' => false, 'list' => '', 'message' => []);

	$election_id = (int)($_SESSION['voter_election_id'] ?? 0);
	$query = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);

	while($row = $query->fetch_assoc()){
		$position = slugify($row['description']);
		$pos_id = (int)$row['id'];
		$max_vote = (int)$row['max_vote'];

		if(isset($_POST[$position])){
			$candidate_rows = $conn->execute_query("SELECT id, firstname, lastname FROM candidates WHERE position_id = ?", [$pos_id])->fetch_all(MYSQLI_ASSOC);
			if(count($candidate_rows) === 1){
				$choice = is_string($_POST[$position]) ? $_POST[$position] : '';
				if($choice === 'no'){
					$selected_text = 'No';
				} elseif($choice === 'yes:' . (int)$candidate_rows[0]['id']){
					$selected_text = 'Yes: ' . $candidate_rows[0]['firstname'] . ' ' . $candidate_rows[0]['lastname'];
				} else {
					$output['error'] = true;
					$output['message'][] = '<li>Choose Yes or No for ' . escape_html($row['description']) . '</li>';
					continue;
				}
				$output['list'] .= "<div class='row votelist'><span class='col-sm-4'><span class='pull-right'><b>" . escape_html($row['description']) . " :</b></span></span><span class='col-sm-8'>" . escape_html($selected_text) . "</span></div>";
			} elseif($max_vote > 1){
				$selections = is_array($_POST[$position]) ? $_POST[$position] : [$_POST[$position]];
				if(count($selections) > $max_vote){
					$output['error'] = true;
					$output['message'][] = '<li>You can only choose ' . $max_vote . ' candidates for ' . escape_html($row['description']) . '</li>';
				}
				else{
					foreach($selections as $cid){
						$cid = (int)$cid;
						$cmquery = $conn->execute_query("SELECT * FROM candidates WHERE id = ? AND position_id = ?", [$cid, $pos_id]);
						if($cmrow = $cmquery->fetch_assoc()){
							$output['list'] .= "
								<div class='row votelist'>
									<span class='col-sm-4'><span class='pull-right'><b>" . escape_html($row['description']) . " :</b></span></span> 
									<span class='col-sm-8'>" . escape_html($cmrow['firstname'] . ' ' . $cmrow['lastname']) . "</span>
								</div>
							";
						}
					}
				}
			}
			else{
				$candidate = (int)$_POST[$position];
				$csquery = $conn->execute_query("SELECT * FROM candidates WHERE id = ? AND position_id = ?", [$candidate, $pos_id]);
				if($csrow = $csquery->fetch_assoc()){
					$output['list'] .= "
						<div class='row votelist'>
							<span class='col-sm-4'><span class='pull-right'><b>" . escape_html($row['description']) . " :</b></span></span> 
							<span class='col-sm-8'>" . escape_html($csrow['firstname'] . ' ' . $csrow['lastname']) . "</span>
						</div>
					";
				}
			}
		}
	}

	echo json_encode($output);
?>