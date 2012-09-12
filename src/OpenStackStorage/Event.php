<?php
namespace OpenStackStorage;

use Guzzle\Common\Event as BaseEvent;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestInterface;

/**
 * User: endeveit
 * Date: 2012-09-12
 * Time: 12:07
 */
class Event extends BaseEvent
{

    public function __construct(RequestInterface $request, Response $response)
    {
        $this['request']  = $request;
        $this['response'] = $response;
    }

}
