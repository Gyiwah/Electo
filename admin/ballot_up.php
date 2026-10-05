<?php
	include 'includes/session.php';

	header('Content-Type: application/json');
	$output = array('error' => false);

	if(isset($_POST['id'])){
		$id = (int)$_POST['id'];

		$row = $conn->execute_query("SELECT * FROM positions WHERE id = ?", [$id])->fetch_assoc();
		if(!$row){
			$output['error'] = true;
			$output['message'] = 'Position not found';
			echo json_encode($output);
			exit();
		}

		$current_priority = (int)$row['priority'];
		$target_priority = $current_priority - 1;

		if($target_priority < 1){
			$output['error'] = true;
			$output['message'] = 'This position is already at the top';
		}
		else{
			$conn->begin_transaction();
			try {
				// Swap priorities atomically
				$conn->execute_query("UPDATE positions SET priority = ? WHERE priority = ?", [$current_priority, $target_priority]);
				$conn->execute_query("UPDATE positions SET priority = ? WHERE id = ?", [$target_priority, $id]);
				$conn->commit();
			} catch (Exception $e) {
				$conn->rollback();
				$output['error'] = true;
				$output['message'] = 'Failed to reorder position: ' . $e->getMessage();
			}
		}
	} else {
		$output['error'] = true;
		$output['message'] = 'Position ID required';
	}

	echo json_encode($output);
?>