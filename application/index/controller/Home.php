<?php
namespace app\index\controller;

use app\index\model\User;
use app\index\util\Common;
use app\index\util\LogUtil;
use think\Db;
use think\Exception;
use think\Request;

class Home extends CommonController{

    private $log_model = 'Home';

    private $message;

    private $limit = 10;
    private $page = 1;

    public function __construct(Request $request)
    {
        parent::__construct($request);
//        LogUtil::writeLog($this->params,$this->log_model,__FUNCTION__,'请求参数');
    }

    /**
     * 上传用户信息
     */
    public function uploadUserInfo()
    {
        try{
            //检查必传参数
            $str = 'openId,name,headImage,wxData,wxIv,sessionKey';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询用户存不存在，不存在添加数据
            $userList = Db::table('user')->where(['openId'=>$this->params['openId']])->find();

            //返回数据
            $return = array();
            $return['walkCoin']     = '0';
            $return['friendNumber'] = '0';
            $return['friendBless']  = '100';


            if(empty($userList)){
                //添加
                $mark = false;
                $insert['openId']       = $this->params['openId'];
                $insert['name']         = $this->params['name'];
                $insert['headImage']    = $this->params['headImage'];
                $insert['createDate']   = Common::getDate();
                $insert['loginDate']    = Common::getDate();
                $res = Db::table('user')->insert($insert);
            }else{
                //更改
                $mark = true;
                $map['openId']          = $this->params['openId'];
                $update['name']         = $this->params['name'];
                $update['headImage']    = $this->params['headImage'];
                $update['loginDate']    = Common::getDate();
                $res = Db::table('user')->where($map)->update($update);

                $return['walkCoin']     = $userList['walkCoin'];
                $return['friendNumber'] = $userList['friendNumber'];
                $return['friendBless']  = $userList['friendBless'];
            }

            if(!$res){
                $this->returnError('信息同步失败，请稍后再试！');
            }


            //获取微信用户
            $userModel = new User();

            //解密微信步数
            $step = $userModel->getNowDayStep($this->params['wxData'],$this->params['wxIv'],$this->params['sessionKey']);
            if(!$step && $step != 0) $this->returnError($userModel->errorCode);

            $res = $userModel->getUserWxStep($this->params['openId'],$step,$this->config['oneDayStepNumber']);
            if(!$res && $res != 0) $this->returnError('信息同步失败，请稍后再试！');

            //返回数据给前端
            $return['stepNumber']    = $res;
            $return['exchangeLimit'] = $this->config['exchangeCoin'];
            $return['friendShare'] = array();
            if($mark){
                //非首次进来，查询该用户推荐的好友
                $friend = Db::table('user')->where(['shareOpenId'=>$this->params['openId']])->field('headImage,openId')->order('id desc')->limit(4)->select();
                if(!empty($friend)){
                    $return['friendShare'] = $friend;
                }
            }

            $this->returnSuccess('success',$return);
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage().$e->getLine(),$this->log_model,__FUNCTION__,'系统错误');
            $this->returnError($this->systemMessage);
        }

    }

    /**
     * 获取首页相关信息
     */
    public function getBannerList()
    {
        try{
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询banner表
            $map['status'] = 0;
            $list = Db::table('banner')->where($map)->order('sort desc')->field('imageUrl,link')->select();

            $this->returnSuccess('success',$list);

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage().$e->getLine(),$this->log_model,__FUNCTION__,'系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 获取商品列表接口
     */
    public function getGoodList()
    {
        try{
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //分页
            if(isset($this->params['page'])){
                $this->page = $this->params['page'];
            }

            $limit = ($this->page - 1) * $this->limit .','.$this->limit ;

            $field = "proNo,name,defaultImage,category,price,exchangeType,exchangeCon,tag";
            $list = Db::table('product')->where(['status'=>0])->field($field)->order('sort asc')->limit($limit)->select();

            $this->returnSuccess('success',$list);

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage().$e->getLine(),$this->log_model,__FUNCTION__,'系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 获取步友榜数据
     */
    public function getUserRank()
    {
        try{
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询用户好友openid
            $friendList = Db::table('user_friend')->where(['openId'=>$this->params['openId']])->field('friendOpenId')->select();

            $userStr = $this->params['openId'];
            if(!empty($friendList)){
                foreach($friendList as $value){
                    $userStr .=  ','.$value['friendOpenId'];
                }
            }

            //查询步数。然后排行
            $userMap['openId'] = array('in',$userStr);
            $list = Db::table('user')->where($userMap)->field('openId,stepNumber,name,headImage,loginDate')->order('stepNumber desc')->select();

            $user = array();
            foreach($list as $key => &$value){
                if($value['openId'] == $this->params['openId']){
                    $user['stepNumber'] = $value['stepNumber'];
                    $user['rank']       = $key + 1;
                }

                $time = substr($value['loginDate'],0,10);

                if($time == date("Y-m-d")){
                    $value['isLogin'] = 1;
                }else{
                    $value['isLogin'] = 0;
                }
            }

            $return['userList'] = $list;
            $return['ownData']  = $user;

            $this->returnSuccess('success',$return);

        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage().$e->getLine(),$this->log_model,__FUNCTION__,'系统错误');
            $this->returnError($this->systemMessage);
        }
    }

    /**
     * 获取最近的兑换消息
     */
    public function getExchangeData()
    {
        try{
            //检查必传参数
            $str = 'openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询最近的一条兑换记录
            $map['openId'] = $this->params['openId'];
            $map['status'] = 0;
            $list = Db::table('user_notice')->where($map)->field('id,uname,name,type')->order('id asc')->find();

            if(empty($list)){
                $this->returnSuccess('success',array());
            }else{
                Db::table('user_notice')->where(['id'=>$list['id']])->update(['status'=>1]);
                $this->returnSuccess('success',$list);
            }
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage().$e->getLine(),$this->log_model,__FUNCTION__,'系统错误');
            $this->returnError($this->systemMessage);
        }
    }

}