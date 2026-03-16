<?php

namespace Tqdev\PdoJson;

class PathInference
{
    private $schema;

    /**
     * Constructs a PathInference instance.
     * 
     * @param Schema $schema The schema object for foreign key information
     */
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Infer paths for all columns in a query result.
     * 
     * Analyzes the query structure, foreign key relationships, and path hints
     * to determine the hierarchical path for each column in the result set.
     * 
     * @param QueryAnalyzer $analysis The analyzed query information
     * @param array $columns Array of column names to infer paths for
     * @param SmartPdo $db Database connection for schema queries
     * @return array Array of paths corresponding to each column
     */
    public function inferPaths(QueryAnalyzer $analysis, array $columns, SmartPdo $db): array
    {
        $paths = [];
        $cardinality = $this->buildCardinalityMap($analysis, $db);

        foreach ($columns as $idx => $col) {
            $paths[$idx] = $this->inferColumnPath($col, $analysis, $cardinality);
        }

        return $paths;
    }

    private function buildCardinalityMap(QueryAnalyzer $analysis, SmartPdo $db): array
    {
        $cardinality = [];
        $allFks = $this->schema->getForeignKeys($db);

        $rootAlias = '';
        $joinedAliases = [];
        foreach ($analysis->joins as $join) {
            $joinedAliases[$join['rightAlias']] = true;
        }

        foreach ($analysis->tables as $alias => $tableName) {
            if (!isset($joinedAliases[$alias])) {
                $rootAlias = $alias;
                break;
            }
        }

        // Special case: if there's a PATH hint for $ (root), use it
        if (isset($analysis->pathHints['$'])) {
            if ($rootAlias === '') {
                $rootAlias = '$';
                $analysis->tables['$'] = '$';
            }
            $analysis->pathHints[$rootAlias] = $analysis->pathHints['$'];
        }

        if ($rootAlias !== '') {
            if (isset($analysis->pathHints[$rootAlias])) {
                $hintPath = $analysis->pathHints[$rootAlias];
                if (substr($hintPath, -2) === '[]') {
                    $cardinality[$rootAlias] = true;
                } elseif ($hintPath === '$') {
                    $cardinality[$rootAlias] = false;
                } else {
                    $cardinality[$rootAlias] = count($analysis->joins) > 0;
                }
            } else {
                $cardinality[$rootAlias] = true;
            }
        }

        foreach ($analysis->joins as $join) {
            $cardinality[$join['rightAlias']] = $this->isOneToManyJoin($join, $allFks);
        }

        foreach ($analysis->tables as $alias => $tableName) {
            if (!isset($cardinality[$alias])) {
                $cardinality[$alias] = true;
            }
        }

        return $cardinality;
    }

    private function isOneToManyJoin(array $join, array $allFks): bool
    {
        if (empty($join['onColumns'])) {
            return in_array($join['joinType'], ['LEFT', 'LEFT OUTER']);
        }

        foreach ($join['onColumns'] as $jc) {
            foreach ($allFks as $fk) {
                // Right table has FK to left table -> One to Many
                if ($fk['from_table'] === $join['rightTable'] && $fk['to_table'] === $join['leftTable']) {
                    if (($jc['rightAlias'] === $join['rightAlias'] && $jc['rightColumn'] === $fk['from_column']) ||
                        ($jc['leftAlias'] === $join['rightAlias'] && $jc['leftColumn'] === $fk['from_column'])
                    ) {
                        return true;
                    }
                }

                // Left table has FK to right table -> Many to One
                if ($fk['from_table'] === $join['leftTable'] && $fk['to_table'] === $join['rightTable']) {
                    if (($jc['leftAlias'] === $join['leftAlias'] && $jc['leftColumn'] === $fk['from_column']) ||
                        ($jc['rightAlias'] === $join['leftAlias'] && $jc['rightColumn'] === $fk['from_column'])
                    ) {
                        return false;
                    }
                }
            }
        }

        return in_array($join['joinType'], ['LEFT', 'LEFT OUTER']);
    }

    private function inferColumnPath(string $column, QueryAnalyzer $analysis, array $cardinality): string
    {
        $parts = explode('.', $column);
        $alias = '';
        $colName = '';

        if (count($parts) === 2) {
            $alias = $parts[0];
            $colName = $parts[1];

            if (isset($analysis->pathHints[$alias])) {
                $hintPath = $analysis->pathHints[$alias];
                // Path hint already specifies the structure, just append column name
                return rtrim($hintPath, '.') . '.' . $colName;
            }
        } else {
            $colName = $column;
            $alias = $this->guessAliasForColumn($colName, $analysis);

            if ($alias === '') {
                if (isset($analysis->pathHints['$'])) {
                    $hintPath = $analysis->pathHints['$'];
                    // Check if the hint already ends with the column name
                    if (substr($hintPath, -strlen($colName)) === $colName) {
                        return $hintPath;
                    }
                    return rtrim($hintPath, '.') . '.' . $colName;
                }
                return '$.' . $colName;
            }

            if (isset($analysis->pathHints[$alias])) {
                $hintPath = $analysis->pathHints[$alias];
                // Check if the hint already ends with the column name
                if (substr($hintPath, -strlen($colName)) === $colName) {
                    return $hintPath;
                }
                return rtrim($hintPath, '.') . '.' . $colName;
            }
        }

        if (count($analysis->joins) === 0) {
            if (!empty($cardinality[$alias])) {
                return '$[].' . $colName;
            }
            return '$.' . $colName;
        }

        $rootAlias = $this->findRootAlias($analysis);

        // If this column belongs to the root table, use simplified path
        if ($alias === $rootAlias) {
            if (isset($analysis->pathHints[$rootAlias])) {
                $rootHint = $analysis->pathHints[$rootAlias];
                return rtrim($rootHint, '.') . '.' . $colName;
            }
            if (!empty($cardinality[$alias])) {
                return '$[].' . $colName;
            }
            return '$.' . $colName;
        }

        // For non-root columns, build full path
        if (isset($analysis->pathHints[$rootAlias])) {
            $rootHint = $analysis->pathHints[$rootAlias];
            if (!empty($cardinality[$rootAlias])) {
                if (!empty($cardinality[$alias])) {
                    if (substr($rootHint, -2) !== '[]') {
                        return $rootHint . '[].' . $alias . '[].' . $colName;
                    }
                    return $rootHint . '.' . $alias . '[].' . $colName;
                }
                if (substr($rootHint, -2) !== '[]') {
                    return $rootHint . '[].' . $alias . '.' . $colName;
                }
                return $rootHint . '.' . $alias . '.' . $colName;
            } else {
                if (!empty($cardinality[$alias])) {
                    return $rootHint . '.' . $alias . '[].' . $colName;
                }
                return $rootHint . '.' . $alias . '.' . $colName;
            }
        }

        $path = $this->buildPathToTable($alias, $analysis, $cardinality);
        return $path . '.' . $colName;
    }

    private function findRootAlias(QueryAnalyzer $analysis): string
    {
        $joinedAliases = [];
        foreach ($analysis->joins as $join) {
            $joinedAliases[$join['rightAlias']] = true;
        }
        foreach ($analysis->tables as $alias => $tableName) {
            if (!isset($joinedAliases[$alias])) {
                return $alias;
            }
        }
        return '';
    }

    private function guessAliasForColumn(string $column, QueryAnalyzer $analysis): string
    {
        if (count($analysis->tables) === 1) {
            reset($analysis->tables);
            return key($analysis->tables) ?? '';
        }

        foreach ($analysis->tables as $alias => $tableName) {
            return $alias;
        }

        return '';
    }

    private function buildPathToTable(string $targetAlias, QueryAnalyzer $analysis, array $cardinality): string
    {
        $visited = [];
        return $this->buildPathRecursive($targetAlias, $analysis, $cardinality, $visited);
    }

    private function buildPathRecursive(string $targetAlias, QueryAnalyzer $analysis, array $cardinality, array &$visited): string
    {
        if (isset($visited[$targetAlias])) {
            return '';
        }
        $visited[$targetAlias] = true;

        $rootAlias = $this->findRootAlias($analysis);
        $isRoot = ($targetAlias === $rootAlias);

        if ($isRoot) {
            // Root should not include alias in path
            if (!empty($cardinality[$targetAlias])) {
                return '$[]';
            }
            return '$';
        }

        if (!empty($cardinality[$rootAlias])) {
            if (!empty($cardinality[$targetAlias])) {
                return '$[].' . $targetAlias . '[]';
            }
            return '$[].' . $targetAlias;
        }

        if (!empty($cardinality[$targetAlias])) {
            return '$.' . $targetAlias . '[]';
        }
        return '$.' . $targetAlias;
    }
}
