<?php
declare(strict_types=1);

namespace app\modules\user\controllers;

use app\modules\user\services\PaymentService;
use Yii;
use yii\web\BadRequestHttpException;

class PaymentController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'options' => ['OPTIONS']];
    }

    public function actionIndex(): array
    {
        $page = Yii::$app->request->get('page', '1');
        if (!is_scalar($page) || filter_var($page, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new BadRequestHttpException('Номер страницы должен быть положительным целым числом.');
        }
        return (new PaymentService(Yii::$app->db))->listing((int) $this->module->get('user')->id, (int) $page);
    }
}
