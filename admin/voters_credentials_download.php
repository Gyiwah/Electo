<?php
include 'includes/session.php';

if(!isset($_SESSION['imported_credentials']) || empty($_SESSION['imported_credentials'])){
    $_SESSION['error'] = 'No recent imported credentials found to download.';
    safe_redirect('voters.php');
}

$credentials = $_SESSION['imported_credentials'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=voter_credentials_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($output, ['Voter ID', 'First Name', 'Last Name', 'Phone Number (Login Password)', 'Generated At']);

$now = date('Y-m-d H:i:s');
foreach($credentials as $c){
    fputcsv($output, [
        $c['voter_id'],
        $c['firstname'],
        $c['lastname'],
        $c['phone'] ?? $c['password'] ?? '',
        $now
    ]);
}

fclose($output);
exit();
?>
