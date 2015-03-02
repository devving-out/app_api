<?php

$host = '54.69.7.81';
$user = 'mac';
$pass = 'reuben99';
$pers = false;
// $db = new SimplePDO(
//     "mysql:host={$host};dbname={$name}",
//     $user,
//     $pass,
//     $pers
// );

var_dump(new DB($user, $pass, $host));

class DB {

    private $dbh;
    private $stmt;

    public function __construct($user, $pass, $host) {
        $this->dbh = new PDO(
            "mysql:host=localhost;dbname=app;port:3306;",
            $user,
            $pass,
            array( PDO::ATTR_PERSISTENT => false )
        );
    }

    public function query($query) {
        $this->stmt = $this->dbh->prepare($query);
        return $this;
    }

    public function bind($pos, $value, $type = null) {

        if( is_null($type) ) {
            switch( true ) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }

        $this->stmt->bindValue($pos, $value, $type);
        return $this;
    }

    public function execute() {
        return $this->stmt->execute();
    }

    public function resultset() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }
}

?>