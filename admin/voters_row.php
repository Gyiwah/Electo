<?php 
	include 'includes/session.php';

	header('Content-Type: application/json');

	if(isset($_POST['id'])){
		$id = (int)$_POST['id'];
		$query = $conn->execute_query("SELECT id, voters_id, firstname, lastname, phone, photo FROM voters WHERE id = ?", [$id]);
		$row = $query->fetch_assoc();

		if($row){
			$elec_query = $conn->execute_query("SELECT election_id FROM voter_elections WHERE voter_id = ?", [$id]);
			$row['elections'] = [];
			while($erow = $elec_query->fetch_assoc()){
				$row['elections'][] = (int)$erow['election_id'];
			}
		}

		echo json_encode($row ?: []);
	}
?>