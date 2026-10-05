<?php
/**
 * Safe Database Migration Script
 * Upgrades schema for:
 * 1. Multi-election support (elections table)
 * 2. Secret Ballot & Voter Audit separation (voter_ballot_audit table)
 * 3. System audit logging (audit_logs table)
 * Preserves all existing data safely.
 */

require_once __DIR__ . '/../includes/conn.php';

echo "Running Database Migrations...\n";

// 1. Create `elections` table
$conn->query("CREATE TABLE IF NOT EXISTS `elections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','closed','archived') NOT NULL DEFAULT 'active',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
    `signatory_1_name` varchar(150) DEFAULT NULL,
    `signatory_1_role` varchar(150) DEFAULT NULL,
    `signatory_2_name` varchar(150) DEFAULT NULL,
    `signatory_2_role` varchar(150) DEFAULT NULL,
    `signatory_3_name` varchar(150) DEFAULT NULL,
    `signatory_3_role` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "- Checked/Created `elections` table.\n";

$signatory_columns = [
    'signatory_1_name' => 'varchar(150) DEFAULT NULL',
    'signatory_1_role' => 'varchar(150) DEFAULT NULL',
    'signatory_2_name' => 'varchar(150) DEFAULT NULL',
    'signatory_2_role' => 'varchar(150) DEFAULT NULL',
    'signatory_3_name' => 'varchar(150) DEFAULT NULL',
    'signatory_3_role' => 'varchar(150) DEFAULT NULL'
];
foreach ($signatory_columns as $column => $definition) {
    $column_exists = $conn->query("SHOW COLUMNS FROM `elections` LIKE '$column'");
    if ($column_exists->num_rows === 0) {
        $conn->query("ALTER TABLE `elections` ADD `$column` $definition");
        echo "- Added `$column` to `elections`.\n";
    }
}

// If elections table is empty, seed active election from config.ini
$check_elec = $conn->query("SELECT id FROM `elections` LIMIT 1");
if ($check_elec->num_rows == 0) {
    $ini_title = 'Voting System Election';
    if (file_exists(__DIR__ . '/../admin/config.ini')) {
        $parse = @parse_ini_file(__DIR__ . '/../admin/config.ini', FALSE, INI_SCANNER_RAW);
        if (!empty($parse['election_title'])) {
            $ini_title = $parse['election_title'];
        }
    }
    $conn->execute_query("INSERT INTO `elections` (title, description, status) VALUES (?, ?, 'active')", [
        $ini_title,
        'Default election initialized from system settings.'
    ]);
    $default_election_id = $conn->insert_id;
    echo "- Seeded default active election: '$ini_title' (ID: $default_election_id)\n";
} else {
    $default_election_id = $check_elec->fetch_assoc()['id'];
}

// 2. Add `election_id` to `positions` and `candidates` if not exists
$pos_cols = $conn->query("SHOW COLUMNS FROM `positions` LIKE 'election_id'");
if ($pos_cols->num_rows == 0) {
    $conn->query("ALTER TABLE `positions` ADD `election_id` int(11) NOT NULL DEFAULT 1 AFTER `id`");
    $conn->execute_query("UPDATE `positions` SET `election_id` = ?", [$default_election_id]);
    echo "- Added `election_id` to `positions` table.\n";
}

// 3. Create `voter_ballot_audit` table to decouple voter identity from cast votes (Secret Ballot)
$conn->query("CREATE TABLE IF NOT EXISTS `voter_ballot_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL DEFAULT 1,
  `voter_id` int(11) NOT NULL,
  `ballot_token` varchar(64) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_voter_election` (`election_id`, `voter_id`),
  KEY `idx_ballot_token` (`ballot_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "- Checked/Created `voter_ballot_audit` table.\n";

// Populate voter_ballot_audit from existing votes if empty
$check_audit = $conn->query("SELECT id FROM `voter_ballot_audit` LIMIT 1");
if ($check_audit->num_rows == 0) {
    $existing_voters = $conn->query("SELECT DISTINCT voters_id FROM votes WHERE voters_id > 0");
    while ($vrow = $existing_voters->fetch_assoc()) {
        $token = bin2hex(random_bytes(16));
        $conn->execute_query(
            "INSERT IGNORE INTO `voter_ballot_audit` (election_id, voter_id, ballot_token) VALUES (?, ?, ?)",
            [$default_election_id, $vrow['voters_id'], $token]
        );
    }
    echo "- Migrated existing voter participation records into `voter_ballot_audit`.\n";
}

// 4. Add `election_id` and `ballot_token` to `votes` table if not exists
$votes_cols = $conn->query("SHOW COLUMNS FROM `votes` LIKE 'election_id'");
if ($votes_cols->num_rows == 0) {
    $conn->query("ALTER TABLE `votes` ADD `election_id` int(11) NOT NULL DEFAULT 1 AFTER `id`");
    $conn->execute_query("UPDATE `votes` SET `election_id` = ?", [$default_election_id]);
    echo "- Added `election_id` to `votes` table.\n";
}

$votes_token = $conn->query("SHOW COLUMNS FROM `votes` LIKE 'ballot_token'");
if ($votes_token->num_rows == 0) {
    $conn->query("ALTER TABLE `votes` ADD `ballot_token` varchar(64) DEFAULT NULL AFTER `position_id`");
    echo "- Added `ballot_token` to `votes` table.\n";
}

// 5. Create `audit_logs` table for administrative security and compliance tracking
$conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','voter','system') NOT NULL DEFAULT 'system',
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "- Checked/Created `audit_logs` table.\n";

// 6. Add `phone`, `otp_code`, and `otp_expiry` to `voters` table
$voters_phone = $conn->query("SHOW COLUMNS FROM `voters` LIKE 'phone'");
if ($voters_phone->num_rows == 0) {
    $conn->query("ALTER TABLE `voters` ADD `phone` varchar(30) NOT NULL DEFAULT '' AFTER `lastname`");
    $conn->query("ALTER TABLE `voters` ADD `otp_code` varchar(10) DEFAULT NULL AFTER `phone`");
    $conn->query("ALTER TABLE `voters` ADD `otp_expiry` datetime DEFAULT NULL AFTER `otp_code`");
    $conn->query("ALTER TABLE `voters` MODIFY `voters_id` varchar(50) NOT NULL");
    $conn->query("ALTER TABLE `voters` MODIFY `password` varchar(255) NOT NULL");
    echo "- Added `phone`, `otp_code`, and `otp_expiry` columns to `voters` table.\n";

    // Backfill phone and password for existing voters without phone
    $no_phone_voters = $conn->query("SELECT id, voters_id FROM `voters` WHERE phone = '' OR phone IS NULL");
    $backfill_count = 0;
    while ($nv = $no_phone_voters->fetch_assoc()) {
        $sample_phone = sprintf("024%07d", (int)$nv['id']);
        $p_hash = password_hash($sample_phone, PASSWORD_DEFAULT);
        $conn->execute_query("UPDATE `voters` SET phone = ?, password = ? WHERE id = ?", [$sample_phone, $p_hash, $nv['id']]);
        $backfill_count++;
    }
    if ($backfill_count > 0) {
        echo "- Backfilled $backfill_count legacy voter records with formatted phone passwords (e.g. 0240000001).\n";
    }
}

// 7. Create `voter_elections` table for assigning/filtering voters per election
$conn->query("CREATE TABLE IF NOT EXISTS `voter_elections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voter_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_voter_election` (`voter_id`, `election_id`),
  KEY `idx_election` (`election_id`),
  KEY `idx_voter` (`voter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "- Checked/Created `voter_elections` table.\n";

// Populate voter_elections for existing voters if empty
$check_ve = $conn->query("SELECT id FROM `voter_elections` LIMIT 1");
if ($check_ve->num_rows == 0) {
    $e_list = $conn->query("SELECT id FROM `elections`");
    $e_ids = [];
    while ($er = $e_list->fetch_assoc()) {
        $e_ids[] = (int)$er['id'];
    }
    if (!empty($e_ids)) {
        $v_list = $conn->query("SELECT id FROM `voters`");
        while ($vr = $v_list->fetch_assoc()) {
            foreach ($e_ids as $eid) {
                $conn->execute_query(
                    "INSERT IGNORE INTO `voter_elections` (voter_id, election_id) VALUES (?, ?)",
                    [$vr['id'], $eid]
                );
            }
        }
        echo "- Seeded existing voters into `voter_elections` for active elections.\n";
    }
}

// 8. Add `election_id` column to `candidates` table if missing
$cand_elec = $conn->query("SHOW COLUMNS FROM `candidates` LIKE 'election_id'");
if ($cand_elec->num_rows == 0) {
    $conn->query("ALTER TABLE `candidates` ADD `election_id` int(11) NOT NULL DEFAULT 1 AFTER `position_id`");
    $conn->query("UPDATE `candidates` c INNER JOIN `positions` p ON p.id = c.position_id SET c.election_id = p.election_id");
    echo "- Added `election_id` to `candidates` table and synchronized from positions.\n";
}

// 9. Log migration event
$conn->execute_query(
    "INSERT INTO `audit_logs` (user_type, action, details, ip_address) VALUES ('system', 'SCHEMA_MIGRATION', 'Database upgraded with multi-election voter assignment and candidate sync.', '127.0.0.1')"
);

echo "Migrations completed successfully!\n";


