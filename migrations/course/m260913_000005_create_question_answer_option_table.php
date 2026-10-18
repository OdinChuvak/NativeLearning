<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000005_create_question_answer_option_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_answer_option}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'value' => $this->text()->notNull(),
        ]);

        $this->createIndex('idx-question_answer_option-question_id', '{{%question_answer_option}}', 'question_id');
        $this->addForeignKey(
            'fk-question_answer_option-question_id',
            '{{%question_answer_option}}',
            'question_id',
            '{{%question}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-question_answer_option-question_id', '{{%question_answer_option}}');
        $this->dropTable('{{%question_answer_option}}');
    }
}
