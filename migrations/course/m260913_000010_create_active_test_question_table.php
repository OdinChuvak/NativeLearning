<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000010_create_active_test_question_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%active_test_question}}', [
            'id' => $this->primaryKey(),
            'active_test_id' => $this->integer()->notNull(),
            'question_id' => $this->integer()->notNull(),
            'is_shown' => $this->boolean()->notNull()->defaultValue(false)->check('[[is_shown]] IN (0, 1)'),
        ]);
        $this->createIndex('uq-active_test_question-1', '{{%active_test_question}}', ['active_test_id', 'question_id'], true);
        $this->createIndex('idx-active_test_question-active_test_id', '{{%active_test_question}}', 'active_test_id');
        $this->addForeignKey(
            'fk-active_test_question-active_test_id',
            '{{%active_test_question}}', 'active_test_id', '{{%active_test}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-active_test_question-question_id', '{{%active_test_question}}', 'question_id');
        $this->addForeignKey(
            'fk-active_test_question-question_id',
            '{{%active_test_question}}', 'question_id', '{{%question}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-active_test_question-question_id', '{{%active_test_question}}');
        $this->dropForeignKey('fk-active_test_question-active_test_id', '{{%active_test_question}}');
        $this->dropTable('{{%active_test_question}}');
    }
}
