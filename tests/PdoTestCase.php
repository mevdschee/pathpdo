<?php

namespace Tqdev\PdoJson\Tests;

use PHPUnit\Framework\TestCase;

class PdoTestCase extends TestCase
{
    static $db;
    static $class = '\Tqdev\PdoJson\SmartPdo';

    public static function setUpBeforeClass(): void
    {
        $args = [getenv('PDO_DRIVER_USERNAME'), getenv('PDO_DRIVER_PASSWORD'), getenv('PDO_DRIVER_DATABASE')];
        static::$db = forward_static_call_array(static::$class . '::create', $args);
        static::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        static::$db->rollback();
    }
}
