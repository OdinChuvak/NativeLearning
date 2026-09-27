<?php

declare(strict_types=1);

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;
use app\enum\QuestionTypeCategory;
use yii\db\Expression;
use yii\db\Query;

require_once __DIR__ . '/../support/MoyOgorodData.php';

/** Seeds the separate vegetable-garden database from the validated source bank. */
final class m260927_000002_load_moy_ogorod extends BaseMigration
{
    public static function getConnections(): ConnectionGroup
    {
        return ConnectionGroup::from(Yii::$app->dbManager->getConnection('course:moy_ogorod'));
    }

    public function safeUp(): void
    {
        $tables = $this->rows();
        // This is an initial import, not an update/merge of existing educational content.
        foreach (array_keys($tables) as $table) {
            if ((new Query())->from('{{%' . $table . '}}')->exists($this->db)) {
                throw new RuntimeException("Загрузка курса «Мой огород» требует пустой таблицы {$table}.");
            }
        }
        foreach ($tables as $table => $rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                $this->batchInsert('{{%' . $table . '}}', array_keys($rows[0]), array_map('array_values', $chunk));
            }
        }
    }

    public function safeDown(): void
    {
        // Delete only seeded IDs, in reverse dependency order. References from attempts
        // prevent rollback through the schema's RESTRICT foreign keys.
        foreach (array_reverse($this->rows(), true) as $table => $rows) {
            $key = $table === 'question_topic' ? 'question_id' : 'id';
            foreach (array_chunk(array_column($rows, $key), 100) as $ids) {
                $this->delete('{{%' . $table . '}}', [$key => $ids]);
            }
        }
    }

    private function rows(): array
    {
        $data = MoyOgorodData::load();
        $tables = [
            'question_type' => [[
                'id' => 1,
                'name' => '4 варианта, 1 ответ',
                'description' => 'Один правильный ответ. Банк курса содержит 10 вариантов на вопрос.',
                'category' => QuestionTypeCategory::CHOICE,
                'score' => $data['course']['score_per_question'],
            ]],
            'category' => [], 'topic' => [], 'question' => [],
            'answer_option' => [], 'right_answer' => [], 'question_topic' => [],
        ];
        $optionId = 0;
        foreach ($data['categories'] as $category) {
            $tables['category'][] = [
                'id' => $category['id'], 'name' => $category['name'],
                'description' => $category['description'] ?? null,
            ];
            foreach ($category['topics'] as $topic) {
                $tables['topic'][] = [
                    'id' => $topic['id'], 'category_id' => $category['id'],
                    'name' => $topic['name'], 'description' => $topic['description'],
                ];
                foreach ($topic['questions'] as $question) {
                    $id = $question['id'];
                    $tables['question'][] = ['id' => $id, 'question' => $question['question'], 'type' => 1];
                    $tables['question_topic'][] = ['question_id' => $id, 'topic_id' => $topic['id']];
                    $correct = [];
                    foreach ($question['answers'] as $answer) {
                        ++$optionId;
                        $tables['answer_option'][] = ['id' => $optionId, 'question_id' => $id, 'answer' => $answer['answer']];
                        if ($answer['is_correct']) {
                            $correct[] = $optionId;
                        }
                    }
                    // Bind encoded JSON as a scalar: MySQL's JSON typecasting must not
                    // encode an already encoded string a second time.
                    $parameter = ':right_answer_' . $id;
                    $tables['right_answer'][] = [
                        'id' => $id, 'question_id' => $id,
                        'answer' => new Expression($parameter, [
                            $parameter => json_encode(['choice' => $correct], JSON_THROW_ON_ERROR),
                        ]),
                    ];
                }
            }
        }
        return $tables;
    }
}
