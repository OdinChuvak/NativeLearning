<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000003_create_topic_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%topic}}', [
            'id' => $this->primaryKey(),
            'category_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
        ]);

        $this->createIndex('idx-topic-category_id', '{{%topic}}', 'category_id');
        $this->addForeignKey(
            'fk-topic-category_id',
            '{{%topic}}',
            'category_id',
            '{{%category}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-topic-category_id', '{{%topic}}');
        $this->dropTable('{{%topic}}');
    }
}
