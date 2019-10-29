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

## Auto mode

If no alias starting with a "$" is specified, then PathQL will work in "auto" mode.
Any query result originating from a single table will structured as 
a simple object per result row:

    SELECT * FROM posts;

    [
        {
            "id": 1,
            "title": "Hello world!"
        },
        ...(more rows)...
    ]

Any query result originating from multiple tables will have the results 
grouped by originating table for each result row.

    SELECT * FROM posts JOIN comments ON comments.post_id = posts.id;

    [
        {
            "posts": {
                "id": 1,
                "title": "Hello world!"
            },
            "comments": {
                "id": 2,
                "message": "great!"
            }
        },
        ...(more rows)...
    ]

Note that merging of rows does not happen in "auto" mode and that the
grouping is done on the originating table as reported by the driver.

## Implementations

Currently PathQL is implemented in PHP and Python for MySQL and PostgreSQL.

- [path-pdo](https://github.com/mevdschee/path-pdo)
- [path-alchemy](https://github.com/mevdschee/path-alchemy)

The PHP version depends on PDO, while the Python version depends on SqlAlchemy.