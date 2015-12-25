<?php
namespace OpenStackStorageTest;

use OpenStackStorage\Utils;

class UtilsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @see \OpenStackStorage\Utils::parseUrl()
     *
     * @expectedException        \OpenStackStorage\Exceptions\InvalidUrl
     * @expectedExceptionMessage The string must be a valid URL
     */
    public function testInvalidUrl()
    {
        Utils::parseUrl('Not a valid URL string');
    }

    /**
     * @see \OpenStackStorage\Utils::parseUrl()
     *
     * @expectedException        \OpenStackStorage\Exceptions\InvalidUrl
     * @expectedExceptionMessage Scheme must be one of http or https
     */
    public function testInvalidScheme()
    {
        Utils::parseUrl('file:///etc/passwd');
    }

    /**
     * @see \OpenStackStorage\Utils::parseUrl()
     */
    public function testParseUrl()
    {
        $info = Utils::parseUrl('http://example.org');

        $this->assertInternalType('array', $info);

        $this->assertArrayHasKey('scheme', $info);
        $this->assertArrayHasKey('host', $info);
        $this->assertArrayHasKey('port', $info);

        $this->assertEquals('http', $info['scheme']);
        $this->assertEquals('example.org', $info['host']);
        $this->assertEquals(80, $info['port']);
    }
}
