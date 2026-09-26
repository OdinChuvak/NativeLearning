<?php

declare(strict_types=1);

use app\components\db\BaseMigration as Migration;

final class m260913_000004_create_question_table extends Migration
{
    public static function getConnections(): \app\components\db\ConnectionGroup
    {
        return Yii::$app->dbManager->getGroup('course');
    }

    public function safeUp(): void
    {
        $this->createTable('{{%question}}', [
            'id' => $this->primaryKey(),
            'question' => $this->text()->notNull(),
            'type' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx-question-type', '{{%question}}', 'type');
        $this->addForeignKey(
            'fk-question-type', '{{%question}}', 'type', '{{%question_type}}', 'id',
            'RESTRICT', 'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-question-type', '{{%question}}');
        $this->dropTable('{{%question}}');
    }
}
