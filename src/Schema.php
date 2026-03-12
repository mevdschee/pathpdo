<?php

namespace Tqdev\PdoJson;

class Schema
{
    /** @var array<string, array> */
    private $foreignKeysCache = [];

    /**
     * Gets all foreign keys for the current database.
     * Returns an array of objects/arrays with:
     * - from_table
     * - from_column
     * - to_table
     * - to_column
     */
    public function getForeignKeys(SmartPdo $db): array
    {
        $driver = $db->getDriver();

        if (isset($this->foreignKeysCache[$driver])) {
            return $this->foreignKeysCache[$driver];
        }

        $fks = [];
        switch ($driver) {
            case 'mysql':
                $sql = "
                    SELECT 
                        TABLE_NAME AS from_table, 
                        COLUMN_NAME AS from_column, 
                        REFERENCED_TABLE_NAME AS to_table, 
                        REFERENCED_COLUMN_NAME AS to_column 
                    FROM information_schema.key_column_usage 
                    WHERE REFERENCED_TABLE_NAME IS NOT NULL 
                      AND TABLE_SCHEMA = DATABASE()
                ";
                $fks = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                break;
            case 'pgsql':
                $sql = "
                    SELECT
                        tc.table_name AS from_table,
                        kcu.column_name AS from_column,
                        ccu.table_name AS to_table,
                        ccu.column_name AS to_column
                    FROM information_schema.table_constraints AS tc
                    JOIN information_schema.key_column_usage AS kcu
                      ON tc.constraint_name = kcu.constraint_name
                      AND tc.table_schema = kcu.table_schema
                    JOIN information_schema.constraint_column_usage AS ccu
                      ON ccu.constraint_name = tc.constraint_name
                      AND ccu.table_schema = tc.table_schema
                    WHERE tc.constraint_type = 'FOREIGN KEY'
                      AND tc.table_schema = current_schema()
                ";
                $fks = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                break;
            case 'sqlsrv':
                $sql = "
                    SELECT 
                        tp.name AS from_table,
                        cp.name AS from_column,
                        tr.name AS to_table,
                        cr.name AS to_column
                    FROM sys.foreign_keys fk
                    INNER JOIN sys.tables tp ON fk.parent_object_id = tp.object_id
                    INNER JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id
                    INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                    INNER JOIN sys.columns cp ON fkc.parent_column_id = cp.column_id AND fkc.parent_object_id = cp.object_id
                    INNER JOIN sys.columns cr ON fkc.referenced_column_id = cr.column_id AND fkc.referenced_object_id = cr.object_id
                ";
                $fks = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
                break;
            // Add sqlite fallback later if needed.
        }

        $this->foreignKeysCache[$driver] = $fks;
        return $fks;
    }
}
