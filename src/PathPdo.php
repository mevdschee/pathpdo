<?php

namespace Tqdev\PdoJson;

class PathPdo extends SimplePdo
{
    private $schema;
    private $queryAnalyzer;
    private $pathInference;

    public function __construct(string $dsn, /*?string*/ $username = null, /*?string*/ $password = null, array $options = [])
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->schema = new Schema();
        $this->queryAnalyzer = new QueryAnalyzer();
        $this->pathInference = new PathInference($this->schema);
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
            default:
                throw new \Exception("Unsupported driver '$driver'");
        }
        return new static($dsn, $username, $password, $options);
    }

    public function pathQuery(string $query, array $params = [])
    {
        if (empty($params)) {
            $statement = $this->query($query);
        } else {
            $statement = $this->prepare($query);
            $statement->execute($params);
        }
        $pdoColumns = $this->getColumns($statement);

        // Analyze query and infer paths
        $this->queryAnalyzer->analyze($query);
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

    private function getColumns($statement): array
    {
        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $columns[] = $statement->getColumnMeta($i)['name'];
        }
        return $columns;
    }

    private function getAllRecords($statement, array $paths): array
    {
        $records = [];
        while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
            $record = [];
            foreach ($row as $i => $value) {
                $path = $paths[$i];
                // Strip leading "$" if present, else keep the dot (e.g. .id)
                $record[($path[0] === '$' ? substr($path, 1) : $path)] = $value;
            }
            $records[] = $record;
        }
        return $records;
    }

    private function buildObject(array $record): array
    {
        $result = [];
        foreach ($record as $key => $value) {
            $key = ltrim($key, '.');
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

    private function buildFlatArray(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $obj = [];
            foreach ($record as $key => $value) {
                $obj[ltrim($key, '.')] = $value;
            }
            $results[] = $obj;
        }
        return $results;
    }

    private function groupBySeparator(array $records, string $separator): array
    {
        $results = [];
        foreach ($records as $record) {
            $result = [];
            foreach ($record as $name => $value) {
                $parts   = explode($separator, $name);
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

    private function addHashes(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $mapping = [];
            foreach ($record as $key => $part) {
                if (substr($key, -2) != '[]') {
                    continue;
                }
                $hash             = md5(json_encode($part));
                $mapping[$key]    = substr($key, 0, -2) . '.!' . $hash . '!';
            }
            uksort($mapping, function ($a, $b) {
                return strlen($b) - strlen($a);
            });
            $keys    = array_keys($record);
            $values  = array_values($record);
            $newKeys = str_replace(array_keys($mapping), array_values($mapping), $keys);
            $results[] = array_combine($newKeys, $values);
        }
        return $results;
    }

    private function combineIntoTree(array $records, string $separator): array
    {
        $results = [];
        foreach ($records as $record) {
            foreach ($record as $name => $value) {
                foreach ($value as $key => $v) {
                    $path    = explode($separator, $name . $key);
                    $newName = array_pop($path);
                    $current = &$results;
                    foreach ($path as $p) {
                        if (!isset($current[$p])) {
                            $current[$p] = [];
                        }
                        $current = &$current[$p];
                    }
                    $current[$newName] = $v;
                }
            }
        }
        return $results[''] ?? [];
    }

    private function removeHashes(array $tree, string $path): array
    {
        $values  = [];
        $trees   = [];
        $results = [];
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
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
