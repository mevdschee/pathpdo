<?php

namespace Tqdev\PdoJson\Tests;

class PathPdoTest extends PdoTestCase
{
    static $pdo;
    static $class = '\Tqdev\PdoJson\PathPdo';

    /**
     * @dataProvider pathQueryDataProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pathQueryDataProvider')]
    public function testPathQuery($a, $b, $expected)
    {
        $this->assertSame($expected, json_encode($this->db->pathQuery($a, $b)));
    }

    public static function pathQueryDataProvider()
    {
        return [
            // --- No-path flat array (fast path, no "$" aliases) ---
            'single record no path' => [
                'select id, content from posts where id=?',
                [1],
                '[{"id":1,"content":"blog started"}]',
            ],
            'two records no path' => [
                'select id from posts where id<=:id order by id',
                ['id' => 2],
                '[{"id":1},{"id":2}]',
            ],
            'two records named params no path' => [
                'select id from posts where id<=:two and id>=:one order by id',
                ['one' => 1, 'two' => 2],
                '[{"id":1},{"id":2}]',
            ],
            'count posts grouped no path' => [
                'select categories.name, count(posts.id) as post_count from posts, categories where posts.category_id = categories.id group by categories.name order by categories.name',
                [],
                '[{"name":"announcement","post_count":11},{"name":"article","post_count":1}]',
            ],

            // --- Single-object fast path via PATH hints ---
            'count posts as object with path hint' => [
                'select count(*) as posts from posts -- PATH $ $.posts',
                [],
                '{"posts":12}',
            ],
            'count posts with added root set in path hint' => [
                'select count(*) as posts from posts -- PATH $ $.statistics.posts',
                [],
                '{"statistics":{"posts":12}}',
            ],
            'count posts and comments as object with path hint' => [
                'select (select count(*) from posts) as posts, (select count(*) from comments) as comments -- PATH $ $.stats',
                [],
                '{"stats":{"posts":12,"comments":6}}',
            ],

            // --- PATH comment hint (new feature) ---
            'count as object with PATH hint' => [
                'select count(*) as posts from posts p -- PATH p $',
                [],
                '{"posts":12}',
            ],
            'nested statistics with PATH hint' => [
                'select count(*) as posts from posts p -- PATH p $.statistics',
                [],
                '{"statistics":{"posts":12}}',
            ],
            'count posts and comments with PATH hint' => [
                'select (select count(*) from posts) as posts, (select count(*) from comments) as comments -- PATH $ $.statistics',
                [],
                '{"statistics":{"posts":12,"comments":6}}',
            ],

            // --- Automatic Path Inference (from JOINs and FKs) ---
            'two tables flat join with PATH hint flat array' => [
                'select p.id as "p.id", c.id as "c.id" from posts p left join comments c on c.post_id = p.id where p.id=1 order by c.id -- PATH p $[].p -- PATH c $[].c',
                [],
                '[{"p":{"id":1},"c":{"id":1}},{"p":{"id":1},"c":{"id":2}}]',
            ],
            'posts with comments properly nested' => [
                'select p.id, c.id from posts p left join comments c on c.post_id = p.id where p.id<=2 order by p.id, c.id',
                [],
                '[{"id":1,"c":[{"id":1},{"id":2}]},{"id":2,"c":[{"id":3},{"id":4},{"id":5},{"id":6}]}]',
            ],
            'comments with post properly nested' => [
                'select c.id, p.id from comments c join posts p on c.post_id = p.id where p.id<=2 order by c.id, p.id',
                [],
                '[{"id":1,"p":{"id":1}},{"id":2,"p":{"id":1}},{"id":3,"p":{"id":2}},{"id":4,"p":{"id":2}},{"id":5,"p":{"id":2}},{"id":6,"p":{"id":2}}]',
            ],
            'count posts with array path hint' => [
                'select count(*) as posts from posts p -- PATH p $[]',
                [],
                '[{"posts":12}]',
            ],

            // --- Automatic Path Inference without PATH hints ---
            'simple query with alias no joins' => [
                'select p.id, p.content from posts p where p.id=1',
                [],
                '[{"id":1,"content":"blog started"}]',
            ],
            'posts with comments one-to-many with content' => [
                'select p.id, p.content, c.id, c.message from posts p left join comments c on c.post_id = p.id where p.id=1 order by c.id',
                [],
                '[{"id":1,"content":"blog started","c":[{"id":1,"message":"great!"},{"id":2,"message":"nice!"}]}]',
            ],
            'multiple posts with comments with message' => [
                'select p.id, c.id, c.message from posts p left join comments c on c.post_id = p.id where p.id<=2 order by p.id, c.id',
                [],
                '[{"id":1,"c":[{"id":1,"message":"great!"},{"id":2,"message":"nice!"}]},{"id":2,"c":[{"id":3,"message":"interesting"},{"id":4,"message":"cool"},{"id":5,"message":"wow"},{"id":6,"message":"amazing"}]}]',
            ],
            'posts with category many-to-one' => [
                'select p.id, p.content, cat.id, cat.name from posts p left join categories cat on p.category_id = cat.id where p.id=1',
                [],
                '[{"id":1,"content":"blog started","cat":{"id":1,"name":"announcement"}}]',
            ],
        ];
    }

    /**
     * Test pathQuery with explicit paths parameter
     */
    public function testPathQueryWithPathsParameter()
    {
        // Single object result with paths parameter
        $result = $this->db->pathQuery(
            'SELECT COUNT(*) as posts FROM posts p',
            [],
            ['p' => '$.statistics']
        );
        $this->assertEquals(['statistics' => ['posts' => 12]], $result);
    }

    public function testPathQueryWithPathsParameterNestedArrays()
    {
        // Posts with comments using paths parameter
        $result = $this->db->pathQuery(
            'SELECT p.id, c.id, c.message 
             FROM posts p 
             LEFT JOIN comments c ON c.post_id = p.id 
             WHERE p.id = :id
             ORDER BY c.id',
            ['id' => 1],
            [
                'p' => '$',
                'c' => '$.comments[]'
            ]
        );

        $expected = [
            'id' => 1,
            'comments' => [
                ['id' => 1, 'message' => 'great!'],
                ['id' => 2, 'message' => 'nice!']
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testPathQueryWithPathsParameterOverridesComments()
    {
        // Paths parameter should override SQL comment hints
        $result = $this->db->pathQuery(
            'SELECT p.id, c.id 
             FROM posts p -- PATH p $.wrong
             LEFT JOIN comments c ON c.post_id = p.id -- PATH c $.wrong[]
             WHERE p.id = ?
             ORDER BY c.id',
            [1],
            [
                'p' => '$',
                'c' => '$.comments[]'
            ]
        );

        $expected = [
            'id' => 1,
            'comments' => [
                ['id' => 1],
                ['id' => 2]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testPathQueryWithPathsParameterMultipleLevels()
    {
        // Test deeply nested paths
        $result = $this->db->pathQuery(
            'SELECT p.id, c.id, c.message 
             FROM posts p 
             LEFT JOIN comments c ON c.post_id = p.id 
             WHERE p.id = :id 
             ORDER BY c.id',
            ['id' => 2],
            [
                'p' => '$.data',
                'c' => '$.data.comments[]'
            ]
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('id', $result['data']);
        $this->assertEquals(2, $result['data']['id']);
        $this->assertArrayHasKey('comments', $result['data']);
        $this->assertCount(4, $result['data']['comments']);
    }

    public function testPathQueryWithPathsParameterArrayRoot()
    {
        // Test array at root level
        $result = $this->db->pathQuery(
            'SELECT p.id, p.content FROM posts p WHERE p.id <= :id ORDER BY p.id',
            ['id' => 2],
            ['p' => '$[]']
        );

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(2, $result[1]['id']);
        $this->assertEquals('blog started', $result[0]['content']);
    }

    public function testPathQueryWithEmptyPathsParameter()
    {
        // Empty paths parameter should work like normal query
        $result = $this->db->pathQuery(
            'SELECT id, content FROM posts WHERE id = :id',
            ['id' => 1]
        );

        $this->assertEquals([['id' => 1, 'content' => 'blog started']], $result);
    }
}
