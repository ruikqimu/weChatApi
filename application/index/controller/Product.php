<?php
namespace app\index\controller;

use app\index\model\ProductModel;
use app\index\util\Common;
use app\index\util\LogUtil;
use think\Db;
use think\Exception;
use think\Request;

class Product extends CommonController
{

    private $log_model = "Product";
    public $message;

    public function __construct(Request $request)
    {
        parent::__construct($request);
//        LogUtil::writeLog($this->params,$this->log_model,__FUNCTION__,'请求参数');
    }

    /**
     * 获取商品详情表
     */
    public function getProductDetail()
    {
        try {
            //检查必传参数
            $str = 'proNo';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询商品轮播图片
            $field = "a.proNo,a.`name`,a.defaultImage,a.detail,a.category,a.price,a.exchangeType,a.exchangeCon,GROUP_CONCAT(b.image) as bimage";
            $map['a.proNo'] = $this->params['proNo'];
            $map['b.status'] = 0;
            $list = Db::table('product')->alias('a')
                ->join('product_img b', 'a.proNo=b.proNo')
                ->field($field)->order('b.id desc')
                ->where($map)->group('a.proNo')->find();

            if (empty($list)) {
                $this->returnError('该商品不存在');
            } else {
                $productImg = explode(',', $list['bimage']);
                $return['proNo'] = $list['proNo'];
                $return['name'] = $list['name'];
                $return['detail'] = $list['detail'];
                $return['category'] = $list['category'];
                $return['price'] = $list['price'];
                $return['exchangeType'] = $list['exchangeType'];
                $return['exchangeCon'] = $list['exchangeCon'];
                $return['imageList'] = $productImg;

                $this->returnSuccess('success', $return);
            }

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 查看商品的兑换记录
     */
    public function getProductChange()
    {
        try {
            //检查必传参数
            $str = 'proNo';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }


        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 检验用户当前能否兑换该产品
     */
    public function checkProductChange()
    {
        try {
            //检查必传参数
            $str = 'proNo,openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询商品属性
            $field = "proNo,name,defaultImage,category,exchangeType,exchangeCon";
            $product = Db::table('product')->field($field)->where(['proNo' => $this->params['proNo']])->find();

            if (empty($product)) $this->returnError('商品信息有误！');

            //判断商品库存
            if ($product['category'] <= 0) $this->returnError('商品库存不足');

            //获取用户的总步数和步动币
            $userInfo = Db::table('user')->where(['openId' => $this->params['openId']])->field('walkCoin,stepNumber')->find();
            if (empty($userInfo)) $this->returnError('查询用户信息失败');

            //判断用户兑换条件
            if ($product['exchangeType'] == 0) {
                //兑换条件为步数
                $stepNumber = $product['exchangeCon'] * 10000;
                $return['isEnough'] = $userInfo['stepNumber'] > $stepNumber ? 1 : 0;
            } else {
                //兑换条件为步动币
                $return['isEnough'] = $userInfo['walkCoin'] > $product['exchangeCon'] ? 1 : 0;
            }
            $return['proNo'] = $product['proNo'];
            $return['defaultImage'] = $product['defaultImage'];
            $return['name'] = $product['name'];
            $return['exchangeType'] = $product['exchangeType'];
            $return['exchangeCon'] = $product['exchangeCon'];
            $return['stepNumber'] = $userInfo['stepNumber'];
            $return['walkCoin'] = $userInfo['walkCoin'];

            $this->returnSuccess('success', $return);

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 兑换商品
     */
    public function exChangeProduct()
    {
        try {
            //检查必传参数
            $str = 'proNo,openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询商品属性
            $field = "proNo,category,exchangeType,exchangeCon,name,nature";
            $product = Db::table('product')->field($field)->where(['proNo' => $this->params['proNo']])->find();

            if (empty($product)) $this->returnError('商品信息有误！');

            //判断商品库存
            if ($product['category'] <= 0) $this->returnError('商品库存不足');

            //获取用户的总步数和步动币
            $userInfo = Db::table('user')->where(['openId' => $this->params['openId']])->field('name,walkCoin,stepNumber')->find();
            if (empty($userInfo)) $this->returnError('查询用户信息失败');

            $productModel = new ProductModel();
            //判断用户兑换条件
            if ($product['exchangeType'] == 0) {
                //兑换条件为步数
                $stepNumber = $product['exchangeCon'] * 10000;

                if ($userInfo['stepNumber'] < $stepNumber) {
                    $this->returnError('兑换步数不足');
                }

                //兑换并减去相应的数量
                $result = $productModel->productChange(0, $this->params['openId'], $stepNumber, $this->params['proNo'], $product['name'] , $product['nature']);

            } else {
                //兑换条件为步动币
                if ($userInfo['walkCoin'] < $product['exchangeCon']) {
                    $this->returnError('兑换步动币不足');
                }
                //兑换并减去相应的数量
                $result = $productModel->productChange(1, $this->params['openId'], $product['exchangeCon'], $this->params['proNo'], $product['name'] , $product['nature']);
            }

            if (!is_array($result) && !$result){
                $this->returnError('兑换失败，请稍后再试');
            }else{
                $res = $productModel->addNotice($product['name'],$product['exchangeType'],$userInfo['name']);
                if(!$res) LogUtil::writeLog($product,$this->log_model,'addNotice','添加消息失败');
                $return['nature'] = $product['nature'];
                $return['productId'] = $result['id'];
                $return['exchangeCode'] = $result['code'];
                $this->returnSuccess('success',$return);
            }


        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    /**
     * 商品兑换记录
     */
    public function exchangeRecord()
    {
        try {
            //检查必传参数
            $str = 'proNo,openId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询商品兑换记录
            $field = "a.name,a.date,b.name as uname,b.headImage";
            $map['a.proNo'] = $this->params['proNo'];
            $list = Db::table('product_exchange')->alias('a')->join('user b', 'a.openId=b.openId')
                ->field($field)->where($map)->order('a.date desc')
                ->limit(10)->select();

            $return = array();
            if(!empty($list)){
                //处理数据
                foreach ($list as $key => $value) {
                    //时间处理
                    $time = $this->time_tran($value['date']);
                    if (strpos($time, '前') === false) {
                        $return[$key]['date'] = substr($time, 5, 11);
                    } else {
                        $return[$key]['date'] = $time;
                    }

                    //名称处理
                    $return[$key]['uname'] = mb_substr($value['uname'], 0, 1) . '**';
                    $return[$key]['headImage'] = $value['headImage'];
                    $return[$key]['name'] = $value['name'];
                }
            }

            $this->returnSuccess('success',$return);

        } catch (Exception $e) {
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }


    private function time_tran($the_time)
    {
        $now_time = date("Y-m-d H:i:s", time());
        //echo $now_time;
        $now_time = strtotime($now_time);
        $show_time = strtotime($the_time);
        $dur = $now_time - $show_time;
        if ($dur < 0) {
            return $the_time;
        } else {
            if ($dur < 60) {
                return $dur . '秒前';
            } else {
                if ($dur < 3600) {
                    return floor($dur / 60) . '分钟前';
                } else {
                    if ($dur < 86400) {
                        return floor($dur / 3600) . '小时前';
                    } else {
                        if ($dur < 259200) {//3天内
                            return floor($dur / 86400) . '天前';
                        } else {
                            return $the_time;
                        }
                    }
                }
            }
        }
    }

    /**
     * 兑换的商品绑定收货地址
     */
    public function selectAddress()
    {
        try{
            //检查必传参数
            $str = 'openId,productId,addressId';
            if (!Common::checkParams($this->params, $str, $this->message)) {
                $this->returnError($this->message);
            }

            //查询兑换的商品
            $map['openId'] = $this->params['openId'];
            $map['id']     = $this->params['productId'];
            $product = Db::table('product_exchange')->where($map)->find();

            if(empty($product)) $this->returnError('未找到兑换记录');

            $res = Db::table('product_exchange')->where($map)->update(['addressId'=>$this->params['addressId']]);

            if($res || $res == '0'){
                $this->returnSuccess('success');
            }else{
                $this->returnError('绑定收货地址失败，请重试');
            }


        }catch (Exception $e){
            LogUtil::writeLog($e->getMessage() . $e->getLine(), $this->log_model, __FUNCTION__, '系统错误');
            $this->returnError($this->systemMessage);
        }
    }
}