<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000013_create_learning_item_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%learning_item}}', [
            'id' => $this->primaryKey(),
            'type_id' => $this->integer()->notNull(),
            'value' => $this->text()->notNull(),
        ]);
        $this->createIndex('idx-learning_item-type_id', '{{%learning_item}}', 'type_id');
        $this->addForeignKey('fk-learning_item-type_id', '{{%learning_item}}', 'type_id', '{{%learning_item_type}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-learning_item-type_id', '{{%learning_item}}');
        $this->dropTable('{{%learning_item}}');
    }
}

