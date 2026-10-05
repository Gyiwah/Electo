<?php 
	include 'includes/session.php';

	header('Content-Type: application/json');

	if(isset($_POST['id'])){
		$id = (int)$_POST['id'];
		$active_election = get_active_election($conn);
		$query = $conn->execute_query("SELECT candidates.*, candidates.id AS canid, positions.description FROM candidates INNER JOIN positions ON positions.id = candidates.position_id WHERE candidates.id = ? AND positions.election_id = ?", [$id, (int)$active_election['id']]);
		$row = $query->fetch_assoc();

		echo json_encode($row ?: []);
	}
?>