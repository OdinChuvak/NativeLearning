<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;
use yii\base\Security;

/** @property-read User|null $user */
class LoginForm extends Model
{
    // Form input is untrusted: validators handle arrays and other invalid types.
    public $username = '';
    public $password = '';

    public function __construct(private readonly Security $security, $config = [])
    {
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['username', 'string', 'max' => 100],
            ['password', 'string', 'max' => 4096],
            ['password', 'validatePassword'],
        ];
    }

    public function validatePassword(string $attribute, ?array $params = null): void
    {
        if ($this->hasErrors()) {
            return;
        }
        $user = $this->getUser();
        try {
            $valid = $user !== null && $this->security->validatePassword($this->password, $user->password_hash);
        } catch (\yii\base\InvalidArgumentException) {
            $valid = false;
        }
        if (!$valid) {
            $this->addError($attribute, 'Incorrect username or password.');
        }
    }

    public function login(): bool
    {
        return $this->validate() && Yii::$app->user->login($this->getUser(), 0);
    }

    public function getUser(): ?User
    {
        return is_string($this->username) ? User::findByUsername($this->username) : null;
    }
}
