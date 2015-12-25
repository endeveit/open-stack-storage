<?php
namespace OpenStackStorageTest;

use OpenStackStorage\Authentication;

class AuthenticationTest extends Base
{

    public function testAuthenticate()
    {
        $auth = new Authentication(
            self::$config['username'],
            self::$config['apiKey'],
            self::$config['options']['authurl'],
            'PHPUnit',
            self::$config['timeout']
        );

        $info = $auth->authenticate();

        $this->assertInternalType('array', $info);
        $this->assertCount(3, $info);
    }
}
