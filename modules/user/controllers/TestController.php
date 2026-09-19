<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

final class TestController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'materials' => ['GET'], 'create' => ['POST'], 'options' => ['OPTIONS']];
    }

    private function service(): \app\modules\user\services\TestService
    {
        return new \app\modules\user\services\TestService(\Yii::$app->db);
    }

    public function actionIndex(): array
    {
        return $this->service()->listing((int) $this->module->get('user')->id);
    }

    public function actionMaterials(): array
    {
        return $this->service()->materials((int) $this->module->get('user')->id);
    }

    public function actionCreate(): array
    {
        $body = \Yii::$app->request->bodyParams;
        $result = $this->service()->create((int) $this->module->get('user')->id, $body['name'] ?? null, $body['materials'] ?? null, $body['question_count'] ?? null);
        \Yii::$app->response->statusCode = 201;
        return $result;
    }
}
