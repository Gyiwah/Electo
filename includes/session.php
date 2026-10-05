<?php
	require_once __DIR__ . '/security.php';
	require_once __DIR__ . '/conn.php';

	if(isset($_SESSION['voter'])){
		$query = $conn->execute_query("SELECT * FROM voters WHERE id = ?", [$_SESSION['voter']]);
		$voter = $query->fetch_assoc();
		if(!$voter){
			unset($_SESSION['voter']);
			safe_redirect('index.php');
		}
	}
	else{
		safe_redirect('index.php');
	}
?>