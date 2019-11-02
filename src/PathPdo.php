<?php

namespace Tqdev\PdoJson;

class PathPdo extends SimplePdo
{
    public function q(string $query, array $params = [], bool $returnNumAffected = false, bool $returnLastInsertId = false)
    {
        if ($returnNumAffected || $returnLastInsertId) {
            return parent::q($query, $params, $returnNumAffected, $returnLastInsertId);
        }
        if (empty($params)) {
            $statement = $this->query($query);
        } else {
            $statement = $this->prepare($query);
            $statement->execute($params);
        }
        $columns = $this->getColumns($statement);
        $paths = $this->getPaths($columns);
        $records = $this->getAllRecords($statement, $paths);
        $groups = $this->groupBySeparator($records, '[]');
        $hashes = $this->addHashes($groups);
        $tree = $this->combineIntoTree($hashes, '.');
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

    private function getPaths($columns): array
    {
        $paths = [];
        $path = '$[]';
        foreach ($columns as $i => $column) {
            $prop = $column;
            if (substr($column, 0, 1) == '$') {
                $pos = strrpos($column, '.');
                if ($pos !== false) {
                    $path = substr($column, 0, $pos);
                    $prop = substr($column, $pos + 1);
                }
            }
            $paths[] = $path . '.' . $prop;
        }
        return $paths;
    }

    private function getAllRecords($statement, $paths): array
    {
        $records = [];
        while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
            $record = [];
            foreach ($row as $i => $value) {
                $record[substr($paths[$i], 1)] = $value;
            }
            $records[] = $record;
        }
        return $records;
    }

    private function groupBySeparator($records, $separator): array
    {
        $results = [];
        foreach ($records as $record) {
            $result = [];
            foreach ($record as $name => $value) {
                $parts = explode($separator, $name);
                $newName = array_pop($parts);
                $path = implode($separator, $parts);
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

    private function addHashes($records): array
    {
        foreach ($records as $record) {
            $mapping = [];
            foreach ($record as $key => $part) {
                if (substr($key, -2) != '[]') {
                    continue;
                }
                $hash = md5(json_encode($part));
                $mapping[$key] = substr($key, 0, -2) . '.!' . $hash . '!';
            }
            uksort($mapping, function ($a, $b) {
                return strlen($b) - strlen($a);
            });
            $keys = array_keys($record);
            $values = array_values($record);
            $newKeys = str_replace(array_keys($mapping), array_values($mapping), $keys);
            $results[] = array_combine($newKeys, $values);
        }
        return $results;
    }

    private function combineIntoTree($records, $separator): array
    {
        $results = [];
        foreach ($records as $record) {
            foreach ($record as $name => $value) {
                foreach ($value as $key => $v) {
                    $path = explode($separator, $name . $key);
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
        return $results[''];
    }

    private function removeHashes($tree, $path): array
    {
        $values = [];
        $trees = [];
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
