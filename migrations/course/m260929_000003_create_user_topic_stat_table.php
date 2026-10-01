<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260929_000003_create_user_topic_stat_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%user_topic_stat}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'topic_id' => $this->integer()->notNull(),
            'score' => $this->tinyInteger()->notNull(),
            'completed_test_id' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-user_topic_stat-user_id', '{{%user_topic_stat}}', 'user_id');
        $this->createIndex('idx-user_topic_stat-topic_id', '{{%user_topic_stat}}', 'topic_id');
        $this->addForeignKey(
            'fk-user_topic_stat-topic_id',
            '{{%user_topic_stat}}', 'topic_id', '{{%topic}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-user_topic_stat-completed_test_id', '{{%user_topic_stat}}', 'completed_test_id');
        $this->addForeignKey(
            'fk-user_topic_stat-completed_test_id',
            '{{%user_topic_stat}}', 'completed_test_id', '{{%completed_test}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_topic_stat-completed_test_id', '{{%user_topic_stat}}');
        $this->dropForeignKey('fk-user_topic_stat-topic_id', '{{%user_topic_stat}}');
        $this->dropTable('{{%user_topic_stat}}');
    }
}
