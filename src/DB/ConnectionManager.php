<?php

namespace DB;

class ConnectionManager
{
    // Associative array of connections
    // key   = unique identifier to a connection
    // value = the SimplePdo connection
    private static $_stored_connections = array();

    // Associative array of debug connections
    // key   = unique identifier to a connection
    // value = the SimplePdo connection
    private static $_debug_stored_connections = array();

    // Associative array of connection times
    // key   = unique identifier to a connection
    // value = the time that connection was created
    private static $_stored_times = array();

    // Associative array of debug connection times
    // key   = unique identifier to a connection
    // value = the time that connection was created
    private static $_debug_stored_times = array();

    // lifetime of a SimplePdo connection before refresh
    const DEFAULT_TIME_LIMIT = 600;

    /**
     * Creates a Simple PDO connection
     *
     * @param String $host address of server to connect
     * @param String $name name of db schema to access
     * @param String $user user name to connect with
     * @param String $pass password of the user
     * @param Bool   $pers whether the connection to be persistent
     *
     * @return SimplePDO object on success, error on failure and returns false
     */
    private static function _createConnection(
        $host, $name, $user, $pass, $pers = false
    ) {
        try {
            if ($pers === true) {
                $pers = array(PDO::ATTR_PERSISTENT => true);
            } else {
                $pers = array();
            }
            $db = new SimplePDO(
                "mysql:host={$host};dbname={$name}",
                $user,
                $pass,
                $pers
            );
        } catch (\PDOException $e) {
            throw new \Exception(
                'Database connection failed: ' . $e->getMessage()
            );
            return false;
        }
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->queryPrepared("SET time_zone = 'America/Los_Angeles'", array());
        $db->exec('SET NAMES utf8');

        return $db;
    }


    /**
     * Gets a connection based on parameters
     *
     * @param String $db_location name of the key in connection_credentials array
     *                              (things like dev/live/test)
     * @param String $db_name     name of the label for that database
     *                              (defined in _connection_credentials)
     * @param Array  $type        whether is a read/write connection
     * @param Mixed  $custom_key  custom key name used for multiple identical connections
     *
     * @return PDO database connection on success, otherwise false
     */
    public static function getConnection(
        $db_location,
        $db_name,
        $type  = 'WRITE',
        $custom_key = false
    ) {

        if (empty(\DB\Credentials\ConnectionCredentials::getCredentials())) {
            throw new \Exception(
                'No credentials found in includes/ConnectionCredentials.php.  ' .
                'Please see in the file header instructions on ' .
                'how to create the file.'
            );
            return false;
        }

        $c    = \DB\Credentials\ConnectionCredentials::getCredentials();
        $s    = self::$_stored_connections;
        $time = self::$_stored_times;

        //Check for location alias in the config file
        $db_location = self::resolveLocationAlias($db_location);

        // if program is in debug mode
        if (self::getMode() == 'DEBUG') {
            $s = self::$_debug_stored_connections;
            $time = self::$_debug_stored_times;
        }

        // validate incoming parameters
        if (!isset($c[$db_location])
            || !isset($c[$db_location][$db_name])
            || ($type != "WRITE"
            && $type != "READ")
        ) {
            throw new \Exception(
                'Missing credentials for ' . $db_location . ': '
                    . $db_name . ' -> ' . $type
                );
            return false;
        }

        // create unique key to represent this connection if it is custom
        $key = $db_location . "_" . $db_name . "_" . $type;

        if ($custom_key !== false) {
            $key .= '_' . $custom_key;
        }
        // check to see if db exists
        // and is within timespan
        if (isset($s[$key])
            && (time() - $time[$key] < self::_getTimeLimit())
        ) {
            return $s[$key];
        }

        // get the credentials from our list of credentials
        $creds = $c[$db_location][$db_name][$type];

        // create connection and store it
        $db = self::_createConnection(
            $creds['host'], $creds['name'], $creds['user'], $creds['pass']
        );

        if (self::getMode() == 'DEBUG') {
            self::$_debug_stored_connections[$key] = $db;
            self::$_debug_stored_times[$key]       = time();
            $s = self::$_debug_stored_connections[$key];
        } else {
            self::$_stored_connections[$key] = $db;
            self::$_stored_times[$key]       = time();
            $s = self::$_stored_connections[$key];
        }

        return $s;
    }

    /**
     * Resolves a db location into a final location, resolving any aliases.
     *
     * Specifically, if there's a value defined for the config file property:
     *   db.connection_manager.location_alias.$db_location
     * we return that, otherwise return $db_location as-is.
     *
     * For example, if you have the following defined in config/app.ini:
     *   db.connection_manager.location_alias.DEFAULT = TEST
     * then if $db_location = DEFAULT, we'll return TEST as the actual location.
     *
     * @param string $db_location db location name
     *
     * @return string final db location after resolving aliases
     */
    public static function resolveLocationAlias($db_location)
    {
        return 'LIVE';
    }

    /**
     * Returns the mode of the connection_manager
     *
     * @return String mode
    */
    public static function getMode()
    {
        return 'development';
    }

    /**
     * Returns the time limit of the connection_manager
     *
     * @return String
    */
    private static function _getTimeLimit()
    {
        return 300;
    }

    /**
     * forgetConnection - forgets a specified connection.  Generates a key using the passed paramaters
     *
     * @param String $db_name     name of the db
     * @param String $db_location db location name
     * @param String $type        type of connection
     *
     * @return None
     */
    public static function forgetConnection(
        $db_name,
        $db_location = 'DEFAULT',
        $type  = 'WRITE'
    ) {
        $key = $db_location . "_" . $db_name . "_" . $type;

        if ($custom_key !== false) {
            $key .= '_' . $custom_key;
        }

        if (isset(self::$_stored_connections[$key])) {
            unset(self::$_stored_connections[$key]);
        }
    }

    /**
     * forgetAllConnections - unsets all known connections
     *
     * @return None
     */
    public static function forgetAllConnections()
    {
        self::$_stored_connections = array();
    }
}
?>
