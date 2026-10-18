<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

/** body stores question/answer snapshots and is_correct; scores live in user_score_value. */
final class m260929_000002_create_completed_test_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%completed_test}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'body' => $this->json()->notNull(),
            'completed_at' => $this->dateTime()->null(),
        ]);

        $this->createIndex('idx-completed_test-user_id', '{{%completed_test}}', 'user_id');
        $this->createIndex('idx-completed_test-user-completed', '{{%completed_test}}', ['user_id', 'completed_at', 'id']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%completed_test}}');
    }
}
