<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage\Exceptions;

/**
 * Raised when attempting to create a container when the container
 * already exists.
 */
class ContainerExists extends Error
{

    /**
     * The class constructor.
     *
     * @param string     $containerName
     * @param integer    $code
     * @param \Exception $previous
     */
    public function __construct($containerName, $code = 0, \Exception $previous = null)
    {
        parent::__construct('Container ' . $containerName . ' already exists');
    }

}
