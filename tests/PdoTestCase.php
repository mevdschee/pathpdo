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
        if ($config === false) {
            throw new \RuntimeException("Failed to parse config file");
        }
        $username = $config['phpunit']['username'];
        $password = $config['phpunit']['password'];
        $database = $config['phpunit']['database'];
        $driver = $config['phpunit']['driver'];
        $address = $config['phpunit']['address'];
        $port = $config['phpunit']['port'];
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
        static::$pdo->rollback();
    }
}
