<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000016_create_topic_learning_item_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%topic_learning_item}}', [
            'id' => $this->primaryKey(),
            'topic_id' => $this->integer()->notNull(),
            'learning_item_id' => $this->integer()->notNull(),
            'type' => $this->string(8)->notNull(),
            "CHECK ([[type]] IN ('required', 'acquired'))",
        ]);
        $this->createIndex('uq-topic_learning_item-1', '{{%topic_learning_item}}', ['topic_id', 'learning_item_id', 'type'], true);
        $this->createIndex('idx-topic_learning_item-topic_id', '{{%topic_learning_item}}', 'topic_id');
        $this->addForeignKey('fk-topic_learning_item-topic_id', '{{%topic_learning_item}}', 'topic_id', '{{%topic}}', 'id', 'RESTRICT', 'CASCADE');
        $this->createIndex('idx-topic_learning_item-learning_item_id', '{{%topic_learning_item}}', 'learning_item_id');
        $this->addForeignKey('fk-topic_learning_item-learning_item_id', '{{%topic_learning_item}}', 'learning_item_id', '{{%learning_item}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-topic_learning_item-topic_id', '{{%topic_learning_item}}');
        $this->dropForeignKey('fk-topic_learning_item-learning_item_id', '{{%topic_learning_item}}');
        $this->dropTable('{{%topic_learning_item}}');
    }
}

