<?php

namespace Tqdev\PdoJson;

class SimplePdo extends SmartPdo
{
    /**
     * Create a SimplePdo connection using simplified parameters.
     * 
     * @param string $username The database username
     * @param string $password The database password
     * @param string $database The database name
     * @param string $driver The database driver (mysql, pgsql, or sqlsrv)
     * @param string $address The database server address
     * @param string $port The database server port (uses default if empty)
     * @param array $options Additional PDO options
     * @return SimplePdo A new SimplePdo instance
     */
    public static function create(string $username, string $password, string $database, string $driver = 'mysql', string $address = 'localhost', string $port = '', array $options = array()): SimplePdo
    {
        $dsn = parent::buildDsn($driver, $address, $port, $database);
        return new SimplePdo($dsn, $username, $password, $options);
    }

    /**
     * Insert a record into a table.
     * 
     * @param string $table The table name
     * @param array $record Associative array of column => value pairs
     * @return int The last insert ID or 0 on failure
     */
    public function insert(string $table, array $record): int
    {
        if (empty($table) || empty($record)) {
            return 0;
        }
        $params = [];
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlInsertFields($record) . ' VALUES ';
        $sql .= $this->buildSqlInsertValues($params, $record) . ' ';
        return $this->smartQuery($sql, $params, false, true);
    }

    /**
     * Select records from a table.
     * 
     * @param string $table The table name
     * @param array $fields Array of field names (empty for all fields)
     * @param array $conditions Where conditions (key => value or [field, operator, value])
     * @return array Array of matching records
     */
    public function select(string $table, array $fields = [], array $conditions = []): array
    {
        if (empty($table)) {
            return [];
        }
        $params = [];
        $sql = 'SELECT ' . $this->buildSqlSelect($fields) . ' ';
        $sql .= 'FROM ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->smartQuery($sql, $params, false, false);
    }

    /**
     * Update records in a table.
     * 
     * @param string $table The table name
     * @param array $fields Associative array of column => value pairs to update
     * @param array $conditions Where conditions (key => value or [field, operator, value])
     * @return int Number of rows affected
     */
    public function update(string $table, array $fields, array $conditions): int
    {
        if (empty($table) || empty($fields) || empty($conditions)) {
            return 0;
        }
        $params = [];
        $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' ';
        $sql .= 'SET ' . $this->buildSqlSet($params, $fields) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->smartQuery($sql, $params, true, false);
    }

    /**
     * Delete records from a table.
     * 
     * @param string $table The table name
     * @param array $conditions Where conditions (key => value or [field, operator, value])
     * @return int Number of rows deleted
     */
    public function delete(string $table, array $conditions): int
    {
        if (empty($table) || empty($conditions)) {
            return 0;
        }
        $params = [];
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table) . ' ';
        $sql .= $this->buildSqlWhere($params, $conditions);
        return $this->smartQuery($sql, $params, true);
    }

    /**
     * Quote an identifier (table or column name) for safe use in SQL.
     * 
     * @param string $string The identifier to quote
     * @return string The quoted identifier
     */
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
                throw new \InvalidArgumentException('Invalid condition format: ' . json_encode($value));
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
