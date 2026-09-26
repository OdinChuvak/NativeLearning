<?php

declare(strict_types=1);

use yii\db\Migration;

/** Registers the course in the main database; material import is a separate step. */
final class m260915_000001_create_moy_ogorod_course extends Migration
{
    public function safeUp(): void
    {
        $this->insert('{{%course}}', [
            'name' => 'Мой огород',
            'alias' => 'moy_ogorod',
            'description' => 'Базовый курс по огородоводству: выращивание овощей, сорняки и вредители, питание и средства обработки. 39 тем, 780 вопросов; в каждом вопросе один правильный ответ.',
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%course}}', ['alias' => 'moy_ogorod']);
    }
}
