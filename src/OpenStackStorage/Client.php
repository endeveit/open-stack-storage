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
 * HTTP-request client object.
 */
class Client extends \Resty
{

    const GET = 'GET';
    const PUT = 'PUT';
    const POST = 'POST';
    const DELETE = 'DELETE';
    const HEAD = 'HEAD';
    const CONNECT = 'CONNECT';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const PATCH = 'PATCH';

    /**
     * Request timeout.
     *
     * @var integer
     */
    protected $timeout = 240;

    /**
     * Class constructor.
     *
     * @param array $opts
     */
    public function __construct($opts = null)
    {
        parent::__construct($opts);

        if (is_array($opts) && array_key_exists('timeout', $opts)) {
            $this->timeout = intval($opts['timeout']);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string                                     $url
     * @param  string                                     $method
     * @param  array                                      $querydata
     * @param  array                                      $headers
     * @param  array                                      $options
     * @return array
     * @throws \OpenStackStorage\Exceptions\ResponseError
     * @throws \OpenStackStorage\Exceptions\Error
     */
    public function sendRequest(
        $url,
        $method = 'GET',
        $querydata = null,
        $headers = null,
        $options = null
    ) {
        if (null === $options) {
            $options = array();
        }

        if (!array_key_exists('timeout', $options)) {
            $options['timeout'] = $this->timeout;
        }

        $response = parent::sendRequest($url, $method, $querydata, $headers, $options);

        if (array_key_exists('error_msg', $response) && (null !== $response['error_msg'])) {
            throw new Exceptions\Error(substr($response['error_msg'], 0, strpos($response['error_msg'], ';')));
        }

        if ($response['status'] >= 400) {
            throw new Exceptions\ResponseError($response['status']);
        }

        $response['headers'] = new Client\Headers($response['headers']);

        return $response;
    }

    /**
     * {@inheritdoc}
     * Overridden method to decode JSON to array instead of stdClass.
     *
     * @param  string        $resp
     * @return object|string
     */
    protected function processResponseBody($resp)
    {
        if ($this->parse_body === true) {
            if (isset($resp['headers']['Content-Type'])) {
                $contentType = preg_split('/[;\s]+/', $resp['headers']['Content-Type']);
                $contentType = $contentType[0];
            } else {
                $contentType = null;
            }

            if ((null !== $contentType) && !empty($contentType)) {
                if (in_array($contentType, self::$JSON_TYPES) || strpos($contentType, '+json') !== false) {
                    $this->log('Response body is JSON');

                    $resp['body_raw'] = $resp['body'];
                    $resp['body']     = json_decode($resp['body'], true);

                    return $resp;
                }

                parent::processResponseBody($resp);
            }
        }

        $this->log('Response body not parsed');

        return $resp;
    }

}
