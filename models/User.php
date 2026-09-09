<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property string $password_hash
 * @property string|null $auth_key SHA-256 digest of the API token.
 */
class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public static function findIdentity($id): ?static
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        if (!is_string($token) || $token === '') {
            return null;
        }
        return static::findOne(['auth_key' => hash('sha256', $token)]);
    }

    public static function findByUsername(string $username): ?static
    {
        return static::findOne(['username' => $username]);
    }

    public function getId(): int|string
    {
        return $this->getPrimaryKey();
    }

    public static function findByEmail(string $email): ?static
    {
        return static::find()->where(['LOWER(email)' => strtolower(trim($email))])->one();
    }

    public function getAuthKey(): ?string
    {
        // API tokens are separate from Yii cookie-based authentication.
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
