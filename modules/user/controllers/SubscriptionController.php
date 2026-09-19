<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

use app\modules\user\services\SubscriptionService;
use Yii;
use yii\web\BadRequestHttpException;

final class SubscriptionController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'tariffs' => ['GET'], 'pay' => ['POST'], 'options' => ['OPTIONS']];
    }

    public function actionIndex(): array
    {
        return (new SubscriptionService(Yii::$app->db))->summary((int) $this->module->get('user')->id);
    }

    public function actionTariffs(): array
    {
        return (new SubscriptionService(Yii::$app->db))->tariffs((int) $this->module->get('user')->id);
    }

    public function actionPay(): array
    {
        $body = Yii::$app->request->bodyParams;
        $id = $body['tariff_id'] ?? null;
        $code = $body['discount_code'] ?? null;
        $expected = $body['expected_amount'] ?? null;
        if (!is_int($id) || $id < 1
            || ($code !== null && (!is_string($code) || mb_strlen($code) > 64))
            || ($expected !== null && (!is_string($expected) || !preg_match('/^\d{1,10}\.\d{2}$/D', $expected)))) {
            throw new BadRequestHttpException('Некорректные параметры оплаты.');
        }
        return (new SubscriptionService(Yii::$app->db))->pay((int) $this->module->get('user')->id, $id, $code === null ? null : trim($code), $expected);
    }
}
