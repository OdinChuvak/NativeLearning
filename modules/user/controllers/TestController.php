<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

final class TestController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'materials' => ['GET'], 'history' => ['GET'], 'history-view' => ['GET'], 'create' => ['POST'], 'options' => ['OPTIONS']];
    }

    private function service(): \app\modules\user\services\TestService
    {
        return new \app\modules\user\services\TestService(\Yii::$app->db, \Yii::$app->dbManager);
    }

    public function actionIndex(): array
    {
        return $this->service()->listing((int) $this->module->get('user')->id);
    }

    public function actionHistory(): array
    {
        $service = new \app\modules\user\services\TestHistoryService(\Yii::$app->db, \Yii::$app->dbManager);
        return $service->listing((int) $this->module->get('user')->id, max(1, (int) \Yii::$app->request->get('page', 1)));
    }

    public function actionHistoryView(int $courseId, int $id): array
    {
        $service = new \app\modules\user\services\TestHistoryService(\Yii::$app->db, \Yii::$app->dbManager);
        return $service->view((int) $this->module->get('user')->id, $courseId, $id);
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
