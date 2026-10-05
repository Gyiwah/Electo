<?php
	include 'includes/session.php';

	$allowed_returns = ['home.php', 'ballot.php', 'candidates.php', 'positions.php', 'voters.php', 'votes.php'];
	$return = 'home.php';
	if(isset($_GET['return'])){
		$clean_return = basename($_GET['return']);
		if(in_array($clean_return, $allowed_returns, true)){
			$return = $clean_return;
		}
	}

	if(isset($_POST['save'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Profile update aborted.';
			safe_redirect($return);
		}

		$curr_password = $_POST['curr_password'] ?? '';
		$username = trim($_POST['username'] ?? '');
		$new_password = $_POST['password'] ?? '';
		$firstname = trim($_POST['firstname'] ?? '');
		$lastname = trim($_POST['lastname'] ?? '');

		if(empty($username) || empty($firstname) || empty($lastname)){
			$_SESSION['error'] = 'Username, firstname, and lastname cannot be empty.';
			safe_redirect($return);
		}

		if(password_verify($curr_password, $user['password'])){
			$filename = $user['photo'];
			$upload_error = '';

			if(isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE){
				$upload_result = secure_upload_image($_FILES['photo'], __DIR__ . '/../images', $upload_error);
				if($upload_result === false){
					$_SESSION['error'] = $upload_error;
					safe_redirect($return);
				}
				// Remove old photo if exists
				if(!empty($user['photo'])){
					$old_path = __DIR__ . '/../images/' . $user['photo'];
					if(is_file($old_path)){
						@unlink($old_path);
					}
				}
				$filename = $upload_result;
			}

			// Handle password update
			$password_to_save = $user['password'];
			if(!empty($new_password)){
				$password_to_save = password_hash($new_password, PASSWORD_DEFAULT);
			}

			try {
				$conn->execute_query(
					"UPDATE admin SET username = ?, password = ?, firstname = ?, lastname = ?, photo = ? WHERE id = ?",
					[$username, $password_to_save, $firstname, $lastname, $filename, $user['id']]
				);
				$_SESSION['success'] = 'Admin profile updated successfully';
			} catch (Exception $e) {
				$_SESSION['error'] = 'Database error: ' . $e->getMessage();
			}
		}
		else{
			$_SESSION['error'] = 'Incorrect current password';
		}
	}
	else{
		$_SESSION['error'] = 'Fill up required details first';
	}

	safe_redirect($return);
?>