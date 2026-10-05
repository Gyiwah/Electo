<?php
	require_once __DIR__ . '/../includes/security.php';
	require_once 'includes/conn.php';

	if(isset($_POST['login'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Please try again.';
			safe_redirect('index.php');
		}

		$username = trim($_POST['username'] ?? '');
		$password = $_POST['password'] ?? '';

		if(empty($username) || empty($password)){
			$_SESSION['error'] = 'Please enter both username and password';
			safe_redirect('index.php');
		}

		$query = $conn->execute_query("SELECT * FROM admin WHERE username = ?", [$username]);

		if($query->num_rows < 1){
			$_SESSION['error'] = 'Cannot find account with the username';
		}
		else{
			$row = $query->fetch_assoc();
			if(password_verify($password, $row['password'])){
				session_regenerate_id(true);
				$_SESSION['admin'] = $row['id'];
				get_csrf_token(); // generate fresh CSRF token
				safe_redirect('home.php');
			}
			else{
				$_SESSION['error'] = 'Incorrect password';
			}
		}
	}
	else{
		$_SESSION['error'] = 'Input admin credentials first';
	}

	safe_redirect('index.php');
?>