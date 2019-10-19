<?php

namespace Tqdev\PdoJson\Tests;

class PathPdoTest extends PdoTestCase
{
    static $class = '\Tqdev\PdoJson\PathPdo';

    /**
     * @dataProvider qDataProvider
     */
    public function testQ($a, $b, $expected)
    {
        $this->assertSame($expected, json_encode($this->db->q($a, $b)));
    }

    public function qDataProvider()
    {
        return [
            'single record' => ['select id, content from posts where id=?', [1], '[{"id":1,"content":"blog started"}]'],
            'two records' => ['select id from posts where id<=2', [], '[{"id":1},{"id":2}]'],
            'two records named' => ['select id from posts where id<=:two and id>=:one', ['one' => 1, 'two' => 2], '[{"id":1},{"id":2}]'],
            'posts with comments' => [
                'select posts.id as "$.posts[].id", comments.id as "$.posts[].comments[].id" from posts left join comments on post_id = posts.id where posts.id<=2', [],
                '{"posts":[{"id":1,"comments":[{"id":1},{"id":2}]},{"id":2,"comments":[{"id":3},{"id":4},{"id":5},{"id":6}]}]}'
            ],
            'comments with post' => [
                'select posts.id as "$.comments[].post.id", comments.id as "$.comments[].id" from posts left join comments on post_id = posts.id where posts.id<=2', [],
                '{"comments":[{"id":1,"post":{"id":1}},{"id":2,"post":{"id":1}},{"id":3,"post":{"id":2}},{"id":4,"post":{"id":2}},{"id":5,"post":{"id":2}},{"id":6,"post":{"id":2}}]}'
            ],
            'count posts' => ['select count(*) as "posts" from posts', [], '[{"posts":12}]'],
            'count posts object' => ['select count(*) as "$.posts" from posts', [], '{"posts":12}'],
            'count posts with root' => ['select count(*) as "$.statistics.posts" from posts', [], '{"statistics":{"posts":12}}'],
            'count posts and comments' => [
                'select (select count(*) from posts) as "$.stats.posts", (select count(*) from comments) as "$.stats.comments"', [],
                '{"stats":{"posts":12,"comments":6}}'
            ],
        ];
    }
}
