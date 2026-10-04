<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

final class DashboardController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'chart' => ['GET'], 'options' => ['OPTIONS']];
    }

    public function actionIndex(): array
    {
        return (new \app\modules\user\services\DashboardService(\Yii::$app->db, \Yii::$app->dbManager))
            ->summary((int) $this->module->get('user')->id);
    }

    public function actionChart(): array
    {
        $period = \Yii::$app->request->get('period', 'year');
        $course = \Yii::$app->request->get('course_id');
        if (!in_array($period, ['year', 'month'], true)
            || ($course !== null && filter_var($course, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            throw new \yii\web\BadRequestHttpException('Некорректные параметры графика.');
        }
        return (new \app\modules\user\services\ScoreChartService(\Yii::$app->db, \Yii::$app->dbManager))
            ->chart((int) $this->module->get('user')->id, $period, $course === null ? null : (int) $course);
    }
}
