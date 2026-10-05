<?php
	require_once 'includes/security.php';
	session_unset();
	session_destroy();

	safe_redirect('index.php');
?>