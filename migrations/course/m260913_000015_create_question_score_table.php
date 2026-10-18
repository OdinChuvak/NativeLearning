<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;

final class m260913_000015_create_question_score_table extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_score}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'learning_item_id' => $this->integer()->notNull(),
            'score_type_id' => $this->integer()->notNull(),
            'success_score' => $this->integer()->null(),
            'failure_score' => $this->integer()->null(),
            'CHECK ([[success_score]] IS NULL OR [[success_score]] >= 0)',
            'CHECK ([[failure_score]] IS NULL OR [[failure_score]] >= 0)',
        ]);
        $this->createIndex('uq-question_score-1', '{{%question_score}}', ['question_id', 'learning_item_id', 'score_type_id'], true);
        $this->createIndex('idx-question_score-question_id', '{{%question_score}}', 'question_id');
        $this->addForeignKey('fk-question_score-question_id', '{{%question_score}}', 'question_id', '{{%question}}', 'id', 'RESTRICT', 'CASCADE');
        $this->createIndex('idx-question_score-learning_item_id', '{{%question_score}}', 'learning_item_id');
        $this->addForeignKey('fk-question_score-learning_item_id', '{{%question_score}}', 'learning_item_id', '{{%learning_item}}', 'id', 'RESTRICT', 'CASCADE');
        $this->createIndex('idx-question_score-score_type_id', '{{%question_score}}', 'score_type_id');
        $this->addForeignKey('fk-question_score-score_type_id', '{{%question_score}}', 'score_type_id', '{{%score_type}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-question_score-question_id', '{{%question_score}}');
        $this->dropForeignKey('fk-question_score-learning_item_id', '{{%question_score}}');
        $this->dropForeignKey('fk-question_score-score_type_id', '{{%question_score}}');
        $this->dropTable('{{%question_score}}');
    }
}

