<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260929_000001_create_test_question_frequency_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%test_question_frequency}}', [
            'id' => $this->primaryKey(),
            'test_id' => $this->integer()->notNull(),
            'question_id' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-test_question_frequency-test_id', '{{%test_question_frequency}}', 'test_id');
        $this->addForeignKey(
            'fk-test_question_frequency-test_id',
            '{{%test_question_frequency}}', 'test_id', '{{%test}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-test_question_frequency-question_id', '{{%test_question_frequency}}', 'question_id');
        $this->addForeignKey(
            'fk-test_question_frequency-question_id',
            '{{%test_question_frequency}}', 'question_id', '{{%question}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-test_question_frequency-question_id', '{{%test_question_frequency}}');
        $this->dropForeignKey('fk-test_question_frequency-test_id', '{{%test_question_frequency}}');
        $this->dropTable('{{%test_question_frequency}}');
    }
}
