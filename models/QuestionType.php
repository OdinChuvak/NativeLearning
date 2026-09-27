<?php

declare(strict_types=1);

namespace app\models;

use app\enum\QuestionTypeCategory;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $category One of QuestionTypeCategory constants.
 * @property string $score
 */
class QuestionType extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%question_type}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'category', 'score'], 'required'],
            ['name', 'string', 'max' => 255],
            ['description', 'string'],
            ['category', 'integer'],
            ['category', 'in', 'range' => QuestionTypeCategory::values()],
            ['score', 'number'],
        ];
    }
}
