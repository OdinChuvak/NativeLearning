<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

final class ActiveTestController extends ApiController
{
    protected function verbs(): array
    {
        return ['start' => ['POST'], 'resume' => ['POST'], 'answer' => ['POST'], 'timeout' => ['POST'], 'finish' => ['POST'], 'cancel' => ['POST'], 'options' => ['OPTIONS']];
    }

    private function handle(string $action): array
    {
        $body = \Yii::$app->request->bodyParams;
        foreach (['course_id', $action === 'start' ? 'test_id' : 'attempt_id'] as $key) {
            if (!is_int($body[$key] ?? null) || $body[$key] < 1) throw new \yii\web\BadRequestHttpException('Некорректный идентификатор.');
        }
        $service = new \app\modules\user\services\ActiveTestService(\Yii::$app->db, \Yii::$app->dbManager);
        $user = (int) $this->module->get('user')->id;
        return $action === 'start'
            ? $service->start($user, $body['course_id'], $body['test_id'])
            : $service->act($user, $body['course_id'], $body['attempt_id'], $action, $body);
    }

    public function actionStart(): array { return $this->handle('start'); }
    public function actionResume(): array { return $this->handle('resume'); }
    public function actionAnswer(): array { return $this->handle('answer'); }
    public function actionTimeout(): array { return $this->handle('timeout'); }
    public function actionFinish(): array { return $this->handle('finish'); }
    public function actionCancel(): array { return $this->handle('cancel'); }
}
