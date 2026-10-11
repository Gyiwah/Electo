<?php
include 'includes/session.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_action'])){
	$_SESSION['error'] = 'Choose a bulk candidate action first.';
	safe_redirect('candidates.php');
}

if(!verify_csrf_token()){
	$_SESSION['error'] = 'Security validation failed. No changes were made.';
	safe_redirect('candidates.php');
}

$candidate_ids = $_POST['candidate_ids'] ?? [];
$candidate_ids = is_array($candidate_ids) ? array_values(array_unique(array_filter(array_map('intval', $candidate_ids), function($id){ return $id > 0; }))) : [];

if(empty($candidate_ids)){
	$_SESSION['error'] = 'Select at least one candidate.';
	safe_redirect('candidates.php');
}

$action = $_POST['action'] ?? '';

try {
	$conn->begin_transaction();

	if($action === 'delete'){
		$photos = [];
		foreach($candidate_ids as $cid){
			$crow = $conn->execute_query("SELECT photo FROM candidates WHERE id = ? LIMIT 1", [$cid])->fetch_assoc();
			if($crow && !empty($crow['photo'])){
				$photos[] = $crow['photo'];
			}
			// Delete votes for this candidate if any to avoid constraint violations
			$conn->execute_query("DELETE FROM votes WHERE candidate_id = ?", [$cid]);
			$conn->execute_query("DELETE FROM candidates WHERE id = ?", [$cid]);
		}

		$conn->commit();

		foreach($photos as $photo){
			$file_path = __DIR__ . '/../images/' . basename($photo);
			if(is_file($file_path)){
				@unlink($file_path);
			}
		}

		$_SESSION['success'] = count($candidate_ids) . ' candidate(s) deleted successfully.';
		log_audit($conn, 'admin', $user['id'], 'CANDIDATES_BULK_DELETE', 'Deleted ' . count($candidate_ids) . ' candidates.');
	} else {
		throw new Exception('Invalid bulk action.');
	}
} catch (Exception $e) {
	$conn->rollback();
	$_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

safe_redirect('candidates.php');
?>
