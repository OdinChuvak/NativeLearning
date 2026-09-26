<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260912_000001_create_user_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(100)->notNull(),
            'email' => $this->string(254)->null(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(64)->null(),
        ]);

        $this->createIndex('idx-user-username', '{{%user}}', 'username', true);
        $this->createIndex('idx-user-email', '{{%user}}', 'email', true);
        $this->createIndex('idx-user-auth-key', '{{%user}}', 'auth_key', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user}}');
    }
}
