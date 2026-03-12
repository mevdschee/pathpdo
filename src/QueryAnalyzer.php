<?php

namespace Tqdev\PdoJson;

class QueryAnalyzer
{
    public $tables = []; // alias => table name
    public $joins = []; // list of join info arrays
    public $pathHints = []; // alias => path override

    public function analyze(string $sql): void
    {
        $this->tables = [];
        $this->joins = [];
        $this->pathHints = $this->extractPathHints($sql);

        $sqlNoComments = $this->removeComments($sql);
        $this->extractFromClause($sqlNoComments);
        $this->extractJoins($sqlNoComments);
    }

    private function extractPathHints(string $sql): array
    {
        $hints = [];
        // Matches: -- PATH alias $.path or -- PATH: alias $.path
        // Allow $ alone or followed by chars
        if (preg_match_all('/--\s*PATH:?\s+(\$|\w+)\s+(\$[\w\[\]\.\*]*)/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $alias = $match[1];
                $path = $match[2];
                $hints[$alias] = $path;
            }
        }
        return $hints;
    }

    private function removeComments(string $sql): string
    {
        // Remove single-line comments
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        // Remove multi-line comments
        $sql = preg_replace('|/\*.*?\*/|s', '', $sql);
        return $sql;
    }

    private function extractFromClause(string $sql): void
    {
        // Find FROM clause - stop at WHERE, JOIN, ORDER BY, GROUP BY, LIMIT, or HAVING
        if (preg_match('/FROM\s+(.+?)(?:\s+(?:WHERE|LEFT|RIGHT|INNER|OUTER|JOIN|ORDER|GROUP|LIMIT|HAVING)|$)/si', $sql, $matches)) {
            $tableList = $matches[1];
            $tables = explode(',', $tableList);

            foreach ($tables as $tableSpec) {
                $tableSpec = trim($tableSpec);
                if ($tableSpec === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', $tableSpec);
                if (count($parts) >= 1) {
                    $tableName = $parts[0];
                    // Clean quotes/ticks if any
                    $tableName = trim($tableName, '`"\'');
                    $alias = $tableName;

                    if (count($parts) >= 2) {
                        if (count($parts) >= 3 && strtoupper($parts[1]) === 'AS') {
                            $alias = trim($parts[2], '`"\'');
                        } elseif (strtoupper($parts[1]) !== 'AS') {
                            $alias = trim($parts[1], '`"\'');
                        }
                    }

                    $upperAlias = strtoupper($alias);
                    $keywords = ['WHERE', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'JOIN', 'ORDER', 'GROUP', 'LIMIT', 'HAVING'];
                    if (!in_array($upperAlias, $keywords)) {
                        $this->tables[$alias] = $tableName;
                    }
                }
            }
        }
    }

    private function extractJoins(string $sql): void
    {
        // Pattern: [LEFT|RIGHT|INNER|OUTER] JOIN table [AS] alias ON condition
        $pattern = '/(LEFT\s+|RIGHT\s+|INNER\s+|OUTER\s+|CROSS\s+)?JOIN\s+([a-zA-Z0-9_`"\'\.]+)(?:\s+(?:AS\s+)?([a-zA-Z0-9_`"\']+))?\s+ON\s+(.+)/si';
        
        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            
            foreach ($matches as $match) {
                $joinType = trim(strtoupper($match[1] ?? ''));
                if ($joinType === '') {
                    $joinType = 'INNER';
                }

                $tableName = trim($match[2], '`"\'');
                $alias = $tableName;
                if (!empty($match[3])) {
                    $alias = trim($match[3], '`"\'');
                }
                $condition = trim($match[4]);
                
                $upperCond = strtoupper($condition);
                $stopWords = ['WHERE ', 'GROUP ', 'ORDER ', 'LIMIT ', 'HAVING ', 'LEFT ', 'RIGHT ', 'CROSS ', 'INNER ', 'OUTER ', 'JOIN '];
                $minPos = strlen($condition);
                foreach ($stopWords as $kw) {
                    $pos = strpos($upperCond, $kw);
                    if ($pos !== false && $pos < $minPos) {
                        $minPos = $pos;
                    }
                }
                $condition = trim(substr($condition, 0, $minPos));

                $this->tables[$alias] = $tableName;

                $onColumns = $this->parseJoinCondition($condition);

                $leftAlias = '';
                $leftTable = '';

                if (count($onColumns) > 0) {
                    if ($onColumns[0]['rightAlias'] === $alias) {
                        $leftAlias = $onColumns[0]['leftAlias'];
                    } elseif ($onColumns[0]['leftAlias'] === $alias) {
                        $leftAlias = $onColumns[0]['rightAlias'];
                    } else {
                        foreach ($this->tables as $a => $t) {
                            if ($a !== $alias) {
                                $leftAlias = $a;
                                break;
                            }
                        }
                    }

                    if ($leftAlias !== '') {
                        $leftTable = $this->tables[$leftAlias] ?? '';
                    }
                } else {
                    foreach ($this->tables as $a => $t) {
                        if ($a !== $alias) {
                            $leftAlias = $a;
                            $leftTable = $t;
                            break;
                        }
                    }
                }

                $this->joins[] = [
                    'leftAlias' => $leftAlias,
                    'leftTable' => $leftTable,
                    'rightAlias' => $alias,
                    'rightTable' => $tableName,
                    'joinType' => $joinType,
                    'condition' => $condition,
                    'onColumns' => $onColumns
                ];
            }
        }
    }

    private function parseJoinCondition(string $condition): array
    {
        $columns = [];
        if (preg_match_all('/([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*=\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)/', $condition, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columns[] = [
                    'leftAlias' => $match[1],
                    'leftColumn' => $match[2],
                    'rightAlias' => $match[3],
                    'rightColumn' => $match[4]
                ];
            }
        }
        return $columns;
    }

    public function parseSelectColumns(string $query): array
    {
        if (preg_match('/^\s*SELECT\s+(.+?)\s+FROM\s+/is', $query, $matches)) {
            $selectClause = $matches[1];
            $cols = [];
            $depth = 0;
            $current = '';
            for ($i = 0; $i < strlen($selectClause); $i++) {
                $c = $selectClause[$i];
                if ($c === '(') { $depth++; }
                elseif ($c === ')') { $depth--; }
                elseif ($c === ',' && $depth === 0) {
                    $cols[] = trim($current);
                    $current = '';
                    continue;
                }
                $current .= $c;
            }
            if (trim($current) !== '') {
                $cols[] = trim($current);
            }
            
            $result = [];
            foreach ($cols as $col) {
                if (preg_match('/\s+AS\s+([a-zA-Z0-9_`"\.]+)/i', $col, $m)) {
                    $result[] = trim($m[1], '`"\'');
                } elseif (strpos($col, '.') !== false && strpos($col, '(') === false) {
                    $result[] = trim($col, '`"\' ');
                } else {
                    $result[] = trim($col, '`"\' ');
                }
            }
            return $result;
        }
        return [];
    }
}
