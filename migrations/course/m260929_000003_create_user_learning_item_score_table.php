<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260929_000003_create_user_learning_item_score_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%user_learning_item_score}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'learning_item_id' => $this->integer()->notNull(),
            'completed_test_id' => $this->integer()->notNull(),
        ]);
        $this->createIndex('uq-user_learning_item_score-1', '{{%user_learning_item_score}}', ['user_id', 'learning_item_id', 'completed_test_id'], true);
        $this->createIndex('idx-user_learning_item_score-learning_item_id', '{{%user_learning_item_score}}', 'learning_item_id');
        $this->addForeignKey('fk-user_learning_item_score-learning_item_id', '{{%user_learning_item_score}}', 'learning_item_id', '{{%learning_item}}', 'id', 'RESTRICT', 'CASCADE');
        $this->createIndex('idx-user_learning_item_score-completed_test_id', '{{%user_learning_item_score}}', 'completed_test_id');
        $this->addForeignKey('fk-user_learning_item_score-completed_test_id', '{{%user_learning_item_score}}', 'completed_test_id', '{{%completed_test}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_learning_item_score-learning_item_id', '{{%user_learning_item_score}}');
        $this->dropForeignKey('fk-user_learning_item_score-completed_test_id', '{{%user_learning_item_score}}');
        $this->dropTable('{{%user_learning_item_score}}');
    }
}

