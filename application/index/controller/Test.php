<?php
namespace app\index\controller;

use app\index\util\LogUtil;
use app\index\util\WXBizDataCrypt;
use think\Db;
use think\Exception;
use think\Request;

header('Content-type:text/html;charset=UTF-8');

class Test extends CommonController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function test (Request $request) {

    }




}