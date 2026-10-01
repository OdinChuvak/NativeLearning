<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use app\enum\QuestionTypeCategory;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

final class InputHandler implements QuestionHandler
{
    public function category(): int { return QuestionTypeCategory::INPUT; }
    public function maxSelections(): ?int { return null; }

    public function normalize(mixed $answer, array $optionIds): array
    {
        if (!is_array($answer) || array_keys($answer) !== ['input'] || !is_string($answer['input'])
            || trim($answer['input']) === '' || mb_strlen($answer['input']) > 10000) throw new BadRequestHttpException('Введите текстовый ответ.');
        return ['input' => trim($answer['input'])];
    }

    public function prepare(array $options, array $answers): array
    {
        if (!$answers) throw new ConflictHttpException('Для вопроса не задан правильный ответ.');
        try {
            return ['options' => [], 'right_answers' => array_map(fn(mixed $answer): array => $this->normalize($answer, []), $answers)];
        } catch (BadRequestHttpException $error) {
            throw new ConflictHttpException('Некорректный правильный ответ вопроса.', 0, $error);
        }
    }

    public function matches(array $answer, array $right): bool
    {
        return mb_strtolower($answer['input']) === mb_strtolower($right['input']);
    }
}
