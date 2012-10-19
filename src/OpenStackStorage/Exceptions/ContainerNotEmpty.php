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
 * Raised when attempting to delete a container that still contains objects.
 */
class ContainerNotEmpty extends Error
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
        parent::__construct(
            'Cannot delete non-empty container ' . $containerName,
            $code,
            $previous
        );
    }

}
