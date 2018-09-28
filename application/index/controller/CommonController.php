<?php
namespace app\index\controller;

use app\index\util\LogUtil;
use app\index\util\WebResult;
use think\Db;
use think\Request;

class CommonController{

    public $systemMessage = '系统错误,请联系客服';
    public $config = array();

    public $params;

    public function __construct(Request $request)
    {
        header("Access-Control-Allow-Origin: http://a.com"); // 允许a.com发起的跨域请求
        //如果需要设置允许所有域名发起的跨域请求，可以使用通配符 *
        header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求
        header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');

        //上传参数
        $this->params = $request->post();

        //获取公共配置
        $this->config = Db::table('config')->find();


    }

    public function returnSuccess($message='success',$data = null)
    {
        LogUtil::close();
        echo WebResult::response200($message,$data);
        exit;
    }

    public function returnError($message='error')
    {
        LogUtil::close();
        echo WebResult::response101($message);
        exit;
    }
}