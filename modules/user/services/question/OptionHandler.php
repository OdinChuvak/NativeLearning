<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

abstract class OptionHandler implements QuestionHandler
{
    abstract protected function key(): string;

    public function normalize(mixed $answer, array $optionIds): array
    {
        $key = $this->key();
        if (!is_array($answer) || array_keys($answer) !== [$key]) throw new BadRequestHttpException('Некорректный формат ответа.');
        $value = $answer[$key];
        if (!is_array($value) || !array_is_list($value) || !$value || count($value) !== count(array_unique($value, SORT_REGULAR))) {
            throw new BadRequestHttpException('Выберите варианты ответа без повторений.');
        }
        foreach ($value as $id) {
            if (!is_int($id) || !in_array($id, $optionIds, true)) throw new BadRequestHttpException('Вариант ответа не был предложен в этой попытке.');
        }
        if ($key === 'order' && count($value) !== count($optionIds)) throw new BadRequestHttpException('Расставьте все варианты ответа.');
        if ($this->maxSelections() !== null && count($value) > $this->maxSelections()) throw new BadRequestHttpException('Выберите только один вариант ответа.');
        if ($key === 'choice') sort($value, SORT_NUMERIC);
        return [$key => $value];
    }

    protected function rightAnswers(array $options, array $answers): array
    {
        $ids = array_map('intval', array_column($options, 'id'));
        if (!$ids || count($ids) !== count(array_unique($ids)) || !$answers) throw new ConflictHttpException('Вопрос содержит некорректный набор вариантов или правильных ответов.');
        try {
            return array_map(fn(mixed $answer): array => $this->normalize($answer, $ids), $answers);
        } catch (BadRequestHttpException $error) {
            throw new ConflictHttpException('Некорректный правильный ответ вопроса.', 0, $error);
        }
    }

    public function matches(array $answer, array $right): bool
    {
        return $answer === $right;
    }

    public function maxSelections(): ?int
    {
        return null;
    }
}
