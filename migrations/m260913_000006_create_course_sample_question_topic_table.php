<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000006_create_course_sample_question_topic_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course_sample_question_topic}}', [
            'question_id' => $this->integer()->notNull(),
            'topic_id' => $this->integer()->notNull(),
            'PRIMARY KEY ([[question_id]], [[topic_id]])',
        ]);

        $this->createIndex('idx-course_sample_question_topic-topic_id', '{{%course_sample_question_topic}}', 'topic_id');
        $this->addForeignKey(
            'fk-course_sample_question_topic-question_id',
            '{{%course_sample_question_topic}}',
            'question_id',
            '{{%course_sample_question}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk-course_sample_question_topic-topic_id',
            '{{%course_sample_question_topic}}',
            'topic_id',
            '{{%course_sample_topic}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-course_sample_question_topic-topic_id', '{{%course_sample_question_topic}}');
        $this->dropForeignKey('fk-course_sample_question_topic-question_id', '{{%course_sample_question_topic}}');
        $this->dropTable('{{%course_sample_question_topic}}');
    }
}
