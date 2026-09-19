<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000015_create_payment_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%payment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'tariff_id' => $this->integer()->notNull(),
            'discount_id' => $this->integer()->null(),
            'payment_amount' => $this->decimal(12, 2)->notNull()->check('[[payment_amount]] >= 0'),
            'payed_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx-payment-user_id-payed_at', '{{%payment}}', ['user_id', 'payed_at']);
        $this->createIndex('uq-payment-discount_id', '{{%payment}}', 'discount_id', true);
        foreach (['user', 'tariff', 'discount'] as $table) {
            $this->addForeignKey(
                'fk-payment-' . $table . '_id',
                '{{%payment}}', $table . '_id', '{{%' . $table . '}}', 'id',
                'RESTRICT', 'CASCADE',
            );
        }
    }

    public function safeDown(): void
    {
        foreach (['discount', 'tariff', 'user'] as $table) {
            $this->dropForeignKey('fk-payment-' . $table . '_id', '{{%payment}}');
        }
        $this->dropTable('{{%payment}}');
    }
}
