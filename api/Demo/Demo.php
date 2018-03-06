<?php
namespace api\Demo;

use common\helpers\Tata;
use LightApi\BaseApi;

class Demo extends BaseApi
{
    /**
     * 示例
     * @return array
     */
    public function test($params = null)
    { 
        $data = ['result' => "WELCOME!"];
        return $this->endResponse($data);
    }

    /**
     * tata示例
     * @return array
     */
    public function tata($params = null)
    {
        $data = Tata::usercenter()->call('Demo.Demo.test', []);
        return $this->endResponse($data);
    }
}