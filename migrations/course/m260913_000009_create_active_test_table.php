<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000009_create_active_test_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%active_test}}', [
            'id' => $this->primaryKey(),
            'test_id' => $this->integer()->notNull(),
            'status' => $this->string(16)->notNull()->defaultValue('active')->check("[[status]] IN ('active', 'rejected', 'passed')"),
        ]);

        $this->createIndex('idx-active_test-test_id', '{{%active_test}}', 'test_id');
        $this->addForeignKey(
            'fk-active_test-test_id',
            '{{%active_test}}', 'test_id', '{{%test}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-active_test-test_id', '{{%active_test}}');
        $this->dropTable('{{%active_test}}');
    }
}
