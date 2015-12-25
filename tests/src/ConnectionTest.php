<?php
namespace OpenStackStorageTest;

class ConnectionTest extends Base
{

    /**
     * @var string
     */
    protected $containerTempName = 'container-temp';

    /**
     * @see \OpenStackStorage\Connection::getAuthToken()
     */
    public function testGetAuthToken()
    {
        $token = self::$connection->getAuthToken();

        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('AUTH_', $token);
    }

    /**
     * @see \OpenStackStorage\Connection::getAccountInfo()
     */
    public function testGetAccountInfo()
    {
        $info = self::$connection->getAccountInfo();

        $this->assertInternalType('array', $info);
        $this->assertCount(3, $info);
    }

    /**
     * @see \OpenStackStorage\Connection::createContainer()
     */
    public function testCreateContainer()
    {
        $container = self::$connection->createContainer($this->containerTempName);

        $this->assertInstanceOf('\OpenStackStorage\Container', $container);
    }

    /**
     * @see \OpenStackStorage\Connection::createContainer()
     *
     * @depends           testCreateContainer
     * @expectedException \OpenStackStorage\Exceptions\ContainerExists
     */
    public function testCreateExistContainer()
    {
        self::$connection->createContainer($this->containerTempName, true);
    }

    /**
     * @see \OpenStackStorage\Connection::createContainer()
     *
     * @expectedException \OpenStackStorage\Exceptions\InvalidContainerName
     */
    public function testCreateContainerInvalidContainerName()
    {
        self::$connection->createContainer(str_repeat('1', CONTAINER_NAME_LIMIT + 1));
    }

    /**
     * @see \OpenStackStorage\Connection::deleteContainer()
     *
     * @depends testCreateContainer
     */
    public function testDeleteContainer()
    {
        self::$connection->deleteContainer($this->containerTempName);
    }

    /**
     * @see \OpenStackStorage\Connection::deleteContainer()
     *
     * @depends           testDeleteContainer
     * @expectedException \OpenStackStorage\Exceptions\NoSuchContainer
     */
    public function testDeleteNotExistContainer()
    {
        self::$connection->deleteContainer($this->containerTempName);
    }

    /**
     * @see \OpenStackStorage\Connection::deleteContainer()
     *
     * @depends           testDeleteContainer
     * @expectedException \OpenStackStorage\Exceptions\ContainerNotEmpty
     */
    public function testDeleteNotEmptyContainer()
    {
        self::$connection->deleteContainer('container-1');
    }

    /**
     * @see \OpenStackStorage\Connection::getContainersInfo()
     * @see \OpenStackStorage\Connection::getContainersList()
     *
     * @depends testDeleteContainer
     */
    public function testContainers()
    {
        $containers = self::$connection->getContainersInfo();

        $this->assertInternalType('array', $containers);
        $this->assertCount(2, $containers);

        $containers = self::$connection->getContainersList();

        $this->assertInternalType('array', $containers);
        $this->assertCount(2, $containers);
        $this->assertContains('container-1', $containers);
        $this->assertContains('container-2', $containers);
    }

    /**
     * @see \OpenStackStorage\Utils::getPublicContainersList()
     *
     * @expectedException \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function testGetCdnEnabled()
    {
        self::$connection->getPublicContainersList();
    }
}
