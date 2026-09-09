<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000003_create_course_sample_topic_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course_sample_topic}}', [
            'id' => $this->primaryKey(),
            'category_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
        ]);

        $this->createIndex('idx-course_sample_topic-category_id', '{{%course_sample_topic}}', 'category_id');
        $this->addForeignKey(
            'fk-course_sample_topic-category_id',
            '{{%course_sample_topic}}',
            'category_id',
            '{{%course_sample_category}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-course_sample_topic-category_id', '{{%course_sample_topic}}');
        $this->dropTable('{{%course_sample_topic}}');
    }
}
