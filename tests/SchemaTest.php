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
}
