<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000016_create_user_subscription_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user_subscription}}', [
            'id' => $this->primaryKey(),
            'payment_id' => $this->integer()->notNull(),
            'course_id' => $this->integer()->null(),
            'start_at' => $this->dateTime()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('new')->check("[[status]] IN ('new', 'active', 'expired')"),
            "CHECK (([[status]] = 'new' AND [[course_id]] IS NULL AND [[start_at]] IS NULL) OR ([[status]] IN ('active', 'expired') AND [[course_id]] IS NOT NULL AND [[start_at]] IS NOT NULL))",
        ]);
        $this->createIndex('idx-user_subscription-payment_id-status', '{{%user_subscription}}', ['payment_id', 'status']);
        $this->createIndex('idx-user_subscription-course_id-status', '{{%user_subscription}}', ['course_id', 'status']);
        $this->createIndex('idx-user_subscription-status-start_at', '{{%user_subscription}}', ['status', 'start_at']);
        $this->addForeignKey(
            'fk-user_subscription-payment_id',
            '{{%user_subscription}}', 'payment_id', '{{%user_payment}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->addForeignKey(
            'fk-user_subscription-course_id',
            '{{%user_subscription}}', 'course_id', '{{%course}}', 'id',
            'RESTRICT', 'RESTRICT',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_subscription-course_id', '{{%user_subscription}}');
        $this->dropForeignKey('fk-user_subscription-payment_id', '{{%user_subscription}}');
        $this->dropTable('{{%user_subscription}}');
    }
}
