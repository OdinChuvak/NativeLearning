<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use app\enum\QuestionTypeCategory;
use yii\web\ConflictHttpException;

final class ChoiceHandler extends OptionHandler
{
    public function __construct(private readonly ?int $optionCount = null) {}

    public function category(): int { return QuestionTypeCategory::CHOICE; }
    protected function key(): string { return 'choice'; }
    public function maxSelections(): ?int { return $this->optionCount === null ? null : 1; }

    public function prepare(array $options, array $answers): array
    {
        $answers = $this->rightAnswers($options, $answers);
        if ($this->optionCount !== null) {
            $correctIds = array_values(array_unique(array_merge(...array_column($answers, 'choice'))));
            if (count($correctIds) !== 1) throw new ConflictHttpException('Тип «4 варианта, 1 ответ» требует ровно одного правильного варианта.');
            $wrong = array_values(array_filter($options, static fn(array $option): bool => !in_array((int) $option['id'], $correctIds, true)));
            if (count($wrong) < $this->optionCount - 1) throw new ConflictHttpException('Для вопроса недостаточно неправильных вариантов ответа.');
            shuffle($wrong);
            $correct = array_values(array_filter($options, static fn(array $option): bool => in_array((int) $option['id'], $correctIds, true)));
            $options = [...$correct, ...array_slice($wrong, 0, $this->optionCount - 1)];
        }
        shuffle($options);
        return ['options' => $options, 'right_answers' => $answers];
    }
}
