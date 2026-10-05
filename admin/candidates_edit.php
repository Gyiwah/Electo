<?php
	include 'includes/session.php';

	if(isset($_POST['edit'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Candidate not updated.';
			safe_redirect('candidates.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		$firstname = trim($_POST['firstname'] ?? '');
		$lastname = trim($_POST['lastname'] ?? '');
		$position = (int)($_POST['position'] ?? 0);
		$platform = trim($_POST['platform'] ?? '');

		if($id <= 0 || empty($firstname) || empty($lastname) || $position <= 0){
			$_SESSION['error'] = 'All fields are required to update candidate.';
			safe_redirect('candidates.php');
		}

		$active_election = get_active_election($conn);
		$election_id = (int)($active_election['id'] ?? 0);
		$_SESSION['admin_election_id'] = $election_id;
		$position_check = $conn->execute_query("SELECT id FROM positions WHERE id = ? AND election_id = ?", [$position, $election_id]);
		$candidate_check = $conn->execute_query(
			"SELECT c.id FROM candidates c INNER JOIN positions p ON p.id = c.position_id WHERE c.id = ? AND p.election_id = ?",
			[$id, $election_id]
		);
		if($position_check->num_rows === 0 || $candidate_check->num_rows === 0){
			$_SESSION['error'] = 'Candidate and position must belong to the selected election.';
			safe_redirect('candidates.php');
		}

		try {
			$conn->execute_query(
				"UPDATE candidates SET firstname = ?, lastname = ?, position_id = ?, platform = ? WHERE id = ?",
				[$firstname, $lastname, $position, $platform, $id]
			);
			$_SESSION['success'] = 'Candidate updated successfully';
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up edit form first';
	}

	safe_redirect('candidates.php');
?>