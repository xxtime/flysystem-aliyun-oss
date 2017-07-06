## flysystem-aliyun-oss 
AliYun OSS Storage adapter for flysystem - a PHP filesystem abstraction.

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