<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

use app\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

final class AuthController extends ApiController
{
    protected array $publicActions = ['login'];

    protected function verbs(): array
    {
        return [
            'login' => ['POST'], 'me' => ['GET'], 'logout' => ['POST'], 'options' => ['OPTIONS'],
        ];
    }

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
    public function actionMe(): array
    {
        $user = $this->module->get('user')->identity;
        return ['id' => $user->id, 'username' => $user->username];
    }
    public function actionLogout(): array
    {
        $user = $this->module->get('user')->identity;
        User::updateAll(['auth_key' => null], ['id' => $user->id, 'auth_key' => $user->auth_key]);
        $this->module->get('user')->logout(false);
        return ['success' => true];
    }
}
