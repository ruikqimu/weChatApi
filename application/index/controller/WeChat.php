<?php
namespace app\index\controller;

use think\Request;

class WeChat extends CommonController{

    private $appId  = 'wxd480c8b6e0fbdba1';
    private $secret = 'c07523006b342e7abebd21e38167da35';


    public function __construct(Request $request)
    {
        parent::__construct($request);
    }


    /**
     * 获取微信openId和sessionKey
     * @param Request $request
     */
    public function getOpenId()
    {
        $code = $this->params['code'];
        if(empty($code)) $this->returnError('缺少参数code');

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$this->appId.'&secret='.$this->secret.'&js_code='.$code.'&grant_type=authorization_code';

        $res = file_get_contents($url);

        $result = json_decode($res,true);

        if(isset($result['errcode'])){
            $this->returnError($result['errmsg']);
        }else{
            $this->returnSuccess('success',$result);
        }

    }

}