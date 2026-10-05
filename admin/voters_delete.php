<?php
	include 'includes/session.php';

	if(isset($_POST['delete'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Deletion aborted.';
			safe_redirect('voters.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		if($id > 0){
			// Check if voter has photo to clean up
			$vrow = $conn->execute_query("SELECT photo FROM voters WHERE id = ?", [$id])->fetch_assoc();
			if($vrow && !empty($vrow['photo'])){
				$file_path = __DIR__ . '/../images/' . $vrow['photo'];
				if(is_file($file_path)){
					@unlink($file_path);
				}
			}

			try {
				$conn->execute_query("DELETE FROM voters WHERE id = ?", [$id]);
				$_SESSION['success'] = 'Voter deleted successfully';
			} catch (Exception $e) {
				$_SESSION['error'] = 'Database error: ' . $e->getMessage();
			}
		} else {
			$_SESSION['error'] = 'Invalid voter ID';
		}
	}
	else{
		$_SESSION['error'] = 'Select item to delete first';
	}

	safe_redirect('voters.php');
?>