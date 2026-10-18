<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000001_create_question_answer_type_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_answer_type}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull(),
            'description' => $this->text()->null(),
        ]);
        $this->createIndex('uq-question_answer_type-1', '{{%question_answer_type}}', ['code'], true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%question_answer_type}}');
    }
}

