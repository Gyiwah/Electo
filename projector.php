<?php
require_once 'includes/security.php';
require_once 'includes/conn.php';

$active_election = get_active_election($conn);
$selected_election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : (int)($active_election['id'] ?? 1);

// Fetch election title
$elec_q = $conn->execute_query("SELECT id, title, status FROM elections WHERE id = ?", [$selected_election_id]);
$current_election = $elec_q->fetch_assoc() ?: ['id' => 1, 'title' => 'General Election', 'status' => 'active'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Election Tally Projector - <?php echo escape_html($current_election['title']); ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
    <link rel="shortcut icon" href="favicon/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap">
    <link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="dist/css/toast-notifications.css">
    
    <style>
        :root {
            --bg-base: #060913;
            --bg-surface: rgba(15, 23, 42, 0.75);
            --bg-card: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-glow: rgba(59, 130, 246, 0.3);
            --primary: #3b82f6;
            --primary-glow: #60a5fa;
            --accent-gold: #fbbf24;
            --accent-green: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-base);
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(37, 99, 235, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 90% 20%, rgba(16, 185, 129, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 50% 90%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.5;
        }

        /* Top Presenter Bar */
        .projector-header {
            background: rgba(10, 15, 29, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .header-content {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .title-block {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 12px #ef4444;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        .election-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 40%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Metric Badges */
        .metric-group {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-color);
            padding: 8px 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .metric-icon {
            font-size: 20px;
            color: var(--primary-glow);
        }

        .metric-info {
            display: flex;
            flex-direction: column;
        }

        .metric-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .metric-value {
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Presenter Controls */
        .controls-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ctrl-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .ctrl-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            transform: translateY(-1px);
        }

        .ctrl-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        /* Main Grid Container */
        .projector-body {
            max-width: 1800px;
            margin: 30px auto 60px auto;
            padding: 0 40px;
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(520px, 1fr));
            gap: 30px;
        }

        /* Position Card */
        .position-card {
            background: var(--bg-surface);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, border-color 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .position-card:hover {
            border-color: var(--border-glow);
            transform: translateY(-3px);
        }

        .position-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 16px;
        }

        .pos-title {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.3px;
        }

        .pos-badge {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: var(--primary-glow);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Candidate Row */
        .candidate-row {
            margin-bottom: 20px;
            position: relative;
        }

        .candidate-row:last-child {
            margin-bottom: 0;
        }

        .candidate-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .candidate-identity {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .candidate-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .candidate-avatar.leading {
            border-color: var(--accent-gold);
            box-shadow: 0 0 14px rgba(251, 191, 36, 0.4);
        }

        .candidate-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .leader-crown {
            color: var(--accent-gold);
            font-size: 14px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-4px); }
            60% { transform: translateY(-2px); }
        }

        .candidate-stats {
            text-align: right;
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .cand-percentage {
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
        }

        .cand-votes {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Progress Bar Track */
        .progress-track {
            height: 14px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #38bdf8);
            border-radius: 9999px;
            transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);
        }

        .progress-fill.leading-bar {
            background: linear-gradient(90deg, #d97706, #fbbf24);
            box-shadow: 0 0 12px rgba(251, 191, 36, 0.5);
        }

        /* Distance Mode (Extra Large Fonts for 100ft Projectors) */
        body.distance-mode .pos-title { font-size: 28px; }
        body.distance-mode .candidate-name { font-size: 20px; }
        body.distance-mode .cand-percentage { font-size: 26px; }
        body.distance-mode .progress-track { height: 18px; }
        body.distance-mode .candidate-avatar { width: 56px; height: 56px; }

        /* Footer */
        .projector-footer {
            text-align: center;
            padding: 30px 40px;
            color: var(--text-dim);
            font-size: 13px;
            border-top: 1px solid var(--border-color);
            background: rgba(10, 15, 29, 0.4);
        }

        @media (max-width: 768px) {
            .projector-header { padding: 15px 20px; }
            .projector-body { padding: 0 20px; }
            .positions-grid { grid-template-columns: 1fr; }
            .election-title { font-size: 20px; }
        }
    </style>
</head>
<body>

    <!-- Top Presenter Header -->
    <header class="projector-header">
        <div class="header-content">
            <div class="title-block">
                <img src="favicon/android-chrome-192x192.png" alt="GAF Logo" style="width: 44px; height: 44px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); flex-shrink: 0;">
                <div class="live-badge">
                    <span class="pulse-dot"></span> LIVE
                </div>
                <div>
                    <h1 class="election-title" id="disp_election_title"><?php echo escape_html($current_election['title']); ?></h1>
                </div>
            </div>

            <!-- Turnout & Audit Statistics -->
            <div class="metric-group">
                <div class="metric-card">
                    <i class="fa fa-users metric-icon" style="color: #60a5fa;"></i>
                    <div class="metric-info">
                        <span class="metric-label">Voter Turnout</span>
                        <span class="metric-value" id="disp_turnout_pct">0.0%</span>
                    </div>
                </div>

                <div class="metric-card">
                    <i class="fa fa-check-circle metric-icon" style="color: #34d399;"></i>
                    <div class="metric-info">
                        <span class="metric-label">Ballots Cast</span>
                        <span class="metric-value" id="disp_ballots_ratio">0 / 0</span>
                    </div>
                </div>

                <div class="metric-card">
                    <i class="fa fa-clock-o metric-icon" style="color: #fbbf24;"></i>
                    <div class="metric-info">
                        <span class="metric-label">Server Clock</span>
                        <span class="metric-value" id="disp_clock">--:--:--</span>
                    </div>
                </div>
            </div>

            <!-- Presenter Big-Screen Controls -->
            <div class="controls-group">
                <button type="button" class="ctrl-btn" id="btn_toggle_fullscreen" title="Toggle Fullscreen Projection">
                    <i class="fa fa-arrows-alt"></i> <span>Fullscreen</span>
                </button>
                <button type="button" class="ctrl-btn" id="btn_toggle_autoscroll" title="Auto-scroll across positions">
                    <i class="fa fa-refresh"></i> <span>Auto-Cycle</span>
                </button>
                <button type="button" class="ctrl-btn" id="btn_toggle_scale" title="Enlarge typography for auditorium projection">
                    <i class="fa fa-font"></i> <span>Auditorium Zoom</span>
                </button>
                <a href="admin/home.php" class="ctrl-btn" title="Return to Admin Dashboard">
                    <i class="fa fa-dashboard"></i> <span>Admin</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Projector Content Grid -->
    <main class="projector-body">
        <div class="positions-grid" id="positions_container">
            <!-- Dynamically populated via AJAX polling -->
            <div style="text-align: center; padding: 100px; color: var(--text-muted); grid-column: 1 / -1;">
                <i class="fa fa-spinner fa-spin fa-3x" style="color: var(--primary);"></i>
                <p style="margin-top: 15px; font-size: 16px;">Connecting to Secure Election Ballot Box...</p>
            </div>
        </div>
    </main>

    <footer class="projector-footer">
        <p><i class="fa fa-shield text-success"></i> End-to-End Cryptographically Verifiable Secret Ballot Architecture &bull; Auto-refreshing tally stream every 5s</p>
    </footer>

    <!-- Scripts -->
    <script src="bower_components/jquery/dist/jquery.min.js"></script>
    <script>
        const electionId = <?php echo (int)$selected_election_id; ?>;
        let isPolling = true;
        let autoScrollActive = false;
        let scrollInterval = null;

        // Fetch live tally from API
        function fetchLiveTallies() {
            if (!isPolling) return;

            $.ajax({
                url: 'live_data.php?election_id=' + electionId,
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    updateDashboard(data);
                },
                error: function(err) {
                    console.error('Error fetching live tallies:', err);
                }
            });
        }

        // Render data into the DOM without page flicker
        function updateDashboard(data) {
            const elec = data.election;
            $('#disp_election_title').text(elec.title);
            $('#disp_turnout_pct').text(elec.turnout_percentage + '%');
            $('#disp_ballots_ratio').text(elec.ballots_cast + ' / ' + elec.total_voters);
            $('#disp_clock').text(elec.server_time.split(' - ')[1] || elec.server_time);

            const container = $('#positions_container');
            
            // Check if container already initialized
            if (container.find('.position-card').length === 0) {
                // Initial build
                let html = '';
                data.positions.forEach(pos => {
                    html += `
                        <div class="position-card" id="pos_card_${pos.id}">
                            <div class="position-card-header">
                                <h2 class="pos-title">${escapeHtml(pos.description)}</h2>
                                <span class="pos-badge" id="pos_total_${pos.id}">${pos.total_votes} Total Votes</span>
                            </div>
                            <div class="candidates-list" id="pos_cand_list_${pos.id}">
                                ${renderCandidates(pos.candidates)}
                            </div>
                        </div>
                    `;
                });
                container.html(html);
            } else {
                // In-place smooth update (preserves CSS animations)
                data.positions.forEach(pos => {
                    $(`#pos_total_${pos.id}`).text(pos.total_votes + ' Total Votes');
                    $(`#pos_cand_list_${pos.id}`).html(renderCandidates(pos.candidates));
                });
            }
        }

        function renderCandidates(candidates) {
            let html = '';
            candidates.forEach(c => {
                const isLeader = c.is_leader && c.votes > 0;
                const fillClass = isLeader ? 'progress-fill leading-bar' : 'progress-fill';
                const avatarClass = isLeader ? 'candidate-avatar leading' : 'candidate-avatar';
                const crown = isLeader ? '<i class="fa fa-trophy leader-crown" title="Leading Candidate"></i>' : '';

                html += `
                    <div class="candidate-row">
                        <div class="candidate-meta">
                            <div class="candidate-identity">
                                <img src="${escapeHtml(c.photo)}" class="${avatarClass}" alt="${escapeHtml(c.name)}">
                                <div class="candidate-name">
                                    ${crown} ${escapeHtml(c.name)}
                                </div>
                            </div>
                            <div class="candidate-stats">
                                <span class="cand-percentage">${c.percentage}%</span>
                                <span class="cand-votes">(${c.votes} votes)</span>
                            </div>
                        </div>
                        <div class="progress-track">
                            <div class="${fillClass}" style="width: ${c.percentage}%;"></div>
                        </div>
                    </div>
                `;
            });
            return html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        // Fullscreen Toggle
        $('#btn_toggle_fullscreen').on('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    window.showToast('error', `Unable to enable fullscreen: ${err.message}`);
                });
                $(this).addClass('active');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    $(this).removeClass('active');
                }
            }
        });

        // Auditorium Scale Toggle
        $('#btn_toggle_scale').on('click', function() {
            $('body').toggleClass('distance-mode');
            $(this).toggleClass('active');
        });

        // Auto-Scroll Toggle (cycles through content for tall pages)
        $('#btn_toggle_autoscroll').on('click', function() {
            autoScrollActive = !autoScrollActive;
            $(this).toggleClass('active');

            if (autoScrollActive) {
                let direction = 1;
                scrollInterval = setInterval(function() {
                    const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
                    if (window.scrollY >= maxScroll - 10) {
                        direction = -1;
                    } else if (window.scrollY <= 10) {
                        direction = 1;
                    }
                    window.scrollBy({ top: 80 * direction, behavior: 'smooth' });
                }, 2000);
            } else {
                clearInterval(scrollInterval);
            }
        });

        // Initial fetch and 5-second polling loop
        fetchLiveTallies();
        setInterval(fetchLiveTallies, 5000);
    </script>
    <script src="dist/js/toast-notifications.js"></script>
</body>
</html>
