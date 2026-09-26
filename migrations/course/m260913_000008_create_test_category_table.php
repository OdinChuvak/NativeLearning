<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000008_create_test_category_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%test_category}}', [
            'test_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'PRIMARY KEY ([[test_id]], [[category_id]])',
        ]);

        $this->createIndex('idx-test_category-test_id', '{{%test_category}}', 'test_id');
        $this->addForeignKey(
            'fk-test_category-test_id',
            '{{%test_category}}', 'test_id', '{{%test}}', 'id',
            'RESTRICT', 'CASCADE',
        );
        $this->createIndex('idx-test_category-category_id', '{{%test_category}}', 'category_id');
        $this->addForeignKey(
            'fk-test_category-category_id',
            '{{%test_category}}', 'category_id', '{{%category}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-test_category-category_id', '{{%test_category}}');
        $this->dropForeignKey('fk-test_category-test_id', '{{%test_category}}');
        $this->dropTable('{{%test_category}}');
    }
}
