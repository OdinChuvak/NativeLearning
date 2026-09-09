<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000011_create_active_test_answer_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%active_test_answer}}', [
            'id' => $this->primaryKey(),
            'active_test_question_id' => $this->integer()->notNull(),
            'answer_option_id' => $this->integer()->notNull(),
        ]);
        $this->createIndex('uq-active_test_answer-1', '{{%active_test_answer}}', ['active_test_question_id', 'answer_option_id'], true);
        $this->createIndex('idx-active_test_answer-active_test_question_id', '{{%active_test_answer}}', 'active_test_question_id');
        $this->addForeignKey(
            'fk-active_test_answer-active_test_question_id',
            '{{%active_test_answer}}', 'active_test_question_id', '{{%active_test_question}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-active_test_answer-answer_option_id', '{{%active_test_answer}}', 'answer_option_id');
        $this->addForeignKey(
            'fk-active_test_answer-answer_option_id',
            '{{%active_test_answer}}', 'answer_option_id', '{{%course_sample_answer_option}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-active_test_answer-answer_option_id', '{{%active_test_answer}}');
        $this->dropForeignKey('fk-active_test_answer-active_test_question_id', '{{%active_test_answer}}');
        $this->dropTable('{{%active_test_answer}}');
    }
}
