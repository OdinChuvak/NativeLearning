<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000015_create_user_payment_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user_payment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'tariff_id' => $this->integer()->notNull(),
            'discount_id' => $this->integer()->null(),
            'payment_amount' => $this->decimal(12, 2)->notNull()->check('[[payment_amount]] >= 0'),
            'payed_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx-user_payment-user_id-payed_at', '{{%user_payment}}', ['user_id', 'payed_at']);
        $this->createIndex('idx-user_payment-tariff_id', '{{%user_payment}}', 'tariff_id');
        $this->createIndex('uq-user_payment-discount_id', '{{%user_payment}}', 'discount_id', true);
        foreach (['user', 'tariff', 'discount'] as $table) {
            $this->addForeignKey(
                'fk-user_payment-' . $table . '_id',
                '{{%user_payment}}', $table . '_id', '{{%' . $table . '}}', 'id',
                'RESTRICT', 'CASCADE',
            );
        }
    }

    public function safeDown(): void
    {
        foreach (['discount', 'tariff', 'user'] as $table) {
            $this->dropForeignKey('fk-user_payment-' . $table . '_id', '{{%user_payment}}');
        }
        $this->dropTable('{{%user_payment}}');
    }
}
