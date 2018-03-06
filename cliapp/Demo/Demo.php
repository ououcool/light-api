<?php

namespace CliApp\Demo;
use LightApi\BaseCliApp;

class Demo extends BaseCliApp
{
    /**
     * 示例
     * @return array
     */
    public function test()
    {
        $data = [];
        return $this->endResponse($data);
    }
}