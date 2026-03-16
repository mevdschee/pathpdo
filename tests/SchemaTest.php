<?php

namespace Tqdev\PdoJson\Tests;

use Tqdev\PdoJson\Schema;

class SchemaTest extends PdoTestCase
{
    public function testGetForeignKeysFromDatabase()
    {
        $schema = new Schema();
        $fks = $schema->getForeignKeys(static::$pdo);

        $this->assertIsArray($fks);
        $this->assertNotEmpty($fks);

        // Should have foreign keys from test database
        $commentsFk = null;
        foreach ($fks as $fk) {
            if ($fk['from_table'] === 'comments' && $fk['from_column'] === 'post_id') {
                $commentsFk = $fk;
                break;
            }
        }

        $this->assertNotNull($commentsFk);
        $this->assertEquals('posts', $commentsFk['to_table']);
        $this->assertEquals('id', $commentsFk['to_column']);
    }

    public function testExportAndLoadMetadata()
    {
        $schema = new Schema();
        $tempFile = sys_get_temp_dir() . '/pathpdo_test_' . uniqid() . '.json';

        try {
            // Export metadata
            $schema->exportMetadata(static::$pdo, $tempFile, 'json');
            $this->assertFileExists($tempFile);

            // Load metadata from file
            Schema::setMetadataFile($tempFile);
            $fksFromFile = $schema->getForeignKeys(static::$pdo);

            // Reset to database mode
            Schema::setMetadataFile(null);
            $fksFromDb = $schema->getForeignKeys(static::$pdo);

            // Should be the same
            $this->assertEquals($fksFromDb, $fksFromFile);
        } finally {
            // Cleanup
            Schema::setMetadataFile(null);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testMetadataFileSetting()
    {
        $this->assertNull(Schema::getMetadataFile());

        Schema::setMetadataFile('/path/to/metadata.json');
        $this->assertEquals('/path/to/metadata.json', Schema::getMetadataFile());

        Schema::setMetadataFile(null);
        $this->assertNull(Schema::getMetadataFile());
    }

    public function testExportPhpFormat()
    {
        $schema = new Schema();
        $tempFile = sys_get_temp_dir() . '/pathpdo_test_' . uniqid() . '.php';

        try {
            // Export as PHP
            $schema->exportMetadata(static::$pdo, $tempFile, 'php');
            $this->assertFileExists($tempFile);

            // Verify it's valid PHP
            $contents = file_get_contents($tempFile);
            $this->assertStringStartsWith('<?php', $contents);

            // Load and verify structure
            Schema::setMetadataFile($tempFile);
            $fks = $schema->getForeignKeys(static::$pdo);
            $this->assertIsArray($fks);
        } finally {
            // Cleanup
            Schema::setMetadataFile(null);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSetMetaDataWithValidJson()
    {
        $metadata = [
            'foreign_keys' => [
                [
                    'from_table' => 'test_table',
                    'from_column' => 'test_id',
                    'to_table' => 'ref_table',
                    'to_column' => 'id'
                ]
            ]
        ];
        $json = json_encode($metadata);

        try {
            Schema::setMetaData($json);
            $schema = new Schema();
            $fks = $schema->getForeignKeys(static::$pdo);

            $this->assertCount(1, $fks);
            $this->assertEquals('test_table', $fks[0]['from_table']);
            $this->assertEquals('test_id', $fks[0]['from_column']);
            $this->assertEquals('ref_table', $fks[0]['to_table']);
            $this->assertEquals('id', $fks[0]['to_column']);
        } finally {
            // Cleanup
            Schema::clearCache();
        }
    }

    public function testSetMetaDataWithInvalidJson()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON metadata');

        Schema::setMetaData('invalid json {');
    }

    public function testSetMetaDataWithNonArrayJson()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Metadata must be a JSON object/array');

        Schema::setMetaData('"just a string"');
    }

    public function testGetMetaDataFromCache()
    {
        $metadata = [
            'foreign_keys' => [
                [
                    'from_table' => 'cached_table',
                    'from_column' => 'cached_id',
                    'to_table' => 'cached_ref',
                    'to_column' => 'id'
                ]
            ]
        ];
        $json = json_encode($metadata);

        try {
            Schema::setMetaData($json);
            $schema = new Schema();
            $retrieved = $schema->getMetaData();

            $decoded = json_decode($retrieved, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('foreign_keys', $decoded);
            $this->assertCount(1, $decoded['foreign_keys']);
            $this->assertEquals('cached_table', $decoded['foreign_keys'][0]['from_table']);
        } finally {
            // Cleanup
            Schema::clearCache();
        }
    }

    public function testGetMetaDataFromFile()
    {
        $metadata = [
            'foreign_keys' => [
                [
                    'from_table' => 'file_table',
                    'from_column' => 'file_id',
                    'to_table' => 'file_ref',
                    'to_column' => 'id'
                ]
            ]
        ];

        $tempFile = sys_get_temp_dir() . '/pathpdo_test_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($metadata, JSON_PRETTY_PRINT));

        try {
            Schema::setMetadataFile($tempFile);
            $schema = new Schema();
            $retrieved = $schema->getMetaData();

            $decoded = json_decode($retrieved, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('foreign_keys', $decoded);
            $this->assertEquals('file_table', $decoded['foreign_keys'][0]['from_table']);
        } finally {
            // Cleanup
            Schema::setMetadataFile(null);
            Schema::clearCache();
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testGetMetaDataFromDatabase()
    {
        Schema::clearCache();
        Schema::setMetadataFile(null);

        $schema = new Schema();
        $retrieved = $schema->getMetaData(static::$pdo);

        $decoded = json_decode($retrieved, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('foreign_keys', $decoded);
        $this->assertNotEmpty($decoded['foreign_keys']);

        // Verify it contains actual foreign keys from the test database
        $hasForeignKey = false;
        foreach ($decoded['foreign_keys'] as $fk) {
            if (isset($fk['from_table']) && isset($fk['to_table'])) {
                $hasForeignKey = true;
                break;
            }
        }
        $this->assertTrue($hasForeignKey);
    }

    public function testGetMetaDataWithoutDatabaseOrCache()
    {
        Schema::clearCache();
        Schema::setMetadataFile(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection required to retrieve metadata');

        $schema = new Schema();
        $schema->getMetaData();
    }

    public function testSetMetaDataClearsMetadataFile()
    {
        $tempFile = sys_get_temp_dir() . '/pathpdo_test_' . uniqid() . '.json';
        file_put_contents($tempFile, '{"foreign_keys":[]}');

        try {
            // Set a metadata file
            Schema::setMetadataFile($tempFile);
            $this->assertEquals($tempFile, Schema::getMetadataFile());

            // Setting metadata directly should clear the file path
            Schema::setMetaData('{"foreign_keys":[]}');
            $this->assertNull(Schema::getMetadataFile());
        } finally {
            // Cleanup
            Schema::setMetadataFile(null);
            Schema::clearCache();
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
