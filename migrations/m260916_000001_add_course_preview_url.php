<?php
declare(strict_types=1);

use yii\db\Migration;

final class m260916_000001_add_course_preview_url extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%course}}', 'preview_url', $this->string(2048)->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%course}}', 'preview_url');
    }
}
