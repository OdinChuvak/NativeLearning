<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use app\enum\QuestionAnswerCategory;
use yii\web\ConflictHttpException;

final class QuestionHandlerRegistry
{
    public static function categories(): array
    {
        return ['choice_4_single' => QuestionAnswerCategory::CHOICE, 'choice_multiple' => QuestionAnswerCategory::CHOICE,
            'input_text' => QuestionAnswerCategory::INPUT, 'ordering' => QuestionAnswerCategory::ORDERING];
    }

    public static function resolve(string $key): QuestionHandler
    {
        return match ($key) {
            'choice_4_single' => new ChoiceHandler(4),
            'choice_multiple' => new ChoiceHandler(),
            'input_text' => new InputHandler(),
            'ordering' => new OrderingHandler(),
            default => throw new ConflictHttpException('Для типа ответа не зарегистрирован обработчик.'),
        };
    }
}
