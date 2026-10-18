<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260929_000004_create_user_score_value_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%user_score_value}}', [
            'id' => $this->primaryKey(),
            'score_id' => $this->integer()->notNull(),
            'score_type_id' => $this->integer()->notNull(),
            'total_score' => $this->integer()->notNull(),
            'success_score' => $this->integer()->notNull(),
            'failure_score' => $this->integer()->notNull(),
            'CHECK ([[success_score]] >= 0 AND [[failure_score]] >= 0)',
            'CHECK ([[total_score]] = [[success_score]] + [[failure_score]])',
        ]);
        $this->createIndex('uq-user_score_value-1', '{{%user_score_value}}', ['score_id', 'score_type_id'], true);
        $this->createIndex('idx-user_score_value-score_id', '{{%user_score_value}}', 'score_id');
        $this->addForeignKey('fk-user_score_value-score_id', '{{%user_score_value}}', 'score_id', '{{%user_learning_item_score}}', 'id', 'RESTRICT', 'CASCADE');
        $this->createIndex('idx-user_score_value-score_type_id', '{{%user_score_value}}', 'score_type_id');
        $this->addForeignKey('fk-user_score_value-score_type_id', '{{%user_score_value}}', 'score_type_id', '{{%score_type}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_score_value-score_id', '{{%user_score_value}}');
        $this->dropForeignKey('fk-user_score_value-score_type_id', '{{%user_score_value}}');
        $this->dropTable('{{%user_score_value}}');
    }
}

