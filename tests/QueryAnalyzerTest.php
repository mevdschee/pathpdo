<?php

namespace Tqdev\PdoJson\Tests;

use PHPUnit\Framework\TestCase;
use Tqdev\PdoJson\QueryAnalyzer;

class QueryAnalyzerTest extends TestCase
{
    public function testParseSelectColumns()
    {
        $analyzer = new QueryAnalyzer();
        
        $sql = 'select p.id, c.id from posts p left join comments c on c.post_id = p.id';
        $cols = $analyzer->parseSelectColumns($sql);
        $this->assertEquals(['p.id', 'c.id'], $cols);
        
        $sql = 'select id, content from posts';
        $cols = $analyzer->parseSelectColumns($sql);
        $this->assertEquals(['id', 'content'], $cols);

        $sql = 'select p.id as pid, c.id as cid from posts p join comments c';
        $cols = $analyzer->parseSelectColumns($sql);
        $this->assertEquals(['pid', 'cid'], $cols);
        
        $sql = 'select count(*) as posts from posts p';
        $cols = $analyzer->parseSelectColumns($sql);
        $this->assertEquals(['posts'], $cols);
    }
    
    public function testAnalyze()
    {
        $analyzer = new QueryAnalyzer();
        $sql = 'select p.id as pid, c.id as cid from posts p LEFT JOIN comments c ON c.post_id = p.id WHERE p.id <= 2 -- PATH p $[].p -- PATH c $[].c';
        $analyzer->analyze($sql);
        
        $this->assertEquals(['p' => 'posts', 'c' => 'comments'], $analyzer->tables);
        $this->assertEquals(['p' => '$[].p', 'c' => '$[].c'], $analyzer->pathHints);
        $this->assertCount(1, $analyzer->joins);
        
        $join = $analyzer->joins[0];
        $this->assertEquals('p', $join['leftAlias']);
        $this->assertEquals('posts', $join['leftTable']);
        $this->assertEquals('c', $join['rightAlias']);
        $this->assertEquals('comments', $join['rightTable']);
        $this->assertEquals('LEFT', $join['joinType']);
    }
}
