<?php
declare(strict_types=1);

use yii\db\Migration;

final class m260913_000017_create_subscription_slot_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%subscription_slot}}', [
            'id' => $this->primaryKey(),
            'subscription_id' => $this->integer()->notNull(),
            'course_id' => $this->integer()->notNull(),
            'activated_at' => $this->dateTime()->notNull(),
            'deactivated_at' => $this->dateTime()->null(),
            'CHECK ([[deactivated_at]] IS NULL OR [[deactivated_at]] >= [[activated_at]])',
        ]);
        $this->createIndex('idx-subscription_slot-active-course', '{{%subscription_slot}}', ['subscription_id', 'deactivated_at', 'course_id']);
        foreach (['subscription', 'course'] as $table) {
            $this->addForeignKey('fk-subscription_slot-' . $table, '{{%subscription_slot}}', $table . '_id', '{{%' . $table . '}}', 'id', 'RESTRICT', 'RESTRICT');
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%subscription_slot}}');
    }
}
