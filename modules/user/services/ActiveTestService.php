<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use app\modules\user\services\question\QuestionHandlerRegistry;
use app\modules\user\services\question\QuestionPatternRenderer;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\HttpException;

final class ActiveTestService
{
    public function __construct(private readonly Connection $main, private readonly ConnectionManager $connections) {}

    private function database(int $userId, int $courseId): Connection
    {
        $course = (new Query())->from('{{%course}}')->where(['id' => $courseId])->one($this->main);
        if (!$course || !isset($this->connections->groups['course'][$course['alias']])) throw new NotFoundHttpException('Курс не найден.');
        $access = (new Query())->from(['s' => '{{%subscription_slot}}'])
            ->innerJoin(['p' => '{{%subscription}}'], '[[p.id]] = [[s.subscription_id]]')
            ->where(['p.user_id' => $userId, 's.course_id' => $courseId, 's.deactivated_at' => null])
            ->andWhere('[[s.activated_at]] <= CURRENT_TIMESTAMP')
            ->andWhere('[[p.starts_at]] <= CURRENT_TIMESTAMP')->andWhere('[[p.expires_at]] > CURRENT_TIMESTAMP')->exists($this->main);
        if (!$access) throw new ForbiddenHttpException('Нет действующего доступа к курсу.');
        return $this->connections->getConnection('course:' . $course['alias']);
    }

    private function lockTest(Connection $db, int $userId, int $testId): array
    {
        $command = (new Query())->from('{{%test}}')->where(['id' => $testId, 'user_id' => $userId])->createCommand($db);
        if ($db->driverName === 'mysql') {
            // setSql() clears bound parameters in Yii; preserve them for the locking query.
            $params = $command->params;
            $command->setSql($command->getSql() . ' FOR UPDATE')->bindValues($params);
        }
        $test = $command->queryOne();
        if (!$test || !empty($test['is_deleted'])) throw new NotFoundHttpException('Тест не найден.');
        return $test;
    }

    public function start(int $userId, int $courseId, int $testId): array
    {
        $db = $this->database($userId, $courseId);
        return $db->transaction(function () use ($db, $userId, $testId): array {
            $this->lockTest($db, $userId, $testId);
            throw new HttpException(503, 'Запуск тестов временно недоступен. Подбор вопросов находится в разработке.');
        });
    }

    public function act(int $userId, int $courseId, int $attemptId, string $action, array $body = []): array
    {
        $db = $this->database($userId, $courseId);
        // Resolve the parent before beginning the transaction, so MySQL's repeatable-read
        // snapshot is established only after acquiring the test lock.
        $attempt = (new Query())->from('{{%active_test}}')->where(['id' => $attemptId])->one($db);
        if (!$attempt) throw new NotFoundHttpException('Попытка не найдена.');
        return $db->transaction(function () use ($db, $userId, $attemptId, $action, $body, $attempt): array {
            $this->lockTest($db, $userId, (int) $attempt['test_id']);
            // Re-read after taking the lock: another request may have finished the attempt.
            $attempt = (new Query())->from('{{%active_test}}')->where(['id' => $attemptId])->one($db);
            if ($attempt['status'] !== 'active') return ['id' => $attemptId, 'status' => $attempt['status'], 'question' => null];
            $current = $this->lastShown($db, $attemptId);
            if ($action === 'cancel') {
                $ids = (new Query())->select('id')->from('{{%active_test_question}}')->where(['active_test_id' => $attemptId])->column($db);
                $db->createCommand()->delete('{{%active_test_answer}}', ['active_test_question_id' => $ids])->execute();
                $db->createCommand()->delete('{{%active_test_question}}', ['active_test_id' => $attemptId])->execute();
                $db->createCommand()->update('{{%active_test}}', ['status' => 'rejected'], ['id' => $attemptId])->execute();
                return ['id' => $attemptId, 'status' => 'rejected', 'question' => null];
            }
            if ($action === 'finish') {
                if ($this->nextQuestion($db, $attemptId)) throw new ConflictHttpException('Сначала просмотрите все вопросы.');
                return $this->finish($db, $userId, $attempt);
            }
            if (!in_array($action, ['resume', 'answer', 'timeout'], true)) throw new BadRequestHttpException('Неизвестное действие.');
            if ($action === 'resume') return $this->state($db, $attempt);
            if ($current) {
                // The expected question makes retries and simultaneous tabs idempotent.
                $expected = $body['question_id'] ?? null;
                if (!is_int($expected)) throw new BadRequestHttpException('Не указан текущий вопрос попытки.');
                if ($expected !== (int) $current['id']) return $this->state($db, $attempt, false);
                $savedAnswer = (new Query())->from('{{%active_test_answer}}')->where(['active_test_question_id' => $current['id']])->one($db);
                if ($savedAnswer && $savedAnswer['answer'] !== null) return $this->state($db, $attempt, false);
                if ($action === 'timeout') $this->record($db, $current, null);
                else {
                    $skip = $body['skip'] ?? false;
                    if (!is_bool($skip)) throw new BadRequestHttpException('Некорректный признак пропуска.');
                    $this->record($db, $current, $skip ? null : ($body['answer'] ?? []));
                }
            }
            return $this->state($db, $attempt);
        });
    }

    private function nextQuestion(Connection $db, int $id): array|false
    {
        return (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $id, 'is_shown' => 0])->orderBy('id')->one($db);
    }

    private function lastShown(Connection $db, int $id): array|false
    {
        return (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $id, 'is_shown' => 1])->orderBy(['id' => SORT_DESC])->one($db);
    }

    private function question(Connection $db, int $id): array
    {
        $row = (new Query())->select(['q.*', 'p.pattern', 'type_name' => 't.description', 't.code'])
            ->from(['q' => '{{%question}}'])
            ->innerJoin(['p' => '{{%question_pattern}}'], '[[p.id]] = [[q.pattern_id]]')
            ->innerJoin(['t' => '{{%question_answer_type}}'], '[[t.id]] = [[q.answer_type_id]]')
            ->where(['q.id' => $id])->one($db);
        if (!$row) throw new ConflictHttpException('Вопрос больше недоступен.');
        $row['question'] = QuestionPatternRenderer::render($row['pattern'], $row['pattern_values']);
        $row['type_name'] ??= $row['code'];
        $row['category'] = QuestionHandlerRegistry::resolve($row['code'])->category();
        $row['options'] = (new Query())->select(['id', 'answer' => 'value'])
            ->from('{{%question_answer_option}}')->where(['question_id' => $id])->orderBy('id')->all($db);
        $row['options'] = array_map(static fn(array $option): array => ['id' => (int) $option['id'], 'answer' => (string) $option['answer']], $row['options']);
        $row['right_answers'] = array_map(fn(mixed $answer): mixed => $this->decodeAnswer($answer),
            (new Query())->select('answer')->from('{{%question_right_answer}}')->where(['question_id' => $id])->orderBy('id')->column($db));
        return $row;
    }

    private function prepare(array $question): array
    {
        $handler = QuestionHandlerRegistry::resolve($question['code']);
        return $handler->prepare($question['options'], $question['right_answers']) + [
            'question' => $question['question'], 'type_name' => $question['type_name'],
            'code' => $question['code'], 'category' => $handler->category(),
        ];
    }

    private function state(Connection $db, array $attempt, bool $advance = true): array
    {
        $id = (int) $attempt['id'];
        $current = $advance ? $this->nextQuestion($db, $id) : $this->lastShown($db, $id);
        $result = ['id' => $id, 'status' => $attempt['status'], 'question' => null,
            'total' => (int) (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $id])->count('*', $db),
            'answered' => (int) (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $id, 'is_shown' => 1])->count('*', $db)];
        if (!$current) return $result;
        if ($advance) {
            $q = $this->prepare($this->question($db, (int) $current['question_id']));
            $db->createCommand()->insert('{{%active_test_answer}}', [
                'active_test_question_id' => $current['id'], 'answer' => null,
                'presented_options' => $this->jsonValue($q['options']),
            ])->execute();
            $db->createCommand()->update('{{%active_test_question}}', ['is_shown' => 1], ['id' => $current['id']])->execute();
        } else {
            $saved = (new Query())->from('{{%active_test_answer}}')->where(['active_test_question_id' => $current['id']])->one($db);
            if (!$saved || $saved['answer'] !== null) return $result;
            $q = $this->question($db, (int) $current['question_id']);
            $q['options'] = $this->decodeAnswer($saved['presented_options']);
            $result['answered'] = max(0, $result['answered'] - 1);
        }
        $handler = QuestionHandlerRegistry::resolve($q['code']);
        $result['question'] = ['id' => (int) $current['id'], 'text' => $q['question'], 'category' => (int) $q['category'], 'answer_type_code' => $q['code'],
            'options' => array_map(static fn(array $o): array => ['id' => (int) $o['id'], 'text' => $o['answer']], $q['options']),
            'max_selections' => $handler->maxSelections(),
            'remaining_seconds' => 60];
        return $result;
    }

    private function record(Connection $db, array $current, mixed $answer): void
    {
        $saved = (new Query())->from('{{%active_test_answer}}')->where(['active_test_question_id' => $current['id']])->one($db);
        if (!$saved) throw new ConflictHttpException('Вопрос ещё не показан.');
        if ($saved['answer'] !== null) throw new ConflictHttpException('Ответ на этот вопрос уже сохранён.');
        if ($answer !== null) {
            $q = $this->question($db, (int) $current['question_id']);
            $handler = QuestionHandlerRegistry::resolve($q['code']);
            $options = $this->decodeAnswer($saved['presented_options']);
            $answer = $handler->normalize($answer, array_column($options, 'id'));
        }
        $db->createCommand()->update('{{%active_test_answer}}', ['answer' => $answer === null ? null : $this->jsonValue($answer)], ['id' => $saved['id']])->execute();
    }

    private function isCorrect(array $q, mixed $answer): bool
    {
        $correct = false;
        if ($answer !== null) {
            $ids = array_map('intval', array_column($q['options'], 'id'));
            $handler = QuestionHandlerRegistry::resolve($q['code']);
            $answer = $handler->normalize($answer, $ids);
            foreach ($q['right_answers'] as $right) {
                $right = $handler->normalize($right, $q['bank_ids']);
                if ($handler->matches($answer, $right)) { $correct = true; break; }
            }
        }
        return $correct;
    }

    private function finish(Connection $db, int $userId, array $attempt): array
    {
        $rows = (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $attempt['id']])->orderBy('id')->all($db);
        if (!$rows) throw new ConflictHttpException('В попытке нет вопросов.');
        $snapshot = [];
        $results = [];
        foreach ($rows as $row) {
            $questionId = (int) $row['question_id'];
            $q = $this->question($db, $questionId);
            $q['bank_ids'] = array_column($q['options'], 'id');
            $topics = (new Query())->select(['t.id', 't.name', 'category_name' => 'c.name'])->distinct()
                ->from(['t' => '{{%topic}}'])
                ->innerJoin(['c' => '{{%category}}'], '[[c.id]] = [[t.category_id]]')
                ->innerJoin(['tl' => '{{%topic_learning_item}}'], '[[tl.topic_id]] = [[t.id]]')
                ->innerJoin(['qs' => '{{%question_score}}'], '[[qs.learning_item_id]] = [[tl.learning_item_id]]')
                ->where(['qs.question_id' => $questionId, 'tl.type' => 'acquired'])->orderBy('t.id')->all($db);
            $saved = (new Query())->from('{{%active_test_answer}}')->where(['active_test_question_id' => $row['id']])->one($db);
            if (!$saved) throw new ConflictHttpException('Вопрос ещё не показан.');
            $answer = $this->decodeAnswer($saved['answer']);
            $q['options'] = $this->decodeAnswer($saved['presented_options']);
            $correct = $this->isCorrect($q, $answer);
            $results[] = ['question_id' => $questionId, 'is_correct' => $correct];
            $values = array_column($q['options'], 'answer', 'id');
            $value = $answer === null ? null : ($answer['input'] ?? array_map(static fn(int $id): string => $values[$id], $answer['choice'] ?? $answer['order']));
            $snapshot[] = [
                'category' => implode(', ', array_unique(array_column($topics, 'category_name'))),
                'topic' => implode(', ', array_column($topics, 'name')), 'question' => $q['question'],
                'answer_type' => $q['type_name'], 'answer_type_code' => $q['code'],
                'answer_options' => array_values($values), 'answer' => $value, 'is_correct' => $correct,
            ];
        }
        $db->createCommand()->insert('{{%completed_test}}', [
            'user_id' => $userId, 'body' => $this->jsonValue($snapshot), 'completed_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
        $completedId = (int) $db->getLastInsertID();
        (new LearningScoreService($db))->recordTest($userId, $completedId, $results);
        $db->createCommand()->delete('{{%active_test_answer}}', ['active_test_question_id' => array_column($rows, 'id')])->execute();
        $db->createCommand()->delete('{{%active_test_question}}', ['active_test_id' => $attempt['id']])->execute();
        $db->createCommand()->update('{{%active_test}}', ['status' => 'passed'], ['id' => $attempt['id']])->execute();
        return ['id' => (int) $attempt['id'], 'status' => 'passed', 'question' => null];
    }

    private function jsonValue(mixed $value): Expression
    {
        // Bypass MySQL JSON column typecasting of an already encoded string.
        return new Expression(':attempt_json', [':attempt_json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    private function decodeAnswer(mixed $value): mixed
    {
        // Existing attempts may contain a JSON string enclosing the actual JSON answer.
        for ($layer = 0; $layer < 2 && is_string($value); $layer++) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        return $value;
    }
}
