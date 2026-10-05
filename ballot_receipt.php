<?php
include 'includes/session.php';
include 'includes/header.php';

$active_election = get_active_election($conn);
$election_title = $active_election['title'] ?? 'General Election';
$election_id = (int)($active_election['id'] ?? 1);

// Fetch audit record
$audit_q = $conn->execute_query(
    "SELECT ballot_token, voted_at FROM voter_ballot_audit WHERE election_id = ? AND voter_id = ? LIMIT 1",
    [$election_id, $voter['id']]
);

if($audit_q->num_rows === 0){
    $_SESSION['error'][] = 'You have not cast a ballot in this election yet.';
    safe_redirect('home.php');
}

$audit_data = $audit_q->fetch_assoc();
$token = $audit_data['ballot_token'];
$voted_at = $audit_data['voted_at'];

// Count how many selections are sealed under this receipt
$count_q = $conn->execute_query("SELECT COUNT(*) as cnt FROM votes WHERE ballot_token = ?", [$token]);
$votes_count = $count_q->fetch_assoc()['cnt'] ?? 0;
?>
<body class="hold-transition skin-blue layout-top-nav">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container">
            <section class="content">
                <div class="row">
                    <div class="col-md-8 col-md-offset-2">
                        <div class="box box-solid box-primary" style="border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.12); overflow: hidden;">
                            <div class="box-header with-border" style="background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 25px; text-align: center;">
                                <img src="favicon/android-chrome-192x192.png" alt="Electo Logo" style="width: 52px; height: 52px; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.25);">
                                <div style="font-size: 13px; text-transform: uppercase; color: #64748b; letter-spacing: 2px; opacity: 0.85; margin-bottom: 5px;">Certified Cryptographic Ballot Receipt</div>
                                <h2 style="margin: 0; font-weight: 800; color: #1e293b; font-size: 26px;"><?php echo escape_html(strtoupper($election_title)); ?></h2>
                                <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px; color: #64748b;">End-to-End Cryptographically Verifiable Secret Ballot</p>
                            </div>

                            <div class="box-body" style="padding: 35px 40px; background: #fff;">
                                <div class="text-center" style="margin-bottom: 30px;">
                                    <div style="display: inline-block; padding: 6px 16px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 20px; color: #065f46; font-weight: 600; font-size: 13px; margin-bottom: 20px;">
                                        <i class="fa fa-shield"></i> OFFICIAL AUDIT RECORD - VERIFIED IN BALLOT BOX
                                    </div>
                                    <div style="background: #f8fafc; border: 2px dashed #3b82f6; border-radius: 12px; padding: 25px 20px; max-width: 480px; margin: 0 auto;">
                                        <div style="font-size: 12px; font-weight: 700; color: #64748b; letter-spacing: 1.5px; text-transform: uppercase;">Cryptographic Ballot Token</div>
                                        <div style="font-family: 'Courier New', monospace; font-size: 26px; font-weight: 900; color: #1e293b; letter-spacing: 3px; margin: 10px 0;">
                                            <?php echo escape_html($token); ?>
                                        </div>
                                        <div style="font-size: 13px; color: #10b981; font-weight: 600;">
                                            <i class="fa fa-check-circle"></i> <?php echo (int)$votes_count; ?> Position Selections Sealed
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-bordered table-striped" style="margin-bottom: 30px; font-size: 14px;">
                                    <tbody>
                                        <tr>
                                            <th style="width: 35%; background: #f8fafc;">Voter Name</th>
                                            <td><?php echo escape_html(($voter['firstname'] ?? '') . ' ' . ($voter['lastname'] ?? '')); ?></td>
                                        </tr>
                                        <tr>
                                            <th style="background: #f8fafc;">Voter ID</th>
                                            <td><code><?php echo escape_html($voter['voters_id'] ?? ''); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th style="background: #f8fafc;">Election ID</th>
                                            <td>Election #<?php echo (int)$election_id; ?> (<?php echo escape_html($election_title); ?>)</td>
                                        </tr>
                                        <tr>
                                            <th style="background: #f8fafc;">Submission Timestamp</th>
                                            <td><?php echo date('F j, Y \a\t g:i:s A T', strtotime($voted_at)); ?></td>
                                        </tr>
                                        
                                    </tbody>
                                </table>

                                <div class="well" style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #475569; line-height: 1.6;">
                                    <b><i class="fa fa-info-circle text-primary"></i> Verification Instructions:</b>
                                    Keep this receipt token for your personal records. Election observers and the election system use this token to prove that your ballot was deposited into the official ballot box and included in the certified vote tallies without compromising your privacy.
                                </div>

                                <div class="text-center" style="margin-top: 30px;">
                                    <button type="button" onclick="window.print()" class="btn btn-primary btn-lg btn-flat" style="border-radius: 6px; padding: 10px 30px;">
                                        <i class="fa fa-print"></i> Print Official Receipt
                                    </button>
                                    <a href="home.php" class="btn btn-default btn-lg btn-flat" style="border-radius: 6px; padding: 10px 30px; margin-left: 10px;">
                                        <i class="fa fa-arrow-left"></i> Return Home
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</div>

<style>
@media print {
    .main-header, .main-footer, .btn, .well, .alert {
        display: none !important;
    }
    .content-wrapper {
        background: #fff !important;
    }
    .box {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
