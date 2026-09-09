<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260913_000000_create_question_type_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%question_type}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'score' => $this->decimal(10, 2)->notNull(),
        ]);


    }

    public function safeDown(): void
    {

        $this->dropTable('{{%question_type}}');
    }
}
