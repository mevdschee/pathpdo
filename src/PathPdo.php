<?php

namespace Tqdev\PdoJson;

class PathPdo extends SimplePdo
{
    private Schema $schema;
    private QueryAnalyzer $queryAnalyzer;
    private PathInference $pathInference;

    /**
     * Constructs a PathPdo instance with path inference capabilities.
     * 
     * @param string $dsn The Data Source Name
     * @param string|null $username The username for the database connection
     * @param string|null $password The password for the database connection
     * @param array<int, mixed> $options Driver-specific connection options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->schema = new Schema();
        $this->queryAnalyzer = new QueryAnalyzer();
        $this->pathInference = new PathInference($this->schema);
    }

    /**
     * Create a PathPdo connection using simplified parameters.
     * 
     * @param string $username The database username
     * @param string $password The database password
     * @param string $database The database name
     * @param string $driver The database driver (mysql, pgsql, or sqlsrv)
     * @param string $address The database server address
     * @param string $port The database server port (uses default if empty)
     * @param array<int, mixed> $options Additional PDO options
     * @return PathPdo A new PathPdo instance
     * @throws \Exception If the driver is not supported
     */
    public static function create(string $username, string $password, string $database, string $driver = 'mysql', string $address = 'localhost', string $port = '', array $options = array()): PathPdo
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
            default:
                throw new \Exception("Unsupported driver '$driver'");
        }
        return new PathPdo($dsn, $username, $password, $options);
    }

    /**
     * Execute a query with automatic path inference for hierarchical results.
     * 
     * Automatically infers the structure of the result set based on SQL JOINs and
     * foreign key relationships, returning nested arrays/objects instead of flat rows.
     * 
     * @param string $query The SQL query to execute
     * @param array<int|string, mixed> $params Parameters for prepared statement
     * @param array<string, string> $paths Optional path mappings for table aliases (overrides SQL comment hints)
     *                     Format: ['alias' => '$.path', 'other' => '$.parent.child[]']
     * @return array<int|string, mixed> Hierarchical result structure based on inferred paths
     * @throws \RuntimeException If query execution fails
     */
    public function pathQuery(string $query, array $params = [], array $paths = []): array
    {
        if (empty($params)) {
            $statement = $this->query($query);
        } else {
            $statement = $this->prepare($query);
            if ($statement === false) {
                throw new \RuntimeException("Failed to prepare statement");
            }
            $statement->execute($params);
        }
        if ($statement === false) {
            throw new \RuntimeException("Failed to execute query");
        }
        $pdoColumns = $this->getColumns($statement);

        // Analyze query and infer paths
        $this->queryAnalyzer->analyze($query);

        // Merge provided paths into path hints (overrides SQL comment hints)
        if (!empty($paths)) {
            $this->queryAnalyzer->pathHints = array_merge($this->queryAnalyzer->pathHints, $paths);
        }

        $inferColumns = $this->queryAnalyzer->parseSelectColumns($query);
        if (empty($inferColumns) || count($inferColumns) !== count($pdoColumns)) {
            $inferColumns = $pdoColumns; // Fallback entirely to PDO meta if parse fails or count mismatch
        }
        $paths = $this->pathInference->inferPaths($this->queryAnalyzer, $inferColumns, $this);

        // Decide which branch to take based on the paths
        $hasArrayMarkers = false;
        $hasObjectPrefix = false;
        foreach ($paths as $path) {
            if (strpos($path, '[]') !== false) {
                $hasArrayMarkers = true;
            }
            if (strpos($path, '$.') === 0) {
                $hasObjectPrefix = true;
            }
        }

        $isObjectResult = $hasObjectPrefix && !$hasArrayMarkers;

        $records = $this->getAllRecords($statement, $paths);

        if ($isObjectResult && count($records) > 0) {
            return $this->buildObject($records[0]);
        }

        if (!$hasArrayMarkers) {
            return $this->buildFlatArray($records);
        }

        $groups = $this->groupBySeparator($records, '[]');
        $hashes = $this->addHashes($groups);
        $tree   = $this->combineIntoTree($hashes, '.');
        $result = $this->removeHashes($tree, '$');
        return $result;
    }

    /**
     * @return array<int,string>
     */
    private function getColumns(\PDOStatement $statement): array
    {
        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta !== false) {
                $columns[] = $meta['name'];
            }
        }
        return $columns;
    }

    /**
     * @param array<int,string> $paths
     * @return array<int, array<int|string, mixed>>
     */
    private function getAllRecords(\PDOStatement $statement, array $paths): array
    {
        $records = [];
        while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
            if (!is_array($row)) {
                continue;
            }
            $record = [];
            foreach ($row as $i => $value) {
                if (!isset($paths[$i])) {
                    continue;
                }
                $path = $paths[$i];
                // Strip leading "$" if present, else keep the dot (e.g. .id)
                $record[($path[0] === '$' ? substr($path, 1) : $path)] = $value;
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * @param array<int|string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildObject(array $record): array
    {
        $result = [];
        foreach ($record as $key => $value) {
            $key = ltrim((string)$key, '.');
            $parts = explode('.', $key);
            $current = &$result;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current[$part] = $value;
                } else {
                    if (!isset($current[$part]) || !is_array($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
            unset($current);
        }
        return $result;
    }

    /**
     * @param array<int, array<int|string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function buildFlatArray(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $obj = [];
            foreach ($record as $key => $value) {
                $obj[ltrim((string)$key, '.')] = $value;
            }
            $results[] = $obj;
        }
        return $results;
    }

    /**
     * @param array<int, array<int|string, mixed>> $records
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function groupBySeparator(array $records, string $separator): array
    {
        $results = [];
        foreach ($records as $record) {
            $result = [];
            foreach ($record as $name => $value) {
                $parts   = $separator !== '' ? explode($separator, (string)$name) : [(string)$name];
                $newName = array_pop($parts);
                $path    = implode($separator, $parts);
                if ($parts) {
                    $path .= $separator;
                }
                if (!isset($result[$path])) {
                    $result[$path] = [];
                }
                $result[$path][$newName] = $value;
            }
            $results[] = $result;
        }
        return $results;
    }

    /**
     * @param array<int, array<string, array<string, mixed>>> $records
     * @return array<int, array<int|string, mixed>>
     */
    private function addHashes(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $mapping = [];
            foreach ($record as $key => $part) {
                if (substr($key, -2) != '[]') {
                    continue;
                }
                $jsonEncoded = json_encode($part);
                if ($jsonEncoded === false) {
                    $jsonEncoded = '';
                }
                $hash             = md5($jsonEncoded);
                $mapping[$key]    = substr($key, 0, -2) . '.!' . $hash . '!';
            }
            uksort($mapping, function ($a, $b) {
                return strlen($b) - strlen($a);
            });
            $keys    = array_keys($record);
            $values  = array_values($record);
            $newKeys = str_replace(array_keys($mapping), array_values($mapping), array_map('strval', $keys));
            $results[] = array_combine($newKeys, $values);
        }
        return $results;
    }

    /**
     * @param array<int, array<int|string, mixed>> $records
     * @return array<mixed>
     */
    private function combineIntoTree(array $records, string $separator): array
    {
        /** @var array<string, mixed> $results */
        $results = [];
        foreach ($records as $record) {
            foreach ($record as $name => $value) {
                if (!is_array($value)) {
                    continue;
                }
                foreach ($value as $key => $v) {
                    $path    = $separator !== '' ? explode($separator, (string)$name . (string)$key) : [(string)$name . (string)$key];
                    $newName = array_pop($path);
                    $current = &$results;
                    foreach ($path as $p) {
                        if (!isset($current[$p])) {
                            $current[$p] = [];
                        }
                        if (is_array($current[$p])) {
                            $current = &$current[$p];
                        }
                    }
                    $current[$newName] = $v;
                }
            }
        }
        return isset($results['']) && is_array($results['']) ? $results[''] : [];
    }

    /**
     * @param array<mixed> $tree
     * @return array<int|string, mixed>
     */
    private function removeHashes(array $tree, string $path): array
    {
        $values  = [];
        $trees   = [];
        $results = [];
        foreach ($tree as $key => $value) {
            if (is_array($tree[$key])) {
                if (substr($key, 0, 1) == '!' && substr($key, -1, 1) == '!') {
                    $results[] = $this->removeHashes($tree[$key], $path . '[]');
                } else {
                    $trees[$key] = $this->removeHashes($tree[$key], $path . '.' . $key);
                }
            } else {
                $values[$key] = $value;
            }
        }
        if (count($results)) {
            $hidden = array_merge(array_keys($values), array_keys($trees));
            if (count($hidden) > 0) {
                throw new PathError('The path "' . $path . '.' . $hidden[0] . '" is hidden by the path "' . $path . '[]"');
            }
            return $results;
        }
        return array_merge($values, $trees);
    }
}
