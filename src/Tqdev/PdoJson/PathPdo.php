<?php

namespace Tqdev\PdoJson;

class PathPdo extends SimplePdo
{
    public function q(string $statement, array $params = [], bool $returnNumAffected = false, bool $returnLastInsertId = false)
    {
        if ($returnNumAffected || $returnLastInsertId) {
            return parent::q($statement, $params, $returnNumAffected, $returnLastInsertId);
        }
        // query
        if (empty($params)) {
            $stmt = $this->query($statement);
        } else {
            $stmt = $this->prepare($statement);
            $stmt->execute($params);
        }
        // json path
        $results = [];
        while ($record = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result = [];
            foreach ($record as $name => $value) {
                $path = explode('.', $name);
                $name = array_pop($path);
                if (!count($path)) {
                    $path = ['[]'];
                }
                $path = implode('.', $path);
                $current = &$result;
                if (!isset($current[$path])) {
                    $current[$path] = [];
                }
                $current = &$current[$path];
                $current[$name] = $value;
            }
            $results[] = $result;
        }
        // turn into tree
        $tree = new PathTree();
        foreach ($results as $result) {
            $keys = array_keys($result);
            usort($keys, function ($a, $b) {
                return strlen($a) - strlen($b);
            });
            $mapping = [];
            foreach ($keys as $key) {
                $record = $result[$key];
                if (substr($key, -2) == '[]') {
                    $hash = json_encode($record);
                    $key = str_replace(array_keys($mapping), array_values($mapping), $key);
                    $mapping[$key] = substr($key, 0, -2) . ".!$hash!";
                }
                $key = str_replace(array_keys($mapping), array_values($mapping), $key);
                $path = explode('.', $key);
                if (!$tree->match($path)) {
                    $tree->put($path, $record);
                }
            }
        }
        // make json
        $produce = null;
        $produce = function ($tree, $i = 0) use (&$produce) {
            $results = $tree->getValues();
            if (count($results)) $results = $results[0];
            foreach ($tree->getKeys() as $key) {
                if (substr($key, 0, 1) == '!' && substr($key, -1, 1) == '!') {
                    $results[] = $produce($tree->get($key), $i + 1);
                } else {
                    $results[$key] = $produce($tree->get($key), $i + 1);
                }
            }
            return $results;
        };
        return $produce($tree->get('') ?: $tree);
    }
}
