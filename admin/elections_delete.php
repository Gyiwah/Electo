<?php
include 'includes/session.php';

if(isset($_POST['delete'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        safe_redirect('elections.php');
    }

    $id = (int)($_POST['id'] ?? 0);

    if($id <= 0){
        $_SESSION['error'] = 'Invalid election selected.';
        safe_redirect('elections.php');
    }

    // Check if this is the only election or currently active
    $active_q = $conn->execute_query("SELECT id, status, title FROM elections WHERE id = ?", [$id]);
    if($active_q->num_rows === 0){
        $_SESSION['error'] = 'Election not found.';
        safe_redirect('elections.php');
    }
    $elec = $active_q->fetch_assoc();

    // Check if votes or voter audit records are attached
    $votes_cnt = $conn->execute_query("SELECT COUNT(*) as c FROM votes WHERE election_id = ?", [$id])->fetch_assoc()['c'];
    $audit_cnt = $conn->execute_query("SELECT COUNT(*) as c FROM voter_ballot_audit WHERE election_id = ?", [$id])->fetch_assoc()['c'];

    if($votes_cnt > 0 || $audit_cnt > 0){
        // Do NOT delete - archive instead to preserve democratic integrity and permanent audit trail
        $conn->execute_query("UPDATE elections SET status = 'archived' WHERE id = ?", [$id]);
        log_audit($conn, 'admin', $user['id'], 'ELECTION_ARCHIVE', "Archived election #$id ('{$elec['title']}') because it contains $votes_cnt votes and $audit_cnt ballot audits.");
        $_SESSION['success'] = "Election '{$elec['title']}' contains $votes_cnt recorded votes. To preserve the permanent election audit record, it has been safely Archived rather than deleted.";
    } else {
        // Safe to remove unused empty election
        $conn->execute_query("DELETE FROM elections WHERE id = ?", [$id]);
        log_audit($conn, 'admin', $user['id'], 'ELECTION_DELETE', "Deleted empty election #$id ('{$elec['title']}')");
        $_SESSION['success'] = "Election '{$elec['title']}' deleted successfully.";
    }
} else {
    $_SESSION['error'] = 'Select an election to delete first.';
}

safe_redirect('elections.php');
?>
