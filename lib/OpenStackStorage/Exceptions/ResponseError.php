<?php
namespace OpenStackStorage\Exceptions;

use Guzzle\Http\Message\Response;

/**
 * Raised when the remote service returns an error.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 */
class ResponseError extends Error
{

    /**
     * Response object.
     *
     * @var \Guzzle\Http\Message\Response
     */
    protected $response = null;

    /**
     * The class constructor.
     *
     * @param \Guzzle\Http\Message\Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = &$response;

        parent::__construct($response->getReasonPhrase(), $response->getStatusCode());
    }

    /**
     * Return response object.
     *
     * @return \Guzzle\Http\Message\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return sprintf('%d: %s', $this->getCode(), $this->getMessage());
    }

}
