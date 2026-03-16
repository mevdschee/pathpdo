<?php

namespace Tqdev\PdoJson\Tests;

use PHPUnit\Framework\TestCase;

class PdoTestCase extends TestCase
{
    /** @var \Tqdev\PdoJson\SmartPdo|null */
    static $pdo;
    /** @var class-string */
    static $class = '\Tqdev\PdoJson\SmartPdo';

    /** @var \Tqdev\PdoJson\SmartPdo|null */
    protected $db;

    public static function setUpBeforeClass(): void
    {
        $config = parse_ini_file("test_config.ini", true);
        if ($config === false || !isset($config['phpunit'])) {
            throw new \RuntimeException("Failed to parse config file");
        }
        $phpunitConfig = $config['phpunit'];
        if (!is_array($phpunitConfig)) {
            throw new \RuntimeException("Invalid config format");
        }
        $username = is_string($phpunitConfig['username'] ?? null) ? $phpunitConfig['username'] : '';
        $password = is_string($phpunitConfig['password'] ?? null) ? $phpunitConfig['password'] : '';
        $database = is_string($phpunitConfig['database'] ?? null) ? $phpunitConfig['database'] : '';
        $driver = is_string($phpunitConfig['driver'] ?? null) ? $phpunitConfig['driver'] : 'mysql';
        $address = is_string($phpunitConfig['address'] ?? null) ? $phpunitConfig['address'] : 'localhost';
        $port = is_string($phpunitConfig['port'] ?? null) ? $phpunitConfig['port'] : '';
        $class = static::$class;
        $pdo = $class::create($username, $password, $database, $driver, $address, $port);
        assert($pdo instanceof \Tqdev\PdoJson\SmartPdo);
        static::$pdo = $pdo;
        static::$pdo->beginTransaction();
    }

    public function setUp(): void
    {
        $this->db = static::$pdo;
    }

    public function tearDown(): void
    {
        //$this->db = static::$pdo;
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$pdo !== null) {
            static::$pdo->rollback();
        }
    }
}
