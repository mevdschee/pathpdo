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
        $args = [getenv('PDO_DRIVER_USERNAME'), getenv('PDO_DRIVER_PASSWORD'), getenv('PDO_DRIVER_DATABASE')];
        static::$pdo = forward_static_call_array(static::$class . '::create', $args);
        static::$pdo->beginTransaction();
    }

    public function setUp(): void
    {
        $this->db = static::$pdo;
    }

    public function tearDown(): void
    {
        $this->db = static::$pdo;
    }

    public static function tearDownAfterClass(): void
    {
        static::$pdo->rollback();
    }
}
