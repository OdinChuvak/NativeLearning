<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use app\modules\user\services\question\QuestionHandlerRegistry;

/**
 * @property int $id
 * @property string|null $description
 * @property string $code Registered question handler key.
 */
class QuestionAnswerType extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%question_answer_type}}';
    }

    public function rules(): array
    {
        return [
            ['code', 'required'],
            ['code', 'string', 'max' => 64],
            ['code', 'unique'],
            ['description', 'string'],
            ['code', 'in', 'range' => array_keys(QuestionHandlerRegistry::categories())],
        ];
    }
}
