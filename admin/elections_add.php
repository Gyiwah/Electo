<?php
include 'includes/session.php';

if(isset($_POST['add'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        safe_redirect('elections.php');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $start_time = !empty($_POST['start_time']) ? date('Y-m-d H:i:s', strtotime($_POST['start_time'])) : null;
    $end_time = !empty($_POST['end_time']) ? date('Y-m-d H:i:s', strtotime($_POST['end_time'])) : null;
    if(empty($title)){
        $_SESSION['error'] = 'Election title is required.';
        safe_redirect('elections.php');
    }

    if(!in_array($status, ['draft', 'active', 'closed', 'archived'], true)){
        $status = 'draft';
    }

    // Multiple elections can run concurrently; do not deactivate other active elections.

    try {
        $conn->execute_query(
            "INSERT INTO elections (title, description, status, start_time, end_time) VALUES (?, ?, ?, ?, ?)",
            [$title, $description, $status, $start_time, $end_time]
        );
        $new_id = $conn->insert_id;
        log_audit($conn, 'admin', $user['id'], 'ELECTION_CREATE', "Created election #$new_id: '$title' (status: $status)");
        $_SESSION['success'] = 'Election created successfully!';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Fill up the election creation form first.';
}

safe_redirect('elections.php');
?>
