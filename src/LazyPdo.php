<?php

namespace Tqdev\PdoJson;

class LazyPdo extends \PDO
{
    private $dsn;
    private $user;
    private $password;
    private $options;
    private $commands;

    private $pdo = null;

    /**
     * Constructs a lazy PDO connection that only connects when needed.
     * 
     * @param string $dsn The Data Source Name
     * @param string|null $user The username for the database connection
     * @param string|null $password The password for the database connection
     * @param array $options Driver-specific connection options
     */
    public function __construct(string $dsn, ?string $user = null, ?string $password = null, array $options = array())
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
        $this->commands = array();
        // explicitly NOT calling super::__construct
    }

    /**
     * Add a command to execute when the connection is initialized.
     * 
     * @param string $command SQL command to execute on initialization
     */
    public function addInitCommand(string $command) /*: void*/
    {
        $this->commands[] = $command;
    }

    private function pdo()
    {
        if (!$this->pdo) {
            $this->pdo = new \PDO($this->dsn, $this->user, $this->password, $this->options);
            foreach ($this->commands as $command) {
                $this->pdo->query($command);
            }
        }
        return $this->pdo;
    }

    /**
     * Reconstruct the connection with new parameters.
     * 
     * @param string $dsn The Data Source Name
     * @param string|null $user The username for the database connection
     * @param string|null $password The password for the database connection
     * @param array $options Driver-specific connection options
     * @return bool True if an existing connection was closed, false otherwise
     */
    public function reconstruct(string $dsn, ?string $user = null, ?string $password = null, array $options = array()): bool
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
        $this->commands = array();
        if ($this->pdo) {
            $this->pdo = null;
            return true;
        }
        return false;
    }

    /**
     * Checks if inside a transaction.
     * 
     * @return bool True if a transaction is currently active
     */
    public function inTransaction(): bool
    {
        // Do not call parent method if there is no pdo object
        return $this->pdo && parent::inTransaction();
    }

    /**
     * Set an attribute on the PDO connection.
     * 
     * @param int $attribute The attribute to set (PDO::ATTR_* constant)
     * @param mixed $value The value to set
     * @return bool True on success, false on failure
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        if ($this->pdo) {
            return $this->pdo()->setAttribute($attribute, $value);
        }
        $this->options[$attribute] = $value;
        return true;
    }

    /**
     * Retrieve a database connection attribute.
     * 
     * @param int $attribute The attribute to retrieve (PDO::ATTR_* constant)
     * @return mixed The attribute value
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo()->getAttribute($attribute);
    }

    /**
     * Initiates a transaction.
     * 
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    /**
     * Commits a transaction.
     * 
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    /**
     * Rolls back a transaction.
     * 
     * @return bool True on success, false on failure
     */
    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
    }

    /**
     * Fetch the SQLSTATE associated with the last operation.
     * 
     * @return string|null SQLSTATE error code or null
     */
    public function errorCode(): ?string
    {
        return $this->pdo()->errorCode();
    }

    /**
     * Fetch extended error information.
     * 
     * @return array Array containing error information
     */
    public function errorInfo(): array
    {
        return $this->pdo()->errorInfo();
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     * 
     * @param string $statement The SQL statement to execute
     * @return int|false Number of rows affected or false on failure
     */
    public function exec(string $statement): int|false
    {
        return $this->pdo()->exec($statement);
    }

    /**
     * Prepares a statement for execution.
     * 
     * @param string $query The SQL query to prepare
     * @param array $options Driver-specific options for the statement
     * @return \PDOStatement|false Prepared statement or false on failure
     */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->pdo()->prepare($query, $options);
    }

    /**
     * Quotes a string for use in a query.
     * 
     * @param string $string The string to quote
     * @param int $type The data type hint for drivers that have alternate quoting styles
     * @return string|false Quoted string or false on failure
     */
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo()->quote($string, $type);
    }

    /**
     * Returns the ID of the last inserted row.
     * 
     * @param string|null $name Name of the sequence object from which the ID should be returned
     * @return string|false The row ID or false on failure
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo()->lastInsertId($name);
    }

    /**
     * Executes an SQL statement and returns a result set.
     * 
     * @param string $query The SQL query to execute
     * @param int|null $fetchMode The fetch mode for the result set
     * @param mixed ...$fetchModeArgs Additional arguments for the fetch mode
     * @return \PDOStatement|false Statement object or false on failure
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return $this->pdo()->query($query, $fetchMode, ...$fetchModeArgs);
    }
}
