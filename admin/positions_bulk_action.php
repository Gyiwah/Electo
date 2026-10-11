<?php
include 'includes/session.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_action'])){
	$_SESSION['error'] = 'Choose a bulk position action first.';
	safe_redirect('positions.php');
}

if(!verify_csrf_token()){
	$_SESSION['error'] = 'Security validation failed. No changes were made.';
	safe_redirect('positions.php');
}

$position_ids = $_POST['position_ids'] ?? [];
$position_ids = is_array($position_ids) ? array_values(array_unique(array_filter(array_map('intval', $position_ids), function($id){ return $id > 0; }))) : [];

if(empty($position_ids)){
	$_SESSION['error'] = 'Select at least one position.';
	safe_redirect('positions.php');
}

$action = $_POST['action'] ?? '';

try {
	$conn->begin_transaction();

	if($action === 'delete'){
		$photos = [];
		foreach($position_ids as $pid){
			// Collect candidate photos to delete
			$cand_query = $conn->execute_query("SELECT photo FROM candidates WHERE position_id = ?", [$pid]);
			while($crow = $cand_query->fetch_assoc()){
				if(!empty($crow['photo'])){
					$photos[] = $crow['photo'];
				}
			}

			// Clean up child records to preserve relational integrity
			$conn->execute_query("DELETE FROM votes WHERE position_id = ?", [$pid]);
			$conn->execute_query("DELETE FROM candidates WHERE position_id = ?", [$pid]);
			$conn->execute_query("DELETE FROM positions WHERE id = ?", [$pid]);
		}

		$conn->commit();

		foreach($photos as $photo){
			$file_path = __DIR__ . '/../images/' . basename($photo);
			if(is_file($file_path)){
				@unlink($file_path);
			}
		}

		$_SESSION['success'] = count($position_ids) . ' position(s) and associated candidate/vote records deleted successfully.';
		log_audit($conn, 'admin', $user['id'], 'POSITIONS_BULK_DELETE', 'Deleted ' . count($position_ids) . ' positions.');
	} else {
		throw new Exception('Invalid bulk action.');
	}
} catch (Exception $e) {
	$conn->rollback();
	$_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

safe_redirect('positions.php');
?>
