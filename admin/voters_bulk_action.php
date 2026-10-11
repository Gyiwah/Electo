<?php
include 'includes/session.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_action'])){
	$_SESSION['error'] = 'Choose a bulk voter action first.';
	safe_redirect('voters.php');
}

if(!verify_csrf_token()){
	$_SESSION['error'] = 'Security validation failed. No voter changes were made.';
	safe_redirect('voters.php');
}

$return_election_id = (int)($_POST['return_election_id'] ?? 0);
$return_url = 'voters.php' . ($return_election_id > 0 ? '?election_id=' . $return_election_id : '');
$voter_ids = $_POST['voter_ids'] ?? [];
$voter_ids = is_array($voter_ids) ? array_values(array_unique(array_filter(array_map('intval', $voter_ids), function($id){ return $id > 0; }))) : [];

if(empty($voter_ids)){
	$_SESSION['error'] = 'Select at least one voter.';
	safe_redirect($return_url);
}

$action = $_POST['action'] ?? '';

try {
	$conn->begin_transaction();

	if($action === 'assign_elections'){
		$election_ids = $_POST['election_ids'] ?? [];
		$election_ids = is_array($election_ids) ? array_values(array_unique(array_filter(array_map('intval', $election_ids), function($id){ return $id > 0; }))) : [];
		if(empty($election_ids)){
			throw new Exception('Select at least one election to assign.');
		}

		$mode = $_POST['assign_mode'] ?? 'replace'; // 'replace' or 'add'

		foreach($election_ids as $election_id){
			$exists = $conn->execute_query("SELECT id FROM elections WHERE id = ? LIMIT 1", [$election_id]);
			if($exists->num_rows === 0){
				throw new Exception('One or more selected elections no longer exist.');
			}
		}

		foreach($voter_ids as $voter_id){
			$exists = $conn->execute_query("SELECT id FROM voters WHERE id = ? LIMIT 1", [$voter_id]);
			if($exists->num_rows === 0){
				throw new Exception('One or more selected voters no longer exist.');
			}
			if($mode === 'replace'){
				$conn->execute_query("DELETE FROM voter_elections WHERE voter_id = ?", [$voter_id]);
			}
			foreach($election_ids as $election_id){
				$conn->execute_query("INSERT IGNORE INTO voter_elections (voter_id, election_id) VALUES (?, ?)", [$voter_id, $election_id]);
			}
		}

		$conn->commit();
		$_SESSION['success'] = count($voter_ids) . ' voter election assignment(s) updated.';
		log_audit($conn, 'admin', $user['id'], 'VOTERS_BULK_ASSIGN', 'Assigned elections for ' . count($voter_ids) . ' voters (mode: ' . $mode . ').');
	} elseif($action === 'unassign_elections'){
		$election_ids = $_POST['election_ids'] ?? [];
		$election_ids = is_array($election_ids) ? array_values(array_unique(array_filter(array_map('intval', $election_ids), function($id){ return $id > 0; }))) : [];
		if(empty($election_ids)){
			throw new Exception('Select at least one election to unassign.');
		}

		foreach($voter_ids as $voter_id){
			foreach($election_ids as $election_id){
				$conn->execute_query("DELETE FROM voter_elections WHERE voter_id = ? AND election_id = ?", [$voter_id, $election_id]);
			}
		}

		$conn->commit();
		$_SESSION['success'] = 'Unassigned ' . count($election_ids) . ' election(s) from ' . count($voter_ids) . ' voter(s).';
		log_audit($conn, 'admin', $user['id'], 'VOTERS_BULK_UNASSIGN', 'Unassigned ' . count($election_ids) . ' election(s) from ' . count($voter_ids) . ' voters.');
	} elseif($action === 'unassign_all_elections'){
		foreach($voter_ids as $voter_id){
			$conn->execute_query("DELETE FROM voter_elections WHERE voter_id = ?", [$voter_id]);
		}

		$conn->commit();
		$_SESSION['success'] = 'Unassigned all elections from ' . count($voter_ids) . ' voter(s).';
		log_audit($conn, 'admin', $user['id'], 'VOTERS_BULK_UNASSIGN_ALL', 'Unassigned all elections from ' . count($voter_ids) . ' voters.');
	} elseif($action === 'delete'){
		$photos = [];
		foreach($voter_ids as $voter_id){
			$voter = $conn->execute_query("SELECT id, photo FROM voters WHERE id = ? LIMIT 1", [$voter_id])->fetch_assoc();
			if(!$voter){
				throw new Exception('One or more selected voters no longer exist.');
			}
			if(!empty($voter['photo'])){
				$photos[] = $voter['photo'];
			}
			$conn->execute_query("DELETE FROM voters WHERE id = ?", [$voter_id]);
		}

		$conn->commit();
		foreach($photos as $photo){
			$file_path = __DIR__ . '/../images/' . basename($photo);
			if(is_file($file_path)){
				@unlink($file_path);
			}
		}
		$_SESSION['success'] = count($voter_ids) . ' voter(s) deleted successfully.';
		log_audit($conn, 'admin', $user['id'], 'VOTERS_BULK_DELETE', 'Deleted ' . count($voter_ids) . ' voters.');
	} else {
		throw new Exception('Invalid bulk action.');
	}
} catch (Exception $e) {
	$conn->rollback();
	$_SESSION['error'] = $e->getMessage();
}

safe_redirect($return_url);
?>