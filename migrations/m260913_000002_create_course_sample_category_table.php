<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000002_create_course_sample_category_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course_sample_category}}', [
            'id' => $this->primaryKey(),
            'course_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
        ]);

        $this->createIndex('idx-course_sample_category-course_id', '{{%course_sample_category}}', 'course_id');
        $this->addForeignKey(
            'fk-course_sample_category-course_id',
            '{{%course_sample_category}}',
            'course_id',
            '{{%course}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-course_sample_category-course_id', '{{%course_sample_category}}');
        $this->dropTable('{{%course_sample_category}}');
    }
}
