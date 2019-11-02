<?php

namespace Tqdev\PdoJson;

class SmartPdo extends LazyPdo
{
    protected $driver;
    protected $options;
    protected $commands;

    public function __construct(string $dsn, /*?string*/ $username = null, /*?string*/ $password = null, array $options = array())
    {
        $this->driver = strtolower(substr($dsn, 0, strpos($dsn, ':')));
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

    public static function create(string $username, string $password, string $database, string $driver = 'mysql', string $address = 'localhost', string $port = '', array $options = array())
    {
        switch ($driver) {
            case 'mysql':
                $port = $port ?: '3306';
                $dsn = "$driver:host=$address;port=$port;dbname=$database;charset=utf8mb4";
                break;
            case 'pgsql':
                $port = $port ?: '5432';
                $dsn = "$driver:host=$address port=$port dbname=$database options='--client_encoding=UTF8'";
                break;
            case 'sqlsrv':
                $port = $port ?: '1433';
                $dsn = "$driver:Server=$address,$port;Database=$database";
                break;
        }
        return new static($dsn, $username, $password, $options);
    }

    public function smartQuery(string $statement, array $params = [], bool $returnNumAffected = false, bool $returnLastInsertId = false)
    {
        if (empty($params)) {
            $stmt = $this->query($statement);
        } else {
            $stmt = $this->prepare($statement);
            $stmt->execute($params);
        }
        if ($returnNumAffected) {
            return $stmt->rowCount();
        } else if ($returnLastInsertId) {
            return $this->lastInsertId();
        }
        return $stmt->fetchAll();
    }

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
