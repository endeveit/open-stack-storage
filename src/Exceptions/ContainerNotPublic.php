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
 * Raised when public features of a non-public container are accessed.
 */
class ContainerNotPublic extends Error
{
}
