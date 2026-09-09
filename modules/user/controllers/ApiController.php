<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

abstract class ApiController extends \yii\rest\Controller
{
    protected array $publicActions = [];

    public function init(): void
    {
        parent::init();
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Cache-Control', 'no-store');
        \Yii::$app->request->parsers['application/json'] = \yii\web\JsonParser::class;
    }

    public function behaviors(): array
    {
        return [
            'cors' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    'Origin' => $this->module->corsOrigins,
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['Content-Type', 'Authorization'],
                    'Access-Control-Allow-Credentials' => null,
                ],
            ],
            'verbs' => ['class' => \yii\filters\VerbFilter::class, 'actions' => $this->verbs()],
            'authenticator' => [
                'class' => \yii\filters\auth\HttpBearerAuth::class,
                'user' => $this->module->get('user'),
                'except' => [...$this->publicActions, 'options'],
            ],
        ];
    }

    protected function pending(): never
    {
        throw new \yii\web\HttpException(501, 'Эндпоинт объявлен. Логика пока не реализована.');
    }

    public function actionOptions(): \yii\web\Response
    {
        \Yii::$app->response->statusCode = 204;
        return \Yii::$app->response;
    }
}
