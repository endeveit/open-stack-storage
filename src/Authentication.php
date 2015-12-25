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
 * Authentication instances are used to interact with the remote authentication
 * service, retrieving storage system routing information and session tokens.
 */
class Authentication
{

    /**
     * The information about the authentication URL.
     *
     * @var array
     */
    protected $urlInfo = array();

    /**
     * Request headers with username, API key and User-Agent.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * User-Agent for request.
     *
     * @var string
     */
    protected $userAgent = null;

    /**
     * Request timeout.
     *
     * @var integer
     */
    protected $timeout = 5;

    /**
     * The class constructor.
     *
     * @param string  $username
     * @param string  $apiKey
     * @param string  $url
     * @param string  $userAgent
     * @param integer $timeout
     */
    public function __construct($username, $apiKey, $url, $userAgent, $timeout = 5)
    {
        $this->urlInfo   = Utils::parseUrl($url);
        $this->userAgent = $userAgent;
        $this->timeout   = intval($timeout);
        $this->headers   = array(
            'x-auth-user' => $username,
            'x-auth-key'  => $apiKey,
        );
    }

    /**
     * Initiates authentication with the remote service and returns a
     * array containing the storage system URL, CDN URL (can be empty)
     * and session token.
     *
     * @return array
     * @throws \Exception|\OpenStackStorage\Exceptions\ResponseError
     * @throws \OpenStackStorage\Exceptions\AuthenticationError
     * @throws \OpenStackStorage\Exceptions\AuthenticationFailed
     * @throws \Exception
     */
    public function authenticate()
    {
        $client = new Client(array('timeout' => $this->timeout));
        $client->setBaseURL(sprintf(
            '%s://%s:%d/',
            $this->urlInfo['scheme'],
            $this->urlInfo['host'],
            $this->urlInfo['port']
        ));
        $client->setUserAgent($this->userAgent);

        try {
            $response = $client->get($this->urlInfo['path'], null, $this->headers);
        } catch (Exceptions\ResponseError $e) {
            if (401 == $e->getCode()) {
                // A status code of 401 indicates that the supplied credentials
                // were not accepted by the authentication service.
                throw new Exceptions\AuthenticationFailed();
            }

            throw $e;
        }

        $authToken = $response['headers']['x-auth-token'];
        if (!$authToken) {
            $authToken = $response['headers']['x-storage-token'];
        }

        $storageUrl = $response['headers']['x-storage-url'];
        $cdnUrl     = $response['headers']['x-cdn-management-url'];

        if (!($authToken && $storageUrl)) {
            throw new Exceptions\AuthenticationError(
                'Invalid response from the authentication service.'
            );
        }

        return array(
            $storageUrl,
            $cdnUrl,
            $authToken
        );
    }
}
