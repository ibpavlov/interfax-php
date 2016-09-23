<?php
/**
 * Interfax
 *
 * (C) InterFAX, 2016
 *
 * @package interfax/interfax
 * @author Interfax <dev@interfax.net>
 * @copyright Copyright (c) 2016, InterFAX
 * @license MIT
 */

namespace Interfax;

class File
{
    /**
     * @var GenericFactory
     */
    private $factory;
    /**
     * @var Client
     */
    protected $client;

    protected $headers = [];
    protected $mime_type;
    protected $name;
    protected $body;
    private $chunk_size;
    protected static $DEFAULT_CHUNK_SIZE = 1048576; // 1024*1024

    /**
     * File constructor.
     * @param $location
     * @param array $params
     * @throws \InvalidArgumentException
     */
    public function __construct(Client $client, $location, $params = [], GenericFactory $factory = null)
    {
        $this->client = $client;
        if ($factory === null) {
            $factory = new GenericFactory();
        }

        $this->factory = $factory;

        if (preg_match('/^https?:\/\//', $location)) {
            $this->initialiseFromUri($location);
        } else {
            $this->initialiseParams($params);
            $this->initialiseFromPath($location);
        }
    }

    /**
     * @param array $params
     */
    protected function initialiseParams($params = [])
    {
        if (array_key_exists('mime_type', $params)) {
            $this->setMimeType($params['mime_type']);
        }

        if (array_key_exists('name', $params)) {
            $this->name = $params['name'];
        }

        if (array_key_exists('chunk_size', $params)) {
            $this->chunk_size = $params['chunk_size'];
        } else {
            $this->chunk_size = static::$DEFAULT_CHUNK_SIZE;
        }
    }

    /**
     * @param $mime_type
     */
    public function setMimeType($mime_type)
    {
        $this->headers = [
            'Content-Type' => $mime_type
        ];
        $this->mime_type = $mime_type;
    }

    /**
     * @param $location
     * @throws \InvalidArgumentException
     */
    protected function initialiseFromPath($location)
    {
        if (!file_exists($location)) {
            throw new \InvalidArgumentException(
                $location . ' not found. File must exists on filesystem to construct ' . __CLASS__
            );
        }

        if (!$this->name) {
            $this->name = basename($location);
        }

        if (filesize($location) > $this->chunk_size) {
            $this->initialiseFromLargeFile($location);
        } else {
            if (!$this->mime_type) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $this->setMimeType(finfo_file($finfo, $location));
            }

            $this->body = fopen($location, 'r');
        }
    }

    /**
     * @param $location
     */
    protected function initialiseFromLargeFile($location)
    {
        $document = $this->client->documents->create($this->name, filesize($location));

        $stream = fopen($location, 'rb');
        $current = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, $this->chunk_size);
            $end = $current + strlen($chunk);
            $document->upload($current, $end-1, $chunk);
            $current = $end;
        }
        fclose($stream);

        $this->initialiseFromUri($document->getHeaderLocation());
    }

    /**
     * @param $location
     */
    protected function initialiseFromUri($location)
    {
        $this->headers = [
            'Location' => $location
        ];
    }

    /**
     * @return string
     */
    public function getHeader()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }
}
