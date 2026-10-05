<?php
	include 'includes/session.php';
	require_once __DIR__ . '/../includes/sms.php';

	if(isset($_POST['edit'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Voter not updated.';
			safe_redirect('voters.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		$voters_id = trim($_POST['voters_id'] ?? '');
		$firstname = trim($_POST['firstname'] ?? '');
		$lastname = trim($_POST['lastname'] ?? '');
		$phone = normalize_phone_number($_POST['phone'] ?? '');

		if($id <= 0 || empty($firstname) || empty($lastname) || empty($phone) || empty($voters_id)){
			$_SESSION['error'] = 'Voter ID, Firstname, Lastname, and Phone number are required.';
			safe_redirect('voters.php');
		}

		// Ensure Voter ID uniqueness across other voters
		$dup_check = $conn->execute_query("SELECT id FROM voters WHERE voters_id = ? AND id != ? LIMIT 1", [$voters_id, $id]);
		if($dup_check->num_rows > 0){
			$_SESSION['error'] = "Voter ID '{$voters_id}' is already assigned to another voter.";
			safe_redirect('voters.php');
		}

		try {
			// Update phone and set password hash from phone
			$password_hash = password_hash($phone, PASSWORD_DEFAULT);
			$conn->execute_query(
				"UPDATE voters SET voters_id = ?, firstname = ?, lastname = ?, phone = ?, password = ? WHERE id = ?",
				[$voters_id, $firstname, $lastname, $phone, $password_hash, $id]
			);

			if(isset($_POST['elections'])){
				$selected_elections = is_array($_POST['elections']) ? $_POST['elections'] : [];
				$conn->execute_query("DELETE FROM voter_elections WHERE voter_id = ?", [$id]);
				foreach($selected_elections as $eid){
					$eid = (int)$eid;
					if($eid > 0){
						$conn->execute_query("INSERT IGNORE INTO voter_elections (voter_id, election_id) VALUES (?, ?)", [$id, $eid]);
					}
				}
			}
			
			log_audit($conn, 'admin', $user['id'], 'VOTER_EDIT', "Updated voter #{$id} ({$firstname} {$lastname}, ID: {$voters_id}, Phone: {$phone}).");
			$_SESSION['success'] = "Voter updated successfully! Phone (Password): <code>{$phone}</code>";
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up edit form first';
	}

	safe_redirect('voters.php');
?>