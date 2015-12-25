<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage\Client;

/**
 * Contains response headers.
 */
class Headers implements \ArrayAccess, \IteratorAggregate
{

    /**
     * Array containing response headers.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Class constructor.
     *
     * @param array $headers
     */
    public function __construct(array $headers)
    {
        foreach ($headers as $k => $v) {
            $this->headers[strtolower($k)] = $v;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->headers[strtolower($offset)]);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $offset
     * @return string|null
     */
    public function offsetGet($offset)
    {
        $name = strtolower($offset);

        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string     $offset
     * @param  string     $value
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Headers are read-only.');
    }

    /**
     * {@inheritdoc}
     *
     * @param  string     $offset
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception('Headers are read-only.');
    }

    /**
     * {@inheritdoc}
     *
     * @return \ArrayObject
     */
    public function getIterator()
    {
        return new \ArrayObject($this->headers);
    }
}
