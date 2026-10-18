<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000000_create_question_pattern_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_pattern}}', [
            'id' => $this->primaryKey(),
            'pattern' => $this->text()->notNull(),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%question_pattern}}');
    }
}

