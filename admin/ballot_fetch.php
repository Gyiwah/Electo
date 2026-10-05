<?php
	include 'includes/session.php';
	include 'includes/slugify.php';

	header('Content-Type: application/json');

	$total_positions = (int)$conn->query("SELECT COUNT(*) as cnt FROM positions")->fetch_assoc()['cnt'];

	$output = '';
	$candidate = '';

	$query = $conn->query("SELECT * FROM positions ORDER BY priority ASC");
	$num = 1;
	while($row = $query->fetch_assoc()){
		$slug = slugify($row['description']);
		$input = ($row['max_vote'] > 1) 
			? '<input type="checkbox" class="flat-red '.$slug.'" name="'.$slug."[]".'">' 
			: '<input type="radio" class="flat-red '.$slug.'" name="'.$slug.'">';

		$cquery = $conn->execute_query("SELECT * FROM candidates WHERE position_id = ?", [$row['id']]);
		while($crow = $cquery->fetch_assoc()){
			$image = (!empty($crow['photo'])) ? '../images/'.escape_html($crow['photo']) : '../images/profile.jpg';
			$candidate .= '
				<li>
					'.$input.'<button type="button" class="btn btn-primary btn-sm btn-flat clist"><i class="fa fa-search"></i> Platform</button><img src="'.$image.'" height="100px" width="100px" class="clist"><span class="cname clist">'.escape_html($crow['firstname'].' '.$crow['lastname']).'</span>
				</li>
			';
		}

		$instruct = ($row['max_vote'] > 1) ? 'You may select up to '.(int)$row['max_vote'].' candidates' : 'Select only one candidate';
		
		$updisable = ($num == 1) ? 'disabled' : '';
		$downdisable = ($num == $total_positions) ? 'disabled' : '';

		$output .= '
			<div class="row">
				<div class="col-xs-12">
					<div class="box box-solid" id="'.(int)$row['id'].'">
						<div class="box-header with-border">
							<h3 class="box-title"><b>'.escape_html($row['description']).'</b></h3>
							<div class="pull-right box-tools">
				                <button type="button" class="btn btn-default btn-sm moveup" data-id="'.(int)$row['id'].'" '.$updisable.'><i class="fa fa-arrow-up"></i> </button>
				                <button type="button" class="btn btn-default btn-sm movedown" data-id="'.(int)$row['id'].'" '.$downdisable.'><i class="fa fa-arrow-down"></i></button>
				            </div>
						</div>
						<div class="box-body">
							<p>'.$instruct.'
								<span class="pull-right">
									<button type="button" class="btn btn-success btn-sm btn-flat reset" data-desc="'.$slug.'"><i class="fa fa-refresh"></i> Reset</button>
								</span>
							</p>
							<div id="candidate_list">
								<ul>
									'.$candidate.'
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		';

		$num++;
		$candidate = '';
	}

	echo json_encode($output);
?>