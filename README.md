## flysystem-aliyun-oss 
AliYun OSS Storage adapter for flysystem - a PHP filesystem abstraction.  

[![Author](http://img.shields.io/badge/author-@Joe-blue.svg?style=flat-square)](https://www.xxtime.com)
[![Code Climate](https://codeclimate.com/github/xxtime/flysystem-aliyun-oss/badges/gpa.svg)](https://codeclimate.com/github/xxtime/flysystem-aliyun-oss)
[![Total Downloads](https://img.shields.io/packagist/dt/xxtime/flysystem-aliyun-oss.svg?style=flat-square)](https://packagist.org/packages/xxtime/flysystem-aliyun-oss)

### Installation
composer require xxtime/flysystem-aliyun-oss

### Usage

```php
use League\Flysystem\Filesystem;
use Xt\Flysystem\Aliyun\OssAdapter;

$filesystem = new Filesystem(new OssAdapter([
    'access_id'     => 'aliyun access_key_id',
    'access_secret' => 'aliyun access_key_secret',
    'bucket'        => 'aliyun bucket',

    // 'endpoint'       => 'oss-cn-shanghai.aliyuncs.com',
    // 'timeout'        => 3600,
    // 'connectTimeout' => 10,
]));

$filesystem->write('path/to/file.txt', 'contents');
```

### Reference
http://flysystem.thephpleague.com/api/  
https://github.com/thephpleague/flysystem  