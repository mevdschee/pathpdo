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
        $username = (string)($phpunitConfig['username'] ?? '');
        $password = (string)($phpunitConfig['password'] ?? '');
        $database = (string)($phpunitConfig['database'] ?? '');
        $driver = (string)($phpunitConfig['driver'] ?? 'mysql');
        $address = (string)($phpunitConfig['address'] ?? 'localhost');
        $port = (string)($phpunitConfig['port'] ?? '');
        $class = static::$class;
        static::$pdo = $class::create($username, $password, $database, $driver, $address, $port);
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
