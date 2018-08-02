<?php
namespace OpenStackStorageTest;

class ObjectTest extends Base
{

    /**
     * @var string
     */
    protected $containerName = 'container-2';

    /**
     * @var string
     */
    protected $objName = 'ipsum.txt';

    /**
     * @see \OpenStackStorage\Obj::getName()
     * @see \OpenStackStorage\Obj::getContentType()
     * @see \OpenStackStorage\Obj::getSize()
     */
    public function testObject()
    {
        $object = self::$connection->getContainer($this->containerName)->getObject($this->objName);

        $this->assertInstanceOf('\OpenStackStorage\Obj', $object);
        $this->assertEquals($this->objName, $object->getName());
        $this->assertEquals('text/plain', $object->getContentType());
        $this->assertEquals(
            filesize(sprintf('%s/../fixtures/%s/%s', __DIR__, $this->containerName, $this->objName)),
            $object->getSize()
        );
    }

    /**
     * @see \OpenStackStorage\Container::updateMetadata()
     * @see \OpenStackStorage\Container::getMetadata()
     *
     * @depends testObject
     */
    public function testMetadata()
    {
        $object   = self::$connection->getContainer($this->containerName)->getObject($this->objName);
        $metaTest = array(
            'foo' => 'bar',
        );

        $object->setMetadata($metaTest);
        $object->syncMetadata();

        $metaRequest = self::$connection->getContainer($this->containerName)
            ->getObject($this->objName)
            ->getMetadata();

        $this->assertInternalType('array', $metaRequest);
        $this->assertCount(1, $metaRequest);
        $this->assertContains('bar', $metaRequest);
        $this->assertArrayHasKey('foo', $metaRequest);
    }
}
