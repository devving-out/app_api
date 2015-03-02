<?php

namespace DB;

class PdoManager
{
    // Force use write connections for all queries
    protected $critical = 0;

    // transaction special flag letting
    // us know to use write connections
    // only for all queries within a transaction
    protected $hasActiveTransaction = 0;

    // The location of the db you are trying to use
    // OPTIONS: DEV / LIVE / TEST possibly more in the future
    protected $db_location = "LIVE";

    // The name of the db, it references the label in the conection_credentials
    // attribute of the ContectionManager class
    protected $db_name = "";

    // factory list of managers
    protected static $managers = array();

    // used for creating multiple otherwise identical db connections
    protected $custom_key = false;

    /**
     * Constructor for the pdo manager class
     *
     * @param String $db_name     the label/name of the db schema to default to
     * @param String $db_location The location of the DB you are connecting
     *                               (e.g. DEV)
     * @param bool   $critical    force use write connections for all queries
     * @param Mixed  $custom_key  optional paramater passed for giving this connection a
     *                            custom identifier.  Useful for making multiple identical connections
     *
     * @return null
     */
    private function __construct(
        $db_name,
        $db_location,
        $critical    = 0,
        $custom_key = false
    ) {
        $this->db_location = strtoupper($db_location);
        $this->db_name  = strtoupper($db_name);
        $this->critical = $critical;
        $this->custom_key = $custom_key;

        if (ConnectionManager::getMode() === "DEBUG") {
            $this->beginTransaction();
        }
    }

    /**
     * Constructor for the pdo manager class
     *
     * @param String $db_name     the label/name of the db schema to default to
     * @param String $db_location The location of the DB you are connecting
     *                               (e.g. DEV)
     * @param int    $critical    force use write connections for all queries
     * @param Mixed  $custom_key  Optional.  Include a string only if multiple
     *                                identical connections are necessary
     *
     * @return null
     */
    public static function instance(
        $db_name,
        $db_location = 'DEFAULT',
        $critical    = 0,
        $custom_key = false
    ) {
        $db_location = ConnectionManager::resolveLocationAlias($db_location);

        $class = get_called_class();
        $key = $db_location . "_" . $db_name . "_" . $critical;

        // add custom key if necessary
        if ($custom_key !== false) {
            $key .= "_" . $custom_key;
        }
        if (!isset(self::$managers[$key])) {
            self::$managers[$key] = new $class(
                $db_name,
                $db_location,
                $critical,
                $custom_key
            );
        }
        return self::$managers[$key];
    }

    /**
     * removes the passed db from the connection manager cache, needs all the same paramaters
     * as instance because each is used to key the specific connection
     *
     * @param String $db_name     the label/name of the db schema to default to
     * @param String $db_location The location of the DB you are removing
     *                               (e.g. DEV)
     * @param String $type        read / write connection type
     *
     * @return null
     */
    public static function forget(
        $db_name,
        $db_location = 'DEFAULT',
        $type = 'WRITE'
    ) {
        ConnectionManager::forgetConnection($db_name, $db_location, $type, $this->custom_key);
    }

    /**
     * removes all connections that are currently in the connection manager cache
     *
     * @return null
     */
    public static function forgetAll()
    {
        ConnectionManager::forgetAllConnections();
    }

    /**
     * Set the DB name
     *
     * @param Int $db_name database label
     *
     * @return null
     */
    public function setDbName($db_name)
    {
        $this->db_name = $db_name;
    }


    /**
     * Get the proper connection from the Connection Manager
     *
     * @param String $type    read / write connection type
     * @param PDO    $db_name db label name (defaults to $this->db_name)
     *
     * @return SimplePdo - a SimplePdo connection on success,
     *  otherwise Exception is thrown
     */
    protected function getConn($type, $db_name = "")
    {
        // use our default db name unless overriden in this call
        if ($db_name == "") {
            if ($this->db_name) {
                $db_name = $this->db_name;
            } else {
                return false;
            }
        }

        // if critical, override to only use write connections
        if ($this->critical
            || $this->hasActiveTransaction
            || ConnectionManager::getMode() === "DEBUG"
        ) {
            $type = "WRITE";
        }

        // get a connection from the manager
        $conn = ConnectionManager::getConnection(
            $this->db_location,
            $db_name,
            $type,
            $this->custom_key
        );

        return $conn;
    }


    /**
     * Sets PDO attributes
     *
     * @param int   $attribute - the attribute being set
     * @param mixed $value     - the new value
     *
     * @return bool
     */
    public function setAttribute ($attribute, $value)
    {
        $conn = $this->getConn("WRITE");
        if (!$conn) {
            return false;
        }

        return $conn->setAttribute($attribute, $value);
    }

    //------------------------------------------------------------------------//
    //-------------------------Transaction Management-------------------------//
    //------------------------------------------------------------------------//

    /**
     * Starts a transaction if a current transaction isn't already running
     *
     * @return bool success or failure
     */
    public function beginTransaction()
    {
        // transaction flag on
        $this->hasActiveTransaction = 1;
        $conn = $this->getConn("WRITE");
        if (!$conn) {
            return false;
        }

        return $conn->beginTransaction();
    }


    /**
     * Gets the last inserted id from the correct connection
     *
     * @return int - id of the last inserted row
     */
    public function lastInsertId()
    {
        $conn = $this->getConn("WRITE");
        if (!$conn) {
            return false;
        }
        return $conn->lastInsertId();
    }


    /**
     * Commits a database transaction
     *
     * @return bool success or failure
     */
    public function commit()
    {
        // transaction flag off
        $this->hasActiveTransaction = 0;
        $conn = $this->getConn("WRITE");
        if (!$conn) {
            return false;
        }
        $conn->commit();
        return true;
    }


    /**
     * Clears a database transaction
     *
     * @return bool success or failure
     */
    public function rollBack()
    {
        // transaction flag off
        $this->hasActiveTransaction = 0;
        $conn = $this->getConn("WRITE");
        if (!$conn) {
            return false;
        }

        $conn->rollBack();
        return true;
    }

    //------------------------------------------------------------------------//
    //-----------------------End Transaction Management-----------------------//
    //------------------------------------------------------------------------//


    /**
     * prepare - Wrapper/Overload method for PDO->prepare
     *
     * Returns a statement, leaves it to caller to execute with params.
     *
     * @param String $query   MySQL query
     * @param PDO    $db_name name of DB connection to use
     *
     * @return PDOStatement on success, null on failure
     */
    public function prepare($query, $db_name = "")
    {
        $conn = $this->getConn("WRITE", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->prepare($query);
    }


    /**
     * queryPrepared - Wrapper/Overload method for SimplePDO->queryPrepared
     *
     * @param String $query   MySQL query
     * @param Array  $data    Query variables
     * @param PDO    $db_name name of DB connection to use
     *
     * @return Mixed/Query result
     */
    public function queryPrepared($query, $data, $db_name = "")
    {
        $conn = $this->getConn("WRITE", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->queryPrepared($query, $data);
    }


    /**
     * fetchNone - Wrapper/Overload method for SimplePDO->fetchNone
     *
     * Basically the same as queryPrepared(), but uses the READ instance
     * instead of the WRITE.
     *
     * @param String $query   MySQL query
     * @param Array  $data    (optional) Query variables
     * @param PDO    $db_name name of DB connection to use
     *
     * @return PDOStatement on success, null on failure
     */
    public function fetchNone($query, $data = null, $db_name = "")
    {
        $conn = $this->getConn("READ", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->fetchNone($query, $data);
    }


    /**
     * fetchOne - Wrapper/Overload method for SimplePDO->fetchOne
     *
     * @param String $query     MySQL query
     * @param Array  $data      (optional) Query variables
     * @param String $error_msg (optional) Human readable error message
     * @param PDO    $db_name   name of DB connection to use
     *
     * @return Mixed/Query result
     */
    public function fetchOne($query, $data = null, $error_msg = null, $db_name = "")
    {
        $conn = $this->getConn("READ", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->fetchOne($query, $data, $error_msg);
    }


    /**
     * fetchColumn - Wrapper/Overload method for SimplePDO->fetchColumn
     *
     * @param String  $query         MySQL query
     * @param Array   $data          (optional) Query variables
     * @param Integer $column_number (optional) The column to fetch
     * @param PDO     $db_name       name of DB connection to use
     *
     * @return Mixed/Query result
     */
    public function fetchColumn(
        $query, $data = null, $column_number = 0, $db_name = ""
    ) {
        $conn = $this->getConn("READ", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->fetchColumn($query, $data, $column_number);
    }


    /**
     * fetchRow - Wrapper/Overload method for SimplePDO->fetchRow
     *
     * @param String $query       MySQL query
     * @param Array  $data        (optional) Query variables
     * @param Array  $fetch_style Fetch style, see \PDO::fetch()
     * @param PDO    $db_name     name of DB connection to use
     *
     * @return Mixed/Query result
     */
    public function fetchRow(
        $query, $data = null, $fetch_style = \PDO::FETCH_ASSOC, $db_name = ""
    ) {
        $conn = $this->getConn("READ", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->fetchRow($query, $data, $fetch_style);
    }


    /**
     * fetchAll - Wrapper/Overload method for SimplePDO->fetchAll
     *
     * @param String $query       MySQL query
     * @param Array  $data        (optional) Query variables
     * @param Array  $fetch_style Fetch style, see \PDO::fetch()
     * @param PDO    $db_name     name of DB connection to use
     *
     * @return Mixed/Query result
     */
    public function fetchAll(
        $query, $data = null, $fetch_style = \PDO::FETCH_ASSOC, $db_name = ""
    ) {
        $conn = $this->getConn("READ", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->fetchAll($query, $data, $fetch_style);
    }


    /**
     * insert - Wrapper/Overload method for SimplePDO->insert
     *
     * @param String $table   Table to insert into
     * @param Array  $values  Data to insert
     * @param PDO    $db_name name of DB connection to use
     *
     * @return mixed on success, the ID of the new row; On failure, false
     */
    public function insert($table, $values, $db_name = "")
    {
        $conn = $this->getConn("WRITE", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->insert($table, $values);
    }


    /**
     * multiInsert - Wrapper/Overload method for SimplePDO->multiInsert
     *
     * @param String $table   Table to insert into
     * @param Array  &$values Data to insert
     * @param PDO    $db_name name of DB connection to use
     *
     * @return mixed on success, the number of rows INSERTed; On failure, false
     */
    public function multiInsert($table, &$values, $db_name = "")
    {
        $conn = $this->getConn("WRITE", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->multiInsert($table, $values);
    }


    /**
     * update - Wrapper/Overload method for SimplePDO->update
     *
     * @param String  $table    table name to update
     * @param Array   $values   Data to update row with
     * @param Integer $id       The value of the id row to update
     * @param String  $id_field Which column to check the id for
     * @param PDO     $db_name  name of DB connection to use
     *
     * @return mixed the number of rows UPDATEd on success, false on failure
     */
    public function update(
        $table, array $values, $id, $id_field = 'id', $db_name = ""
    ) {
        $conn = $this->getConn("WRITE", $db_name);
        if (!$conn) {
            return false;
        }

        return $conn->update($table, $values, $id, $id_field);
    }
}
?>
