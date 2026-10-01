<?php

declare(strict_types=1);

namespace app\models;

use app\enum\QuestionTypeCategory;
use yii\db\ActiveRecord;
use app\modules\user\services\question\QuestionHandlerRegistry;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $category One of QuestionTypeCategory constants.
 * @property int $success_score
 * @property int $failure_score
 * @property string $code Registered question handler key.
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
            [['name', 'category', 'success_score', 'failure_score', 'code'], 'required'],
            ['name', 'string', 'max' => 255],
            ['description', 'string'],
            ['category', 'integer'],
            ['category', 'in', 'range' => QuestionTypeCategory::values()],
            ['code', 'in', 'range' => array_keys(QuestionHandlerRegistry::categories())],
            ['code', function (string $attribute): void {
                $categories = QuestionHandlerRegistry::categories();
                if (isset($categories[$this->code]) && $categories[$this->code] !== (int) $this->category) {
                    $this->addError($attribute, 'Обработчик не соответствует категории типа вопроса.');
                }
            }],
            [['success_score', 'failure_score'], 'integer', 'min' => -128, 'max' => 127],
        ];
    }
}
