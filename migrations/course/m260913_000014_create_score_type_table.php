<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000014_create_score_type_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%score_type}}', [
            'id' => $this->primaryKey(),
            'description' => $this->text()->notNull(),
            'default_success_score' => $this->integer()->notNull(),
            'default_failure_score' => $this->integer()->notNull(),
            'CHECK ([[default_success_score]] >= 0 AND [[default_failure_score]] >= 0)',
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%score_type}}');
    }
}

