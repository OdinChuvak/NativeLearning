<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000012_create_user_rating_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user_rating}}', [
            'user_id' => $this->integer()->notNull(),
            'course_id' => $this->integer()->notNull(),
            'topic_id' => $this->integer()->notNull(),
            'active_test_question_id' => $this->integer()->notNull(),
            'score' => $this->decimal(10, 2)->notNull(),
            'PRIMARY KEY ([[user_id]], [[course_id]], [[topic_id]], [[active_test_question_id]])',
        ]);

        $this->createIndex('idx-user_rating-user_id', '{{%user_rating}}', 'user_id');
        $this->addForeignKey(
            'fk-user_rating-user_id',
            '{{%user_rating}}', 'user_id', '{{%user}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-user_rating-course_id', '{{%user_rating}}', 'course_id');
        $this->addForeignKey(
            'fk-user_rating-course_id',
            '{{%user_rating}}', 'course_id', '{{%course}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-user_rating-topic_id', '{{%user_rating}}', 'topic_id');
        $this->createIndex('idx-user_rating-active_test_question_id', '{{%user_rating}}', 'active_test_question_id');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_rating-course_id', '{{%user_rating}}');
        $this->dropForeignKey('fk-user_rating-user_id', '{{%user_rating}}');
        $this->dropTable('{{%user_rating}}');
    }
}
