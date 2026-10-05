<?php
	include 'includes/session.php';

	if(isset($_POST['edit'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Position not updated.';
			safe_redirect('positions.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		$description = trim($_POST['description'] ?? '');
		$max_vote = (int)($_POST['max_vote'] ?? 1);

		if($id <= 0 || empty($description) || $max_vote < 1){
			$_SESSION['error'] = 'Valid ID, description, and maximum votes (at least 1) are required.';
			safe_redirect('positions.php');
		}

		$active_election = get_active_election($conn);
		$election_id = (int)($active_election['id'] ?? 0);
		$_SESSION['admin_election_id'] = $election_id;
		$position_check = $conn->execute_query("SELECT id FROM positions WHERE id = ? AND election_id = ?", [$id, $election_id]);
		if($position_check->num_rows === 0){
			$_SESSION['error'] = 'Position does not belong to the selected election.';
			safe_redirect('positions.php');
		}

		try {
			$conn->execute_query(
				"UPDATE positions SET description = ?, max_vote = ? WHERE id = ?",
				[$description, $max_vote, $id]
			);
			$_SESSION['success'] = 'Position updated successfully';
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up edit form first';
	}

	safe_redirect('positions.php');
?>