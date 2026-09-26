<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000002_create_category_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%category}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
        ]);

    }

    public function safeDown(): void
    {
        $this->dropTable('{{%category}}');
    }
}
