<?php
	include 'includes/session.php';

	$allowed_returns = ['home.php', 'ballot.php', 'candidates.php', 'positions.php', 'voters.php', 'votes.php', 'elections.php'];
	$return = 'home.php';
	if(isset($_GET['return'])){
		$clean_return = basename($_GET['return']);
		if(in_array($clean_return, $allowed_returns, true)){
			$return = $clean_return;
		}
	}

	if(isset($_POST['save'])){
		if(!verify_csrf_token()){
			$_SESSION['error'] = 'Security validation failed. Config not saved.';
			safe_redirect($return);
		}

		$title = trim($_POST['title'] ?? '');
		$election_id = (int)($_POST['election_id'] ?? 0);

		// Strip newlines to prevent INI file injection
		$title = preg_replace('/[\r\n]+/', ' ', $title);

		if(empty($title)){
			$_SESSION['error'] = 'Election title cannot be empty.';
			safe_redirect($return);
		}

		// Update database election record if provided or active
		if($election_id > 0){
			$conn->execute_query("UPDATE elections SET title = ? WHERE id = ?", [$title, $election_id]);
			$_SESSION['admin_election_id'] = $election_id;
		} else {
			$active_el = get_active_election($conn);
			$conn->execute_query("UPDATE elections SET title = ? WHERE id = ?", [$title, $active_el['id']]);
			$_SESSION['admin_election_id'] = $active_el['id'];
		}

		// Sync config.ini safely preserving all other config values
		$file = __DIR__ . '/config.ini';
		$existing = file_exists($file) ? (@parse_ini_file($file, false, INI_SCANNER_RAW) ?: []) : [];
		$existing['election_title'] = $title;

		$content = '';
		foreach ($existing as $k => $v) {
			$clean_v = preg_replace('/[\r\n]+/', ' ', (string)$v);
			$clean_v = str_replace('"', '\"', $clean_v);
			$content .= "{$k} = \"{$clean_v}\"\n";
		}

		@file_put_contents($file, $content);

		log_audit($conn, 'admin', $user['id'], 'ELECTION_TITLE_UPDATE', "Updated election #$election_id title to '$title'.");
		$_SESSION['success'] = "Election title updated to '" . escape_html($title) . "' successfully!";
	}
	else{
		$_SESSION['error'] = "Fill up config form first";
	}

	safe_redirect($return);
?>