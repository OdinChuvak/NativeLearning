<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=127.0.1.21;port=3306;dbname=nl',
    'username' => 'root',
    'password' => '', // либо заданный тобой пароль
    'charset' => 'utf8mb4',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
