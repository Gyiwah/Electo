<?php
	include 'includes/session.php';

	if(isset($_POST['delete'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Deletion aborted.';
			safe_redirect('candidates.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		if($id > 0){
			$crow = $conn->execute_query("SELECT photo FROM candidates WHERE id = ?", [$id])->fetch_assoc();
			if($crow && !empty($crow['photo'])){
				$file_path = __DIR__ . '/../images/' . $crow['photo'];
				if(is_file($file_path)){
					@unlink($file_path);
				}
			}

			try {
				$conn->execute_query("DELETE FROM candidates WHERE id = ?", [$id]);
				$_SESSION['success'] = 'Candidate deleted successfully';
			} catch (Exception $e) {
				$_SESSION['error'] = 'Database error: ' . $e->getMessage();
			}
		} else {
			$_SESSION['error'] = 'Invalid candidate ID';
		}
	}
	else{
		$_SESSION['error'] = 'Select item to delete first';
	}

	safe_redirect('candidates.php');
?>