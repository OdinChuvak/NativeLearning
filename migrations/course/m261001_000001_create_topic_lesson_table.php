<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m261001_000001_create_topic_lesson_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%topic_lesson}}', [
            'id' => $this->primaryKey(),
            'topic_id' => $this->integer()->notNull(),
            'title' => $this->string(255)->notNull(),
            'content_html' => 'MEDIUMTEXT NOT NULL',
            'position' => $this->integer()->notNull()->defaultValue(0),
        ]);

        $this->createIndex('idx-topic_lesson-topic_id-position', '{{%topic_lesson}}', ['topic_id', 'position']);
        $this->addForeignKey(
            'fk-topic_lesson-topic_id',
            '{{%topic_lesson}}', 'topic_id', '{{%topic}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-topic_lesson-topic_id', '{{%topic_lesson}}');
        $this->dropTable('{{%topic_lesson}}');
    }
}
