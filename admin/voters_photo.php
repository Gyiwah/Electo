<?php
	include 'includes/session.php';

	if(isset($_POST['upload'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Photo upload aborted.';
			safe_redirect('voters.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		if($id <= 0){
			$_SESSION['error'] = 'Invalid voter ID.';
			safe_redirect('voters.php');
		}

		$upload_error = '';
		$filename = secure_upload_image($_FILES['photo'] ?? null, __DIR__ . '/../images', $upload_error);

		if($filename === false || empty($filename)){
			$_SESSION['error'] = !empty($upload_error) ? $upload_error : 'Please select a valid image file.';
			safe_redirect('voters.php');
		}

		// Clean up old photo
		$old = $conn->execute_query("SELECT photo FROM voters WHERE id = ?", [$id])->fetch_assoc();
		if($old && !empty($old['photo'])){
			$old_path = __DIR__ . '/../images/' . $old['photo'];
			if(is_file($old_path)){
				@unlink($old_path);
			}
		}

		try {
			$conn->execute_query("UPDATE voters SET photo = ? WHERE id = ?", [$filename, $id]);
			$_SESSION['success'] = 'Photo updated successfully';
		} catch (Exception $e) {
			$_SESSION['error'] = 'Database error: ' . $e->getMessage();
		}
	}
	else{
		$_SESSION['error'] = 'Select voter to update photo first';
	}

	safe_redirect('voters.php');
?>