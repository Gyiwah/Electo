<?php
	include 'includes/session.php';
	require_once __DIR__ . '/../includes/sms.php';

	if(isset($_POST['add'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Voter not added.';
			safe_redirect('voters.php');
		}

		$firstname = trim($_POST['firstname'] ?? '');
		$lastname = trim($_POST['lastname'] ?? '');
		$phone = normalize_phone_number($_POST['phone'] ?? '');
		$custom_voter_id = trim($_POST['voters_id'] ?? '');

		if(empty($firstname) || empty($lastname) || empty($phone)){
			$_SESSION['error'] = 'Firstname, Lastname, and Phone number are required.';
			safe_redirect('voters.php');
		}

		// Phone number serves as the password
		$password_hash = password_hash($phone, PASSWORD_DEFAULT);

		// Handle file upload safely
		$filename = '';
		$upload_error = '';
		if(isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE){
			$upload_result = secure_upload_image($_FILES['photo'], __DIR__ . '/../images', $upload_error);
			if($upload_result === false){
				$_SESSION['error'] = $upload_error;
				safe_redirect('voters.php');
			}
			$filename = $upload_result;
		}

		// Voter ID: Use custom if provided, otherwise auto-generate unique ID
		$voter = '';
		if(!empty($custom_voter_id)){
			$check = $conn->execute_query("SELECT id FROM voters WHERE voters_id = ? LIMIT 1", [$custom_voter_id]);
			if($check->num_rows > 0){
				$_SESSION['error'] = "Voter ID '{$custom_voter_id}' is already registered. Please choose another or leave blank.";
				safe_redirect('voters.php');
			}
			$voter = $custom_voter_id;
		} else {
			// Cryptographically secure voter ID generation with uniqueness guarantee
			$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
			$chars_len = strlen($chars);
			do {
				$voter = '';
				for($i = 0; $i < 12; $i++){
					$voter .= $chars[random_int(0, $chars_len - 1)];
				}
				$check = $conn->execute_query("SELECT id FROM voters WHERE voters_id = ? LIMIT 1", [$voter]);
			} while ($check->num_rows > 0);
		}

		$sql = "INSERT INTO voters (voters_id, password, firstname, lastname, phone, photo) VALUES (?, ?, ?, ?, ?, ?)";
		try {
			$conn->execute_query($sql, [$voter, $password_hash, $firstname, $lastname, $phone, $filename]);
			$new_id = $conn->insert_id;

			$selected_elections = $_POST['elections'] ?? [];
			if(!empty($selected_elections) && is_array($selected_elections)){
				foreach($selected_elections as $eid){
					$eid = (int)$eid;
					if($eid > 0){
						$conn->execute_query("INSERT IGNORE INTO voter_elections (voter_id, election_id) VALUES (?, ?)", [$new_id, $eid]);
					}
				}
			}

			log_audit($conn, 'admin', $user['id'], 'VOTER_ADD', "Added voter '{$firstname} {$lastname}' (ID: {$voter}, Phone: {$phone}).");
			$_SESSION['success'] = "Voter added successfully! Voter ID: <code>{$voter}</code> &bull; Phone (Password): <code>{$phone}</code>";
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Fill up add form first';
	}

	safe_redirect('voters.php');
?>