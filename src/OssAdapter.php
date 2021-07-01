<?php

declare(strict_types=1);

namespace Xxtime\Flysystem\Aliyun;

use Exception;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use OSS\OssClient;

class OssAdapter implements FilesystemAdapter
{
    /**
     * @var Supports
     */
    public $supports;

    /**
     * @var OssClient
     */
    private $oss;

    /**
     * @var AliYun bucket
     */
    private $bucket;

    /**
     * @var string
     */
    private $endpoint = 'oss-cn-hangzhou.aliyuncs.com';

    /**
     * OssAdapter constructor.
     * @param array $config
     * @throws Exception
     */
    public function __construct($config = [])
    {
        $isCName = false;
        $token = null;
        $this->supports = new Supports();
        $this->bucket = $config['bucket'];
        empty($config['endpoint']) ? null : $this->endpoint = $config['endpoint'];
        empty($config['timeout']) ? $config['timeout'] = 3600 : null;
        empty($config['connectTimeout']) ? $config['connectTimeout'] = 10 : null;

        if (! empty($config['isCName'])) {
            $isCName = true;
        }
        if (! empty($config['token'])) {
            $token = $config['token'];
        }
        $this->oss = new OssClient(
            $config['accessId'],
            $config['accessSecret'],
            $this->endpoint,
            $isCName,
            $token
        );
        $this->oss->setTimeout($config['timeout']);
        $this->oss->setConnectTimeout($config['connectTimeout']);
    }

    /**
     * Write a new file.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $result = $this->oss->putObject($this->bucket, $path, $contents, $this->getOssOptions($config));
        $this->supports->setFlashData($result);
    }

    /**
     * Write a new file using a stream.
     *
     * @param resource $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        if (! is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'The contents is invalid resource.');
        }
        $i = 0;
        $bufferSize = 1000000; // 1M
        while (! feof($contents)) {
            if (false === $buffer = fread($contents, $block = $bufferSize)) {
                throw UnableToWriteFile::atLocation($path, 'fread failed');
            }
            $position = $i * $bufferSize;
            $size = $this->oss->appendObject($this->bucket, $path, $buffer, $position, $this->getOssOptions($config));
            ++$i;
        }
        fclose($contents);
    }

    /**
     * Rename a file.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->oss->copyObject($this->bucket, $source, $this->bucket, $destination);
        $this->oss->deleteObject($this->bucket, $source);
    }

    /**
     * Copy a file.
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->oss->copyObject($this->bucket, $source, $this->bucket, $destination);
    }

    /**
     * Delete a file.
     *
     * @return bool
     */
    public function delete(string $path): void
    {
        $this->oss->deleteObject($this->bucket, $path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDirectory(string $path): void
    {
        $lists = $this->listContents($path, true);
        if (! $lists) {
            return;
        }
        $objectList = [];
        foreach ($lists as $value) {
            $objectList[] = $value['path'];
        }
        $this->oss->deleteObjects($this->bucket, $objectList);
    }

    /**
     * Create a directory.
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->oss->createObjectDir($this->bucket, $path);
    }

    /**
     * Set the visibility for a file.
     *
     * @return array|false file meta data
     *
     * Aliyun OSS ACL value: 'default', 'private', 'public-read', 'public-read-write'
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->oss->putObjectAcl(
            $this->bucket,
            $path,
            ($visibility == 'public') ? 'public-read' : 'private'
        );
    }

    /**
     * Check whether a file exists.
     */
    public function fileExists(string $path): bool
    {
        return $this->oss->doesObjectExist($this->bucket, $path);
    }

    /**
     * Read a file.
     */
    public function read(string $path): string
    {
        return $this->oss->getObject($this->bucket, $path);
    }

    /**
     * Read a file as a stream.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $resource = fopen('php://temp', 'r+');
        if ($contents !== '') {
            fwrite($resource, $contents);
            fseek($resource, 0);
        }

        return $resource;
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $directory = rtrim($path, '\\/');

        $result = [];
        $nextMarker = '';
        while (true) {
            // max-keys 用于限定此次返回object的最大数，如果不设定，默认为100，max-keys取值不能大于1000。
            // prefix   限定返回的object key必须以prefix作为前缀。注意使用prefix查询时，返回的key中仍会包含prefix。
            // delimiter是一个用于对Object名字进行分组的字符。所有名字包含指定的前缀且第一次出现delimiter字符之间的object作为一组元素
            // marker   用户设定结果从marker之后按字母排序的第一个开始返回。
            $options = [
                'max-keys' => 1000,
                'prefix' => $directory . '/',
                'delimiter' => '/',
                'marker' => $nextMarker,
            ];
            $res = $this->oss->listObjects($this->bucket, $options);

            // 得到nextMarker，从上一次$res读到的最后一个文件的下一个文件开始继续获取文件列表
            $nextMarker = $res->getNextMarker();
            $prefixList = $res->getPrefixList(); // 目录列表
            $objectList = $res->getObjectList(); // 文件列表
            if ($prefixList) {
                foreach ($prefixList as $value) {
                    $result[] = [
                        'type' => 'dir',
                        'path' => $value->getPrefix(),
                    ];
                    if ($deep) {
                        $result = array_merge($result, $this->listContents($value->getPrefix(), $deep));
                    }
                }
            }
            if ($objectList) {
                foreach ($objectList as $value) {
                    if (($value->getSize() === 0) && ($value->getKey() === $directory . '/')) {
                        continue;
                    }
                    $result[] = [
                        'type' => 'file',
                        'path' => $value->getKey(),
                        'timestamp' => strtotime($value->getLastModified()),
                        'size' => $value->getSize(),
                    ];
                }
            }
            if ($nextMarker === '') {
                break;
            }
        }

        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        return $this->oss->getObjectMeta($this->bucket, $path);
    }

    /**
     * Get the size of a file.
     */
    public function fileSize(string $path): FileAttributes
    {
        $response = $this->oss->getObjectMeta($this->bucket, $path);
        return new FileAttributes($path, $response['content-length']);
    }

    /**
     * Get the mimetype of a file.
     */
    public function mimeType(string $path): FileAttributes
    {
        $response = $this->oss->getObjectMeta($this->bucket, $path);
        return new FileAttributes($path, null, null, null, $response['content-type']);
    }

    /**
     * Get the timestamp of a file.
     */
    public function lastModified(string $path): FileAttributes
    {
        $response = $this->oss->getObjectMeta($this->bucket, $path);

        return new FileAttributes($path, null, null, $response['last-modified']);
    }

    /**
     * Get the visibility of a file.
     */
    public function visibility(string $path): FileAttributes
    {
        $response = $this->oss->getObjectAcl($this->bucket, $path);
        return new FileAttributes($path, null, $response);
    }

    /**
     * Get OSS Options.
     */
    private function getOssOptions(Config $config): array
    {
        $options = [];
        if ($headers = $config->get('headers')) {
            $options['headers'] = $headers;
        }

        if ($contentType = $config->get('Content-Type')) {
            $options['Content-Type'] = $contentType;
        }

        if ($contentMd5 = $config->get('Content-Md5')) {
            $options['Content-Md5'] = $contentMd5;
            $options['checkmd5'] = false;
        }
        return $options;
    }
}
