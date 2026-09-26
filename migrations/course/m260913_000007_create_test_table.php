<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000007_create_test_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%test}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'question_count' => $this->integer()->notNull()->check('[[question_count]] > 0'),
        ]);

        $this->createIndex('idx-test-user_id', '{{%test}}', 'user_id');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%test}}');
    }
}
