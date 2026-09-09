<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000007_create_test_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%test}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
        ]);

        $this->createIndex('idx-test-user_id', '{{%test}}', 'user_id');
        $this->addForeignKey(
            'fk-test-user_id',
            '{{%test}}', 'user_id', '{{%user}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-test-user_id', '{{%test}}');
        $this->dropTable('{{%test}}');
    }
}
