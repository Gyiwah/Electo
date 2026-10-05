<?php
	include 'includes/session.php';

	if(isset($_POST['add'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Position not added.';
			safe_redirect('positions.php');
		}

		$description = trim($_POST['description'] ?? '');
		$max_vote = (int)($_POST['max_vote'] ?? 1);

		if(empty($description) || $max_vote < 1){
			$_SESSION['error'] = 'Position description and valid maximum votes (at least 1) are required.';
			safe_redirect('positions.php');
		}

		$active_election = get_active_election($conn);
		$election_id = (int)($active_election['id'] ?? 1);
		$_SESSION['admin_election_id'] = $election_id;
		$row = $conn->execute_query("SELECT priority FROM positions WHERE election_id = ? ORDER BY priority DESC LIMIT 1", [$election_id])->fetch_assoc();
		$priority = ($row) ? (int)$row['priority'] + 1 : 1;

		try {
			$conn->execute_query(
				"INSERT INTO positions (election_id, description, max_vote, priority) VALUES (?, ?, ?, ?)",
				[$election_id, $description, $max_vote, $priority]
			);
			log_audit($conn, 'admin', $user['id'], 'POSITION_ADD', "Added position '$description' (max vote: $max_vote) for election #$election_id");
			$_SESSION['success'] = 'Position added successfully';
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up add form first';
	}

	safe_redirect('positions.php');
?>