<?php

// PHPUnit bootstrap. Loads the composer autoloader and seeds the test database
// with a self-contained fixture so the suite does not depend on whatever data
// happens to already be present.

require __DIR__ . '/../vendor/autoload.php';

(function (): void {
    $configPath = __DIR__ . '/../test_config.ini';
    $config = parse_ini_file($configPath, true);
    if ($config === false || !isset($config['phpunit']) || !is_array($config['phpunit'])) {
        fwrite(STDERR, "bootstrap: could not parse $configPath; skipping fixture load\n");
        return;
    }
    $c = $config['phpunit'];

    $driver = is_string($c['driver'] ?? null) ? $c['driver'] : 'mysql';
    $username = is_string($c['username'] ?? null) ? $c['username'] : '';
    $password = is_string($c['password'] ?? null) ? $c['password'] : '';
    $database = is_string($c['database'] ?? null) ? $c['database'] : '';
    $address = is_string($c['address'] ?? null) ? $c['address'] : 'localhost';
    $port = is_string($c['port'] ?? null) ? $c['port'] : '';

    switch ($driver) {
        case 'mysql':
            $port = $port ?: '3306';
            $dsn = "mysql:host=$address;port=$port;dbname=$database;charset=utf8mb4";
            break;
        case 'pgsql':
            $port = $port ?: '5432';
            $dsn = "pgsql:host=$address port=$port dbname=$database options='--client_encoding=UTF8'";
            break;
        default:
            fwrite(STDERR, "bootstrap: unsupported driver '$driver'; skipping fixture load\n");
            return;
    }

    $fixture = __DIR__ . "/fixtures/blog_{$driver}.sql";
    if (!file_exists($fixture)) {
        fwrite(STDERR, "bootstrap: no fixture for driver '$driver' at $fixture; skipping\n");
        return;
    }

    $sql = file_get_contents($fixture);
    if ($sql === false) {
        fwrite(STDERR, "bootstrap: could not read fixture $fixture; skipping\n");
        return;
    }

    try {
        $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "bootstrap: fixture load failed: " . $e->getMessage() . "\n");
    }
})();

/**
 * Split a SQL script into individual statements, ignoring line comments.
 *
 * @return array<int,string>
 */
function splitStatements(string $sql): array
{
    $lines = preg_split('/\r?\n/', $sql) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $clean[] = $line;
    }
    $statements = [];
    foreach (explode(';', implode("\n", $clean)) as $statement) {
        if (trim($statement) !== '') {
            $statements[] = $statement;
        }
    }
    return $statements;
}
