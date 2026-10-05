<?php
include 'includes/session.php';

if(isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $stmt = $conn->execute_query("SELECT * FROM elections WHERE id = ?", [$id]);
    $row = $stmt->fetch_assoc();

    // Format dates for HTML5 datetime-local inputs (YYYY-MM-DDTHH:MM)
    if(!empty($row['start_time'])){
        $row['start_time'] = date('Y-m-d\TH:i', strtotime($row['start_time']));
    }
    if(!empty($row['end_time'])){
        $row['end_time'] = date('Y-m-d\TH:i', strtotime($row['end_time']));
    }

    header('Content-Type: application/json');
    echo json_encode($row);
}
?>
