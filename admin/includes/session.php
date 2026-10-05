<?php
	require_once __DIR__ . '/../../includes/security.php';
	require_once __DIR__ . '/../../includes/conn.php';

	if(!isset($_SESSION['admin']) || trim((string)$_SESSION['admin']) === ''){
		safe_redirect('index.php');
	}

	$query = $conn->execute_query("SELECT * FROM admin WHERE id = ?", [$_SESSION['admin']]);
	$user = $query->fetch_assoc();

	if(!$user){
		unset($_SESSION['admin']);
		safe_redirect('index.php');
	}
?>