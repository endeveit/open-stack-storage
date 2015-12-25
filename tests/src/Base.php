<?php
namespace OpenStackStorageTest;

use OpenStackStorage\Connection;

class Base extends \PHPUnit_Framework_TestCase
{

    protected static $config;

    /**
     * @var \OpenStackStorage\Connection
     */
    protected static $connection;

    public static function setUpBeforeClass()
    {
        if (null === self::$config) {
            self::$config = [
                'username' => getenv('TEST_SW_USERNAME') ?: 'test:tester',
                'apiKey'   => getenv('TEST_SW_APIKEY') ?: 'testing',
                'options'  => [
                    'authurl' => getenv('TEST_SW_URL') ?: 'http://127.0.0.1:8080/auth/v1.0',
                ],
                'timeout'  => getenv('TEST_SW_TIMEOUT') ?: 10,
            ];
        }

        if (null === self::$connection) {
            self::$connection = new Connection(
                self::$config['username'],
                self::$config['apiKey'],
                self::$config['options'],
                self::$config['timeout']
            );
        }
    }
}
