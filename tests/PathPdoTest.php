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
        $this->assertJsonStringEqualsJsonString($expected, json_encode($this->db->q($a, $b)));
    }

    public function qDataProvider()
    {
        return [
            'single record no path' => ['select id, content from posts where id=?', [1], '[{"id":1,"content":"blog started"}]'],
            'two records no path' => ['select id from posts where id<=2 order by id', [], '[{"id":1},{"id":2}]'],
            'two records named no path' => ['select id from posts where id<=:two and id>=:one order by id', ['one' => 1, 'two' => 2], '[{"id":1},{"id":2}]'],
            'two tables no path' => [
                'select posts.id, comments.id from posts left join comments on post_id = posts.id where posts.id=1', [],
                '[{"posts":{"id":1},"comments":{"id":1}},{"posts":{"id":1},"comments":{"id":2}}]'
            ],
            'two tables with path' => [
                'select posts.id as "$[].posts.id", comments.id as "$[].comments.id" from posts left join comments on post_id = posts.id where posts.id=1', [],
                '[{"posts":{"id":1},"comments":{"id":1}},{"posts":{"id":1},"comments":{"id":2}}]'
            ],
            'posts with comments properly nested' => [
                'select posts.id as "$.posts[].id", comments.id as "$.posts[].comments[].id" from posts left join comments on post_id = posts.id where posts.id<=2 order by posts.id, comments.id', [],
                '{"posts":[{"id":1,"comments":[{"id":1},{"id":2}]},{"id":2,"comments":[{"id":3},{"id":4},{"id":5},{"id":6}]}]}'
            ],
            'comments with post properly nested' => [
                'select posts.id as "$.comments[].post.id", comments.id as "$.comments[].id" from posts left join comments on post_id = posts.id where posts.id<=2 order by comments.id, posts.id', [],
                '{"comments":[{"id":1,"post":{"id":1}},{"id":2,"post":{"id":1}},{"id":3,"post":{"id":2}},{"id":4,"post":{"id":2}},{"id":5,"post":{"id":2}},{"id":6,"post":{"id":2}}]}'
            ],
            'count posts with simple alias' => ['select count(*) as "posts" from posts', [], '[{"posts":12}]'],
            'count posts with path' => ['select count(*) as "$[].posts" from posts', [], '[{"posts":12}]'],
            'count posts as object with path' => ['select count(*) as "$.posts" from posts', [], '{"posts":12}'],
            'count posts grouped no path' => [
                'select categories.name, count(posts.id) as "post_count" from posts, categories where posts.category_id = categories.id group by categories.name order by categories.name', [],
                '[{"name":"announcement","post_count":11},{"name":"article","post_count":1}]'
            ],
            'count posts with added root set in path' => ['select count(*) as "$.statistics.posts" from posts', [], '{"statistics":{"posts":12}}'],
            'count posts and comments as object with path' => [
                'select (select count(*) from posts) as "$.stats.posts", (select count(*) from comments) as "$.stats.comments"', [],
                '{"stats":{"posts":12,"comments":6}}'
            ],
        ];
    }
}
