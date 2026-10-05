<?php
	require_once __DIR__ . '/../includes/security.php';
	session_unset();
	session_destroy();

	safe_redirect('index.php');
?>