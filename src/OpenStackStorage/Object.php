<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace OpenStackStorage;

use Guzzle\Http\Curl\CurlHandle;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;

/**
 * Storage data representing an object, (metadata and data).
 */
class Object
{

    /**
     * Container object.
     *
     * @var \OpenStackStorage\Container
     */
    protected $container = null;

    /**
     * Object name.
     *
     * @var string
     */
    protected $name = null;

    /**
     * The object's MIME-type.
     *
     * @var string
     */
    protected $contentType = null;

    /**
     * The object size (in bytes).
     *
     * @var integer
     */
    protected $size = null;

    /**
     * Date and time of last file modification.
     *
     * @var \DateTime
     */
    protected $lastModified = null;

    /**
     * Object's etag.
     *
     * @var string
     */
    protected $etag = null;

    /**
     * Metadata.
     *
     * @var array
     */
    protected $metadata = array();

    /**
     * Headers.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Manifest, used when working with big file.
     *
     * @var string
     */
    protected $manifest = null;

    /**
     * The class constructor.
     *
     * Storage objects rarely if ever need to be instantiated directly by
     * the user.
     *
     * Instead, use the \OpenStackStorage\Container object methods:
     * <code>
     * $container->createObject('test.txt');
     * $container->getObject('test.txt');
     * $container->getObjects('test.txt');
     * </code>
     *
     * @param \OpenStackStorage\Container $container
     * @param string $name
     * @param boolean $forceExists
     * @param array $objectRecord
     * @throws \OpenStackStorage\Exceptions\NoSuchObject
     */
    public function __construct(Container $container, $name = null, $forceExists = false, array &$objectRecord = array())
    {
        $this->container = &$container;

        if (!empty($objectRecord)) {
            $this->name         = $objectRecord['name'];
            $this->contentType  = $objectRecord['content_type'];
            $this->size         = $objectRecord['bytes'];
            $this->lastModified = $objectRecord['last_modified'];
            $this->etag         = $objectRecord['hash'];
        } else {
            $this->name = $name;
            if (!$this->initialize() && $forceExists) {
                throw new Exceptions\NoSuchObject();
            }
        }
    }

    /**
     * Set the value of property $name.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Return the value of the $name property.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of property $contentType.
     *
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * Return the value of the $contentType property.
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Set the value of property $metadata.
     *
     * @param array $metadata
     */
    public function setMetadata(array $metadata = array())
    {
        $this->metadata = $metadata;
    }

    /**
     * Return the value of the $metadata property.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set the value of property $headers.
     *
     * @param array $headers
     */
    public function setHeaders(array $headers = array())
    {
        $this->headers = $headers;
    }

    /**
     * Return the value of the $headers property.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the value of property $manifest.
     *
     * @param string $manifest
     */
    public function setManifest($manifest)
    {
        $this->manifest = $manifest;
    }

    /**
     * Return the value of the $manifest property.
     *
     * @return string
     */
    public function getManifest()
    {
        return $this->manifest;
    }

    /**
     * Read the content from the remote storage object.
     *
     * By default this method will buffer the response in memory and
     * return it as a string. However, if a \SplFileObject object is passed
     * in $buffer, the response will be written to it instead.
     *
     * @param integer        $size    combined with offset, defines the length
     *                                of data to be read
     * @param integer        $offset  combined with size, defines the start
     *                                location to be read
     * @param array          $headers
     * @param \SplFileObject $buffer
     * @return null|string
     */
    public function read($size = -1, $offset = 0, array $headers = array(), \SplFileObject $buffer = null)
    {
        $this->validateName();

        if ($size > 0) {
            $headers['Range'] = sprintf(
                'bytes=%d-%d',
                $offset,
                ($offset + $size) - 1
            );
        }

        $response = $this->container->getConnection()->makeRequest(
            RequestInterface::GET,
            array($this->container->getName(), $this->name),
            $headers
        );

        if (null !== $buffer) {
            $buffer->fwrite($response->getBody(true));

            return null;
        } else {
            return $response->getBody(true);
        }
    }

    /**
     * Save the contents of the object to filename.
     *
     * @param string $filename
     */
    public function saveToFilename($filename)
    {
        $buffer = new \SplFileObject($filename, 'wb');

        $this->read(-1, 0, array(), $buffer);
    }

    /**
     * Write data to the remote storage system.
     * The $source may be one the following:
     *  — resource
     *  — \SplFileObject instance
     *  — scalar (string)
     *  — null (can be used to create directories when
     *    $contentType = 'application/directory')
     *
     * @param mixed $data
     * @param string $contentType
     * @param integer $filesize
     * @throws \OpenStackStorage\Exceptions\Error
     */
    public function write($data, $contentType = null, $filesize = null)
    {
        $this->validateName();

        $path = null;

        if (is_resource($data)) {
            $meta = stream_get_meta_data($data);
            if (!$contentType) {
                $path = $meta['uri'];
            }

            if (!$filesize) {
                $this->size = filesize($meta['uri']);
            }
        } elseif (is_object($data) && $data instanceof \SplFileObject) {
            $this->size = $data->getSize();
            $path       = $data->getType();
            $tmp        = '';

            while (!$data->eof()) {
                $tmp .= $data->fgets();
            }

            $data = &$tmp;
        } else {
            $data       = strval($data);
            $this->size = strlen($data);
        }

        if ((null === $contentType) && (null !== $path) && is_readable($path)) {
            $contentType = finfo_file(
                finfo_open(FILEINFO_MIME_TYPE),
                $path,
                FILEINFO_MIME_TYPE
            );
        }

        $this->contentType = (!$contentType
            ? 'application/octet-stream'
            : $contentType);

        $connection = &$this->container->getConnection();
        $headers    = array_merge(
            array(
                'User-Agent'   => $connection->getUserAgent(),
                'X-Auth-Token' => $connection->getAuthToken(),
            ),
            $this->getNewHeaders()
        );
        unset($headers['ETag']);

        // Make the request with raw cURL, because of slow PUT requests
        // made with Guzzle
        $response = $this->getPutResponseViaRawCurl(
            $connection->getClient()->put(
                $connection->getPathFromArray(
                    array($this->container->getName(), $this->name)
                ),
                $headers
            ),
            $data,
            $connection->getTimeout()
        );

        $this->etag = $response->getHeader('etag', true);
    }

    /**
     * Commits the metadata and custom headers to the remote storage system.
     *
     * Example:
     * <code>
     * $object = $container->getObject('paradise_lost.pdf);
     * $object->setMetadata(array('author' => 'John Milton'));
     * $object->setHeaders(array('content-disposition' => 'foo'));
     * $object->syncMetadata();
     * </code>
     *
     * @throws \OpenStackStorage\Exceptions\ResponseError
     */
    public function syncMetadata()
    {
        $this->validateName();

        if (!empty($this->metadata) || !empty($this->headers)) {
            $headers = $this->getNewHeaders();
            $headers['Content-Length'] = '0';

            $response = $this->container->getConnection()->makeRequest(
                RequestInterface::POST,
                array($this->container->getName(), $this->name),
                $headers
            );

            if (202 != $response->getStatusCode()) {
                throw new Exceptions\ResponseError($response);
            }
        }
    }

    /**
     * Commits the manifest to the remote storage system.
     *
     * Example:
     * <code>
     * $object = $container->getObject('paradise_lost.pdf);
     * $object->setManifest('container/prefix');
     * $object->syncManifest();
     * </code>
     */
    public function syncManifest()
    {
        $this->validateName();

        if ($this->manifest) {
            $headers = $this->getNewHeaders();
            $headers['Content-Length'] = '0';

            $this->container->getConnection()->makeRequest(
                RequestInterface::PUT,
                array($this->container->getName(), $this->name),
                $headers
            );
        }
    }

    /**
     * Return the URI for this object, if its container is public.
     *
     * @return string
     */
    public function getPublicUri()
    {
        return sprintf(
            '%s/%s',
            rtrim($this->container->getPublicUri(), '/'),
            urlencode($this->name)
        );
    }

    /**
     * Return the SSL URI for this object, if its container is public.
     *
     * @return string
     */
    public function getPublicSslUri()
    {
        return sprintf(
            '%s/%s',
            rtrim($this->container->getPublicSslUri(), '/'),
            urlencode($this->name)
        );
    }

    /**
     * Return the streaming URI for this object, if its container is public.
     *
     * @return string
     */
    public function getPublicStreamingUri()
    {
        return sprintf(
            '%s/%s',
            rtrim($this->container->getPublicStreamingUri(), '/'),
            urlencode($this->name)
        );
    }

    /**
     * Purge Edge cache for this object.
     *
     * You will be notified by email if one is provided when the
     * job completes.
     *
     * Example:
     * <code>
     * $object1->purgeFromCdn();
     * $object2->purgeFromCdn('user@example.com');
     * $object3->purgeFromCdn('user@example.com,user@example.org);
     * </code>
     *
     * @param string $email
     * @throws \OpenStackStorage\Exceptions\CDNNotEnabled
     */
    public function purgeFromCdn($email = null)
    {
        if (!$this->container->getConnection()->getCdnEnabled()) {
            throw new Exceptions\CDNNotEnabled();
        }

        $headers = array();
        if (null !== $email) {
            $headers['X-Purge-Email'] = $email;
        }

        $this->container->getConnection()->makeCdnRequest(
            RequestInterface::DELETE,
            array($this->container->getName(), $this->name),
            $headers
        );
    }

    /**
     * Initialize the object with values from the remote service (if any).
     *
     * @return boolean
     * @throws \OpenStackStorage\Exceptions\ResponseError
     */
    protected function initialize()
    {
        if (!$this->name) {
            return false;
        }

        try {
            $response = $this->container->getConnection()->makeRequest(
                RequestInterface::HEAD,
                array($this->container->getName(), $this->name)
            );
        } catch (Exceptions\ResponseError $e) {
            if (404 == $e->getResponse()->getStatusCode()) {
                return false;
            }

            throw $e;
        }

        $this->manifest     = $response->getHeader('x-object-manifest', true);
        $this->contentType  = $response->getHeader('content-type', true);
        $this->etag         = $response->getHeader('etag', true);
        $this->size         = intval($response->getHeader('content-length', true));
        $this->lastModified = new \DateTime($response->getHeader('last-modified', true));

        foreach ($response->getHeaders() as $name => $values) {
            $name = strtolower($name);

            if (0 === strpos($name, 'x-object-meta-')) {
                $this->metadata[substr($name, 14)] = $values[0];
            }
        }

        return true;
    }

    /**
     * Validates the object name.
     *
     * @param string $name
     * @throws \OpenStackStorage\Exceptions\InvalidObjectName
     */
    protected function validateName($name = null)
    {
        if (null == $name) {
            $name = $this->name;
        }

        if (strlen($name) > OBJECT_NAME_LIMIT) {
            throw new Exceptions\InvalidObjectName();
        }
    }

    /**
     * Returns array representing http headers based on the
     * respective instance attributes.
     *
     * @return array
     * @throws \OpenStackStorage\Exceptions\InvalidMetaValue
     * @throws \OpenStackStorage\Exceptions\InvalidMetaName
     */
    protected function getNewHeaders()
    {
        $headers = array(
            'Content-Length' => (null !== $this->size) ? strval($this->size) : '0',
        );

        if (null !== $this->manifest) {
            $headers['X-Object-Manifest'] = $this->manifest;
        }

        if (null !== $this->etag) {
            $headers['ETag'] = $this->etag;
        }

        if (null !== $this->contentType) {
            $headers['Content-Type'] = $this->contentType;
        } else {
            $headers['Content-Type'] = 'application/octet-stream';
        }

        foreach ($this->metadata as $key => $value) {
            if (strlen($key) > META_NAME_LIMIT) {
                throw new Exceptions\InvalidMetaName();
            }

            if (strlen($value) > META_VALUE_LIMIT) {
                throw new Exceptions\InvalidMetaValue();
            }

            $headers['X-Object-Meta-' + $key] = $value;
        }

        return array_merge($this->headers, $headers);
    }

    /**
     * Returns response headers from PUT request, performed with raw cURL.
     *
     * @param \Guzzle\Http\Message\RequestInterface $request
     * @param mixed $data
     * @param integer $timeout
     * @return \Guzzle\Http\Message\Response
     * @throws \OpenStackStorage\Exceptions\Error
     */
    protected function getPutResponseViaRawCurl(RequestInterface $request, $data, $timeout = 5)
    {
        if (!is_resource($data)) {
            $data = fopen('data://text/plain,' . urlencode($data), 'r');
        }

        $ch = $this->getCurlHandlerFromPutRequest($request, $timeout);
        curl_setopt($ch, CURLOPT_INFILESIZE, $this->size);
        curl_setopt($ch, CURLOPT_INFILE, $data);

        if (!($message = curl_exec($ch))) {
            throw new Exceptions\Error(curl_error($ch), curl_errno($ch));
        }

        $response = Response::fromMessage($message);
        $response->setInfo(curl_getinfo($ch));
        curl_close($ch);

        $ed = $request->getClient()->getEventDispatcher();
        if ($ed->hasListeners('request.complete')) {
            $ed->dispatch('request.complete', new Event($request, $response));
        }

        return $response;
    }

    /**
     * Returns raw cURL handler to perform \Guzzle\Http\Message\Request.
     *
     * @param \Guzzle\Http\Message\RequestInterface $request
     * @param integer $timeout
     * @return resource
     * @throws \OpenStackStorage\Exceptions\Error
     */
    protected function getCurlHandlerFromPutRequest(RequestInterface $request, $timeout = 5)
    {
        if ($request->getMethod() != RequestInterface::PUT) {
            throw new Exceptions\Error(
                'Only PUT requests can be passed to this method'
            );
        }

        CurlHandle::factory($request);

        $options = $request->getParams()->get('curl.last_options');
        $options[CURLOPT_CONNECTTIMEOUT] = $timeout;
        $options[CURLOPT_TIMEOUT]        = $timeout;
        $options[CURLOPT_HEADER]         = true;
        $options[CURLOPT_NOBODY]         = true;
        $options[CURLOPT_RETURNTRANSFER] = true;

        // Unset Guzzle specific options
        unset($options[CURLOPT_WRITEFUNCTION]);
        unset($options[CURLOPT_HEADERFUNCTION]);

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        return $ch;
    }

}
