<?php

namespace DB;

class SimplePDO extends \PDO
{
    /**
     * Flag that determines whether the database has a transaction
     * in progress.
     * @var bool
     */
    protected $has_active_transaction = 0;


    /**
     * Starts a transaction, but checks to see if a transaction is
     * already running.
     *
     * @see \PDO::beginTransaction()
     *
     * @return bool whether or not the execution of the method started
     * a transaction
     */
    public function beginTransaction ()
    {
        if (($this->has_active_transaction > 1
            && ConnectionManager::getMode() === "DEBUG")
            || ($this->has_active_transaction > 0
            && ConnectionManager::getMode() === "LIVE")
        ) {
            throw new \Exception('A transaction has already been started.');
            return false;
        }

        $this->has_active_transaction++;
        if (ConnectionManager::getMode() === "DEBUG"
            && $this->has_active_transaction > 1
        ) {
            return $this->has_active_transaction;
        } else {
            parent::beginTransaction();
            return $this->has_active_transaction;
        }
    }



    /**
     * commit - Implements PDO::commit
     *
     * @return nothing
     */
    public function commit ()
    {
        if ($this->has_active_transaction) {
            if (ConnectionManager::getMode() === "LIVE") {
                parent::commit();
                $this->has_active_transaction--;
            } else if (ConnectionManager::getMode() === "DEBUG"
                && $this->has_active_transaction < 2
            ) {
                throw new \Exception("Failed to commit.  No transaction exists.");
            } else {
                $this->has_active_transaction--;
            }
        } else {
            throw new \Exception("Failed to commit.  No transaction exists.");
        }
    }



    /**
     * rollBack - implements PDO::rollBack
     *
     * @return nothing
     */
    public function rollBack ()
    {
        if ($this->has_active_transaction) {
            if (ConnectionManager::getMode() === "LIVE") {
                parent::rollBack();
                $this->has_active_transaction--;
            } else if (ConnectionManager::getMode() === "DEBUG"
                && $this->has_active_transaction < 2
            ) {
                throw new \Exception("Failed to rollback.  No transaction exists.");
            } else {
                $this->has_active_transaction--;
            }
        } else {
            throw new \Exception("Failed to rollback.  No transaction exists.");
        }
    }


    /**
     * Generates an SQL table field list from an array.
     *
     * @param array $field_list The list of fields.  If array is associative, the key
     * is the original name of the field and the value is the alias.  Otherwise, the
     * value is the name of the field.  If the array is empty, returns "*".
     *
     * @return string
     */
    static public function generateSQLFields ($field_list)
    {
        if (empty($field_list)) {
            return '*';
        }
        $clause_list = array();
        $alias_str = '%s AS %s';
        foreach ($field_list as $name => $alias) {
            if (is_numeric($name)) {
                if (is_array($alias)) {
                    if (count($alias) == 2) {
                        array_push(
                            $clause_list,
                            sprintf($alias_str, $alias[0], $alias[1])
                        );
                    } else {
                        throw new InvalidArgumentException(
                            'Invalid "tuple" - must be (only) two items.'
                        );
                    }
                } else {
                    // not an alias
                    array_push($clause_list, $alias);
                }
            } else {
                array_push($clause_list, sprintf($alias_str, $name, $alias));
            }
        }
        return implode(', ', $clause_list);
    }


    /**
     * A wrapper for PDO::prepare() and PDOStatement::execute().
     *
     * @param mixed $query either an SQL query string, or an already prepared
     * statement (PDOStatement)
     * @param mixed $data  if not array, assume only one parameter
     *
     * @return mixed PDOStatement on success, null on failure
     */
    public function queryPrepared ($query, $data)
    {
        if (!is_array($data)) {
            $data = array($data);
        }
        if (is_object($query)) {
            if ($query instanceof \PDOStatement) {
                $stmt = $query;
            } else {
                trigger_error(
                    sprintf(
                        'The object passed to the $query parameter of %s' .
                        'is not a PDOStatement.',
                        __METHOD__
                    ),
                    E_USER_ERROR
                );
            }
        } else {
            $stmt = $this->prepare($query);
        }
        if ($stmt->execute($data)) {
            return $stmt;
        } else {
            return null;
        }
    }


    /**
     * Determines which query function to call based on the contents of $data.
     *
     * @param mixed $query see ::queryPrepared()
     * @param mixed $data  (optional) if not array, assume only one parameter
     *
     * @return mixed PDOStatement on success, null on failure
     */
    private function _doQuery ($query, $data)
    {
        if (is_null($data)) {
            return $this->query($query);
        } else {
            return $this->queryPrepared($query, $data);
        }
    }

    /**
     * Runs the query but leaves it to the caller to fetch the results.
     * This is basically an alias for queryPrepared but it implies we
     * are running a query rather than doing an update.
     *
     * @param mixed $query see ::queryPrepared()
     * @param mixed $data  (optional) if not array, assume only one parameter
     *
     * @return mixed PDOStatement on success, null on failure
     */
    public function fetchNone ($query, $data = null)
    {
        return $this->_doQuery($query, $data);
    }


    /**
     * Fetches only the first column of the first row of a resultset.
     *
     * @param mixed  $query     see ::queryPrepared()
     * @param mixed  $data      (optional) if not array, assume only one parameter
     * @param string $error_msg (optional) if defined, die with a user-readable
     * error message if the query execution failed, or the number of returned
     * rows is zero
     *
     * @return mixed false on failure, otherwise the value of the column
     */
    public function fetchOne ($query, $data = null, $error_msg = null)
    {
        $result = $this->_doQuery($query, $data);
        if (is_null($result)) {
            if (is_null($error_msg)) {
                return false;
            } else {
                echo $error_msg;
                ob_start();
                debug_print_backtrace();
                trigger_error(
                    sprintf(
                        '<%s::%s()> Execution failed.%sBacktrace:%s',
                        __CLASS__,
                        __METHOD__,
                        PHP_EOL,
                        ob_get_clean()
                    ),
                    E_USER_ERROR
                );
            }
        }
        $value = $result->fetchColumn();
        if (!is_null($error_msg) and $value === false) {
            echo $error_msg;
            ob_start();
            debug_print_backtrace();
            trigger_error(
                sprintf(
                    '<%s::%s()> No rows found.%sBacktrace:%s',
                    __CLASS__,
                    __METHOD__,
                    PHP_EOL,
                    ob_get_clean()
                ),
                E_USER_ERROR
            );
        }
        return $value;
    }


    /**
     * Fetches only one entire column of a resultset.
     *
     * @param mixed $query         see ::queryPrepared()
     * @param mixed $data          (optional) if not array, assume only one parameter
     * @param int   $column_number (optional) the column to fetch, defaults to 0.
     *
     * @return mixed false on failure, array on success
     */
    public function fetchColumn ($query, $data = null, $column_number = 0)
    {
        $result = $this->_doQuery($query, $data);
        if (is_null($result)) {
            return false;
        } else {
            return $result->fetchAll(self::FETCH_COLUMN, $column_number);
        }
    }


    /**
     * Fetches only the first row of a resultset.
     *
     * @param mixed $query       see ::queryPrepared()
     * @param mixed $data        (optional) if not array, assume only one parameter
     * @param int   $fetch_style (optional) See PDO::fetch()
     *
     * @return mixed false on failure, otherwise depends on the $fetch_mode
     */
    public function fetchRow ($query, $data = null, $fetch_style = \PDO::FETCH_ASSOC)
    {
        $result = $this->_doQuery($query, $data);
        if (is_null($result)) {
            return false;
        } else {
            return $result->fetch($fetch_style);
        }
    }


    /**
     * Fetches all of the data from a resultset.
     * If you want to fetch all of a certain column, please use the fetchColumn()
     * method.
     *
     * @param mixed $query       see ::queryPrepared()
     * @param mixed $data        (optional) if not array, assume only one parameter
     * @param int   $fetch_style (optional) See PDO::fetch()
     *
     * @return array
     */
    public function fetchAll ($query, $data = null, $fetch_style = \PDO::FETCH_ASSOC)
    {
        $result = $this->_doQuery($query, $data);
        if (is_null($result)) {
            return false;
        } else {
            return $result->fetchAll($fetch_style);
        }
    }


    /**
     * fetchOneRow - returns entire contents of first row as array
     *
     * @param string $query Query string
     * @param array  $data  Parameters for the query
     *
     * @return array of query results
     */
    public function fetchOneRow ($query, array $data)
    {
        $stmt = $this->prepare($query);
        if ($stmt->execute($data) === false) {
            return null;
        }
        return $stmt->fetch();
    }

    /**
     * Fetches all of the data from a resultset in an associative array, where the
     * key is the column number and the value is the other column.
     *
     * @param string $query         Query String
     * @param mixed  $data          (optional) if not array, assume one parameter
     * @param int    $column_number (optional) The column whose data will serve as
     * the key
     *
     * @return array (associative)
     */
    public function fetchAllAsDictionary ($query, $data = null, $column_number = 0)
    {
        $result = $this->_doQuery($query, $data);
        if (is_null($result)) {
            return false;
        } else {
            return $result->fetchAll(
                \PDO::FETCH_COLUMN | \PDO::FETCH_GROUP,
                $column_number
            );
        }
    }


    /**
     * Helper method for the INSERT-related public methods.
     *
     * @param string  $table           the name of the table
     * @param array   $fields          the list of fields that correspond to $data
     * @param string  $values_str      part of the SQL, contains placeholders
     * @param array   &$data           parameters for PDOStatement::queryPrepared()
     * @param boolean $is_multi_insert whether or not the INSERT statement INSERTs
     * multiple rows into the table
     *
     * @return Integer row ID or number of rows inserted
     */
    private function _doInsert (
        $table,
        $fields,
        $values_str,
        &$data,
        $is_multi_insert
    ) {
        $result = $this->queryPrepared(
            sprintf(
                'INSERT INTO %s (%s) VALUES%s',
                $table,
                '`'.implode('`, `', $fields).'`',
                $values_str
            ),
            $data
        );

        if (is_null($result)) {
            return false;
        } else {
            if ($is_multi_insert) {
                return $result->rowCount();
            } else {
                return $this->lastInsertId();
            }
        }
    }

    /**
     * Inserts a new row into a database table.
     *
     * @param string $table  The name of the table
     * @param array  $values An associative array of field names and values. If a
     * value is an array, then the first element of that array is used as a literal
     * (i.e., non-quoted) value.  This can be useful, for instance, when setting a
     * date field to the current date and time via the "NOW()" SQL function.
     *
     * @return mixed on success, the ID of the new row; On failure, false
     */
    public function insert ($table, $values)
    {
        $fields       = array();
        $placeholders = array();
        $data         = array();

        foreach ($values as $field=>$value) {
            $fields[] = $field;

            if ($value instanceof SimpleExpression) {
                $placeholders[] = $value->getExpression();
                $data           = array_merge($data, $value->getArgs());
            } else if (is_array($value)) {
                $this->_logArrayHack();
                array_push($placeholders, array_shift($value));
                if (count($value) > 0) {
                    $data = array_merge($data, $value);
                }
            } else {
                array_push($placeholders, '?');
                array_push($data, $value);
            }
        }

        return $this->_doInsert(
            $table,
            $fields,
            sprintf(
                ' (%s)',
                implode(', ', $placeholders)
            ),
            $data,
            false
        );
    }


    /**
     * Inserts more than one new row into a database table.
     *
     * @param string $table   The name of the table
     * @param array  &$values An array of associative arrays of field names and
     * values. If a value is an array, then the first element of that array is used
     * as a literal (i.e., non-quoted) value.  This can be useful, for instance,
     * when setting a date field to the current date and time via the "NOW()" SQL
     * function.
     *
     * @return mixed on success, the number of rows INSERTed; On failure, false
     */
    public function multiInsert ($table, &$values)
    {
        $placeholders = array();
        $data = array();
        $fields = array();
        // this is so JUDY arrays will also work
        // since Judy does not have access to the array_keys functionality
        foreach ($values[0] as $key => $v) {
            $fields[] = $key;
        }
        $values_str = '';
        foreach ($values as $new_row) {
            foreach ($new_row as $value) {
                if ($value instanceof SimpleExpression) {
                    $placeholders[] = $value->getExpression();
                    $data           = array_merge($data, $value->getArgs());
                } else if (is_array($value)) {
                    $this->_logArrayHack();
                    array_push($placeholders, array_shift($value));
                    if (count($value) > 0) {
                        $data = array_merge($data, $value);
                    }
                } else {
                    if (is_object($value)) {
                        trigger_error(
                            'One of the values is an object instead of an array',
                            E_USER_ERROR
                        );
                    }
                    array_push($placeholders, '?');
                    array_push($data, $value);
                }
            }
            $values_str .= sprintf(', (%s)', implode(', ', $placeholders));
            $placeholders = array();
        }
        return $this->_doInsert(
            $table,
            $fields,
            substr($values_str, 1),
            $data,
            true
        );
    }


    /**
     * Updates one or more values of a row in a database table.
     *
     * @param string $table    The name of the table
     * @param array  $values   An associative array of field names and values. If a
     * value is an array, then the first element of that array is used as a literal
     * (i.e., non-quoted) value. If there is more than one element in this array,
     * the values are appended to the data to be inserted. This can be useful, for
     * instance, when setting a date field to the current date and time via the
     * "NOW()" SQL function.
     * @param Mixed  $id       The ID of the rows to be updated, or a simple expression
     *                         with the condition
     * @param string $id_field (optional) the name of the ID field, defaults to 'id'
     *
     * @return mixed the number of rows UPDATEd on success, false on failure
     */
    public function update($table, $values, $id, $id_field = 'id')
    {
        $pairs = array();
        $data = array();
        foreach ($values as $field => $value) {
            $pair = $field . '=';
            if ($value instanceof SimpleExpression) {
                $pair .= $value->getExpression();
                $data  = array_merge($data, $value->getArgs());
            } else if (is_array($value)) {
                $this->_logArrayHack();
                $pair .= array_shift($value);
                if (count($value) > 0) {
                    $data = array_merge($data, $value);
                }
            } else {
                $pair .= '?';
                array_push($data, $value);
            }
            array_push($pairs, $pair);
        }
        if ($id instanceof  SimpleExpression) {
            $conditional = $id->getExpression();
            $data = array_merge($data, $id->getArgs());
        } else {
            array_push($data, $id);
            $conditional = "$id_field = ?";
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE '. $conditional,
            $table,
            implode(', ', $pairs)
        );

        $result = $this->queryPrepared(
            $sql,
            $data
        );
        if (is_null($result)) {
            return false;
        } else {
            return $result->rowCount();
        }
    }


    /**
     * this function logs info about queires using array hack
     *
     * @return void
     */
    private function _logArrayHack()
    {
        // phase 1
        $time  = date("H:i:s").': ';
        $trace = debug_backtrace(false);
        $data  = print_r($trace[1], true);
        $data  = explode(PHP_EOL, $data);
        $data  = $time.implode(PHP_EOL.$time, $data).PHP_EOL;
        file_put_contents('/tmp/SimplePDO_ArrayHack.txt', $data, FILE_APPEND);
    }
}
?>
