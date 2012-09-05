<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage;

use OpenStackStorage\Exceptions\InvalidUrl;

/**
 * Common helper functions.
 */
class Utils
{

    /**
     * Return an array of 4 elements containing the scheme, hostname, port
     * and a boolean representing whether the connection should use SSL or not.
     *
     * @static
     * @param string $url
     * @return array
     * @throws Exceptions\InvalidUrl
     */
    public static function parseUrl($url)
    {
        $info = parse_url($url);
        if (!$info) {
            throw new InvalidUrl('The string must be a valid URL');
        }

        if (empty($info['scheme']) || !in_array($info['scheme'], array('http', 'https'))) {
            throw new InvalidUrl('Scheme must be one of http or https');
        }

        if (!preg_match('#([a-zA-Z0-9\-\.]+):?([0-9]{2,5})?#i', $info['host'])) {
            throw new InvalidUrl('Invalid host and/or port: ' . $info['host']);
        }

        if (empty($info['port'])) {
            // Порт не указан
            $port = $info['scheme'] == 'https' ? 443 : 80;
        } else {
            $port = intval($info['port']);
        }

        return array(
            'scheme' => $info['scheme'],
            'host'   => $info['host'],
            'port'   => $port,
            'path'   => !empty($info['path']) ? trim($info['path'], '/') : '',
        );
    }

}
