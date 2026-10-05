<?php
include 'includes/session.php';

if(isset($_POST['set_status'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        safe_redirect('elections.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if($id <= 0 || !in_array($status, ['draft', 'active', 'closed', 'archived'], true)){
        $_SESSION['error'] = 'Invalid request parameters.';
        safe_redirect('elections.php');
    }

    // Multiple elections can run concurrently, so do NOT deactivate other elections!
    $conn->execute_query("UPDATE elections SET status = ? WHERE id = ?", [$status, $id]);

    $el = $conn->execute_query("SELECT title FROM elections WHERE id = ?", [$id])->fetch_assoc();
    $el_title = $el['title'] ?? "Election #$id";

    log_audit($conn, 'admin', $user['id'], 'ELECTION_STATUS_CHANGE', "Changed election #$id ($el_title) status to $status");
    $_SESSION['success'] = "Election '{$el_title}' status updated to " . strtoupper($status) . ".";
} else {
    $_SESSION['error'] = 'Invalid status change request.';
}

safe_redirect('elections.php');
?>
