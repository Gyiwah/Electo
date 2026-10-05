<?php
include 'includes/session.php';

$active_election = get_active_election($conn);
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

// Fetch election title
$elec_q = $conn->execute_query("SELECT title FROM elections WHERE id = ?", [$election_id]);
$election_title = ($elec_q->num_rows > 0) ? $elec_q->fetch_assoc()['title'] : 'Election';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=election_results_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $election_title) . '_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Metadata header
fputcsv($output, ['OFFICIAL CERTIFIED ELECTION RESULTS']);
fputcsv($output, ['Election:', $election_title]);
fputcsv($output, ['Export Date:', date('F j, Y - g:i:s A T')]);
fputcsv($output, []); // blank line

fputcsv($output, ['Position', 'Candidate First Name', 'Candidate Last Name', 'Votes Received', 'Percentage of Position Votes']);

// Fetch positions
$pos_stmt = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);

while($pos = $pos_stmt->fetch_assoc()){
    $pos_id = (int)$pos['id'];
    $pos_desc = $pos['description'];

    // Total votes cast for this position in this election
    $pos_total = $conn->execute_query(
        "SELECT COUNT(*) as cnt FROM votes WHERE position_id = ? AND election_id = ?",
        [$pos_id, $election_id]
    )->fetch_assoc()['cnt'];

    // Candidates in this position
    $cand_stmt = $conn->execute_query(
        "SELECT c.firstname, c.lastname, 
                (SELECT COUNT(*) FROM votes WHERE candidate_id = c.id AND position_id = ? AND election_id = ?) as vote_count
         FROM candidates c 
         WHERE c.position_id = ?
         ORDER BY vote_count DESC, c.lastname ASC",
        [$pos_id, $election_id, $pos_id]
    );

    $candidates = $cand_stmt->fetch_all(MYSQLI_ASSOC);
    if(count($candidates) === 1){
        $candidate = $candidates[0];
        $yes_pct = ($pos_total > 0) ? number_format(($candidate['vote_count'] / $pos_total) * 100, 2) . '%' : '0.00%';
        fputcsv($output, [$pos_desc, 'Yes', $candidate['firstname'] . ' ' . $candidate['lastname'], $candidate['vote_count'], $yes_pct]);

        $no_votes = (int)$conn->execute_query(
            "SELECT COUNT(*) AS cnt FROM votes WHERE candidate_id = 0 AND position_id = ? AND election_id = ?",
            [$pos_id, $election_id]
        )->fetch_assoc()['cnt'];
        $no_pct = ($pos_total > 0) ? number_format(($no_votes / $pos_total) * 100, 2) . '%' : '0.00%';
        fputcsv($output, [$pos_desc, 'No', '', $no_votes, $no_pct]);
    } else {
      foreach($candidates as $cand){
        $pct = ($pos_total > 0) ? number_format(($cand['vote_count'] / $pos_total) * 100, 2) . '%' : '0.00%';
        fputcsv($output, [
            $pos_desc,
            $cand['firstname'],
            $cand['lastname'],
            $cand['vote_count'],
            $pct
        ]);
      }
    }

    fputcsv($output, [$pos_desc . ' - Total Votes', '', '', $pos_total, '100%']);
    fputcsv($output, []); // blank line between positions
}

fclose($output);
exit();
?>
