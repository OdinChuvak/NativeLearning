<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000004_create_question_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question}}', [
            'id' => $this->primaryKey(),
            'pattern_id' => $this->integer()->notNull(),
            'answer_type_id' => $this->integer()->notNull(),
            'pattern_values' => $this->json()->notNull(),
        ]);
        $this->createIndex('idx-question-pattern_id', '{{%question}}', 'pattern_id');
        $this->createIndex('idx-question-answer_type_id', '{{%question}}', 'answer_type_id');
        $this->addForeignKey(
            'fk-question-pattern_id', '{{%question}}', 'pattern_id', '{{%question_pattern}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->addForeignKey(
            'fk-question-answer_type_id', '{{%question}}', 'answer_type_id', '{{%question_answer_type}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-question-answer_type_id', '{{%question}}');
        $this->dropForeignKey('fk-question-pattern_id', '{{%question}}');
        $this->dropTable('{{%question}}');
    }
}
