<?php
declare(strict_types=1);

use yii\db\Migration;

final class m261003_000001_create_profile_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%profile}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'first_name' => $this->string(100)->notNull()->defaultValue(''),
            'last_name' => $this->string(100)->notNull()->defaultValue(''),
            'photo' => $this->string(255)->null(),
        ]);
        $this->createIndex('idx-profile-user', '{{%profile}}', 'user_id', true);
        $this->addForeignKey('fk-profile-user', '{{%profile}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%profile}}');
    }
}
