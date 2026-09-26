<?php

declare(strict_types=1);

namespace app\commands;

use yii\base\InvalidConfigException;
use yii\console\Exception;

/** Uses only the course migration directory; connection selection is inherited. */
final class MigrateCourseController extends MigrateController
{
    public $migrationPath = '@app/migrations/course';

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function beforeAction($action): bool
    {
        $expected = realpath(\Yii::getAlias('@app/migrations/course'));
        $actual = is_string($this->migrationPath) ? realpath(\Yii::getAlias($this->migrationPath)) : false;
        if ($expected === false || $actual === false || $actual !== $expected) {
            throw new \yii\console\Exception('Эта команда предназначена для миграций из директории @app/migrations/course.');
        }
        return parent::beforeAction($action);
    }
}
