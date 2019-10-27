<?php

namespace Tqdev\PdoJson\Tests;

class SimplePdoTest extends PdoTestCase
{
    static $class = '\Tqdev\PdoJson\SimplePdo';

    /**
     * @dataProvider selectDataProvider
     */
    public function testSelect($a, $b, $c, $expected)
    {
        $this->assertSame($expected, $this->jsonSort(json_encode($this->db->select($a, $b, $c)),true));
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
    public function testInsert($a, $b)
    {
        $this->assertIsInt($this->db->insert($a, $b));
    }

    public function insertDataProvider()
    {
        return [
            'insert posts record' => ['posts', ['user_id' => 1, 'category_id' => 1, 'content' => 'blog started']],
            'second records' => ['posts', ['user_id' => 1, 'category_id' => 1, 'content' => 'blog started']],
        ];
    }
}
