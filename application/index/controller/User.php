<?php
namespace app\index\controller;

use app\index\util\Common;
use app\index\util\LogUtil;
use think\Db;
use think\Exception;
use think\Paginator;
use think\Request;

class User extends CommonController
{

    private $log_model = 'User';
    public $message = '';

    private $page = 1;
    private $limit = 10;

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * 获取用户收货地址
     */
    public function getUserAddress()
    {
        try {
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            $map['openId'] = $this->params['openId'];
            $map['status'] = 1;
            $field = "id as addressId,name,mobile,prov,city,area,address,isMain";
            $list = Db::table('user_address')->field($field)->where($map)->select();

            if (empty($list)) $list = array();

            $this->returnSuccess('success', $list);

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 添加用户地址
     */
    public function addUserAddress()
    {
        try {
            //检查必传参数
            $str = 'openId,name,mobile,prov,city,area,address,isMain';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            if (isset($this->params['addressId'])) {
                //更新
                $map['id'] = $this->params['addressId'];
                $map['openId'] = $this->params['openId'];
                $update['name'] = $this->params['name'];
                $update['mobile'] = $this->params['mobile'];
                $update['prov'] = $this->params['prov'];
                $update['city'] = $this->params['city'];
                $update['area'] = $this->params['area'];
                $update['address'] = $this->params['address'];
                $update['isMain'] = $this->params['isMain'];
                $update['updateDate'] = Common::getDate();

                if ($this->params['isMain'] == 1) {
                    try {
                        //更新其他地址为普通地址
                        Db::startTrans();

                        //更新地址
                        Db::table('user_address')->where($map)->update($update);

                        //更新其他地址
                        $ohterMap['openId'] = $this->params['openId'];
                        $ohterMap['id'] = array('neq', $this->params['addressId']);

                        Db::table('user_address')->where($ohterMap)->update(['isMain' => 0]);
                        Db::commit();
                    } catch (Exception $e) {
                        LogUtil::writeLog($e->getMessage(), $this->log_model, __FUNCTION__, '更新地址失败');
                        LogUtil::close();
                        Db::rollback();
                        $this->returnError('更新地址失败');
                    }
                } else {
                    Db::table('user_address')->where($map)->update($update);
                }

            } else {
                //添加
                $insert['openId'] = $this->params['openId'];
                $insert['name'] = $this->params['name'];
                $insert['mobile'] = $this->params['mobile'];
                $insert['prov'] = $this->params['prov'];
                $insert['city'] = $this->params['city'];
                $insert['area'] = $this->params['area'];
                $insert['address'] = $this->params['address'];
                $insert['isMain'] = $this->params['isMain'];
                $insert['createDate'] = Common::getDate();

                if ($this->params['isMain'] == 1) {
                    try {
                        Db::startTrans();
                        $id = Db::table('user_address')->insert($insert, false, true);

                        //更新其他地址
                        $ohterMap['openId'] = $this->params['openId'];
                        $ohterMap['id'] = array('neq', $id);
                        Db::table('user_address')->where($ohterMap)->update(['isMain' => 0]);
                        Db::commit();
                    } catch (Exception $e) {
                        LogUtil::writeLog($e->getMessage(), $this->log_model, __FUNCTION__, '添加地址失败');
                        LogUtil::close();
                        Db::rollback();
                        $this->returnError('添加地址失败');
                    }
                } else {
                    Db::table('user_address')->insert($insert);
                }
            }

            $this->returnSuccess('success');
        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 删除地址
     */
    public function deleteAddress()
    {
        try {
            //检查必传参数
            $str = 'openId,addressId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //删除地址
            $map['openId'] = $this->params['openId'];
            $map['id'] = $this->params['addressId'];

            $update['status'] = 2;
            $update['deleteDate'] = Common::getDate();
            $res = Db::table('user_address')->where($map)->update($update);

            if (!$res) $this->returnError('删除失败，请稍后再试');
            else $this->returnSuccess('success');

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 获取步动币记录
     */
    public function showNumberRecord()
    {
        try {
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询用户获取记录
            $coinList = Db::table('user_coin_log')->field('number,type,getDate as date')->where(['openId' => $this->params['openId']])->order('getDate desc')->select();

            if (empty($coinList)) $coinList = array();

            $this->returnSuccess('success', $coinList);


        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 获取用户的兑换记录
     */
    public function userExchangeRecord()
    {
        try {
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            $map['a.openId'] = $this->params['openId'];

            $page = isset($this->params['page']) ? $this->params['page'] : $this->page;
            $limit = ($page - 1) * $this->limit . ',' . $this->limit;

            $field = "b.name,b.defaultImage,a.id,a.date,a.nature,a.express,a.waybillNum,a.receiveState,a.addressId,a.exchangeCode";
            $list = Db::table('product_exchange')->alias('a')->join('product b', 'a.proNo=b.proNo')
                ->field($field)->where($map)->limit($limit)->order('a.date desc')->select();

            if (empty($list)) {
                $list = array();
            }

            $this->returnSuccess('success', $list);

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }

    }

    public function getShareUser()
    {
        try {
            //检查必传参数
            $str = 'openId,shareOpenId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            LogUtil::writeLog($this->params, $this->log_model, __FUNCTION__, '获取分享参数');

            if($this->params['openId'] == $this->params['shareOpenId']) $this->returnSuccess('success');

            //邀请新用户注册

            //查询被邀请的用户是否已经注册，已注册不处理
            $user = Db::table('user')->where(['openId' => $this->params['openId']])->find();
            LogUtil::writeLog($user,$this->log_model,__FUNCTION__,'用户数据');
            $userModel = new \app\index\model\User();
            try {
                if (empty($user)) {
                    Db::startTrans();
                    //注册新用户
                    $insert['openId'] = $this->params['openId'];
                    $insert['createDate'] = Common::getDate();
                    $insert['loginDate'] = Common::getDate();
                    $insert['shareOpenId'] = $this->params['shareOpenId'];
                    LogUtil::writeLog($insert, $this->log_model, __FUNCTION__, '新用户注册参数');
                    $res = Db::table('user')->insert($insert);
                    if (!$res) $this->returnSuccess('success');

                    //给推荐人增加步动币

                    $res = $userModel->exchangeCoin($this->params['shareOpenId'], 1, $this->config['stepCoin']);
                    if (!$res) $this->returnSuccess('success');
                    Db::table('user')->where(['openId' => $this->params['shareOpenId']])->setInc('walkCoin', $this->config['stepCoin']);

                    //好友表互相添加
                    $userInsert[0]['openId'] = $this->params['openId'];
                    $userInsert[0]['friendOpenId'] = $this->params['shareOpenId'];
                    $userInsert[0]['createDate'] = Common::getDate();
                    $userInsert[1]['openId'] = $this->params['shareOpenId'];
                    $userInsert[1]['friendOpenId'] = $this->params['openId'];
                    $userInsert[1]['createDate'] = Common::getDate();
                    LogUtil::writeLog($userInsert, $this->log_model, __FUNCTION__, '好友互相添加');
                    $res = Db::table('user_friend')->insertAll($userInsert);
                    if (!$res) $this->returnSuccess('success');

                    //更新用户好友数
                    $map['openId'] = array('in', array($this->params['openId'], $this->params['shareOpenId']));
                    Db::table('user')->where($map)->setInc('friendNumber', 1);

                    //更新好友加持
                    $userModel->updateUserBless($this->params['openId']);
                    $userModel->updateUserBless($this->params['shareOpenId']);
                    Db::commit();
                } else {
                    Db::startTrans();
                    //查询
                    $friendMap['openId'] = $this->params['openId'];
                    $friendMap['friendOpenId'] = $this->params['shareOpenId'];
                    $friend = Db::table('user_friend')->where($friendMap)->find();
                    if(empty($friend)){
                        //好友表互相添加
                        $userInsert[0]['openId'] = $this->params['openId'];
                        $userInsert[0]['friendOpenId'] = $this->params['shareOpenId'];
                        $userInsert[0]['createDate'] = Common::getDate();
                        $userInsert[1]['openId'] = $this->params['shareOpenId'];
                        $userInsert[1]['friendOpenId'] = $this->params['openId'];
                        $userInsert[1]['createDate'] = Common::getDate();
                        $res = Db::table('user_friend')->insertAll($userInsert);
                        if(!$res){
                            Db::rollback();
                            LogUtil::writeLog($userInsert, $this->log_model, __FUNCTION__, '好友互相添加');
                            $this->returnSuccess('success');
                        }
                        //更新用户好友数
                        $map['openId'] = array('in', array($this->params['openId'], $this->params['shareOpenId']));
                        Db::table('user')->where($map)->setInc('friendNumber', 1);

                        //更新好友加持
                        $userModel->updateUserBless($this->params['openId']);
                        $userModel->updateUserBless($this->params['shareOpenId']);
                    }
                    //查询今日获取的邀请步数
                    $map['openId'] = $this->params['shareOpenId'];
                    $map['date'] = date('Y-m-d');
                    $count = Db::table('user_step_log')->where($map)->count();

                    if ($count <= $this->config['inviteNumber']) {
                        //添加步数
                        $insert['openId'] = $this->params['shareOpenId'];
                        $insert['step'] = $this->config['stepNumber'];
                        $insert['type'] = 1;
                        $insert['date'] = date("Y-m-d");
                        $insert['getDate'] = Common::getDate();
                        LogUtil::writeLog($insert, $this->log_model, __FUNCTION__, '邀请好友上线获取步数');
                        $res = Db::table('user_step_log')->insert($insert);
                        if (!$res){
                            Db::rollback();
                        }else{
                            Db::commit();
                        }
                    }
                }
                $this->returnSuccess('success');
            } catch (Exception $e) {
                LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '数据库处理失败');
                LogUtil::close();
                Db::rollback();
                $this->returnSuccess('success');
            }

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 用户兑换步动币
     */
    public function userExchangeCoin()
    {
        try {
            //检查必传参数
            $str = 'openId,number';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            if (!is_numeric($this->params['number'])) $this->returnError('兑换数量类型不正确');

            if ($this->params['number'] < $this->config['limitStep']) $this->returnError($this->config['limitStep'] . '步以上才能兑换');

            //查询用户当前步动币
            $user = Db::table('user')->where(['openId' => $this->params['openId']])->find();
            if (empty($user)) $this->returnError('用户信息不存在');

            //查询当日已兑换的步动币
            $coinMap['openId'] = $this->params['openId'];
            $coinMap['type'] = 0;
            $coinMap['date'] = date('Y-m-d');
            $userCoin = Db::table('user_coin_log')->where($coinMap)->sum('number');

            if ($userCoin > $this->config['exchangeCoin']) $this->returnError('当日最多只能兑换'.$this->config['exchangeCoin'].'个步动币');

            if ($this->params['number'] > $user['stepNumber']) $this->returnError('兑换步数不能高于当前步数');

            $number = round($this->params['number'] / $this->config['coinPer'], 2);

            $canCoin = 5 - $userCoin;

            if ($number > $canCoin) $this->returnError('当日还能兑换' . $canCoin . '个步动币');

            //用户兑换记录增加
            $userModel = new \app\index\model\User();
            Db::startTrans();
            $userModel->exchangeCoin($this->params['openId'], 0, $number);

            //用户兑换记录增加
            $insert['openId'] = $this->params['openId'];
            $insert['stepNumber'] = $this->params['number'];
            $insert['walkCoin'] = $number;
            $insert['date'] = date('Y-m-d');
            $insert['getDate'] = Common::getDate();
            Db::table('user_exchange')->insert($insert);

            //更新当前用户表
            $update['stepNumber'] = $user['stepNumber'] - $this->params['number'];
            $update['walkCoin'] = $user['walkCoin'] + $number;
            Db::table('user')->where(['openId' => $this->params['openId']])->update($update);
            Db::commit();
            $this->returnSuccess('success');
        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            LogUtil::close();
            Db::rollback();
            $this->returnError('兑换失败,请稍后再试');
        }
    }

    /**
     * 赠送好友步动币
     */
    public function giveUserCoin()
    {
        try{
            //检查必传参数
            $str = 'openId,giveOpenId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }
            //赠送步动币
            $coin = 1;

            //查询用户步动币
            $user = Db::table('user')->where(['openId'=>$this->params['openId']])->column('walkCoin');
            $userCoin = $user[0];

            if($userCoin < $coin) $this->returnError('酷行币不足');


            //查询今日是否已经有赠送记录
            $map['openId']      = $this->params['openId'];
            $map['giveOpenId']  = $this->params['giveOpenId'];
            $map['time']        = date("Y-m-d");
            $result = Db::table('user_give_log')->where($map)->find();
            if(!empty($result)) $this->returnError('今日已赠送过！');

            Db::startTrans();
            $insert['openId']       = $this->params['openId'];
            $insert['giveOpenId']   = $this->params['giveOpenId'];
            $insert['number']       = $coin;
            $insert['time']         = date("Y-m-d");
            $insert['date']         = Common::getDate();
            $res = Db::table('user_give_log')->insert($insert,false,true);

            if(!$res){
                Db::rollback();
                LogUtil::writeLog($insert,$this->log_model,__FUNCTION__,'赠送记录添加失败');
                $this->returnError('赠送失败！');
            }

            //添加用户获取记录
            $addCoin[0]['openId'] = $this->params['openId'];
            $addCoin[0]['number'] = $coin;
            $addCoin[0]['type']   = 3;
            $addCoin[0]['date']   = date("Y-m-d");
            $addCoin[0]['getDate']= Common::getDate();
            $addCoin[1]['openId'] = $this->params['giveOpenId'];
            $addCoin[1]['number'] = $coin;
            $addCoin[1]['type']   = 4;
            $addCoin[1]['date']   = date("Y-m-d");
            $addCoin[1]['getDate']= Common::getDate();
            $res = Db::table('user_coin_log')->insertAll($addCoin);

            if(!$res){
                Db::rollback();
                LogUtil::writeLog($insert,$this->log_model,__FUNCTION__,'赠送获取记录添加失败');
                $this->returnError('赠送失败！');
            }

            //用户表减去相应的步动币
            Db::table('user')->where(['openId'=>$this->params['openId']])->setDec('walkCoin',$coin);
            Db::table('user')->where(['openId'=>$this->params['giveOpenId']])->setInc('walkCoin',$coin);

            Db::commit();
            $this->returnSuccess('success');

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            LogUtil::close();
            Db::rollback();
            $this->returnError('赠送失败,请稍后再试');
        }
    }
}