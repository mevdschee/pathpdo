<?php

namespace Tqdev\PdoJson;

class SimplePdo extends SmartPdo
{
    public static function create($driver, $address, $port, $username, $password, $database): SimplePdo
    {
        switch (strtolower($driver)) {
            case 'mysql':
                $dsn = "$driver:host=$address;port=$port;dbname=$database;charset=utf8mb4";
            case 'pgsql':
                $dsn = "$driver:host=$address port=$port dbname=$database options='--client_encoding=UTF8'";
            case 'sqlsrv':
                $dsn = "$driver:Server=$address,$port;Database=$database";
            default:
                throw new \Exception(sprintf('Driver "%s" is not supported', $driver));
        }
        return new SimplePdo($dsn, $username, $password);
    }

    public function insert($table, $record): int
    {
        if (empty($table) || empty($record)) {
            return 0;
        }
        $params = [];
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlInsertFields($record) . ' VALUES ';
        $sql .= $this->buildSqlInsertValues($params, $record) . ' ';
        return $this->q($sql, $params, false, true);
    }

    public function select($table, $fields = [], $conditions = []): array
    {
        if (empty($table)) {
            return [];
        }
        $params = [];
        $sql = 'SELECT ' . $this->buildSqlSelect($fields) . ' ';
        $sql .= 'FROM ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->q($sql, $params, false, false);
    }

    public function update($table, $fields, $conditions): int
    {
        if (empty($table) || empty($fields) || empty($conditions)) {
            return 0;
        }
        $params = [];
        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' ';
        $sql .= 'SET ' . $this->buildSqlSet($params, $fields) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->q($sql, $params, true, false);
    }

    public function delete($table, $conditions): int
    {
        if (empty($table) || empty($conditions)) {
            return 0;
        }
        $params = [];
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->q($sql, $params, true);
    }

    public function quoteIdentifier(string $string): string
    {
        $str = \preg_replace('/[^\.0-9a-zA-Z_\/]/', '', $string);
        if (\strpos($str, '.') !== false) {
            $parts = \explode('.', $str);
            foreach ($parts as $i => $part) {
                $parts[$i] = $this->quoteIdentifier($part);
            }
            return \implode('.', $parts);
        }
        return '"' . $str . '"';
    }

    protected function buildSqlInsertFields(array $record): string
    {
        $names = [];
        foreach (array_keys($record) as $name) {
            $names[] = $this->quoteIdentifier($name);
        }
        return '(' . \implode(', ', $names) . ')';
    }

    protected function buildSqlInsertValues(array &$params, array $record): string
    {
        $args = [];
        foreach ($record as $value) {
            if ($value === null) {
                $args[] = 'NULL';
            } elseif (\is_bool($value)) {
                $args[] = $this->makeBool($value);
            } else {
                $args[] = '?';
                $params[] = $value;
            }
        }
        return '(' . \implode(', ', $args) . ')';
    }

    protected function buildSqlSelect(array $fields): string
    {
        if (empty($fields)) {
            return '*';
        }
        $args = [];
        foreach ($fields as $name) {
            $args[] = $this->quoteIdentifier($name);
        }
        return \implode(', ', $args);
    }

    protected function buildSqlSet(array &$params, array $fields): string
    {
        $args = [];
        foreach ($fields as $name => $value) {
            $name = $this->quoteIdentifier($name);
            if ($value === null) {
                $args[] = $name . ' = NULL';
            } elseif (\is_bool($value)) {
                $args[] = $name . ' = ' . $this->makeBool($value);
            } else {
                $args[] = $name . ' = ?';
                $params[] = $value;
            }
        }
        return \implode(', ', $args);
    }

    protected function selectOperator(string $operator): string
    {
        $operators = ['', '=', '>', '<', '>=', '<=', '<>', 'IS', 'IN', 'IS NOT', 'NOT IN', 'LIKE', 'NOT LIKE'];
        return in_array($operator, $operators) ? $operator : '';
    }

    protected function buildSqlWhere(array &$params, array $conditions): string
    {
        if (empty($conditions)) {
            return '';
        }
        $args = [];
        foreach ($conditions as $name => $value) {
            if (!is_numeric($name)) {
                $operator = '';
            } elseif (is_array($value) && count($value) == 3) {
                list($name, $operator, $value) = $value;
            } else {
                $args[] = '1 = 0';
                continue;
            }
            $name = $this->quoteIdentifier($name);
            $operator = $this->selectOperator($operator);
            if ($value === null) {
                $operator = $operator ?: 'IS';
                $args[] = $name . ' ' . $operator . ' NULL';
            } elseif (\is_array($value)) {
                $operator = $operator ?: 'IN';
                $qmarks = implode(', ', str_split(str_repeat('?', count($value))));
                $args[] = $name . ' ' . $operator . ' (' . $qmarks . ')';
                foreach ($value as $val) {
                    $params[] = $val;
                }
            } elseif (\is_bool($value)) {
                $operator = $operator ?: '=';
                $args[] = $name . ' ' . $operator . ' ' . $this->makeBool($value);
            } else {
                $operator = $operator ?: '=';
                $args[] = $name . ' ' . $operator . ' ?';
                $params[] = $value;
            }
        }
        return 'WHERE ' . \implode(' AND ', $args);
    }

    protected function makeBool(bool $value): string
    {
        switch ($this->driver) {
            case 'sqlite':
                return $value ? '1' : '0';
            default:
                return $value ? 'TRUE' : 'FALSE';
        }
    }
}
