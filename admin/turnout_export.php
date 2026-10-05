<?php
include 'includes/session.php';

$active_election = get_active_election($conn);
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

// Fetch election title
$elec_q = $conn->execute_query("SELECT title FROM elections WHERE id = ?", [$election_id]);
$election_title = ($elec_q->num_rows > 0) ? $elec_q->fetch_assoc()['title'] : 'Election';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=turnout_audit_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $election_title) . '_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Metadata header
fputcsv($output, ['OFFICIAL TURNOUT & BALLOT PARTICIPATION AUDIT']);
fputcsv($output, ['Election:', $election_title]);
fputcsv($output, ['Export Date:', date('F j, Y - g:i:s A T')]);
fputcsv($output, []);

fputcsv($output, ['#', 'Voter ID', 'First Name', 'Last Name', 'Ballot Receipt Token', 'Timestamp Cast', 'Selections Recorded']);

$sql = "SELECT vba.id, v.voters_id, v.firstname, v.lastname, vba.ballot_token, vba.voted_at,
               (SELECT COUNT(*) FROM votes WHERE ballot_token = vba.ballot_token) as selections_count
        FROM voter_ballot_audit vba
        JOIN voters v ON v.id = vba.voter_id
        WHERE vba.election_id = ?
        ORDER BY vba.voted_at ASC";

$stmt = $conn->execute_query($sql, [$election_id]);
$i = 1;
while($row = $stmt->fetch_assoc()){
    fputcsv($output, [
        $i++,
        $row['voters_id'],
        $row['firstname'],
        $row['lastname'],
        $row['ballot_token'],
        $row['voted_at'],
        $row['selections_count']
    ]);
}

fclose($output);
exit();
?>
