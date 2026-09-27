<?php

declare(strict_types=1);

namespace app\enum;

final class QuestionTypeCategory
{
    /** Вопрос с выбором варианта ответа. */
    public const CHOICE = 1;

    /** Вопрос с самостоятельным вводом ответа. */
    public const INPUT = 2;

    /** Вопрос с расстановкой вариантов в правильной последовательности. */
    public const ORDERING = 3;

    public static function values(): array
    {
        return [self::CHOICE, self::INPUT, self::ORDERING];
    }
}
