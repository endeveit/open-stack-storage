<?php
namespace OpenStackStorage;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;

/**
 * Authentication instances are used to interact with the remote authentication
 * service, retrieving storage system routing information and session tokens.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
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
     * Request timeout.
     *
     * @var integer
     */
    protected $timeout = 5;

    /**
     * The class constructor.
     *
     * @param string $username
     * @param string $apiKey
     * @param string $url
     * @param string $userAgent
     * @param integer $timeout
     */
    public function __construct($username, $apiKey, $url, $userAgent, $timeout = 5)
    {
        $this->urlInfo = Utils::parseUrl($url);
        $this->timeout = intval($timeout);
        $this->headers = array(
            'x-auth-user' => $username,
            'x-auth-key'  => $apiKey,
            'User-Agent'  => $userAgent,
        );
    }

    /**
     * Initiates authentication with the remote service and returns a
     * array containing the storage system URL, CDN URL (can be empty)
     * and session token.
     *
     * @return array
     * @throws Exceptions\AuthenticationFailed
     * @throws Exceptions\AuthenticationError
     * @throws Exceptions\ResponseError
     */
    public function authenticate()
    {
        $client = new Client(
            sprintf(
                '%s://%s:%d',
                $this->urlInfo['scheme'],
                $this->urlInfo['host'],
                $this->urlInfo['port']
            ),
            array(
                'curl.CURLOPT_SSL_VERIFYHOST' => false,
                'curl.CURLOPT_SSL_VERIFYPEER' => false,
                'curl.CURLOPT_CONNECTTIMEOUT' => $this->timeout
            )
        );

        try {
            $response = $client->get($this->urlInfo['path'], $this->headers)->send();
        } catch (ClientErrorResponseException $e) {
            $response = $e->getResponse();
            if (401 == $response->getStatusCode()) {
                // A status code of 401 indicates that the supplied credentials
                // were not accepted by the authentication service.
                throw new Exceptions\AuthenticationFailed();
            }
        }

        // Raise an error for any response that is not 2XX
        if (2 != floor($response->getStatusCode() / 100)) {
            throw new Exceptions\ResponseError($response);
        }

        $authToken = $response->getHeader('x-auth-token', true);
        if (!$authToken) {
            $authToken = $response->getHeader('x-storage-token', true);
        }

        $storageUrl = $response->getHeader('x-storage-url', true);
        $cdnUrl     = $response->getHeader('x-cdn-management-url', true);

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
