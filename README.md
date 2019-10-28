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

## Implementations

Currently PathQL is implemented in PHP and Python for MySQL and PostgreSQL.

- [path-pdo](https://github.com/mevdschee/path-pdo)
- [path-alchemy](https://github.com/mevdschee/path-alchemy)

The PHP version depends on PDO, while the Python version depends on SqlAlchemy.