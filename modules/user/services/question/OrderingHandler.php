<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use app\enum\QuestionTypeCategory;

final class OrderingHandler extends OptionHandler
{
    public function category(): int { return QuestionTypeCategory::ORDERING; }
    protected function key(): string { return 'order'; }

    public function prepare(array $options, array $answers): array
    {
        $answers = $this->rightAnswers($options, $answers);
        shuffle($options);
        return ['options' => $options, 'right_answers' => $answers];
    }
}
