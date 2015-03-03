<?php


require_once '../scripts/autoload.php';

$user = new User\AppUser(1);

print_r($user->getLeads());

?>