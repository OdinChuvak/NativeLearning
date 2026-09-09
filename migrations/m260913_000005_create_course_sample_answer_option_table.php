<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000005_create_course_sample_answer_option_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course_sample_answer_option}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'answer' => $this->text()->notNull(),
            'is_correct' => $this->boolean()->notNull()->defaultValue(false)->check('[[is_correct]] IN (0, 1)'),
        ]);

        $this->createIndex('idx-course_sample_answer_option-question_id', '{{%course_sample_answer_option}}', 'question_id');
        $this->addForeignKey(
            'fk-course_sample_answer_option-question_id',
            '{{%course_sample_answer_option}}',
            'question_id',
            '{{%course_sample_question}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-course_sample_answer_option-question_id', '{{%course_sample_answer_option}}');
        $this->dropTable('{{%course_sample_answer_option}}');
    }
}
