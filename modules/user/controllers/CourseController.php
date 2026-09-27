<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

final class CourseController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'view' => ['GET'], 'activate' => ['POST'], 'options' => ['OPTIONS']];
    }

    private function service(): \app\modules\user\services\CourseService
    {
        return new \app\modules\user\services\CourseService(\Yii::$app->db, \Yii::$app->dbManager);
    }

    public function actionIndex(): array
    {
        $page = \Yii::$app->request->get('page', '1');
        $scope = \Yii::$app->request->get('scope', 'catalog');
        if (!is_scalar($page) || filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new \yii\web\BadRequestHttpException('Некорректный номер страницы.');
        }
        if (!in_array($scope, ['mine', 'catalog'], true)) {
            throw new \yii\web\BadRequestHttpException('Некорректный раздел курсов.');
        }
        return $this->service()->listing((int) $this->module->get('user')->id, $scope, (int) $page);
    }

    public function actionView(int $id): array
    {
        return $this->service()->details((int) $this->module->get('user')->id, $id);
    }

    public function actionActivate(int $id): array
    {
        return (new \app\modules\user\services\SubscriptionService(\Yii::$app->db))->activate((int) $this->module->get('user')->id, $id);
    }

}
