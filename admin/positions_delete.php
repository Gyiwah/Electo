<?php
	include 'includes/session.php';

	if(isset($_POST['delete'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Deletion aborted.';
			safe_redirect('positions.php');
		}

		$id = (int)($_POST['id'] ?? 0);
		if($id > 0){
			// Clean up candidates photos under this position
			$cand_query = $conn->execute_query("SELECT photo FROM candidates WHERE position_id = ?", [$id]);
			while($crow = $cand_query->fetch_assoc()){
				if(!empty($crow['photo'])){
					$file_path = __DIR__ . '/../images/' . $crow['photo'];
					if(is_file($file_path)){
						@unlink($file_path);
					}
				}
			}

			try {
				// Delete position and associated candidates and votes to maintain relational integrity
				$conn->begin_transaction();
				$conn->execute_query("DELETE FROM votes WHERE position_id = ?", [$id]);
				$conn->execute_query("DELETE FROM candidates WHERE position_id = ?", [$id]);
				$conn->execute_query("DELETE FROM positions WHERE id = ?", [$id]);
				$conn->commit();

				$_SESSION['success'] = 'Position and associated records deleted successfully';
			} catch (Exception $e) {
				$conn->rollback();
				$_SESSION['error'] = 'Database error: ' . $e->getMessage();
			}
		} else {
			$_SESSION['error'] = 'Invalid position ID';
		}
	}
	else{
		$_SESSION['error'] = 'Select item to delete first';
	}

	safe_redirect('positions.php');
?>