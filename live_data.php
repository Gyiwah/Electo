<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$active_election = get_active_election($conn);
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

// Fetch election details
$elec_stmt = $conn->execute_query("SELECT * FROM elections WHERE id = ?", [$election_id]);
$election = $elec_stmt->fetch_assoc() ?: [
    'id' => 1,
    'title' => 'General Election',
    'status' => 'active'
];

// Total voters
$total_voters = (int)$conn->query("SELECT COUNT(*) as c FROM voters")->fetch_assoc()['c'];

// Ballots cast in this election
$ballots_stmt = $conn->execute_query(
    "SELECT COUNT(DISTINCT voter_id) as c FROM voter_ballot_audit WHERE election_id = ?",
    [$election_id]
);
$ballots_cast = (int)$ballots_stmt->fetch_assoc()['c'];
if ($ballots_cast === 0) {
    // Legacy fallback check
    $ballots_cast = (int)$conn->query("SELECT COUNT(DISTINCT voters_id) as c FROM votes WHERE voters_id > 0")->fetch_assoc()['c'];
}

$turnout_pct = ($total_voters > 0) ? round(($ballots_cast / $total_voters) * 100, 1) : 0;

// Total individual selections in election
$total_votes_overall = (int)$conn->execute_query(
    "SELECT COUNT(*) as c FROM votes WHERE election_id = ?",
    [$election_id]
)->fetch_assoc()['c'];

// Fetch positions & candidates
$positions = [];
$pos_stmt = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);

while($pos = $pos_stmt->fetch_assoc()){
    $pos_id = (int)$pos['id'];
    
    // Total votes cast for this position
    $total_pos_votes = (int)$conn->execute_query(
        "SELECT COUNT(*) as c FROM votes WHERE position_id = ? AND election_id = ?",
        [$pos_id, $election_id]
    )->fetch_assoc()['c'];

    // Candidates in this position
    $cand_stmt = $conn->execute_query(
        "SELECT c.id, c.firstname, c.lastname, c.photo, c.platform,
                (SELECT COUNT(*) FROM votes WHERE candidate_id = c.id AND position_id = ? AND election_id = ?) as votes
         FROM candidates c
         WHERE c.position_id = ?
         ORDER BY votes DESC, c.lastname ASC",
        [$pos_id, $election_id, $pos_id]
    );

    $candidates = [];
    $max_votes_in_pos = 0;
    while($cand = $cand_stmt->fetch_assoc()){
        $cand_votes = (int)$cand['votes'];
        if ($cand_votes > $max_votes_in_pos) {
            $max_votes_in_pos = $cand_votes;
        }
        $cand_pct = ($total_pos_votes > 0) ? round(($cand_votes / $total_pos_votes) * 100, 1) : 0;
        
        $candidates[] = [
            'id' => (int)$cand['id'],
            'name' => $cand['firstname'] . ' ' . $cand['lastname'],
            'photo' => !empty($cand['photo']) ? 'images/' . $cand['photo'] : 'images/profile.jpg',
            'platform' => $cand['platform'],
            'votes' => $cand_votes,
            'percentage' => $cand_pct,
            'is_leader' => false
        ];
    }

    if(count($candidates) === 1){
        $candidates[0]['name'] = 'Yes: ' . $candidates[0]['name'];
        $no_votes = (int)$conn->execute_query(
            "SELECT COUNT(*) AS c FROM votes WHERE candidate_id = 0 AND position_id = ? AND election_id = ?",
            [$pos_id, $election_id]
        )->fetch_assoc()['c'];
        $candidates[] = [
            'id' => 0,
            'name' => 'No',
            'photo' => 'images/profile.jpg',
            'platform' => '',
            'votes' => $no_votes,
            'percentage' => ($total_pos_votes > 0) ? round(($no_votes / $total_pos_votes) * 100, 1) : 0,
            'is_leader' => false
        ];
        $max_votes_in_pos = max($max_votes_in_pos, $no_votes);
    }

    // Assign is_leader flag if top candidate has at least 1 vote
    if ($max_votes_in_pos > 0) {
        foreach ($candidates as &$c) {
            if ($c['votes'] === $max_votes_in_pos) {
                $c['is_leader'] = true;
            }
        }
        unset($c);
    }

    $positions[] = [
        'id' => $pos_id,
        'description' => $pos['description'],
        'max_vote' => (int)$pos['max_vote'],
        'total_votes' => $total_pos_votes,
        'candidates' => $candidates
    ];
}

echo json_encode([
    'election' => [
        'id' => (int)$election['id'],
        'title' => $election['title'],
        'status' => $election['status'],
        'total_voters' => $total_voters,
        'ballots_cast' => $ballots_cast,
        'turnout_percentage' => $turnout_pct,
        'total_votes_overall' => $total_votes_overall,
        'server_time' => date('M j, Y - h:i:s A')
    ],
    'positions' => $positions
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
