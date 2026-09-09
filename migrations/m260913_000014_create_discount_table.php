<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000014_create_discount_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%discount}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull()->check("CHAR_LENGTH(TRIM([[code]])) > 0"),
            'value' => $this->decimal(5, 2)->notNull()->check('[[value]] > 0 AND [[value]] <= 100'),
            'status' => $this->string(16)->notNull()->defaultValue('new')->check("[[status]] IN ('new', 'used')"),
        ]);
        $this->createIndex('uq-discount-code', '{{%discount}}', 'code', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%discount}}');
    }
}
