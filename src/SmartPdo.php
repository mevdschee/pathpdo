<?php

namespace Tqdev\PdoJson;

class SmartPdo extends LazyPdo
{
    protected string $driver;
    /** @var array<int, mixed> */
    protected array $options;
    /** @var array<int,string> */
    protected array $commands;

    /**
     * Constructs a smart PDO connection with driver-specific optimizations.
     * 
     * @param string $dsn The Data Source Name
     * @param string|null $username The username for the database connection
     * @param string|null $password The password for the database connection
     * @param array<int, mixed> $options Driver-specific connection options
     * @throws \Exception If the driver is not supported
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, array $options = array())
    {
        $colonPos = strpos($dsn, ':');
        $this->driver = strtolower(substr($dsn, 0, $colonPos !== false ? $colonPos : 0));
        if (!in_array($this->driver, ['mysql', 'pgsql', 'sqlsrv'])) {
            throw new \Exception(sprintf('Driver "%s" is not supported', $this->driver));
        }
        $this->options = $this->getOptions($options);
        parent::__construct($dsn, $username, $password, $this->options);
        $this->commands = $this->getCommands(array());
        foreach ($this->commands as $command) {
            $this->addInitCommand($command);
        }
    }

    /**
     * Get the database driver name.
     * 
     * @return string The driver name (mysql, pgsql, or sqlsrv)
     */
    public function getDriver(): string
    {
        return $this->driver;
    }


    /**
     * Build a DSN string from connection parameters.
     * 
     * @param string $driver The database driver (mysql, pgsql, or sqlsrv)
     * @param string $address The database server address
     * @param string $port The database server port (uses default if empty)
     * @param string $database The database name
     * @return string The DSN string
     * @throws \Exception If the driver is not supported
     */
    protected static function buildDsn(string $driver, string $address, string $port, string $database): string
    {
        switch ($driver) {
            case 'mysql':
                $port = $port ?: '3306';
                return "$driver:host=$address;port=$port;dbname=$database;charset=utf8mb4";
            case 'pgsql':
                $port = $port ?: '5432';
                return "$driver:host=$address port=$port dbname=$database options='--client_encoding=UTF8'";
            case 'sqlsrv':
                $port = $port ?: '1433';
                return "$driver:Server=$address,$port;Database=$database";
            default:
                throw new \Exception(sprintf('Driver "%s" is not supported', $driver));
        }
    }

    /**
     * Create a PDO connection using simplified parameters.
     * 
     * @param string $username The database username
     * @param string $password The database password
     * @param string $database The database name
     * @param string $driver The database driver (mysql, pgsql, or sqlsrv)
     * @param string $address The database server address
     * @param string $port The database server port (uses default if empty)
     * @param array<int, mixed> $options Additional PDO options
     * @return SmartPdo A new SmartPdo instance
     */
    public static function create(string $username, string $password, string $database, string $driver = 'mysql', string $address = 'localhost', string $port = '', array $options = array()): SmartPdo
    {
        $dsn = static::buildDsn($driver, $address, $port, $database);
        return new SmartPdo($dsn, $username, $password, $options);
    }

    /**
     * Execute a SQL query with optional parameters and return handling.
     * 
     * @param string $statement The SQL statement to execute
     * @param array<int|string, mixed> $params Parameters for prepared statement
     * @param bool $returnNumAffected Return number of rows affected instead of results
     * @param bool $returnLastInsertId Return the last insert ID instead of results
     * @return array<int|string, mixed>|int Array of results, row count, or last insert ID
     */
    public function smartQuery(string $statement, array $params = [], bool $returnNumAffected = false, bool $returnLastInsertId = false): array|int
    {
        if (empty($params)) {
            $stmt = $this->query($statement);
        } else {
            $stmt = $this->prepare($statement);
            if ($stmt === false) {
                return $returnNumAffected || $returnLastInsertId ? 0 : [];
            }
            $stmt->execute($params);
        }
        if ($stmt === false) {
            return $returnNumAffected || $returnLastInsertId ? 0 : [];
        }
        if ($returnNumAffected) {
            return $stmt->rowCount();
        } else if ($returnLastInsertId) {
            $lastId = $this->lastInsertId();
            return is_numeric($lastId) ? (int)$lastId : 0;
        }
        return $stmt->fetchAll();
    }

    /**
     * @param array<int,string> $commands
     * @return array<int,string>
     */
    protected function getCommands(array $commands): array
    {
        switch ($this->driver) {
            case 'mysql':
                return $commands + [
                    'SET SESSION sql_warnings=1;',
                    'SET NAMES utf8mb4;',
                    'SET SESSION sql_mode = "ANSI,TRADITIONAL";',
                ];
            case 'pgsql':
                return $commands + [
                    "SET NAMES 'UTF8';",
                ];
        }
        return $commands;
    }

    /**
     * @param array<int, mixed> $options
     * @return array<int, mixed>
     */
    protected function getOptions(array $options): array
    {
        $options += array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        );
        switch ($this->driver) {
            case 'mysql':
                return $options + [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                    \PDO::ATTR_PERSISTENT => true,
                ];
            case 'pgsql':
                return $options + [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => true,
                ];
            case 'sqlsrv':
                return $options + [
                    \PDO::SQLSRV_ATTR_DIRECT_QUERY => false,
                    \PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
                ];
        }
        return $options;
    }
}
