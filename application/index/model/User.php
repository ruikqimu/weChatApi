<?php
namespace app\index\model;

use app\index\util\Common;
use app\index\util\ErrorCode;
use app\index\util\LogUtil;
use think\Db;
use think\Exception;

class User{
    public $errorCode = '';

    private $appId = 'wxd480c8b6e0fbdba1';

    /**
     * 获取微信步数
     * @param $openId
     * @param $step
     * @return mixed
     * @throws \think\Exception
     */
    public function getUserWxStep($openId,$step,$allStep)
    {
        $map['openId'] = $openId;
        $map['date']   = date("Y-m-d");
        $map['type']   = 0;
        $userStep = Db::table('user_step_log')->where($map)->find();
        if(empty($userStep)){
            //添加当日信息
            $insert['openId'] = $openId;
            $insert['date']   = date("Y-m-d");
            $insert['step']   = $step;
            $insert['getDate']= Common::getDate();
            Db::table('user_step_log')->insert($insert);
        }else{
            if($userStep['step'] != $step){
                Db::table('user_step_log')->where($map)->update(['step'=>$step]);
            }
        }

        //更新用户表总步数
        $allMap['openId'] = $openId;
        $allMap['date']   = date("Y-m-d");
        $nowStep = Db::table('user_step_log')->where($allMap)->sum('step');

        //查询用户当天有没有兑换记录
        $changeMap['openId'] = $openId;
        $changeMap['time']   = date("Y-m-d");
        $changeMap['type']   = 0;
        $changeStep = Db::table('product_exchange')->where($changeMap)->sum('number');

        //查询用户有没有兑换步动币记录
        $changeCoinMap['openId'] = $openId;
        $changeCoinMap['date']   = date("Y-m-d");
        $changeCoin = Db::table('user_exchange')->where($changeCoinMap)->sum('stepNumber');

        $allChange = $changeStep + $changeCoin;

        $tempStep = $nowStep > $allStep ? $allStep : $nowStep;
        if($allChange != 0){
            $update['stepNumber'] = $tempStep - $allChange;
        }else{
            $update['stepNumber'] = $tempStep;
        }

        if($update['stepNumber'] < 0) $update['stepNumber'] = 0;

        $res =  Db::table('user')->where(['openid'=>$openId])->update($update);
        if($res || $res == '0'){
            return $update['stepNumber'];
        }else{
            return false;
        }
    }


    /**
     * 获取微信步数
     * @param $wxData
     * @param $wxIv
     * @param $sessionKey
     * @return bool
     */
    public function getNowDayStep($wxData,$wxIv,$sessionKey)
    {
        try{
            $res  = $this->decryptData($wxData,$wxIv,$sessionKey,$data);
            LogUtil::writeLog($data,'User',__FUNCTION__,'微信步数解密');
            if($res != '0'){
                $this->errorCode = $res;
                return false;
            }else{
                //解密数据
                $result = json_decode($data,true);

                //取当天的步数
                $step = $result['stepInfoList'][30]['step'];
                return $step;
            }
        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage(),'User',__FUNCTION__,'解密失败');
            LogUtil::close();
            $this->errorCode = '数据异常';
            return false;
        }

    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $sessionKey string 用户sessionKey
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData( $encryptedData, $iv,$sessionKey, &$data )
    {
        if (strlen($sessionKey) != 24) {
            return ErrorCode::$IllegalAesKey;
        }
        $aesKey=base64_decode($sessionKey);


        if (strlen($iv) != 24) {
            return ErrorCode::$IllegalIv;
        }
        $aesIV=base64_decode($iv);

        $aesCipher=base64_decode($encryptedData);

        $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

        $dataObj=json_decode( $result );
        if( $dataObj  == NULL )
        {
            return ErrorCode::$IllegalBuffer;
        }
        if( $dataObj->watermark->appid != $this->appId )
        {
            return ErrorCode::$IllegalBuffer;
        }
        $data = $result;
        return ErrorCode::$OK;
    }


    /**
     * 步动币兑换记录
     * @param $openId
     * @param $type
     * @param $number
     * @return bool
     */
    public function exchangeCoin($openId,$type,$number)
    {
        try{
            $insert['openId'] = $openId;
            $insert['type']   = $type;
            $insert['number'] = $number;
            $insert['date']   = date("Y-m-d");
            $insert['getDate']= Common::getDate();

            $res = Db::table('user_coin_log')->insert($insert);
            LogUtil::writeLog($insert,'User',__FUNCTION__,'添加记录参数');
            if(!$res){
                return false;
            }else{
                return true;
            }
        }catch (Exception $e){
            LogUtil::writeLog($openId.'--'.$type.'--'.$number,'User',__FUNCTION__,'记录步动币失败');
            LogUtil::close();
            return false;
        }
    }

    /**
     * 更新好友加持
     * @param $openId
     * @return bool
     * @throws Exception
     */
    public function updateUserBless($openId)
    {
        //查询用户好友数
        $count = Db::table('user_friend')->where(['openId'=>$openId])->count();
        $bless = 100 + $count;

        Db::table('user')->where(['openId'=>$openId])->update(['friendBless'=>$bless]);

        return true;
    }

}