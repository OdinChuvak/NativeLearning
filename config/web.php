<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'modules' => [
        'admin' => ['class' => \app\modules\admin\Module::class],
        'user' => ['class' => \app\modules\user\Module::class],
    ],
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,
                // send all mails to a file by default.
                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '4hXL1ORmSWlSuVThPSFm3vdCWogcOBaE',
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET user/tests/materials' => 'user/test/materials',
                'OPTIONS user/tests/materials' => 'user/test/options',
                'GET user/subscription' => 'user/subscription/index',
                'OPTIONS user/subscription' => 'user/subscription/options',
                'GET user/tariffs' => 'user/subscription/tariffs',
                'OPTIONS user/tariffs' => 'user/subscription/options',
                'POST user/payments' => 'user/subscription/pay',
                'POST user/courses/<id:\d+>/activate' => 'user/course/activate',
                'OPTIONS user/courses/<id:\d+>/activate' => 'user/course/options',
                'GET user/payments' => 'user/payment/index',
                'OPTIONS user/payments' => 'user/payment/options',
                'GET user/subscriptions' => 'user/subscription/index',
                'OPTIONS user/subscriptions' => 'user/subscription/options',
                'GET user/courses/<course_id:\d+>/categories' => 'user/category/index',
                'GET user/categories/<category_id:\d+>/topics' => 'user/topic/index',
                'GET user/topics/<topic_id:\d+>/questions' => 'user/question/index',
                'OPTIONS user/courses/<course_id:\d+>/categories' => 'user/category/options',
                'OPTIONS user/categories/<category_id:\d+>/topics' => 'user/topic/options',
                'OPTIONS user/topics/<topic_id:\d+>/questions' => 'user/question/options',
                'OPTIONS user/auth/<action:(login|me|logout)>' => 'user/auth/options',
                [
                    'class' => \yii\rest\UrlRule::class,
                    'controller' => [
                        'user/courses' => 'user/course',
                        'user/categories' => 'user/category',
                        'user/tests' => 'user/test',
                        'user/active-tests' => 'user/active-test',
                        'user/topics' => 'user/topic',
                        'user/questions' => 'user/question',
                        'user/question-types' => 'user/question-type',
                    ],
                    'pluralize' => false,
                    'only' => ['index', 'view', 'create', 'update', 'delete', 'options'],
                ],
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
