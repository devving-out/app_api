<?php

require_once '../../../scripts/autoload.php';

$post = file_get_contents("php://input");
$post = json_decode($post, true);


$success = json_encode(array('status' => true));
$fail = json_encode(array('status' => false));

if (isset($post['username']) && isset($post['password']))  {
    $username = $post['username'];
    $password = $post['password'];
    $user = new User\AppUser();
    $user->setUserByUsername($username);
    if ($user && $user->validatePassword($password)) {
        echo $success;
    } else {
        echo $fail;
    }
} else {
    echo $fail;
}


?>