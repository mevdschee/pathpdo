<?php

namespace Tqdev\PdoJson\Tests;

class SimplePdoTest extends PdoTestCase
{
    /** @var \Tqdev\PdoJson\SimplePdo|null */
    static $pdo;
    /** @var class-string */
    static $class = '\Tqdev\PdoJson\SimplePdo';
    /** @var \Tqdev\PdoJson\SimplePdo|null */
    protected $db;

    /**
     * @param array<int, string> $b
     * @param array<int|string,mixed> $c
     * @dataProvider selectDataProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectDataProvider')]
    public function testSelect(string $a, array $b, array $c, string $expected): void
    {
        $this->assertNotNull($this->db);
        $this->assertEqualsCanonicalizing(json_decode($expected, true), $this->db->select($a, $b, $c));
    }

    /**
     * @return array<string,array{0: string, 1: array<int,string>, 2: array<int|string,mixed>, 3: string}>
     */
    public static function selectDataProvider(): array
    {
        return [
            'full record' => ['posts', [], ['id' => 1], '[{"id":1,"user_id":1,"category_id":1,"content":"blog started"}]'],
            'single record' => ['posts', ['id', 'content'], ['id' => 1], '[{"id":1,"content":"blog started"}]'],
            'two records' => ['posts', ['id'], [['id', '>=', 1], ['id', '<=', 2]], '[{"id":1},{"id":2}]'],
        ];
    }

    /**
     * @param array<string,mixed> $b
     * @dataProvider insertDataProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('insertDataProvider')]
    public function testInsert(string $a, array $b): void
    {
        $this->assertNotNull($this->db);
        $this->assertGreaterThan(0, $this->db->insert($a, $b));
    }

    /**
     * @return array<string,array{0: string, 1: array<string,mixed>}>
     */
    public static function insertDataProvider(): array
    {
        return [
            'insert posts record' => ['posts', ['user_id' => 1, 'category_id' => 1, 'content' => 'blog started']],
            'second records' => ['posts', ['user_id' => 1, 'category_id' => 1, 'content' => 'blog started']],
        ];
    }
}
