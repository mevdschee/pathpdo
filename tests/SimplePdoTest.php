<?php

use PHPUnit\Framework\TestCase;
use Tqdev\PdoJson\PathPdo;

class SimplePdoTest extends TestCase
{
    /**
     * @dataProvider qDataProvider
     */
    public function testQ($a, $b, $expected)
    {
        $db = new PathPdo('mysql:host=localhost;port=3306;dbname=php-crud-api;charset=utf8mb4', 'php-crud-api', 'php-crud-api');
        $this->assertSame($expected, $db->q($a, $b));
    }

    public function qDataProvider()
    {
        return [
            'single record' => ['select id, content from posts where id=?', [1], [['id' => 1, 'content' => 'blog started']]],
            'two records' => ['select id from posts where id<=2', [], [['id' => 1], ['id' => 2]]],
        ];
    }

    /**
     * @dataProvider selectDataProvider
     */
    public function testSelect($a, $b, $c, $expected)
    {
        $db = new PathPdo('mysql:host=localhost;port=3306;dbname=php-crud-api;charset=utf8mb4', 'php-crud-api', 'php-crud-api');
        $this->assertSame($expected, $db->select($a, $b, $c));
    }

    public function selectDataProvider()
    {
        return [
            'single record' => ['posts', ['id', 'content'], ['id' => 1], [['id' => 1, 'content' => 'blog started']]],
            'two records' => ['posts', ['id'], ['id <=' => 2], [['id' => 1], ['id' => 2]]],
        ];
    }
}
