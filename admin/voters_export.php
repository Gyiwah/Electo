<?php
include 'includes/session.php';

$active_election = get_active_election($conn);
$election_id = (int)($active_election['id'] ?? 1);
$election_title = $active_election['title'] ?? 'Current Election';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=voters_roster_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['ID', 'Voter ID', 'First Name', 'Last Name', 'Phone Number', "Turnout Status ($election_title)", 'Voted Timestamp']);

$sql = "SELECT v.id, v.voters_id, v.firstname, v.lastname, v.phone,
               vba.voted_at,
               (CASE WHEN vba.id IS NOT NULL OR legacy.id IS NOT NULL THEN 'Voted' ELSE 'Not Voted' END) as status
        FROM voters v
        LEFT JOIN voter_ballot_audit vba ON vba.voter_id = v.id AND vba.election_id = ?
        LEFT JOIN votes legacy ON legacy.voters_id = v.id
        GROUP BY v.id
        ORDER BY v.lastname ASC, v.firstname ASC";

$stmt = $conn->execute_query($sql, [$election_id]);

while($row = $stmt->fetch_assoc()){
    fputcsv($output, [
        $row['id'],
        $row['voters_id'],
        $row['firstname'],
        $row['lastname'],
        $row['phone'],
        $row['status'],
        $row['voted_at'] ?? 'N/A'
    ]);
}

fclose($output);
exit();
?>
