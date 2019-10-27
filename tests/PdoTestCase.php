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

    public function jsonSort(string $json, $sortLists=false)
    {
        $strsum = function ($s) { return array_sum(array_map('ord', str_split($s))); };
        $order = null;
        $order = function (&$json) use (&$order, $strsum, $sortLists) {    
            if ($sortLists && is_array($json)) {
                usort($json,function ($a,$b) use ($strsum) {
                    return $strsum(json_encode($a))<=>$strsum(json_encode($b));
                });
                foreach ($json as &$value) {
                    $order($value);
                }
            } elseif (is_object($json)) {
                $arr = (array) $json;
                uksort($arr,function ($a,$b) use ($arr, $strsum) {
                    return $strsum(json_encode([$a => $arr[$a]]))<=>$strsum(json_encode([$b => $arr[$b]]));
                });
                $json = (object) $arr;
                foreach ($json as &$value) {
                    $order($value);
                }
            }
        };
        $json = json_decode($json);
        $order($json);
        return json_encode($json);
    }
}
 