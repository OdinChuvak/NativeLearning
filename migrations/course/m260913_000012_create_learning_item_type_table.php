<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000012_create_learning_item_type_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%learning_item_type}}', [
            'id' => $this->primaryKey(),
            'key' => $this->string(64)->notNull(),
            'description' => $this->text()->null(),
        ]);
        $this->createIndex('uq-learning_item_type-1', '{{%learning_item_type}}', ['key'], true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learning_item_type}}');
    }
}

