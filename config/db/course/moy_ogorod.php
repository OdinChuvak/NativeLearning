<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => getenv('NL_MOY_OGOROD_DB_DSN') !== false
        ? getenv('NL_MOY_OGOROD_DB_DSN')
        : 'mysql:host=127.0.1.21;port=3306;dbname=nl_course_moy_ogorod',
    'username' => getenv('NL_MOY_OGOROD_DB_USER') !== false
        ? getenv('NL_MOY_OGOROD_DB_USER')
        : 'root',
    'password' => getenv('NL_MOY_OGOROD_DB_PASSWORD') !== false
        ? getenv('NL_MOY_OGOROD_DB_PASSWORD')
        : '',
    'charset' => 'utf8mb4',
];
