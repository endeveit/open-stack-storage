<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage;

/**
 * \OpenStackStorage\Container object and \OpenStackStorage\Object
 * instance factory.
 *
 * If your account has the feature enabled, containers can be publically
 * shared over a global content delivery network.
 */
class Container
{

    /**
     * Connection object.
     *
     * @var \OpenStackStorage\Connection
     */
    protected $connection = null;

    /**
     * Container name.
     *
     * @var string
     */
    protected $name = null;

    /**
     * Number of objects in container.
     *
     * @var integer
     */
    protected $nbObjects = 0;

    /**
     * The sum of the sizes of all objects in this container (in bytes).
     *
     * @var integer
     */
    protected $sizeUsed = 0;

    /**
     * Metadata.
     *
     * @var array
     */
    protected $metadata = array();

    /**
     * URI for this container, if it is publically accessible via the CDN.
     *
     * @var string
     */
    protected $cdnUri = null;

    /**
     * SSL URI for this container, if it is publically accessible via the CDN.
     *
     * @var string
     */
    protected $cdnSslUri = null;

    /**
     * Streaming URI for this container, if it is publically accessible
     * via the CDN.
     *
     * @var string
     */
    protected $cdnStreamingUri = null;

    /**
     * The time-to-live of the CDN's public cache of this container.
     *
     * @var integer
     */
    protected $cdnTtl = null;

    /**
     * Retention of the logs in the container.
     *
     * @var boolean
     */
    protected $cdnLogRetention = false;

    /**
     * List of parameters that are allowed to be used in the GET-requests to
     * fetch
     * information about the objects in this container:
     *  — limit      For an integer value n, limits the number of results to at
     *               most n values.
     *  — marker     Given a string value x, return object names greater in
     *               value than the specified marker.
     *  — end_marker Given a string value x, return object names less in
     *               value than the specified marker.
     *  — prefix     For a string value x, causes the results to be limited to
     *               object names beginning with the substring x.
     *  — format     Response format (json, xml, plain).
     *  — delimiter  For a character c, return all the object names nested in
     *               the container (without the need for the directory marker
     *               objects).
     *
     * @link http://docs.openstack.org/api/openstack-object-storage/1.0/content/list-objects.html
     * @var array
     */
    protected static $allowedParameters = array(
        'limit',
        'marker',
        'end_marker',
        'prefix',
        'format',
        'delimiter',
    );

    /**
     * Local cache of requests to fetch list of objects in this container.
     *
     * @var array
     */
    protected static $listObjectsCache = array();

    /**
     * The class constructor.
     *
     * Containers will rarely if ever need to be instantiated directly by
     * the user.
     *
     * Instead, use the \OpenStackStorage\OpenStackStorage\Connection
     * object methods:
     * <code>
     * $connection->createContainer('test');
     * $connection->getContainer('test');
     * $connection->getContainers();
     * </code>
     *
     * @param \OpenStackStorage\Connection $connection
     * @param string                       $name
     * @param integer                      $nbObjects
     * @param integer                      $sizeUsed
     * @param array                        $metadata
     */
    public function __construct(Connection $connection, $name, $nbObjects = 0, $sizeUsed = 0, array $metadata = array())
    {
        $this->connection = $connection;
        $this->name       = $name;

        if ($nbObjects > 0) {
            $this->nbObjects = intval($nbObjects);
        }

        if ($sizeUsed > 0) {
            $this->sizeUsed = intval($sizeUsed);
        }

        if (!empty($metadata)) {
            $this->metadata = $metadata;
        }

        // Fetch the CDN data from the CDN service
        if ($connection->getCdnEnabled()) {
            $response = $connection->makeCdnRequest(Client::HEAD, array($name));

            $this->cdnUri          = $response['headers']['x-cdn-uri'];
            $this->cdnTtl          = $response['headers']['x-ttl'];
            $this->cdnSslUri       = $response['headers']['x-cdn-ssl-uri'];
            $this->cdnStreamingUri = $response['headers']['x-cdn-streaming-uri'];

            $logRetention = $response['headers']['x-log-retention'];
            if ($logRetention && (0 === strcasecmp($logRetention, 'true'))) {
                $this->cdnLogRetention = true;
            }
        }
    }

    /**
     * Return the value of the $connection property.
     *
     * @return \OpenStackStorage\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Return the value of the $name property.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return the value of the $metadata property.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Updates container metadata.
     *
     * Example:
     * <code>
     * $container->updateMetadata(array(
     *     'X-Container-Meta-Foo' => 'bar',
     * ));
     * </code>
     *
     * @param array $metadata
     */
    public function updateMetadata(array $metadata)
    {
        $this->connection->makeRequest(Client::POST, array($this->name), $metadata);
    }

    /**
     * Enable static web for this container.
     *
     * Example:
     * <code>
     * $container->enableStaticWeb('index.html', true, 'error.html', 'style.css');
     * </code>
     *
     * @link http://docs.rackspace.com/files/api/v1/cf-devguide/content/StaticWeb.html
     * @param string  $index
     * @param boolean $enableListings
     * @param string  $error
     * @param string  $listingsCss
     */
    public function enableStaticWeb($index = null, $enableListings = null, $error = null, $listingsCss = null)
    {
        $metadata = array(
            'X-Container-Meta-Web-Index'        => '',
            'X-Container-Meta-Web-Listings'     => '',
            'X-Container-Meta-Web-Error'        => '',
            'X-Container-Meta-Web-Listings-CSS' => '',
        );

        if (null !== $index) {
            $metadata['X-Container-Meta-Web-Index'] = strval($index);
        }

        if (null !== $enableListings && is_bool($enableListings)) {
            $metadata['X-Container-Meta-Web-Listings'] = $enableListings ? 'True' : 'False';
        }

        if (null !== $error) {
            $metadata['X-Container-Meta-Web-Error'] = strval($error);
        }

        if (null !== $listingsCss) {
            $metadata['X-Container-Meta-Listings-CSS'] = strval($listingsCss);
        }

        $this->updateMetadata($metadata);
    }

    /**
     * Disable static web for this container.
     */
    public function disableStaticWeb()
    {
        $this->enableStaticWeb();
    }

    /**
     * Enable object versioning on this container.
     *
     * @link http://docs.rackspace.com/files/api/v1/cf-devguide/content/Object_Versioning-e1e3230.html
     * @param string $containerName The container where versions will be stored
     */
    public function enableObjectVersioning($containerName)
    {
        $this->updateMetadata(array('X-Versions-Location' => strval($containerName)));
    }

    /**
     * Disable object versioning on this container.
     */
    public function disableObjectVersioning()
    {
        $this->updateMetadata(array('X-Versions-Location' => ''));
    }

    /**
     * Either publishes the current container to the CDN or updates its
     * CDN attributes.
     * Requires CDN be enabled on the account.
     *
     * @param  integer                                    $ttl cache duration in seconds of the CDN server
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function makePublic($ttl = 86400)
    {
        if (!$this->connection->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        if ($this->cdnUri) {
            $requestMethod = Client::POST;
        } else {
            $requestMethod = Client::PUT;
        }

        $response = $this->connection->makeCdnRequest(
            $requestMethod,
            array($this->name),
            array(
                'X-TTL'          => strval($ttl),
                'X-CDN-Enabled'  => 'True',
                'Content-Length' => 0,
            )
        );

        $this->cdnTtl    = $ttl;
        $this->cdnUri    = $response['headers']['x-cdn-uri'];
        $this->cdnSslUri = $response['headers']['x-cdn-ssl-uri'];
    }

    /**
     * Disables CDN access to this container.
     * It may continue to be available until its TTL expires.
     *
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function makePrivate()
    {
        if (!$this->connection->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        $this->cdnUri = null;
        $this->connection->makeCdnRequest(
            Client::POST,
            array($this->name),
            array('X-CDN-Enabled' => 'False')
        );
    }

    /**
     * Purge Edge cache for all object inside of this container.
     * You will be notified by email if one is provided when the
     * job completes.
     *
     * <code>
     * $container1->purgeFromCdn();
     * $container2->purgeFromCdn('user@example.com');
     * $container3->purgeFromCdn('user@example.com,user@example.org);
     * </code>
     *
     * @param  string                                     $email
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function purgeFromCdn($email = null)
    {
        if (!$this->connection->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        $headers = array();
        if (null !== $email) {
            $headers['X-Purge-Email'] = $email;
        }

        $this->connection->makeCdnRequest(Client::DELETE, array($this->name), $headers);
    }

    /**
     * Enable CDN log retention on the container. If enabled logs will be
     * periodically (at unpredictable intervals) compressed and uploaded to
     * a ".CDN_ACCESS_LOGS" container in the form of
     * "container_name/YYYY/MM/DD/HH/XXXX.gz". Requires CDN be enabled on
     * the account.
     *
     * @param  boolean                                    $logRetention
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function logRetention($logRetention = false)
    {
        if (!$this->connection->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        $logRetention = (boolean) $logRetention;
        $this->connection->makeCdnRequest(
            Client::POST,
            array($this->name),
            array('X-Log-Retention' => $logRetention ? 'True' : 'False')
        );

        $this->cdnLogRetention = $logRetention;
    }

    /**
     * Return a boolean indicating whether or not this container is
     * publically accessible via the CDN.
     *
     * Example:
     * <code>
     * $container->isPublic(); // false
     * $container->makePublic();
     * $container->isPublic(); // true
     * </code>
     *
     * @return boolean
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function isPublic()
    {
        if (!$this->connection->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        return null !== $this->cdnUri;
    }

    /**
     * Return the URI for this container, if it is publically
     * accessible via the CDN.
     *
     * Example:
     * <code>
     * echo $container->getPublicUri();
     * // Outputs "http://c00061.cdn.cloudfiles.rackspacecloud.com"
     * </code>
     *
     * @return string
     * @throws \OpenStackStorage\Exceptions\ContainerNotPublic
     */
    public function getPublicUri()
    {
        if (!$this->isPublic()) {
            throw new Exceptions\ContainerNotPublic();
        }

        return $this->cdnUri;
    }

    /**
     * Return the SSL URI for this container, if it is publically
     * accessible via the CDN.
     *
     * Example:
     * <code>
     * echo $container->getPublicSslUri();
     * // Outputs "https://c61.ssl.cf0.rackcdn.com"
     * </code>
     *
     * @return string
     * @throws \OpenStackStorage\Exceptions\ContainerNotPublic
     */
    public function getPublicSslUri()
    {
        if (!$this->isPublic()) {
            throw new Exceptions\ContainerNotPublic();
        }

        return $this->cdnSslUri;
    }

    /**
     * Return the Streaming URI for this container, if it is publically
     * accessible via the CDN.
     *
     * Example:
     * <code>
     * echo $container->getPublicStreamingUri();
     * // Outputs "https://c61.stream.rackcdn.com"
     * </code>
     *
     * @return string
     * @throws \OpenStackStorage\Exceptions\ContainerNotPublic
     */
    public function getPublicStreamingUri()
    {
        if (!$this->isPublic()) {
            throw new Exceptions\ContainerNotPublic();
        }

        return $this->cdnStreamingUri;
    }

    /**
     * Return an \OpenStackStorage\Object instance, creating it if necessary.
     *
     * When passed the name of an existing object, this method will
     * return an instance of that object, otherwise it will create a
     * new one
     *
     * @param  string                   $name
     * @return \OpenStackStorage\Object
     */
    public function createObject($name)
    {
        return new Object($this, $name);
    }

    /**
     * Return an \OpenStackStorage\Object instance for an existing storage object.
     *
     * @param  string                                    $name
     * @return \OpenStackStorage\Object
     * @throws \OpenStackStorage\Exceptions\NoSuchObject
     */
    public function getObject($name)
    {
        return new Object($this, $name, true);
    }

    /**
     * Permanently remove a storage object.
     *
     * @param string|\OpenStackStorage\Object $name
     */
    public function deleteObject($name)
    {
        if (is_object($name) && $name instanceof Object) {
            $name = $name->getName();
        }

        $this->connection->makeRequest(Client::DELETE, array($this->name, $name));
    }

    /**
     * Return array with objects of container.
     *
     * @see \OpenStackStorage\Container::$allowedParameters
     * @param  array                      $parameters
     * @return \OpenStackStorage\Object[]
     */
    public function getObjects(array $parameters = array())
    {
        $objects = array();

        foreach ($this->getObjectsInfo($parameters) as $record) {
            $objects[] = new Object($this, null, false, $record);
        }

        return $objects;
    }

    /**
     * Return information about objects in container.
     *
     * @see \OpenStackStorage\Container::$allowedParameters
     * @param  array $parameters
     * @return array
     */
    public function getObjectsInfo(array $parameters = array())
    {
        $parameters['format'] = 'json';

        return $this->getObjectsRawData($parameters);
    }

    /**
     * Return names of objects in container.
     *
     * @see \OpenStackStorage\Container::$allowedParameters
     * @param  array $parameters
     * @return array
     */
    public function getObjectsList(array $parameters = array())
    {
        $parameters['format'] = 'plain';

        return array_filter(explode("\n", trim($this->getObjectsRawData($parameters))));
    }

    /**
     * Return a raw response string with information about container objects.
     *
     * @see \OpenStackStorage\Container::$allowedParameters
     * @param  array  $parameters
     * @return string
     */
    protected function getObjectsRawData(array $parameters = array())
    {
        $cacheKey = md5(json_encode(array($this->getName(), $parameters)));

        if (!array_key_exists($cacheKey, self::$listObjectsCache)) {
            $tmp = array();

            foreach ($parameters as $k => $v) {
                if (in_array($k, self::$allowedParameters)) {
                    $tmp[$k] = $v;
                }
            }

            $response = $this->connection->makeRequest(Client::GET, array($this->name), array(), $tmp);
            self::$listObjectsCache[$cacheKey] = $response['body'];
        }

        return self::$listObjectsCache[$cacheKey];
    }

}
