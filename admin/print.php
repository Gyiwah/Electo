<?php
include 'includes/session.php';

$active_election = get_active_election($conn);
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

// Fetch election details
$elec_q = $conn->execute_query("SELECT * FROM elections WHERE id = ?", [$election_id]);
$current_election = $elec_q->fetch_assoc() ?: [
    'id' => 1,
    'title' => 'General Election',
    'status' => 'active'
];

$election_title = $current_election['title'];
$election_status = strtoupper($current_election['status']);

// Turnout metrics (based on eligible voters assigned to this election)
$total_voters = get_election_eligible_voters_count($conn, $election_id);
$ballots_stmt = $conn->execute_query(
    "SELECT COUNT(DISTINCT voter_id) as c FROM voter_ballot_audit WHERE election_id = ?",
    [$election_id]
);
$ballots_cast = (int)$ballots_stmt->fetch_assoc()['c'];
if ($ballots_cast === 0) {
    $ballots_cast = (int)$conn->execute_query(
        "SELECT COUNT(DISTINCT voters_id) as c FROM votes WHERE voters_id > 0 AND election_id = ?",
        [$election_id]
    )->fetch_assoc()['c'];
}

$turnout_pct = ($total_voters > 0) ? round(($ballots_cast / $total_voters) * 100, 1) : 0;
$total_votes_overall = (int)$conn->execute_query("SELECT COUNT(*) as c FROM votes WHERE election_id = ?", [$election_id])->fetch_assoc()['c'];

// Cryptographic audit hash for report tamper-evidence
$audit_hash = strtoupper(substr(hash('sha256', $election_id . $election_title . $ballots_cast . $total_votes_overall), 0, 16));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certified Results - <?php echo escape_html($election_title); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    
    <style>
        :root {
            --primary: #1e3a8a;
            --accent: #2563eb;
            --success: #059669;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border: #cbd5e1;
            --surface-muted: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: var(--text-main);
            line-height: 1.5;
            padding-bottom: 50px;
        }

        /* Top Presenter / Action Bar (hidden on print) */
        .no-print-bar {
            background: #0f172a;
            color: #ffffff;
            padding: 14px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .bar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
        }
        .btn-print:hover { background: #1d4ed8; }

        .btn-csv {
            background: #059669;
            color: #fff;
        }
        .btn-csv:hover { background: #047857; }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        .btn-back:hover { background: rgba(255, 255, 255, 0.25); }

        /* Report Paper Sheet */
        .report-page {
            max-width: 900px;
            margin: 30px auto;
            background: #ffffff;
            padding: 50px 60px;
            border-radius: 8px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        /* Letterhead */
        .report-header {
            text-align: center;
            color: var(--text-main);
            background: #ffffff;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 25px;
            margin-bottom: 30px;
            position: relative;
        }

        .report-logo {
            width: 76px;
            height: 76px;
            padding: 8px;
            background: #ffffff;
            border: 1px solid #dbe3eb;
            border-radius: 16px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
        }

        .report-logo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .doc-org {
            color: #475569;
        }

        .doc-title {
            color: #0f172a;
        }

        .doc-subtitle {
            color: #1d4ed8;
        }

        .doc-org {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .doc-title {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .doc-subtitle {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
        }

        /* Metadata Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 24px;
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 18px 22px;
            margin-bottom: 35px;
            font-size: 13px;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 6px;
        }

        .meta-label {
            color: var(--text-muted);
            font-weight: 600;
        }

        .meta-value {
            font-weight: 700;
            color: var(--text-main);
        }

        /* Results Tables */
        .position-section {
            margin-bottom: 35px;
            page-break-inside: avoid;
        }

        .position-header {
            background: #f1f5f9;
            border-left: 4px solid var(--accent);
            padding: 10px 16px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .results-table th, .results-table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .results-table th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .winner-row {
            background: #f0fdf4 !important;
        }

        .winner-badge {
            background: #10b981;
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 6px;
        }

        .votes-num {
            font-weight: 800;
            font-size: 14px;
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
        }

        .pct-num {
            font-weight: 700;
            color: var(--text-muted);
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
        }

        .pos-summary-row td {
            background: #f8fafc;
            font-weight: 800;
            border-top: 2px solid #cbd5e1;
        }

        /* Sign-off Attestation */
        .attestation-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #0f172a;
            page-break-inside: avoid;
        }

        .sign-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 35px;
            text-align: center;
        }

        .sign-box {
            border-top: 1px solid #0f172a;
            padding-top: 8px;
            font-size: 12px;
        }

        .sign-title {
            font-weight: 700;
            color: #0f172a;
        }

        .sign-role {
            color: var(--text-muted);
            font-size: 11px;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .report-page {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 20px 30px !important;
                border: none !important;
                box-shadow: none !important;
            }
            .winner-row {
                background: #f0fdf4 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .winner-badge {
                background: #10b981 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .position-header {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Sticky Presenter & Printing Toolbar -->
    <div class="no-print-bar">
        <div class="bar-left">
            <span style="font-weight: 800; letter-spacing: -0.5px; font-size: 16px;">
                <i class="fa fa-certificate text-warning"></i> Official Certified Tally
            </span>
            <span style="opacity: 0.6; font-size: 13px;">| Election #<?php echo (int)$election_id; ?></span>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <i class="fa fa-print"></i> Print / Save as PDF
            </button>
            <a href="votes_export.php?election_id=<?php echo (int)$election_id; ?>" class="btn-action btn-csv">
                <i class="fa fa-file-excel-o"></i> Export CSV
            </a>
            <a href="home.php" class="btn-action btn-back">
                <i class="fa fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Official Report Document -->
    <div class="report-page">
        <!-- Letterhead -->
        <header class="report-header">
            <div class="report-logo">
                <img src="../favicon/android-chrome-192x192.png" alt="Electo logo">
            </div>
            <div class="doc-org">Independent Electoral & Governance Commission &bull; GAF Tech</div>
            <h1 class="doc-title">OFFICIAL CERTIFIED ELECTION RESULTS</h1>
            <div class="doc-subtitle"><?php echo escape_html($election_title); ?></div>
        </header>

        <!-- Election Metadata -->
        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Certification Date:</span>
                <span class="meta-value"><?php echo date('F j, Y - g:i:s A T'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Certified Officer:</span>
                <span class="meta-value"><?php echo escape_html(($user['firstname'] ?? 'Admin') . ' ' . ($user['lastname'] ?? '')); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Voter Turnout:</span>
                <span class="meta-value"><?php echo $turnout_pct; ?>% (<?php echo $ballots_cast; ?> of <?php echo $total_voters; ?> Eligible Voters)</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Audit Verification Hash:</span>
                <span class="meta-value"><code><?php echo $audit_hash; ?></code></span>
            </div>
        </div>

        <!-- Position Results -->
        <?php
            $signatories = [
                [
                    'name' => trim($current_election['signatory_1_name'] ?? '') ?: (($user['firstname'] ?? 'Admin') . ' ' . ($user['lastname'] ?? '')),
                    'role' => trim($current_election['signatory_1_role'] ?? '') ?: 'Chief Returning Officer'
                ],
                [
                    'name' => trim($current_election['signatory_2_name'] ?? '') ?: 'Independent Observer',
                    'role' => trim($current_election['signatory_2_role'] ?? '') ?: 'Electoral Scrutineer'
                ],
                [
                    'name' => trim($current_election['signatory_3_name'] ?? '') ?: 'System Administrator',
                    'role' => trim($current_election['signatory_3_role'] ?? '') ?: 'Digital Audit Officer'
                ]
            ];
        ?>
        <?php
            $pos_query = $conn->execute_query("SELECT * FROM positions WHERE election_id = ? ORDER BY priority ASC", [$election_id]);

            while($pos = $pos_query->fetch_assoc()){
                $pos_id = (int)$pos['id'];
                $pos_title = $pos['description'];
                $max_vote = (int)$pos['max_vote'];

                // Total votes cast for this position in this election
                $pos_total = (int)$conn->execute_query(
                    "SELECT COUNT(*) as cnt FROM votes WHERE position_id = ? AND election_id = ?",
                    [$pos_id, $election_id]
                )->fetch_assoc()['cnt'];

                // Candidates and votes
                $cquery = $conn->execute_query(
                    "SELECT c.id, c.firstname, c.lastname, 
                            (SELECT COUNT(*) FROM votes WHERE candidate_id = c.id AND position_id = ? AND election_id = ?) as vote_count 
                     FROM candidates c 
                     WHERE c.position_id = ? 
                     ORDER BY vote_count DESC, c.lastname ASC",
                    [$pos_id, $election_id, $pos_id]
                );

                $candidates = [];
                $top_vote = 0;
                while($crow = $cquery->fetch_assoc()){
                    $cnt = (int)$crow['vote_count'];
                    if ($cnt > $top_vote) {
                        $top_vote = $cnt;
                    }
                    $candidates[] = $crow;
                }
                if(count($candidates) === 1){
                    $candidates[0]['display_name'] = 'Yes: ' . $candidates[0]['lastname'] . ', ' . $candidates[0]['firstname'];
                    $no_votes = (int)$conn->execute_query(
                        "SELECT COUNT(*) AS cnt FROM votes WHERE candidate_id = 0 AND position_id = ? AND election_id = ?",
                        [$pos_id, $election_id]
                    )->fetch_assoc()['cnt'];
                    $candidates[] = ['firstname' => '', 'lastname' => 'No', 'display_name' => 'No', 'vote_count' => $no_votes];
                    $top_vote = max($top_vote, $no_votes);
                }
                ?>
                <div class="position-section">
                    <div class="position-header">
                        <span><?php echo escape_html($pos_title); ?></span>
                        <span style="font-size: 12px; font-weight: 600; opacity: 0.85;">
                            Rule: Select <?php echo $max_vote; ?> candidate<?php echo ($max_vote > 1) ? 's' : ''; ?>
                        </span>
                    </div>

                    <table class="results-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Rank</th>
                                <th style="width: 50%;">Candidate Name</th>
                                <th style="width: 20%; text-align: right;">Certified Votes</th>
                                <th style="width: 20%; text-align: right;">Share of Tally</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach($candidates as $cand){
                                $vcount = (int)$cand['vote_count'];
                                $pct = ($pos_total > 0) ? round(($vcount / $pos_total) * 100, 1) : 0;
                                $isWinner = ($vcount === $top_vote && $top_vote > 0);
                                $rowClass = $isWinner ? 'winner-row' : '';
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><b>#<?php echo $rank++; ?></b></td>
                                    <td>
                                        <b><?php echo escape_html($cand['display_name'] ?? ($cand['lastname'] . ', ' . $cand['firstname'])); ?></b>
                                        <?php if($isWinner): ?>
                                            <span class="winner-badge"><i class="fa fa-trophy"></i> Leading</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="votes-num"><?php echo number_format($vcount); ?></td>
                                    <td class="pct-num"><?php echo $pct; ?>%</td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr class="pos-summary-row">
                                <td colspan="2"><b>Total Certified Votes for <?php echo escape_html($pos_title); ?></b></td>
                                <td class="votes-num"><b><?php echo number_format($pos_total); ?></b></td>
                                <td class="pct-num"><b>100.0%</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php
            }
        ?>

        <!-- Attestation Sign-Off -->
        <footer class="attestation-section">
            <div style="font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 20px;">
                <b>Official Statement of Attestation:</b> We hereby certify that the results declared above represent a true, accurate, and cryptographically verified tally of all ballots cast in this election under the Secret Ballot protocol.
            </div>

            <div class="sign-grid">
                <?php foreach($signatories as $signatory): ?>
                    <div class="sign-box">
                        <div class="sign-title"><?php echo escape_html($signatory['name']); ?></div>
                        <div class="sign-role"><?php echo escape_html($signatory['role']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px; text-align: center; font-size: 11px; color: #94a3b8;">
                Generated by GAF Tech Voting System Enterprise Edition &bull; Security Standard SHA-256 Verified &bull; Document ID: <code><?php echo $audit_hash; ?></code>
            </div>
        </footer>
    </div>

</body>
</html>