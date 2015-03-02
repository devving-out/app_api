<?php

//set include path to app_api/src/
set_include_path(get_include_path() . ':' . realpath(dirname(__FILE__)) . '/../src');

//Namespace based autoloader
//class name must match filename
//namespace pieces must match file path
function __autoload($class)
{
    $parts = explode('\\', $class);
    require '' . implode('/', $parts) . '.php';
}

?>