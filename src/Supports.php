<?php

declare(strict_types=1);

namespace Xxtime\Flysystem\Aliyun;

class Supports
{
    private $flashData;

    public function setFlashData($data = null)
    {
        $this->flashData = $data;
    }

    public function getFlashData()
    {
        $flash = $this->flashData;
        $this->flashData = null;
        return $flash;
    }
}
