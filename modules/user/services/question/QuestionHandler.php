<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

interface QuestionHandler
{
    public function category(): int;

    /** Returns the options presented to the user and normalized accepted answers. */
    public function prepare(array $options, array $answers): array;

    public function normalize(mixed $answer, array $optionIds): array;

    public function matches(array $answer, array $right): bool;

    public function maxSelections(): ?int;
}
