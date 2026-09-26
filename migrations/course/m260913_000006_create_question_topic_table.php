<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000006_create_question_topic_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question_topic}}', [
            'question_id' => $this->integer()->notNull(),
            'topic_id' => $this->integer()->notNull(),
            'PRIMARY KEY ([[question_id]], [[topic_id]])',
        ]);

        $this->createIndex('idx-question_topic-topic_id', '{{%question_topic}}', 'topic_id');
        $this->addForeignKey(
            'fk-question_topic-question_id',
            '{{%question_topic}}',
            'question_id',
            '{{%question}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk-question_topic-topic_id',
            '{{%question_topic}}',
            'topic_id',
            '{{%topic}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-question_topic-topic_id', '{{%question_topic}}');
        $this->dropForeignKey('fk-question_topic-question_id', '{{%question_topic}}');
        $this->dropTable('{{%question_topic}}');
    }
}
