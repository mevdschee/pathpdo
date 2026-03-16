<?php

namespace Tqdev\PdoJson;

class Schema
{
    /** @var string|null */
    private static $metadataFile = null;

    /** @var array<string,array<int,array<string,string>>>|null */
    private static $fileMetadata = null;

    /** @var array<string,array<int,array<string,string>>> */
    private static $foreignKeysCache = [];

    /**
     * Set the metadata file path. If set, metadata will be loaded from this file
     * instead of querying the database. Set to null to use database.
     * 
     * @param string|null $filename Path to metadata file (JSON or PHP array format)
     */
    public static function setMetadataFile(?string $filename): void
    {
        self::$metadataFile = $filename;
        self::$fileMetadata = null; // Clear cache when changing file
    }

    /**
     * Set metadata directly from a JSON string.
     * This allows custom metadata caching implementations without using files.
     * 
     * @param string $json JSON-encoded metadata (same format as metadata files)
     * @throws \RuntimeException if JSON is invalid
     */
    public static function setMetaData(string $json): void
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON metadata: " . json_last_error_msg());
        }
        if (!is_array($data)) {
            throw new \RuntimeException("Metadata must be a JSON object/array");
        }

        /** @var array<string,array<int,array<string,string>>> $data */
        self::$fileMetadata = $data;
        self::$metadataFile = null; // Clear file path when using direct metadata
    }

    /**
     * Get metadata as a JSON string.
     * Returns current metadata from cache, file, or database.
     * 
     * @param SmartPdo|null $db Database connection (required if no metadata is cached)
     * @return string JSON-encoded metadata
     * @throws \RuntimeException if metadata cannot be retrieved
     */
    public function getMetaData(?SmartPdo $db = null): string
    {
        // If we have cached metadata, return it
        if (self::$fileMetadata !== null) {
            $json = json_encode(self::$fileMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException("Failed to encode metadata as JSON");
            }
            return $json;
        }

        // If a metadata file is configured, load from it
        if (self::$metadataFile !== null) {
            $metadata = self::loadMetadataFromFile();
            $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException("Failed to encode metadata as JSON");
            }
            return $json;
        }

        // Otherwise, query the database
        if ($db === null) {
            throw new \RuntimeException("Database connection required to retrieve metadata");
        }

        $metadata = [
            'foreign_keys' => $this->getForeignKeysFromDatabase($db),
        ];

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode metadata as JSON");
        }
        return $json;
    }

    /**
     * Get the current metadata file path.
     * 
     * @return string|null
     */
    public static function getMetadataFile(): ?string
    {
        return self::$metadataFile;
    }

    /**
     * Clear the in-memory foreign keys cache.
     * Useful for testing or when schema changes are expected.
     */
    public static function clearCache(): void
    {
        self::$foreignKeysCache = [];
        self::$fileMetadata = null;
    }

    /**
     * Load metadata from the configured file.
     * 
     * @return array<string,mixed>
     * @throws \RuntimeException
     */
    private static function loadMetadataFromFile(): array
    {
        if (self::$fileMetadata !== null) {
            return self::$fileMetadata;
        }

        if (self::$metadataFile === null) {
            return [];
        }

        if (!file_exists(self::$metadataFile)) {
            throw new \RuntimeException("Metadata file not found: " . self::$metadataFile);
        }

        $contents = file_get_contents(self::$metadataFile);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read metadata file: " . self::$metadataFile);
        }

        // Try JSON first
        $data = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            /** @var array<string,array<int,array<string,string>>> $data */
            self::$fileMetadata = $data;
            return $data;
        }

        // Try PHP file format (starts with <?php)
        if (str_starts_with(trim($contents), '<?php')) {
            $data = include self::$metadataFile;
            if (is_array($data)) {
                /** @var array<string,array<int,array<string,string>>> $data */
                self::$fileMetadata = $data;
                return $data;
            }
        }

        throw new \RuntimeException("Invalid metadata file format: " . self::$metadataFile);
    }

    /**
     * Save metadata to a file.
     * 
     * @param string $filename Path to save metadata to
     * @param array<string,mixed> $metadata Metadata array to save
     * @param string $format Format: 'json' or 'php' (default: 'json')
     * @throws \RuntimeException
     */
    public static function saveMetadataToFile(string $filename, array $metadata, string $format = 'json'): void
    {
        if ($format === 'json') {
            $contents = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $contents = "<?php\nreturn " . var_export($metadata, true) . ";\n";
        }

        if (file_put_contents($filename, $contents) === false) {
            throw new \RuntimeException("Failed to write metadata file: " . $filename);
        }
    }

    /**
     * Export current database metadata to a file.
     * 
     * @param SmartPdo $db Database connection
     * @param string $filename Path to save metadata to
     * @param string $format Format: 'json' or 'php' (default: 'json')
     */
    public function exportMetadata(SmartPdo $db, string $filename, string $format = 'json'): void
    {
        $metadata = [
            'foreign_keys' => $this->getForeignKeysFromDatabase($db),
        ];
        self::saveMetadataToFile($filename, $metadata, $format);
    }

    /**
     * Gets all foreign keys for the current database.
     * Returns an array of objects/arrays with:
     * - from_table
     * - from_column
     * - to_table
     * - to_column
     * 
     * @return array<int,array<string,string>>
     */
    public function getForeignKeys(SmartPdo $db): array
    {
        // Check if we have directly set metadata (via setMetaData)
        if (self::$fileMetadata !== null) {
            /** @var array<int,array<string,string>> $data */
            $data = self::$fileMetadata['foreign_keys'] ?? [];
            return $data;
        }

        // Check if we should use file-based metadata
        if (self::$metadataFile !== null) {
            return $this->getForeignKeysFromFile();
        }

        return $this->getForeignKeysFromDatabase($db);
    }

    /**
     * Get foreign keys from configured metadata file.
     * 
     * @return array<int,array<string,string>>
     */
    private function getForeignKeysFromFile(): array
    {
        $metadata = self::loadMetadataFromFile();
        /** @var array<int,array<string,string>> $data */
        $data = $metadata['foreign_keys'] ?? [];
        return $data;
    }

    /**
     * Get foreign keys by querying the database.
     * 
     * @param SmartPdo $db
     * @return array<int,array<string,string>>
     */
    private function getForeignKeysFromDatabase(SmartPdo $db): array
    {
        $driver = $db->getDriver();

        if (isset(self::$foreignKeysCache[$driver])) {
            return self::$foreignKeysCache[$driver];
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
                $stmt = $db->query($sql);
                if ($stmt === false) {
                    break;
                }
                $fks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
                $stmt = $db->query($sql);
                if ($stmt === false) {
                    break;
                }
                $fks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
                $stmt = $db->query($sql);
                if ($stmt === false) {
                    break;
                }
                $fks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                break;
                // Add sqlite fallback later if needed.
        }
        /** @var array<int,array<string,string>> $data */
        $data = $fks;
        self::$foreignKeysCache[$driver] = $data;
        return $data;
    }
}
