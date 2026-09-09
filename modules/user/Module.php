<?php
declare(strict_types=1);
namespace app\modules\user;

final class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\\modules\\user\\controllers';
    public array $corsOrigins = ['*'];

    public function init(): void
    {
        parent::init();
        $this->set('user', [
            'class' => \yii\web\User::class,
            'identityClass' => \app\models\User::class,
            'enableSession' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null,
        ]);
    }
}
