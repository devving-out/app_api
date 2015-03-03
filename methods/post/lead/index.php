<?php

require_once '../../../scripts/autoload.php';

$content = file_get_contents("php://input");
$post = json_decode($content, true);

if (isset($post['lead'])) {
    $lead_obj = new \Leads\Lead();
    $lead = $post['lead'];
    if ($lead_obj->create($lead)) {
        $return = array('status' => true, 'message' => 'Lead successfull created!');
        echo json_encode($return);
    } else {
        $return = array('status' => false, 'message' => 'Error creating lead!');
        echo json_encode($return);
    }
}