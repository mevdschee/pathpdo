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

    public function __construct(string $dsn, ?string $user = null, ?string $password = null, array $options = array())
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
        $this->commands = array();
        // explicitly NOT calling super::__construct
    }

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

    public function inTransaction(): bool
    {
        // Do not call parent method if there is no pdo object
        return $this->pdo && parent::inTransaction();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        if ($this->pdo) {
            return $this->pdo()->setAttribute($attribute, $value);
        }
        $this->options[$attribute] = $value;
        return true;
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo()->getAttribute($attribute);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
    }

    public function errorCode(): ?string
    {
        return $this->pdo()->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->pdo()->errorInfo();
    }

    public function exec(string $statement): int|false
    {
        return $this->pdo()->exec($statement);
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return $this->pdo()->prepare($query, $options);
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return $this->pdo()->quote($string, $type);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo()->lastInsertId($name);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        return $this->pdo()->query($query, $fetchMode, ...$fetchModeArgs);
    }
}
