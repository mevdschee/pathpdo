<?php

namespace Tqdev\PdoJson\Tests;

use PHPUnit\Framework\TestCase;
use Tqdev\PdoJson\QueryAnalyzer;
use Tqdev\PdoJson\PathInference;
use Tqdev\PdoJson\Schema;

class PathInferenceTest extends PdoTestCase
{
    public function testInferPathsWithFlatJoin(): void
    {
        $this->assertNotNull(static::$pdo);
        $analyzer = new QueryAnalyzer();
        $sql = 'select p.id as "p.id", c.id as "c.id" from posts p left join comments c on c.post_id = p.id where p.id=1 order by c.id';
        $analyzer->analyze($sql);
        // Set path hints via the pathHints property (simulating the paths parameter)
        $analyzer->pathHints = ['p' => '$[].p', 'c' => '$[].c'];

        $cols = $analyzer->parseSelectColumns($sql);

        $schema = new Schema();
        $inference = new PathInference($schema);

        $paths = $inference->inferPaths($analyzer, $cols, static::$pdo);
        $this->assertEquals(['$[].p.id', '$[].c.id'], $paths);
    }

    public function testInferPathsWithNestedOneToMany(): void
    {
        $this->assertNotNull(static::$pdo);
        $analyzer = new QueryAnalyzer();
        $sql = 'select p.id, c.id from posts p left join comments c on c.post_id = p.id where p.id<=2 order by p.id, c.id';
        $analyzer->analyze($sql);

        $cols = $analyzer->parseSelectColumns($sql);

        $schema = new Schema();
        $inference = new PathInference($schema);

        $paths = $inference->inferPaths($analyzer, $cols, static::$pdo);
        // posts is root, so it's an array $[].id
        // comments is joined as one-to-many, so it should be nested under posts as an array $[].c[].id
        $this->assertEquals(['$[].id', '$[].c[].id'], $paths);
    }

    public function testInferPathsWithNestedManyToOne(): void
    {
        $this->assertNotNull(static::$pdo);
        $analyzer = new QueryAnalyzer();
        $sql = 'select c.id, p.id from comments c join posts p on c.post_id = p.id where p.id<=2 order by c.id, p.id';
        $analyzer->analyze($sql);

        $cols = $analyzer->parseSelectColumns($sql);

        $schema = new Schema();
        $inference = new PathInference($schema);

        $paths = $inference->inferPaths($analyzer, $cols, static::$pdo);
        // comments is root, so it's an array $[].id
        // posts is joined as many-to-one, so it should be nested under comments as an object $[].p.id
        $this->assertEquals(['$[].id', '$[].p.id'], $paths);
    }

    public function testInferPathsWithAliasHints(): void
    {
        $this->assertNotNull(static::$pdo);
        $analyzer = new QueryAnalyzer();
        // Just the count object
        $sql = 'select count(*) as posts from posts';
        $analyzer->analyze($sql);
        // Set path hints via the pathHints property (simulating the paths parameter)
        $analyzer->pathHints = ['$' => '$.posts'];
        $cols = $analyzer->parseSelectColumns($sql);

        $schema = new Schema();
        $inference = new PathInference($schema);

        $paths = $inference->inferPaths($analyzer, $cols, static::$pdo);
        $this->assertEquals(['$.posts'], $paths);
    }
}
