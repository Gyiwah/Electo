<?php
	include 'includes/session.php';

	if(isset($_POST['add'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Candidate not added.';
			safe_redirect('candidates.php');
		}

		$firstname = trim($_POST['firstname'] ?? '');
		$lastname = trim($_POST['lastname'] ?? '');
		$position = (int)($_POST['position'] ?? 0);
		$platform = trim($_POST['platform'] ?? '');

		if(empty($firstname) || empty($lastname) || $position <= 0){
			$_SESSION['error'] = 'Firstname, lastname, and valid position are required.';
			safe_redirect('candidates.php');
		}

		$active_election = get_active_election($conn);
		$election_id = (int)($active_election['id'] ?? 0);
		$_SESSION['admin_election_id'] = $election_id;

		// A candidate must belong to a position in the selected election.
		$pos_check = $conn->execute_query("SELECT id FROM positions WHERE id = ? AND election_id = ?", [$position, $election_id]);
		if($pos_check->num_rows < 1){
			$_SESSION['error'] = 'Selected position does not belong to the selected election.';
			safe_redirect('candidates.php');
		}

		// Handle file upload safely
		$filename = '';
		$upload_error = '';
		if(isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE){
			$upload_result = secure_upload_image($_FILES['photo'], __DIR__ . '/../images', $upload_error);
			if($upload_result === false){
				$_SESSION['error'] = $upload_error;
				safe_redirect('candidates.php');
			}
			$filename = $upload_result;
		}

		try {
			$conn->execute_query(
				"INSERT INTO candidates (position_id, firstname, lastname, photo, platform) VALUES (?, ?, ?, ?, ?)",
				[$position, $firstname, $lastname, $filename, $platform]
			);
			$_SESSION['success'] = 'Candidate added successfully';
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up add form first';
	}

	safe_redirect('candidates.php');
?>