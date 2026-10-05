<?php
include 'includes/session.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_logs_export_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header
fputcsv($output, ['ID', 'Timestamp', 'User Type', 'User ID', 'Action', 'Details', 'IP Address']);

$query = $conn->query("SELECT * FROM audit_logs ORDER BY id DESC");
while($row = $query->fetch_assoc()){
    fputcsv($output, [
        $row['id'],
        $row['created_at'],
        $row['user_type'],
        $row['user_id'] ?? 'N/A',
        $row['action'],
        $row['details'],
        $row['ip_address'] ?? 'N/A'
    ]);
}

fclose($output);
exit();
?>
