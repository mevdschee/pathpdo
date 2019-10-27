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

    private function jsonOrderedCopy(&$json)
    {    
        if (is_array($json)) {
            usort($json,function ($a,$b) {
                $val = strlen(json_encode($a))<=>strlen(json_encode($b));
                if ($val==0) {
                    $val = json_encode($a)<=>json_encode($b);
                }
                return $val;
            });
            foreach ($json as &$value) {
                $this->jsonOrderedCopy($value);
            }
        } elseif (is_object($json)) {
            $arr = (array) $json;
            uksort($arr,function ($a,$b) use ($arr) {
                $val = strlen(json_encode([$a,$arr[$a]]))<=>strlen(json_encode([$b,$arr[$b]]));
                if ($val==0) {
                    $val = json_encode([$a,$arr[$a]])<=>json_encode([$b,$arr[$b]]);
                }
                return $val;
            });
            $json = (object) $arr;
            foreach ($json as &$value) {
                $this->jsonOrderedCopy($value);
            }
        }
    }

    public function jsonSort(string $json)
    {
        $json = json_decode($json);
        $this->jsonOrderedCopy($json);
        return json_encode($json);
    }
}
 