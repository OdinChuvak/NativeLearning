<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;
use app\components\db\ConnectionGroup;

final class m260927_000001_create_right_answer_table extends Migration
{
    public static function getConnections(): ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%right_answer}}', [
            'id' => $this->primaryKey(),
            'question_id' => $this->integer()->notNull(),
            'answer' => $this->json()->notNull(),
        ]);
        $this->createIndex('idx-right_answer-question_id', '{{%right_answer}}', 'question_id');
        $this->addForeignKey(
            'fk-right_answer-question_id',
            '{{%right_answer}}', 'question_id', '{{%question}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-right_answer-question_id', '{{%right_answer}}');
        $this->dropTable('{{%right_answer}}');
    }
}
