<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use app\enum\QuestionTypeCategory;
use yii\web\ConflictHttpException;

final class QuestionHandlerRegistry
{
    public static function categories(): array
    {
        return ['choice_4_single' => QuestionTypeCategory::CHOICE, 'choice_multiple' => QuestionTypeCategory::CHOICE,
            'input_text' => QuestionTypeCategory::INPUT, 'ordering' => QuestionTypeCategory::ORDERING];
    }

    public static function resolve(string $key, int $category): QuestionHandler
    {
        $handler = match ($key) {
            'choice_4_single' => new ChoiceHandler(4),
            'choice_multiple' => new ChoiceHandler(),
            'input_text' => new InputHandler(),
            'ordering' => new OrderingHandler(),
            default => throw new ConflictHttpException('Для типа вопроса не зарегистрирован обработчик.'),
        };
        if ($handler->category() !== $category) throw new ConflictHttpException('Обработчик не соответствует категории типа вопроса.');
        return $handler;
    }
}
