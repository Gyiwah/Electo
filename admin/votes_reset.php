<?php
include 'includes/session.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Security validation failed. Reset aborted.';
        safe_redirect('votes.php');
    }

    $admin_password = $_POST['admin_password'] ?? '';
    $election_mode = $_POST['election_id'] ?? 'current';

    // Verify admin password
    $admin_q = $conn->execute_query("SELECT password FROM admin WHERE id = ? LIMIT 1", [$user['id']]);
    $admin_row = $admin_q->fetch_assoc();

    if(!$admin_row || !password_verify($admin_password, $admin_row['password'])){
        log_audit($conn, 'admin', $user['id'], 'VOTES_RESET_FAILED', 'Failed votes reset attempt: invalid administrator password.');
        $_SESSION['error'] = 'Authentication failed: Incorrect administrator password. Reset cancelled.';
        safe_redirect('votes.php');
    }

    // Determine target election
    $active_election = get_active_election($conn);
    $active_id = (int)($active_election['id'] ?? 1);

    if($election_mode === 'all'){
        // Reset ALL votes and participation audits
        $conn->query("DELETE FROM votes");
        $conn->query("DELETE FROM voter_ballot_audit");
        log_audit($conn, 'admin', $user['id'], 'VOTES_RESET_ALL', 'Cleared all votes and voter audit records across all elections.');
        $_SESSION['success'] = 'All votes and ballot records across all elections have been reset successfully.';
    } else {
        // Reset selected active election
        $conn->execute_query("DELETE FROM votes WHERE election_id = ?", [$active_id]);
        $conn->execute_query("DELETE FROM voter_ballot_audit WHERE election_id = ?", [$active_id]);
        log_audit($conn, 'admin', $user['id'], 'VOTES_RESET_ELECTION', "Reset votes and voter audit records for election #$active_id ('{$active_election['title']}').");
        $_SESSION['success'] = "Ballots for '{$active_election['title']}' have been reset successfully.";
    }
} else {
    $_SESSION['error'] = 'Unauthorized request or missing parameters.';
}

safe_redirect('votes.php');
?>