<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000001_create_course_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%course}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'alias' => $this->string(32)->notNull()->check("[[alias]] REGEXP '^[a-z][a-z0-9_]{0,31}$' AND BINARY [[alias]] = BINARY LOWER([[alias]]) AND [[alias]] <> 'sample'"),
            'description' => $this->text()->null(),
        ]);
        $this->createIndex('uq-course-alias', '{{%course}}', 'alias', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%course}}');
    }
}
