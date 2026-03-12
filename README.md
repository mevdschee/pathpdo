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
