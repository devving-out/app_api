<?php

require_once '../../../scripts/autoload.php';

if (isset($_GET['user_id']))  {
	$user = new User\AppUser($_GET['user_id']);
	if ($user) {
		$leads = $user->getLeads();
		echo json_encode(array('status' => true, 'message' => 'Leads fetched!', 'data' => $leads));
	} else {
		echo json_encode(array('status' => false, 'message' => 'Unknown user!', 'data' => false));
	}
}


?>