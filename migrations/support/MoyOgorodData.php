<?php

declare(strict_types=1);

/** Immutable-format validation before the migration performs any DDL. */
final class MoyOgorodData
{
    public static function load(?string $path = null): array
    {
        $path ??= __DIR__ . '/../../data/courses/moy_ogorod/course.json';
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Course data file is missing or unreadable: ' . $path);
        }
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::validate($data);
        return $data;
    }

    public static function validate(array $data): void
    {
        self::ensure(($data['format_version'] ?? null) === 1, 'Unsupported course format.');
        self::ensure(($data['course']['alias'] ?? null) === 'moy_ogorod', 'Unexpected course alias.');
        self::ensure(($data['course']['name'] ?? null) === 'Мой огород', 'Unexpected course name.');
        self::ensure(($data['course']['answer_mode'] ?? null) === 'single', 'Expected single-answer questions.');
        self::ensure(($data['course']['score_per_question'] ?? null) === 1, 'Unexpected question score.');
        self::nonempty($data['course']['description'] ?? null);
        $names = ['Основы ведения огорода', 'Сорняки и вредители в огороде', 'Удобрения и средства обработки для огорода'];
        self::ensure(is_array($data['categories'] ?? null) && count($data['categories']) === 3, 'Expected 3 categories.');
        $sourceIds = array_column($data['sources'] ?? [], 'id');
        $topicId = 0;
        $questionId = 0;
        $seenQuestions = [];
        $seenTopics = [];
        foreach ($data['categories'] as $index => $category) {
            self::ensure(($category['id'] ?? null) === $index + 1 && ($category['name'] ?? null) === $names[$index], 'Invalid category.');
            self::ensure(is_array($category['topics'] ?? null) && count($category['topics']) === 13, 'Expected 13 topics per category.');
            foreach ($category['topics'] as $topic) {
                self::ensure(($topic['id'] ?? null) === ++$topicId, 'Invalid topic ID.');
                self::nonempty($topic['name'] ?? null, 255);
                self::ensure(!isset($seenTopics[$topic['name']]), 'Duplicate topic.');
                $seenTopics[$topic['name']] = true;
                self::nonempty($topic['description'] ?? null);
                self::ensure(!empty($topic['source_ids']) && array_diff($topic['source_ids'], $sourceIds) === [], 'Invalid topic sources.');
                self::ensure(is_array($topic['questions'] ?? null) && count($topic['questions']) === 20, 'Expected 20 questions per topic.');
                foreach ($topic['questions'] as $question) {
                    self::ensure(($question['id'] ?? null) === ++$questionId, 'Invalid question ID.');
                    self::nonempty($question['question'] ?? null);
                    self::ensure(!isset($seenQuestions[$question['question']]), 'Duplicate question.');
                    $seenQuestions[$question['question']] = true;
                    self::ensure(is_array($question['answers'] ?? null) && count($question['answers']) === 10, 'Expected 10 answers per question.');
                    $seenAnswers = [];
                    $correct = 0;
                    foreach ($question['answers'] as $answer) {
                        self::nonempty($answer['answer'] ?? null);
                        self::ensure(!isset($seenAnswers[$answer['answer']]), 'Duplicate answer within a question.');
                        $seenAnswers[$answer['answer']] = true;
                        self::ensure(is_bool($answer['is_correct'] ?? null), 'Answer flag must be boolean.');
                        $correct += (int) $answer['is_correct'];
                    }
                    self::ensure($correct === 1, 'Expected exactly one correct answer.');
                }
            }
        }
    }

    private static function nonempty(mixed $value, int $maxLength = 16000): void
    {
        self::ensure(is_string($value) && trim($value) !== '' && mb_strlen($value, 'UTF-8') <= $maxLength, 'Invalid or oversized course text.');
    }

    private static function ensure(bool $valid, string $message): void
    {
        if (!$valid) {
            throw new RuntimeException($message);
        }
    }
}
