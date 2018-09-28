<?php
namespace app\index\model;

use app\index\util\Common;
use app\index\util\LogUtil;
use think\Db;
use think\Exception;

class ProductModel{

    /**
     * 记录用户兑换商品
     * @param $type
     * @param $openId
     * @param $number
     * @param $proNo
     * @param $name
     * @param $nature
     * @return bool
     */
    public function productChange($type , $openId ,$number , $proNo , $name , $nature)
    {
        try{
            $exchangeCode = 0;
            if($nature == 0){
                //查询最近一个兑换码
                $code = Db::table('product_code')->where(['proNo'=>$proNo,'status'=>0])->order('id')->find();
                if(!empty($code)){
                    $exchangeCode = $code['exchangeCode'];
                }else{
                    $exchangeCode = -1;
                }
            }

            //开启事务
            Db::startTrans();

            //更新虚拟码使用状态
            $updateCode['status'] = 1;
            $updateCode['updateDate'] = Common::getDate();
            Db::table('product_code')->where(['exchangeCode'=>$exchangeCode])->update($updateCode);

            //商品兑换表添加数据
            $insert['openId'] = $openId;
            $insert['proNo']  = $proNo;
            $insert['type']   = $type;
            $insert['number'] = $number;
            $insert['name']   = $name;
            $insert['nature'] = $nature;
            $insert['exchangeCode'] = $exchangeCode;
            $insert['date']   = Common::getDate();
            $insert['time']   = date("Y-m-d");

            $id = Db::table('product_exchange')->insert($insert,false,true);

            //减少库存
            Db::table('product')->where(['proNo'=>$proNo])->setDec('category');

            //更新用户当日总步数或者步动币
            if($type == 0){
                //步数兑换
                Db::table('user')->where(['openId'=>$openId])->setDec('stepNumber',$number);
            }else{
                //步动币兑换
                Db::table('user')->where(['openId'=>$openId])->setDec('walkCoin',$number);
                $userModel = new User();
                //步动币使用记录
                $result = $userModel->exchangeCoin($openId,2,$number);
                if(!$result){
                    Db::rollback();
                    return false;
                }
            }

            // 提交事务
            Db::commit();

            $return['id']   = $id;
            $return['code'] = $exchangeCode;
            return $return;

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),'Product',__FUNCTION__,'兑换失败');
            LogUtil::close();
            //事务回滚
            Db::rollback();
            return false;
        }
    }


    /**
     * 添加兑换消息
     * @param $name
     * @param $type
     * @return bool
     */
    public function addNotice($name,$type,$uname){
        try{
            //增加临时内存
            ini_set('memory_limit','1024M');
            set_time_limit(0);
            //查询所有用户
            $userList = Db::table('user')->field('openId')->select();

            foreach($userList as $key => $value){
                $add[$key]['openId']      = $value['openId'];
                $add[$key]['uname']       = $uname;
                $add[$key]['name']        = $name;
                $add[$key]['type']        = $type;
                $add[$key]['createDate']  = Common::getDate();
            }
            $res = Db::table('user_notice')->insertAll($add);

            if(!$res){
                LogUtil::writeLog($add,'Product',__FUNCTION__,'添加消息失败');
                return false;
            }else{
                return true;
            }

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),'Product',__FUNCTION__,'添加兑换消息');
            LogUtil::close();
            return false;
        }
    }
}