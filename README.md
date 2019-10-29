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

Results shown as pretty printed JSON:

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

while without PathQL results would have been:

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

Got it?

## JSON path syntax

In JSON path a language is defined for reading data from a path in a JSON document.
We need a langauge to write data to a path in a JSON document and that is why
we only use a subset of the JSON path operators:

- "$" root element
- "." object child operator
- "[]" array element operator

Note that the brackets should always be empty as the index in the array is
determined by the path merging algorithm.

## The base path

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