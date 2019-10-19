<?php

use PHPUnit\Framework\TestCase;
use Tqdev\PdoJson\SimplePdo;

class SimplePdoTest extends TestCase
{
    /**
     * @dataProvider selectDataProvider
     */
    public function testSelect($a, $b, $c, $expected)
    {
        $db = SimplePdo::create('php-crud-api', 'php-crud-api', 'php-crud-api');
        $this->assertSame($expected, json_encode($db->select($a, $b, $c)));
    }

    public function selectDataProvider()
    {
        return [
            'single record' => ['posts', ['id', 'content'], ['id' => 1], '[{"id":1,"content":"blog started"}]'],
            'two records' => ['posts', ['id'], [['id', '>=', 1], ['id', '<=', 2]], '[{"id":1},{"id":2}]'],
        ];
    }
}
