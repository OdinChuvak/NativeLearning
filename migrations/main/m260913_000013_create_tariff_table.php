<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000013_create_tariff_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%tariff}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'price' => $this->decimal(12, 2)->notNull()->check('[[price]] >= 0'),
            'duration_days' => $this->integer()->notNull()->check('[[duration_days]] > 0'),
            'course_limit' => $this->integer()->notNull()->check('[[course_limit]] > 0'),
        ]);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%tariff}}');
    }
}
