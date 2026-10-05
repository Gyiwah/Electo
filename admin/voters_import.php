<?php
include 'includes/session.php';
require_once __DIR__ . '/../includes/sms.php';

if(isset($_POST['import'])){
    if(!verify_csrf_token()){
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        safe_redirect('voters.php');
    }

    if(!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK){
        $_SESSION['error'] = 'Please upload a valid CSV file.';
        safe_redirect('voters.php');
    }

    $file = $_FILES['csv_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if($ext !== 'csv'){
        $_SESSION['error'] = 'Only CSV files (.csv) are accepted.';
        safe_redirect('voters.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if(!$handle){
        $_SESSION['error'] = 'Unable to read the uploaded CSV file.';
        safe_redirect('voters.php');
    }

    $has_header = !empty($_POST['has_header']);
    if($has_header){
        // Discard header row
        fgetcsv($handle, 2048, ',');
    }

    $imported = [];
    $errors = [];
    $row_num = $has_header ? 1 : 0;

    $insert_stmt = $conn->prepare("INSERT INTO voters (voters_id, password, firstname, lastname, phone, photo) VALUES (?, ?, ?, ?, ?, '')");

    $target_election_id = (int)($_POST['import_election_id'] ?? 0);
    $active_elecs = get_active_elections($conn);

    while(($data = fgetcsv($handle, 2048, ',')) !== false){
        $row_num++;
        // Allow trimming and skip completely blank rows
        $data = array_map('trim', $data);
        if(empty(array_filter($data))){
            continue;
        }

        $firstname = $data[0] ?? '';
        $lastname = $data[1] ?? '';
        $phone = normalize_phone_number($data[2] ?? '');
        $voter_id = $data[3] ?? '';

        if(empty($firstname) || empty($lastname)){
            $errors[] = "Row #$row_num: Firstname and Lastname are required. Row skipped.";
            continue;
        }

        if(empty($phone)){
            $errors[] = "Row #$row_num: Phone number is required for {$firstname} {$lastname}. Row skipped.";
            continue;
        }

        // Generate voter ID if omitted
        if(empty($voter_id)){
            do {
                $voter_id = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 10));
                $check_v = $conn->execute_query("SELECT id FROM voters WHERE voters_id = ? LIMIT 1", [$voter_id]);
            } while($check_v->num_rows > 0);
        } else {
            // Check if voter ID already exists
            $check_v = $conn->execute_query("SELECT id FROM voters WHERE voters_id = ? LIMIT 1", [$voter_id]);
            if($check_v->num_rows > 0){
                $errors[] = "Row #$row_num: Voter ID '$voter_id' already exists in the system. Row skipped.";
                continue;
            }
        }

        // Phone number is the password
        $hashed_pass = password_hash($phone, PASSWORD_DEFAULT);
        $insert_stmt->bind_param('sssss', $voter_id, $hashed_pass, $firstname, $lastname, $phone);

        if($insert_stmt->execute()){
            $new_voter_id = $conn->insert_id;
            if($target_election_id > 0){
                $conn->execute_query("INSERT IGNORE INTO voter_elections (voter_id, election_id) VALUES (?, ?)", [$new_voter_id, $target_election_id]);
            } else {
                foreach($active_elecs as $ae){
                    $conn->execute_query("INSERT IGNORE INTO voter_elections (voter_id, election_id) VALUES (?, ?)", [$new_voter_id, (int)$ae['id']]);
                }
            }

            $imported[] = [
                'voter_id'  => $voter_id,
                'firstname' => $firstname,
                'lastname'  => $lastname,
                'phone'     => $phone
            ];
        } else {
            $errors[] = "Row #$row_num: Database error - " . $conn->error;
        }
    }

    fclose($handle);

    $count_imported = count($imported);
    if($count_imported > 0){
        $_SESSION['imported_credentials'] = $imported;
        log_audit($conn, 'admin', $user['id'], 'VOTERS_BULK_IMPORT', "Bulk imported $count_imported new voters via CSV with phone credentials.");
        $msg = "Successfully imported $count_imported new voters!";
        if(!empty($errors)){
            $msg .= " (" . count($errors) . " rows had errors and were skipped).";
        }
        $_SESSION['success'] = $msg;
    } else {
        $_SESSION['error'] = !empty($errors) ? implode('<br>', $errors) : 'No voters were found in the uploaded file.';
    }
} else {
    $_SESSION['error'] = 'Select a CSV file to import first.';
}

safe_redirect('voters.php');
?>
