<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\models\User;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

final class AuthController extends \yii\rest\Controller
{
    public function init(): void
    {
        parent::init();
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->request->parsers['application/json'] = \yii\web\JsonParser::class;
    }

    /**
     * @throws InvalidConfigException
     */
    public function behaviors(): array
    {
        return [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => $this->module->corsOrigins,
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['Content-Type', 'Authorization'],
                    'Access-Control-Allow-Credentials' => null,
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create-user' => ['POST', 'OPTIONS'],
                    'login' => [
                        'POST',
                        'OPTIONS'
                    ],
                    'me' => [
                        'GET',
                        'OPTIONS'
                    ],
                    'logout' => [
                        'POST',
                        'OPTIONS'
                    ]
                ],
            ],
            'authenticator' => [
                'class' => HttpBearerAuth::class,
                'user' => $this->module->get('user'),
                'except' => ['login', 'create-user'],
            ],
        ];
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     * @throws UnauthorizedHttpException
     * @throws BadRequestHttpException
     */
    public function actionLogin(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $username = $body['username'] ?? null;
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (($username !== null) === ($email !== null)
            || ($username !== null && (!is_string($username) || $username === '' || mb_strlen($username) > 100))
            || ($email !== null && (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)))
            || !is_string($password) || $password === '' || strlen($password) > 4096) {
            throw new BadRequestHttpException('Укажите username либо email и password строками.');
        }

        $user = $email !== null ? User::findByEmail($email) : User::findByUsername($username);
        try {
            $valid = $user !== null && Yii::$app->security->validatePassword($password, $user->password_hash);
        } catch (\yii\base\InvalidArgumentException) {
            $valid = false;
        }
        if (!$valid) {
            throw new UnauthorizedHttpException('Неверный логин или пароль.');
        }

        $token = Yii::$app->security->generateRandomString(64);
        $user->auth_key = hash('sha256', $token);
        $user->save(false, ['auth_key']);

        return ['auth_key' => $token, 'token_type' => 'Bearer', 'user' => ['id' => $user->id, 'username' => $user->username]];
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionCreateUser(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $username = $body['username'] ?? null;
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($username) || trim($username) === '' || mb_strlen($username) > 100
            || !is_string($password) || $password === '' || strlen($password) > 72
            || str_contains($password, "\0")) {
            throw new BadRequestHttpException('Укажите логин до 100 символов и пароль от 1 до 72 байт без нулевых символов.');
        }

        if (User::findByUsername($username) !== null) {
            throw new \yii\web\ConflictHttpException('Пользователь с таким логином уже существует.');
        }
        if ($email !== null) {
            if (!is_string($email) || strlen($email) > 254 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                throw new BadRequestHttpException('Укажите корректный email.');
            }
            $email = strtolower(trim($email));
            if (User::findByEmail($email) !== null) {
                throw new \yii\web\ConflictHttpException('Пользователь с таким email уже существует.');
            }
        }
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->password_hash = Yii::$app->security->generatePasswordHash($password);
        $user->auth_key = null;
        try {
            $user->save(false);
        } catch (\yii\db\IntegrityException $exception) {
            // The unique index also protects against simultaneous creation requests.
            if (User::findByUsername($username) !== null) {
                throw new \yii\web\ConflictHttpException('Пользователь с таким логином уже существует.');
            }
            if ($email !== null && User::findByEmail($email) !== null) {
                throw new \yii\web\ConflictHttpException('Пользователь с таким email уже существует.');
            }
            throw $exception;
        }
        Yii::$app->response->statusCode = 201;
        return ['id' => $user->id, 'username' => $user->username];
    }

    public function actionMe(): array
    {
        $user = $this->module->get('user')->identity;
        return ['id' => $user->id, 'username' => $user->username];
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionLogout(): array
    {
        $user = $this->module->get('user')->identity;
        User::updateAll(['auth_key' => null], ['id' => $user->id, 'auth_key' => $user->auth_key]);
        $this->module->get('user')->logout(false);
        return ['success' => true];
    }
}
