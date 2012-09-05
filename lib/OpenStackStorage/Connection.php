<?php
namespace OpenStackStorage;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Message\RequestInterface;

define('CONTAINER_NAME_LIMIT', 256);
define('OBJECT_NAME_LIMIT',    1024);
define('META_NAME_LIMIT',      128);
define('META_VALUE_LIMIT',     256);

/**
 * Manages the connection to the storage system and serves as a factory
 * for Container instances.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
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
     * @var Authentication
     */
    protected $auth = null;

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
     * @var \Guzzle\Http\Client
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
     * HTTP-client to work with storage via CDN.
     *
     * @var \Guzzle\Http\Client
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
     * @param string $username
     * @param string $apiKey
     * @param array $options
     * @param integer $timeout
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

        $this->auth = new Authentication(
            $username,
            $apiKey,
            $options['authurl'],
            $this->userAgent,
            $timeout
        );
        $this->authenticate();
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
     * @return \Guzzle\Http\Client
     */
    public function getClient()
    {
        return $this->client;
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
     * @param string $method     name of the method (i.e. GET, PUT, POST, etc)
     * @param array  $path       list of tokens that will be added to connection
     *                           URI string
     * @param array  $headers    additional headers
     * @param array  $parameters additional parameters that will be added to the
     *                           query string
     * @return \Guzzle\Http\Message\Response
     */
    public function makeRequest($method, array $path = array(), array $headers = array(), array $parameters = array())
    {
        $path = $this->getPathFromArray($path);
        if (!empty($parameters)) {
            $path .= '?' . http_build_query($parameters);
        }

        return $this->makeRealRequest($this->client, $method, $path, $headers);
    }

    /**
     * Performs an http request to the CDN.
     *
     * @param string $method  name of the method (i.e. GET, PUT, POST, etc)
     * @param array  $path    list of tokens that will be added to connection
     *                        URI string
     * @param array  $headers additional headers
     * @return \Guzzle\Http\Message\Response
     * @throws Exceptions\CDNNotEnabled
     */
    public function makeCdnRequest($method, array $path = array(), array $headers = array())
    {
        if (!$this->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        return $this->makeRealRequest(
            $this->cdnClient,
            $method,
            $this->getPathFromArray($path),
            $headers
        );
    }

    /**
     * Return array with number of containers, total bytes in the account and
     * account metadata.
     *
     * @return array
     */
    public function getAccountInfo()
    {
        $response     = $this->makeRequest(RequestInterface::HEAD);
        $nbContainers = 0;
        $totalSize    = 0;
        $metadata     = array();

        foreach ($response->getHeaders() as $name => $values) {
            $name = strtolower($name);

            if (0 === strcmp($name, 'x-account-container-count')) {
                $nbContainers = intval($values[0]);
            } elseif (0 === strcmp($name, 'x-account-bytes-used')) {
                $totalSize = intval($values[0]);
            } elseif (0 === strpos($name, 'x-account-meta-')) {
                $metadata[substr($name, 15)] = $values[0];
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
     * <?php
     * $connection->updateAccountMetadata(array(
     *  'X-Account-Meta-Foo' => 'bar',
     * ));
     * ?>
     * </code>
     *
     * @param array $metadata
     */
    public function updateAccountMetadata(array $metadata)
    {
        $this->makeRequest(RequestInterface::POST, array(), $metadata);
    }

    /**
     * Create new container.
     *
     * If $errorOnExisting is true and container already exists,
     * throws Exceptions\ContainerExists.
     *
     * @param string  $name
     * @param boolean $errorOnExisting
     * @return Container
     * @throws Exceptions\ContainerExists
     */
    public function createContainer($name, $errorOnExisting = false)
    {
        $this->validateContainerName($name);

        $response = $this->makeRequest(RequestInterface::PUT, array($name));
        if ($errorOnExisting && 202 == $response->getStatusCode()) {
            throw new Exceptions\ContainerExists($name);
        }

        return new Container($this, $name);
    }

    /**
     * Delete container.
     *
     * @param Container|string $container
     * @throws Exceptions\NoSuchContainer
     * @throws Exceptions\ResponseError
     * @throws Exceptions\ContainerNotEmpty
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
            $this->makeRequest(RequestInterface::DELETE, array($name));
        } catch (Exceptions\ResponseError $e) {
            $response = $e->getResponse();
            switch ($response->getStatusCode()) {
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
                RequestInterface::POST,
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
     * @param string $name
     * @return Container
     * @throws Exceptions\NoSuchContainer
     * @throws Exceptions\ResponseError
     */
    public function getContainer($name)
    {
        $this->validateContainerName($name);

        try {
            $response = $this->makeRequest(RequestInterface::HEAD, array($name));
        } catch (Exceptions\ResponseError $e) {
            $response = $e->getResponse();

            if (404 == $response->getStatusCode()) {
                throw new Exceptions\NoSuchContainer();
            }

            throw $e;
        }

        $nbObjects = $response->getHeader('x-container-object-count', true);
        $sizeUsed  = $response->getHeader('x-container-bytes-used', true);
        $metadata  = array();

        foreach ($response->getHeaders() as $header => $values) {
            $header = strtolower($header);

            if (0 === strpos($header, 'x-container-meta-')) {
                $metadata[substr($header, 17)] = $values[0];
            }
        }

        return new Container($this, $name, $nbObjects, $sizeUsed, $metadata);
    }

    /**
     * Return array with containers.
     *
     * @param array $parameters
     * @return Container[]
     */
    public function getContainers(array $parameters = array())
    {
        $result = array();

        foreach ($this->getContainersInfo($parameters) as $info) {
            $result[] = new Container(
                $this,
                $info['name'],
                $info['count'],
                $info['bytes']
            );
        }

        return $result;
    }

    /**
     * Return names of public containers.
     *
     * @return array
     * @throws Exceptions\CDNNotEnabled
     */
    public function getPublicContainersList()
    {
        if (!$this->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        return explode(
            "\n",
            trim($this->makeCdnRequest(RequestInterface::GET)->getBody(true))
        );
    }

    /**
     * Return information about containers.
     *
     * @see Connection::$allowedParameters
     * @param array $parameters
     * @return array
     */
    public function getContainersInfo(array $parameters = array())
    {
        $parameters['format'] = 'json';

        return json_decode($this->getContainersRawData($parameters), true);
    }

    /**
     * Return names of containers.
     *
     * @see Connection::$allowedParameters
     * @param array $parameters
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
     * @param array $path
     * @return string
     */
    public function getPathFromArray(array $path = array())
    {
        $tmp = array();

        foreach ($path as $value) {
            $tmp[] = urlencode($value);
        }

        return sprintf(
            '/%s/%s',
            rtrim($this->connectionUrlInfo['path'], '/'),
            implode('/', $tmp)
        );
    }

    /**
     * Authenticate and setup this instance with the values returned.
     */
    protected function authenticate()
    {
        list($url, $this->cdnUrl, $this->authToken) = $this->auth->authenticate();
        if ($this->useServicenet) {
            $url = str_replace('https://', 'https://snet-%s', $url);
        }

        $this->connectionUrlInfo = Utils::parseUrl($url);
        $this->httpConnect();

        if ($this->cdnUrl) {
            $this->cdnConnect();
        }
    }

    /**
     * Setup the http connection instance.
     */
    protected function httpConnect()
    {
        $this->client = new Client(
            sprintf(
                '%s://%s:%d',
                $this->connectionUrlInfo['scheme'],
                $this->connectionUrlInfo['host'],
                $this->connectionUrlInfo['port']
            ),
            array(
                'curl.CURLOPT_SSL_VERIFYHOST' => false,
                'curl.CURLOPT_SSL_VERIFYPEER' => false,
                'curl.CURLOPT_CONNECTTIMEOUT' => $this->timeout
            )
        );
    }

    /**
     * Setup the http connection instance for the CDN service.
     */
    protected function cdnConnect()
    {
        $info             = Utils::parseUrl($this->cdnUrl);
        $this->cdnEnabled = true;
        $this->cdnClient  = new Client(
            sprintf(
                '%s://%s:%d',
                $info['scheme'],
                $info['host'],
                $info['port']
            ),
            array(
                'curl.CURLOPT_CONNECTTIMEOUT' => $this->timeout
            )
        );
    }

    /**
     * Performs the real http request.
     *
     * @param \Guzzle\Http\Client $client
     * @param string $method
     * @param string $path
     * @param array $headers
     * @return \Guzzle\Http\Message\Response
     * @throws Exceptions\ResponseError
     * @throws \Guzzle\Http\Exception\ClientErrorResponseException
     * @throws \Exception
     */
    protected function makeRealRequest(Client $client, $method, $path, array $headers = array())
    {
        $headers = array_merge(
            array(
                'User-Agent'   => $this->userAgent,
                'X-Auth-Token' => $this->authToken,
            ),
            $headers
        );

        $request = $client->createRequest($method, $path, $headers);

        try {
            $response = $request->send();
        } catch (ClientErrorResponseException $e) {
            // Authentication token has been expired
            if (401 == $e->getResponse()->getStatusCode()) {
                $this->authenticate();
                $response = $this->retryRequest($client, $method, $path, $headers);
            } else {
                $response = $e->getResponse();
            }
        } catch (\Exception $e) {
            // Let's try again
            $response = $this->retryRequest($client, $method, $path, $headers);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exceptions\ResponseError($response);
        }

        return $response;
    }

    /**
     * Retry a failed request once.
     *
     * @param \Guzzle\Http\Client $client
     * @param string $method
     * @param string $path
     * @param array $headers
     * @return \Guzzle\Http\Message\Response
     */
    protected function retryRequest(Client $client, $method, $path, array $headers = array())
    {
        $headers['X-Auth-Token'] = $this->authToken;

        return $client->createRequest($method, $path, $headers)->send();
    }

    /**
     * Validates the container name.
     *
     * @param string $name
     * @throws Exceptions\InvalidContainerName
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
     * @see Connection::$allowedParameters
     * @param array $parameters
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

            self::$listContainersCache[$cacheKey] = $this->makeRequest(
                RequestInterface::GET,
                array(),
                array(),
                $tmp
            )->getBody(true);
        }

        return self::$listContainersCache[$cacheKey];
    }

}
