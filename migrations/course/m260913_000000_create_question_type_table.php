<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;
use app\enum\QuestionTypeCategory;

final class m260913_000000_create_question_type_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_type}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'code' => $this->string(64)->null(),
            'description' => $this->text()->null(),
            'category' => $this->smallInteger()->notNull(),
            'success_score' => $this->tinyInteger()->notNull(),
            'failure_score' => $this->tinyInteger()->notNull(),
            'CHECK ([[category]] IN (' . implode(', ', QuestionTypeCategory::values()) . '))',
        ]);


    }

    public function safeDown(): void
    {

        $this->dropTable('{{%question_type}}');
    }
}
