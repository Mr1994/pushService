<?php


namespace app\controllers;

use ArrayObject;
use yii\rest\Controller;

class SiteController extends Controller
{
    // 该回调地址关闭csrf验证
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['rateLimiter']);
        return $behaviors;
    }

    public function actionIndex()
    {
        return [
            'code'   => 0,
            'errMsg' => '请求成功,参数非法',
            'data'   => new ArrayObject()
        ];
    }
}