<?php

namespace Tqdev\PdoJson;

class PathPdo extends SimplePdo
{
    public function q(string $query, array $params = [], bool $returnNumAffected = false, bool $returnLastInsertId = false)
    {
        if ($returnNumAffected || $returnLastInsertId) {
            return parent::q($query, $params, $returnNumAffected, $returnLastInsertId);
        }
        // query
        if (empty($params)) {
            $statement = $this->query($query);
        } else {
            $statement = $this->prepare($query);
            $statement->execute($params);
        }
        // get meta
        $meta = $this->getMeta($statement);
        // get all record paths
        $records = $this->getAllRecords($statement, $meta);
        // group by brackets
        $results = $this->groupBySeparator($records, '[]');
        // add hashes
        $results = $this->addHashes($results);
        // combine into tree by dots
        $results = $this->combineIntoTree($results, '.');
        // remove hashes
        $results = $this->removeHashes($results);
        return $results;
    }

    private function getMeta($statement): array
    {
        $meta = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta[] = $statement->getColumnMeta($i);
        }
        return $meta;
    }

    private function getTableCount($meta): int
    {
        return count(array_filter(array_unique(array_column($meta, 'table'))));
    }

    private function getAllRecords($statement, $meta): array
    {
        $tableCount = $this->getTableCount($meta);

        $records = [];
        while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
            $record = [];
            foreach ($row as $i => $value) {
                $name = $meta[$i]['name'];
                // enable auto-mode
                if (substr($name, 0, 1) != '$') {
                    $table = $meta[$i]['table'];
                    if ($tableCount > 1) {
                        $name = '$[].' . $table . '.' . $name;
                    } else {
                        $name = '$[].' . $name;
                    }
                }
                $record[substr($name, 1)] = $value;
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
        return $results;
    }

    private function removeHashes($tree): array
    {
        $produce = null;
        $produce = function ($tree) use (&$produce) {
            $values = [];
            $trees = [];
            foreach ($tree as $key => $value) {
                if (is_array($value)) {
                    if (substr($key, 0, 1) == '!' && substr($key, -1, 1) == '!') {
                        $trees[] = $produce($tree[$key]);
                    } else {
                        $trees[$key] = $produce($tree[$key]);
                    }
                } else {
                    $values[$key] = $value;
                }
            }
            return array_merge($values, $trees);
        };
        return $produce($tree['']);
    }
}
