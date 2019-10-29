# path-pdo

This is the PHP (PDO) implementation of PathQL.

# PathQL

This is what PathQL does in one sentence:

> PathQL allows grouping and nesting of the results of a complex SQL query
> using JSON path notation in SQL column aliases

This is what PathQL does in one example:

    select 
        posts.id as "$.posts[].id", 
        comments.id as "$.posts[].comments[].id" 
    from 
        posts, 
        comments 
    where 
        post_id = posts.id and
        posts.id = 1 

Without PathQL the results would be (wrong):

    [
        {
          "$.posts[].id": 1,
          "$.posts[].comments[].id": 1
        },
        {
          "$.posts[].id": 1,
          "$.posts[].comments[].id": 2
        }
    ]

With PathQL the results will be (correct):

    {
        "posts": [
            {
                "id": 1,
                "comments": [
                    {
                        "id": 1
                    },
                    {
                        "id": 2
                    }
                ]
            }
        ]
    }

As you can see the rows are merged together into a tree.

## JSON path syntax

In JSON path a language is defined for reading data from a path in a JSON document.
We need a langauge to write data to a path in a JSON document and that is why
we only use a subset of the JSON path operators:

- "$" root element
- "." object child operator
- "[]" array element operator

Note that the brackets should always be empty as the index in the array is
determined by the path merging algorithm.

## Base path

The path "$[].posts.id" consists of two parts "$[].posts" (the base path) and 
it's last segment "id" (the property name). Only columns that have an alias
starting with a "$" are defining a new base path. Any other alias or column 
name is treated as a property name. 

The full path of a column can be constructed by combining the last specified
base path with the property name. The initial base path for every query is "$[]".

Thus queries without specified paths generate a simple object per result row:

    SELECT * FROM posts;

    [
        {
            "id": 1,
            "title": "Hello world!"
        },
        ...(more rows)...
    ]

Columns that follow a column with a specified path will inherit the base path:

    SELECT id as "$[].post.id", title FROM posts;

    [
        {
            "post": {
                "id": 1,
                "title": "Hello world!"
            }
        },
        ...(more rows)...
    ]

Note that duplicate and/or conflicting paths trigger an error message.

## Implementations

Currently PathQL is implemented in PHP and Python for MySQL and PostgreSQL.

- [path-pdo](https://github.com/mevdschee/path-pdo)
- [path-alchemy](https://github.com/mevdschee/path-alchemy)

The PHP version depends on PDO, while the Python version depends on SqlAlchemy.

## HTTP API

As we have seen in the implementations, PathQL can be added seemlessly to a DBAL.

You can also "speak" PathQL over HTTP(S). Here we specify how this should be done:

    POST /pathql HTTP/1.1
    Content-Type: application/json

    {"query":"select posts.id as \"$.posts[].id\", comments.id as \"$.posts[].comments[].id\" 
    from posts, comments where post_id = posts.id and posts.id = :id;","params":{"id":1}}

The response will be:

    Content-Type: application/json
    {"posts":[{"id":1,"comments":[{"id":1},{"id":2}]}]}

As you can see you should make an endpoint named "pathql" that accepts and returns JSON.
The request should be sent in the POST body as a JSON object with properties "query" and
"params", where "query" must be a (SQL) string with named parameters and "params" must be
the set of named parameters that should be applied.
