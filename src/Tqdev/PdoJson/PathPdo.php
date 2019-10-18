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
                $current = &$result;
                foreach ($path as $p) {
                    if (!isset($current[$p])) {
                        $current[$p] = [];
                    }
                    $current = &$current[$p];
                }
                $current = $value;
            }
            $results[] = $result;
        }
        // compress (todo)
        // ...
        return $results;
    }
}
