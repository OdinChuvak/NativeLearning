<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000011_create_active_test_answer_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%active_test_answer}}', [
            'id' => $this->primaryKey(),
            'active_test_question_id' => $this->integer()->notNull(),
            'answer' => $this->json()->null(),
            'presented_options' => $this->json()->notNull(),
        ]);
        $this->createIndex('idx-active_test_answer-active_test_question_id', '{{%active_test_answer}}', 'active_test_question_id', true);
        $this->addForeignKey(
            'fk-active_test_answer-active_test_question_id',
            '{{%active_test_answer}}', 'active_test_question_id', '{{%active_test_question}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-active_test_answer-active_test_question_id', '{{%active_test_answer}}');
        $this->dropTable('{{%active_test_answer}}');
    }
}
