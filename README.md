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
