<?php

use PHPUnit\Framework\TestCase;
use Tqdev\PdoJson\SimplePdo;

class SimplePdoTest extends TestCase
{
    static $db;

    public static function setUpBeforeClass(): void
    {
        static::$db = SimplePdo::create(getenv('PDO_DRIVER_USERNAME'), getenv('PDO_DRIVER_PASSWORD'), getenv('PDO_DRIVER_DATABASE'));
        static::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        static::$db->rollback();
    }

    /**
     * @dataProvider selectDataProvider
     */
    public function testSelect($a, $b, $c, $expected)
    {
        $this->assertSame($expected, json_encode(static::$db->select($a, $b, $c)));
    }

    public function selectDataProvider()
    {
        return [
            'full record' => ['posts', [], ['id' => 1], '[{"id":1,"user_id":1,"category_id":1,"content":"blog started"}]'],
            'single record' => ['posts', ['id', 'content'], ['id' => 1], '[{"id":1,"content":"blog started"}]'],
            'two records' => ['posts', ['id'], [['id', '>=', 1], ['id', '<=', 2]], '[{"id":1},{"id":2}]'],
        ];
    }

    /**
     * @dataProvider insertDataProvider
     */
    public function testInsert($a, $b, $expected)
    {
        $this->assertSame($expected, json_encode(static::$db->insert($a, $b)));
    }

    public function insertDataProvider()
    {
        return [
            'insert posts record' => ['posts', ['id' => 15, 'user_id' => 1, 'category_id' => 1, 'content' => 'blog started'], '15'],
            'second records' => ['posts', ['id' => 16, 'user_id' => 1, 'category_id' => 1, 'content' => 'blog started'], '16'],
        ];
    }
}
