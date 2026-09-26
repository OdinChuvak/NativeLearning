<?php

declare(strict_types=1);

namespace app\components\db;

abstract class BaseMigration extends \yii\db\Migration
{
    /** Null means the connection selected by the command. Must have no database side effects. */
    public static function getConnections(): ?ConnectionGroup
    {
        return null;
    }
}
