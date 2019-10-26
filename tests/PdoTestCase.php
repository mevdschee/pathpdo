<?php

namespace Tqdev\PdoJson\Tests;

use PHPUnit\Framework\TestCase;

class PdoTestCase extends TestCase
{
    static $pdo;
    static $class = '\Tqdev\PdoJson\SmartPdo';

    protected $db;

    public static function setUpBeforeClass(): void
    {
        $config = parse_ini_file("test_config.ini", true);
        $dsn = $config['phpunit']['dsn'];
        $username = $config['phpunit']['username'];
        $password = $config['phpunit']['password'];
        static::$pdo = new static::$class($dsn, $username, $password);
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
