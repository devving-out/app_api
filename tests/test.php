<?php

require_once '../scripts/autoload.php';

$db = DB\PdoManager::instance('APP');

var_dump(
    DB\PdoManager::instance('APP')->fetchOne(
        'select username from users limit 1', array()
    )
);

?>