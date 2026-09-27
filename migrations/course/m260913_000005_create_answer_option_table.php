<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000005_create_answer_option_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%answer_option}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'answer' => $this->text()->notNull(),
        ]);

        $this->createIndex('idx-answer_option-question_id', '{{%answer_option}}', 'question_id');
        $this->addForeignKey(
            'fk-answer_option-question_id',
            '{{%answer_option}}',
            'question_id',
            '{{%question}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-answer_option-question_id', '{{%answer_option}}');
        $this->dropTable('{{%answer_option}}');
    }
}
