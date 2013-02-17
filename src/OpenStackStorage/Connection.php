<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage;

define('CONTAINER_NAME_LIMIT', 256);
define('OBJECT_NAME_LIMIT',    1024);
define('META_NAME_LIMIT',      128);
define('META_VALUE_LIMIT',     256);

/**
 * Manages the connection to the storage system and serves as a factory
 * for \OpenStackStorage\Container instances.
 */
class Connection
{

    /**
     * Use Rackspace servicenet to access Cloud Files.
     *
     * @var boolean
     */
    protected $useServicenet = false;

    /**
     * User-Agent for request.
     *
     * @var string
     */
    protected $userAgent = 'PHP OpenStackStorage';

    /**
     * Request timeout.
     *
     * @var integer
     */
    protected $timeout = 5;

    /**
     * Authentication object.
     *
     * @var \OpenStackStorage\Authentication
     */
    protected $auth = null;

    /**
     * Authentication has already been processed.
     *
     * @var boolean
     */
    protected $isAuthenticated = false;

    /**
     * Authentication token.
     *
     * @var string
     */
    protected $authToken = null;

    /**
     * Array with information about the connection URI.
     *
     * @var array
     */
    protected $connectionUrlInfo = null;

    /**
     * HTTP-client to work with storage.
     *
     * @var \OpenStackStorage\Client
     */
    protected $client = null;

    /**
     * CDN connection URL.
     *
     * @var string
     */
    protected $cdnUrl = null;

    /**
     * Is the access via CDN enabled.
     *
     * @var boolean
     */
    protected $cdnEnabled = false;

    /**
     * HTTP-client to work with storage.
     *
     * @var \OpenStackStorage\Client
     */
    protected $cdnClient = null;

    /**
     * List of parameters that are allowed to be used in the GET-requests to
     * fetch information about the containers:
     *  — limit      For an integer value n, limits the number of results
     *               to n values.
     *  — marker     Given a string value x, return container names greater
     *               in value than the specified marker.
     *  — end_marker Given a string value x, return container names less
     *               in value than the specified marker.
     *  — format     Response format (json, xml, plain).
     *
     * @link http://docs.openstack.org/api/openstack-object-storage/1.0/content/s_listcontainers.html
     * @var array
     */
    protected static $allowedParameters = array(
        'limit',
        'marker',
        'end_marker',
        'format',
    );

    /**
     * Local cache of requests to fetch list of containers.
     *
     * @var array
     */
    protected static $listContainersCache = array();

    /**
     * The class constructor.
     *
     * @param  string                    $username
     * @param  string                    $apiKey
     * @param  array                     $options
     * @param  integer                   $timeout
     * @throws \InvalidArgumentException
     */
    public function __construct($username, $apiKey, $options = array(), $timeout = 5)
    {
        $this->timeout = intval($timeout);

        // If the environement variable RACKSPACE_SERVICENET is set (to
        // anything) it will automatically set $useServicenet=true
        if (array_key_exists('servicenet', $options)) {
            $this->useServicenet = (boolean) $options['servicenet'];
        } elseif (array_key_exists('RACKSPACE_SERVICENET', $_ENV)) {
            $this->useServicenet = true;
        }

        if (!empty($options['useragent'])) {
            $this->userAgent = strval($options['useragent']);
        }

        // Authentication
        if (empty($options['authurl'])) {
            throw new \InvalidArgumentException(
                'Incorrect or invalid arguments supplied'
            );
        }

        $this->auth = new Authentication($username, $apiKey, $options['authurl'], $this->userAgent, $timeout);
    }

    /**
     * Return the value of the $authToken property.
     *
     * @return string
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * Return the value of the $cdnEnabled property.
     *
     * @return boolean
     */
    public function getCdnEnabled()
    {
        return $this->cdnEnabled;
    }

    /**
     * Return the value of the $connection property.
     *
     * @return \OpenStackStorage\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Return the value of the $timeout property.
     *
     * @return integer
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Return the value of the $userAgent property.
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Performs an http request to the storage.
     *
     * @param string $method name of the method (i.e. GET, PUT, POST, etc)
     * @param array  $path   list of tokens that will be added to connection
     *                           URI string
     * @param array $headers    additional headers
     * @param array $parameters additional parameters that will be added to the
     *                           query string
     * @return array
     */
    public function makeRequest($method, array $path = array(), array $headers = array(), $parameters = array())
    {
        $this->authenticate();

        return $this->makeRealRequest($this->client, $method, $this->getPathFromArray($path), $parameters, $headers);
    }

    /**
     * Performs an http request to the CDN.
     *
     * @param string $method name of the method (i.e. GET, PUT, POST, etc)
     * @param array  $path   list of tokens that will be added to connection
     *                        URI string
     * @param  array                                      $headers additional headers
     * @return array
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function makeCdnRequest($method, array $path = array(), array $headers = array())
    {
        $this->authenticate();

        if (!$this->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        return $this->makeRealRequest($this->cdnClient, $method, $this->getPathFromArray($path), $headers);
    }

    /**
     * Return array with number of containers, total bytes in the account and
     * account metadata.
     *
     * @return array
     */
    public function getAccountInfo()
    {
        $response     = $this->makeRequest(Client::HEAD);
        $nbContainers = 0;
        $totalSize    = 0;
        $metadata     = array();

        foreach ($response['headers'] as $name => $value) {
            $name = strtolower($name);

            if (0 === strcmp($name, 'x-account-container-count')) {
                $nbContainers = intval($value);
            } elseif (0 === strcmp($name, 'x-account-bytes-used')) {
                $totalSize = intval($value);
            } elseif (0 === strpos($name, 'x-account-meta-')) {
                $metadata[substr($name, 15)] = $value;
            }
        }

        return array(
            $nbContainers,
            $totalSize,
            $metadata
        );
    }

    /**
     * Update account metadata.
     *
     * Example:
     * <code>
     * $connection->updateAccountMetadata(array(
     *     'X-Account-Meta-Foo' => 'bar',
     * ));
     * </code>
     *
     * @param array $metadata
     */
    public function updateAccountMetadata(array $metadata)
    {
        $this->makeRequest(Client::POST, array(), $metadata);
    }

    /**
     * Create new container.
     *
     * If $errorOnExisting is true and container already exists,
     * throws \OpenStackStorage\Exceptions\ContainerExists.
     *
     * @param  string                                       $name
     * @param  boolean                                      $errorOnExisting
     * @return \OpenStackStorage\Container
     * @throws \OpenStackStorage\Exceptions\ContainerExists
     */
    public function createContainer($name, $errorOnExisting = false)
    {
        $this->validateContainerName($name);

        $response = $this->makeRequest(Client::PUT, array($name));
        if ($errorOnExisting && 202 == $response['status']) {
            throw new Exceptions\ContainerExists($name);
        }

        return new Container($this, $name);
    }

    /**
     * Delete container.
     *
     * @param  \OpenStackStorage\Container|string                    $container
     * @throws \OpenStackStorage\Exceptions\NoSuchContainer
     * @throws \Exception|\OpenStackStorage\Exceptions\ResponseError
     * @throws \OpenStackStorage\Exceptions\ContainerNotEmpty
     */
    public function deleteContainer($container)
    {
        if (is_object($container) && $container instanceof Container) {
            $name = $container->getName();
        } else {
            $name = strval($container);
        }

        $this->validateContainerName($name);

        try {
            $this->makeRequest(Client::DELETE, array($name));
        } catch (Exceptions\ResponseError $e) {
            switch ($e->getCode()) {
                case 409:
                    throw new Exceptions\ContainerNotEmpty($name);
                    break;
                case 404:
                    throw new Exceptions\NoSuchContainer();
                    break;
                default:
                    throw $e;
                    break;
            }
        }

        if ($this->getCdnEnabled()) {
            $this->makeCdnRequest(
                Client::POST,
                array($name),
                array(
                    'X-CDN-Enabled' => 'False',
                )
            );
        }
    }

    /**
     * Return container object.
     *
     * @param  string                                                $name
     * @return \OpenStackStorage\Container
     * @throws \OpenStackStorage\Exceptions\NoSuchContainer
     * @throws \Exception|\OpenStackStorage\Exceptions\ResponseError
     */
    public function getContainer($name)
    {
        $this->validateContainerName($name);

        try {
            $response = $this->makeRequest(Client::HEAD, array($name));
        } catch (Exceptions\ResponseError $e) {
            if (404 == $e->getCode()) {
                throw new Exceptions\NoSuchContainer();
            }

            throw $e;
        }

        $nbObjects = $response['headers']['x-container-object-count'];
        $sizeUsed  = $response['headers']['x-container-bytes-used'];
        $metadata  = array();

        foreach ($response['headers'] as $k => $value) {
            if (0 === strpos($k, 'x-container-meta-')) {
                $metadata[substr($k, 17)] = $value;
            }
        }

        return new Container($this, $name, $nbObjects, $sizeUsed, $metadata);
    }

    /**
     * Return array with containers.
     *
     * @param  array                         $parameters
     * @return \OpenStackStorage\Container[]
     */
    public function getContainers(array $parameters = array())
    {
        $result = array();

        foreach ($this->getContainersInfo($parameters) as $info) {
            $result[] = new Container($this, $info['name'], $info['count'], $info['bytes']);
        }

        return $result;
    }

    /**
     * Return names of public containers.
     *
     * @return array
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function getPublicContainersList()
    {
        if (!$this->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        $response = $this->makeCdnRequest(Client::GET);

        return explode("\n", trim($response['body']));
    }

    /**
     * Return information about containers.
     *
     * @see \OpenStackStorage\Connection::$allowedParameters
     * @param  array $parameters
     * @return array
     */
    public function getContainersInfo(array $parameters = array())
    {
        $parameters['format'] = 'json';

        return $this->getContainersRawData($parameters);
    }

    /**
     * Return names of containers.
     *
     * @see \OpenStackStorage\Connection::$allowedParameters
     * @param  array $parameters
     * @return array
     */
    public function getContainersList(array $parameters = array())
    {
        $parameters['format'] = 'plain';

        return explode("\n", trim($this->getContainersRawData($parameters)));
    }

    /**
     * Generate path for query string.
     *
     * @param  array  $path
     * @return string
     */
    public function getPathFromArray(array $path = array())
    {
        $tmp = array();

        foreach ($path as $value) {
            $tmp[] = rawurlencode($value);
        }

        return sprintf(
            '/%s/%s',
            rtrim($this->connectionUrlInfo['path'], '/'),
            str_replace('%2F', '/', implode('/', $tmp))
        );
    }

    /**
     * Authenticate and setup this instance with the values returned.
     */
    protected function authenticate()
    {
        if (!$this->isAuthenticated) {
            list($url, $this->cdnUrl, $this->authToken) = $this->auth->authenticate();
            if ($this->useServicenet) {
                $url = str_replace('https://', 'https://snet-%s', $url);
            }

            $this->connectionUrlInfo = Utils::parseUrl($url);
            $this->httpConnect();

            if ($this->cdnUrl) {
                $this->cdnConnect();
            }

            $this->isAuthenticated = true;
        }
    }

    /**
     * Setup the http connection instance.
     */
    protected function httpConnect()
    {
        $this->client = new Client(array('timeout' => $this->timeout));
        $this->client->setUserAgent($this->userAgent);
        $this->client->setBaseURL(sprintf(
            '%s://%s:%d',
            $this->connectionUrlInfo['scheme'],
            $this->connectionUrlInfo['host'],
            $this->connectionUrlInfo['port']
        ));
    }

    /**
     * Setup the http connection instance for the CDN service.
     */
    protected function cdnConnect()
    {
        $info                = Utils::parseUrl($this->cdnUrl);
        $this->cdnEnabled    = true;
        $this->cdnClient = new Client(array('timeout' => $this->timeout));
        $this->cdnClient->setUserAgent($this->userAgent);
        $this->cdnClient->setBaseURL(sprintf(
            '%s://%s:%d',
            $info['scheme'],
            $info['host'],
            $info['port']
        ));
    }

    /**
     * Performs the real http request.
     *
     * @param  \OpenStackStorage\Client $client
     * @param  string                   $method
     * @param  string                   $path
     * @param  array                    $parameters
     * @param  array                    $headers
     * @return array
     * @throws \Exception
     */
    protected function makeRealRequest(Client $client, $method, $path, $parameters = array(), array $headers = array())
    {
        $headers['X-Auth-Token'] = $this->authToken;

        return $client->sendRequest($path, $method, $parameters, $headers);
    }

    /**
     * Validates the container name.
     *
     * @param  string                                            $name
     * @throws \OpenStackStorage\Exceptions\InvalidContainerName
     */
    protected function validateContainerName($name)
    {
        if (empty($name)
            || (false !== strpos($name, '/'))
            || strlen($name) > CONTAINER_NAME_LIMIT) {
            throw new Exceptions\InvalidContainerName();
        }
    }

    /**
     * Return a raw response string with containers data.
     *
     * @see \OpenStackStorage\Connection::$allowedParameters
     * @param  array  $parameters
     * @return string
     */
    protected function getContainersRawData(array $parameters = array())
    {
        $cacheKey = md5(serialize($parameters));

        if (!array_key_exists($cacheKey, self::$listContainersCache)) {
            $tmp = array();

            foreach ($parameters as $k => $v) {
                if (in_array($k, self::$allowedParameters)) {
                    $tmp[$k] = $v;
                }
            }

            $response = $this->makeRequest(Client::GET, array(), array(), $tmp);
            self::$listContainersCache[$cacheKey] = $response['body'];
        }

        return self::$listContainersCache[$cacheKey];
    }

}
