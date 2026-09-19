<?php
declare(strict_types=1);

use yii\db\Migration;

final class m260913_000016_create_subscription_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%subscription}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'tariff_id' => $this->integer()->notNull(),
            'course_limit' => $this->integer()->notNull()->check('[[course_limit]] > 0'),
            'starts_at' => $this->dateTime()->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'CHECK ([[expires_at]] > [[starts_at]])',
        ]);
        $this->createIndex('uq-subscription-user', '{{%subscription}}', 'user_id', true);
        foreach (['user', 'tariff'] as $table) {
            $this->addForeignKey('fk-subscription-' . $table, '{{%subscription}}', $table . '_id', '{{%' . $table . '}}', 'id', 'RESTRICT', 'RESTRICT');
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%subscription}}');
    }
}
