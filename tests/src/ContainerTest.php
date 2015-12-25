<?php
namespace OpenStackStorageTest;

class ContainerTest extends Base
{

    /**
     * @var string
     */
    protected $containerName = 'container-1';

    /**
     * @see \OpenStackStorage\Container::getName()
     * @see \OpenStackStorage\Container::getNbObjects()
     * @see \OpenStackStorage\Container::getSizeUsed()
     */
    public function testContainer()
    {
        $container = self::$connection->getContainer($this->containerName);

        $this->assertInstanceOf('\OpenStackStorage\Container', $container);
        $this->assertEquals($this->containerName, $container->getName());
        $this->assertEquals(2, $container->getNbObjects());

        $totalSize = 0;

        foreach (glob(sprintf('%s/../fixtures/%s/*', __DIR__, $this->containerName)) as $file) {
            $totalSize += filesize($file);
        }

        $this->assertEquals($container->getSizeUsed(), $totalSize);
    }

    /**
     * @see \OpenStackStorage\Container::isPublic()
     *
     * @depends           testContainer
     * @expectedException \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function testIsPublic()
    {
        $container = self::$connection->getContainer($this->containerName);
        $container->isPublic();
    }

    /**
     * @see \OpenStackStorage\Container::getPublicStreamingUri()
     *
     * @depends           testContainer
     * @expectedException \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function testGetPublicStreamingUri()
    {
        $container = self::$connection->getContainer($this->containerName);
        $container->getPublicStreamingUri();
    }

    /**
     * @see \OpenStackStorage\Container::getPublicSslUri()
     *
     * @depends           testContainer
     * @expectedException \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function testGetPublicSslUri()
    {
        $container = self::$connection->getContainer($this->containerName);
        $container->getPublicSslUri();
    }

    /**
 * @see \OpenStackStorage\Container::updateMetadata()
 * @see \OpenStackStorage\Container::getMetadata()
 *
 * @depends testContainer
 */
    public function testMetadata()
    {
        $container = self::$connection->getContainer($this->containerName);
        $metaTest  = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $container->updateMetadata($metaTest);

        $metaRequest = $container->getMetadata();

        $this->assertInternalType('array', $metaRequest);
        $this->assertCount(2, $metaRequest);
        $this->assertContains('bar', $metaRequest);
        $this->assertContains('baz', $metaRequest);
        $this->assertArrayHasKey('foo', $metaRequest);
        $this->assertArrayHasKey('bar', $metaRequest);
    }

    /**
     * @see \OpenStackStorage\Connection::getContainersInfo()
     * @see \OpenStackStorage\Connection::getContainersList()
     *
     * @depends testContainer
     */
    public function testObjects()
    {
        $container = self::$connection->getContainer($this->containerName);

        $objects = $container->getObjectsInfo();
        $this->assertInternalType('array', $objects);
        $this->assertCount(2, $objects);

        $objects = $container->getObjectsList();

        $this->assertInternalType('array', $objects);
        $this->assertCount(2, $objects);
        $this->assertContains('Lenna.png', $objects);
        $this->assertContains('lorem.txt', $objects);
    }
}
