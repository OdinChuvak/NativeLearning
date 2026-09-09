<?php

declare(strict_types=1);

namespace app\modules\admin;

final class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\admin\controllers';

    /** @var string[] Allowed frontend origins; bearer authentication does not use cookies. */
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
