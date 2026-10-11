<?php
include 'includes/session.php';

if(isset($_POST['edit'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        safe_redirect('elections.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $start_time = !empty($_POST['start_time']) ? date('Y-m-d H:i:s', strtotime($_POST['start_time'])) : null;
    $end_time = !empty($_POST['end_time']) ? date('Y-m-d H:i:s', strtotime($_POST['end_time'])) : null;
    if($id <= 0 || empty($title)){
        $_SESSION['error'] = 'Invalid election information provided.';
        safe_redirect('elections.php');
    }

    if(!in_array($status, ['draft', 'active', 'closed', 'archived'], true)){
        $status = 'draft';
    }

    // Multiple elections can run concurrently; do not deactivate other active elections.

    try {
        $conn->execute_query(
            "UPDATE elections SET title = ?, description = ?, status = ?, start_time = ?, end_time = ? WHERE id = ?",
            [$title, $description, $status, $start_time, $end_time, $id]
        );
        log_audit($conn, 'admin', $user['id'], 'ELECTION_UPDATE', "Updated election #$id: '$title' (status: $status)");
        $_SESSION['success'] = 'Election configuration updated successfully!';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Select an election to edit first.';
}

safe_redirect('elections.php');
?>
