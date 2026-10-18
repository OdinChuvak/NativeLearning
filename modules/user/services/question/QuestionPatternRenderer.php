<?php
declare(strict_types=1);
namespace app\modules\user\services\question;

use yii\web\ConflictHttpException;

final class QuestionPatternRenderer
{
    public static function render(string $pattern, mixed $values): string
    {
        if (is_string($values)) {
            try {
                $values = json_decode($values, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new ConflictHttpException('Некорректные значения переменных вопроса.', 0, $error);
            }
            if (!$values instanceof \stdClass) {
                throw new ConflictHttpException('Значения переменных вопроса должны быть JSON-объектом.');
            }
        }
        if ($values instanceof \stdClass) $values = get_object_vars($values);
        if (!is_array($values)) throw new ConflictHttpException('Значения переменных вопроса должны быть JSON-объектом.');

        return preg_replace_callback('/\{\{([^{}]+)\}\}/u', static function (array $match) use ($values): string {
            $key = $match[1];
            if (!array_key_exists($key, $values)) {
                throw new ConflictHttpException('Не задана переменная вопроса: ' . $key);
            }
            // Values are literal content. Do not evaluate markup or nested placeholders.
            return is_string($values[$key]) ? $values[$key]
                : json_encode($values[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, $pattern);
    }
}
