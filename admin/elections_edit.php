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
    $signatory_names = [];
    $signatory_roles = [];
    for($signatory = 1; $signatory <= 3; $signatory++){
        $signatory_names[] = trim($_POST['signatory_' . $signatory . '_name'] ?? '');
        $signatory_roles[] = trim($_POST['signatory_' . $signatory . '_role'] ?? '');
    }

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
            "UPDATE elections SET title = ?, description = ?, status = ?, start_time = ?, end_time = ?, signatory_1_name = ?, signatory_1_role = ?, signatory_2_name = ?, signatory_2_role = ?, signatory_3_name = ?, signatory_3_role = ? WHERE id = ?",
            [$title, $description, $status, $start_time, $end_time, $signatory_names[0], $signatory_roles[0], $signatory_names[1], $signatory_roles[1], $signatory_names[2], $signatory_roles[2], $id]
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
