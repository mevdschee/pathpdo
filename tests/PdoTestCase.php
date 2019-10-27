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
        $username = $config['phpunit']['username'];
        $password = $config['phpunit']['password'];
        $database = $config['phpunit']['database'];
        $driver = $config['phpunit']['driver'];
        $address = $config['phpunit']['address'];
        $port = $config['phpunit']['port']+0;
        static::$pdo = static::$class::create($username, $password, $database, $driver, $address, $port);
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

    public function jsonSort(string $json, bool $sortArrays=false)
    {
        $order = null;
        $order = function ($json) use (&$order, $sortArrays) { 
            foreach ($json as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    if (is_array($json)) {
                        $json[$key] = $order($value);
                    } else {
                        $json->$key = $order($value);
                    }
                }
            }   
            if ($sortArrays && is_array($json)) {
                usort($json,function ($a,$b) {
                    return json_encode($a)<=>json_encode($b);
                });
            } elseif (is_object($json)) {
                $arr = (array) $json;
                uksort($arr,function ($a,$b) use ($arr) {
                    return json_encode([$a => $arr[$a]])<=>json_encode([$b => $arr[$b]]);
                });
                $json = (object) $arr;
            }
            return $json;
        };
        return json_encode($order(json_decode($json)));
    }
}
 