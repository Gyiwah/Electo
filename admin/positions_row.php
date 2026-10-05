<?php 
	include 'includes/session.php';

	header('Content-Type: application/json');

	if(isset($_POST['id'])){
		$id = (int)$_POST['id'];
		$active_election = get_active_election($conn);
		$query = $conn->execute_query("SELECT * FROM positions WHERE id = ? AND election_id = ?", [$id, (int)$active_election['id']]);
		$row = $query->fetch_assoc();

		echo json_encode($row ?: []);
	}
?>