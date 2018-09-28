<?php
namespace app\index\util;
use think\Db;
use think\Exception;
class Common{

    /**
     * 检查必传字段信息
     * @param $param 验证参数
     * @param string $str 多个字段逗号拼接
     * @param $message
     * @return bool|void
     */
    public static function checkParams($param, $str, &$message){
        if(empty($str)) return;

        $arr = explode(',',$str);

        foreach($arr as $key => $value){
            if(!isset($param[$value])){
                $message = "缺少参数" . $value;
                return false;
            }
            if(empty($param[$value]) && $param[$value] !== '0' ){
                $message = "缺少参数" . $value;
                return false;
            }
            if($param[$value] == 'undefined'){
                $message = "参数错误" . $value;
                return false;
            }
        }
        return true;
    }

    /**
     *获取日期格式
     */
    public static function getDate(){
        return date("Y-m-d H:i:s",time());
    }

}