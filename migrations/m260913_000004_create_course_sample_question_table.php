<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000004_create_course_sample_question_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course_sample_question}}', [
            'id' => $this->primaryKey(),
            'question' => $this->text()->notNull(),
            'type' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-course_sample_question-type', '{{%course_sample_question}}', 'type');
        $this->addForeignKey(
            'fk-course_sample_question-type', '{{%course_sample_question}}', 'type', '{{%question_type}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-course_sample_question-type', '{{%course_sample_question}}');
        $this->dropTable('{{%course_sample_question}}');
    }
}
