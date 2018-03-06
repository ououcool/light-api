<?php
namespace Api\Demo;

use LightApi\BaseApi;

class Demo extends BaseApi
{
    /**
     * 示例
     * @return array
     */
    public function test($params = null)
    { 
        $data = ['result' => "DONE"];
        return $this->endResponse($data);
    }
}