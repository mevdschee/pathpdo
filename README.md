# pathpdo

A PHP path engine library for PDO. Allows to query the database using PathQL
(see: [PathQL.org](https://pathql.org/)).

### Requirements

- PHP 8 with JSON
- PDO drivers

## Metadata Configuration

By default, PathPDO queries the database schema at runtime to determine foreign
key relationships for automatic path inference. For better performance, you can
cache this metadata in a file.

### Setting a Metadata File

```php
use Tqdev\PdoJson\Schema;

// Use a metadata file instead of querying the database
Schema::setMetadataFile('pathpdo.json');

// Or use PHP array format
Schema::setMetadataFile('pathpdo.php');

// Switch back to database-based metadata
Schema::setMetadataFile(null);
```

### Exporting Metadata

To create a metadata file from your current database:

```php
$db = PathPdo::create($username, $password, $database);
$schema = new Schema();

// Export as JSON (default)
$schema->exportMetadata($db, 'pathpdo.json');

// Export as PHP array
$schema->exportMetadata($db, 'pathpdo.php', 'php');
```

### Custom Metadata Cache

For advanced use cases, you can implement your own metadata caching (e.g.,
Redis, Memcached, database) using the `setMetaData()` and `getMetaData()`
methods:

```php
use Tqdev\PdoJson\Schema;

// Example: Caching metadata in Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Check if metadata is cached
if ($redis->exists('pathpdo:metadata')) {
    // Load from cache
    $json = $redis->get('pathpdo:metadata');
    Schema::setMetaData($json);
} else {
    // Generate from database
    $db = PathPdo::create($username, $password, $database);
    $schema = new Schema();
    $json = $schema->getMetaData($db);
    
    // Store in cache
    $redis->set('pathpdo:metadata', $json, 3600); // Cache for 1 hour
    Schema::setMetaData($json);
}

// Now PathPDO will use the cached metadata
```

The `setMetaData()` method accepts a JSON string in the same format as metadata
files, while `getMetaData()` returns the current metadata as JSON (from cache,
file, or database).

### Metadata File Format (JSON)

```json
{
    "foreign_keys": [
        {
            "from_table": "comments",
            "from_column": "post_id",
            "to_table": "posts",
            "to_column": "id"
        },
        {
            "from_table": "posts",
            "from_column": "category_id",
            "to_table": "categories",
            "to_column": "id"
        }
    ]
}
```

### Benefits

- **Performance**: Eliminates schema queries on every request
- **Portability**: Works even without direct access to information_schema
- **Version Control**: Track schema changes in your repository
- **Consistency**: Ensures the same schema interpretation across environments

## Using PathQL

### Basic Query

The `pathQuery()` method executes SQL queries and returns results in a
hierarchical structure based on table relationships:

```php
$db = PathPdo::create($username, $password, $database);

// Simple query
$results = $db->pathQuery('SELECT `id`,`name` FROM `users`');
// Returns: [{"id": 1, "name": "John"}, {"id": 2, "name": "Jane"}]

// With parameters (named/ordered)
$results = $db->pathQuery('SELECT * FROM users WHERE id = :id', ['id' => 1]);
$results = $db->pathQuery('SELECT * FROM users WHERE id = ?', [1]);
```

### Automatic Path Inference

When you do not pass any paths, PathPDO infers the structure from the JOINs in
the query and the foreign keys in the schema. Each table alias becomes a JSON
key, and the relationship direction decides whether it nests as an array or an
object:

```php
// One-to-many: comments has a foreign key to posts, so comments nest as an
// array under each post. The aliases (p, c) become the JSON keys.
$db->pathQuery(
    'SELECT p.id, c.id, c.message
     FROM posts p
     LEFT JOIN comments c ON c.post_id = p.id
     WHERE p.id <= 2 ORDER BY p.id, c.id'
);
// [
//   {"id":1,"c":[{"id":1,"message":"great!"},{"id":2,"message":"nice!"}]},
//   {"id":2,"c":[{"id":3,"message":"interesting"}, ...]}
// ]

// Many-to-one: posts has a foreign key to categories, so the category nests as
// a single object under each post.
$db->pathQuery(
    'SELECT p.id, p.content, cat.id, cat.name
     FROM posts p
     LEFT JOIN categories cat ON p.category_id = cat.id
     WHERE p.id = 1'
);
// [{"id":1,"content":"blog started","cat":{"id":1,"name":"announcement"}}]
```

How the cardinality is decided:

- **Foreign keys**: if the joined table has a foreign key to the root table, the
  join is one-to-many (array). If the root table has a foreign key to the joined
  table, it is many-to-one (object).
- **Join type**: without foreign-key information, a `LEFT JOIN` defaults to
  one-to-many (array).
- **Root**: a query that returns multiple rows defaults to an array at the root.

Foreign keys are read from the schema (see Metadata Configuration above), so for
inference to work the relationships must exist in the database or in a metadata
file.

### Specifying Paths with Array Parameter

You can specify paths for tables or aliases using the third parameter:

```php
// Map table aliases to their paths
$results = $db->pathQuery(
    'SELECT p.id, c.id, c.content 
     FROM posts p 
     LEFT JOIN comments c ON c.post_id = p.id 
     WHERE p.id = :id',
    ['id' => 1],  // Named query parameters
    [    // Path mapping
        'p' => '$',
        'c' => '$.comments[]'
    ]
);
// Returns: {"id": 1, "comments": [{"id": 1, "content": "..."}, {"id": 2, "content": "..."}]}
```

### Path Syntax

- `$` - Root object
- `$.property` - Nested property
- `$[]` - Array of objects
- `$.property[]` - Nested array
- `$.parent.child[]` - Deeply nested array

Hints are used exactly as written: `$.posts` is a single object and `$.posts[]`
is an array. PathPDO never adds an `[]` to a hint you provide, so include it
yourself when a table is one-to-many. Tables you do not hint are still nested
automatically from the foreign keys (see Automatic Path Inference above), so
hinting only the root is usually enough: `['posts' => '$.posts[]']` nests the
joined comments under each post without naming them.

### Examples

```php
// Single object result
$stats = $db->pathQuery(
    'SELECT COUNT(*) as posts FROM posts',
    [],
    ['posts' => '$.statistics']
);
// Returns: {"statistics": {"posts": 12}}

// Nested arrays
$results = $db->pathQuery(
    'SELECT u.name, p.title, c.content 
     FROM users u
     LEFT JOIN posts p ON p.user_id = u.id
     LEFT JOIN comments c ON c.post_id = p.id
     WHERE u.id = ?',
    [1], // Ordered query parameters
    [
        'u' => '$',
        'p' => '$.posts[]',
        'c' => '$.posts[].comments[]'
    ]
);
// Returns: {
//   "name": "John",
//   "posts": [
//     {"title": "First Post", "comments": [{"content": "Nice!"}, ...]},
//     ...
//   ]
// }
```
