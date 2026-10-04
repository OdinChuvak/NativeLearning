<?php
declare(strict_types=1);
namespace app\models;

final class Profile extends \yii\db\ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%profile}}';
    }
}
