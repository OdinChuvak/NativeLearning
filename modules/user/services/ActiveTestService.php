<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use app\enum\QuestionTypeCategory;
use app\modules\user\services\question\QuestionHandlerRegistry;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

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
        if (!$test) throw new NotFoundHttpException('Тест не найден.');
        return $test;
    }

    public function start(int $userId, int $courseId, int $testId): array
    {
        $db = $this->database($userId, $courseId);
        return $db->transaction(function () use ($db, $userId, $testId): array {
            $test = $this->lockTest($db, $userId, $testId);
            $attempt = (new Query())->from('{{%active_test}}')->where(['test_id' => $testId, 'status' => 'active'])->orderBy(['id' => SORT_DESC])->one($db);
            // Reopening an active attempt advances past the previously shown question.
            if ($attempt) return $this->state($db, $attempt);
            $selected = (new TopicQuestionSelector())->select($this->topics($db, $userId, $testId), (int) $test['question_count']);
            $db->createCommand()->insert('{{%active_test}}', ['test_id' => $testId, 'status' => 'active'])->execute();
            $id = (int) $db->getLastInsertID();
            foreach ($selected as $question) {
                $db->createCommand()->insert('{{%active_test_question}}', ['question_id' => $question['question_id'], 'active_test_id' => $id, 'is_shown' => 0])->execute();
            }
            return $this->state($db, ['id' => $id, 'test_id' => $testId, 'status' => 'active']);
        });
    }

    private function topics(Connection $db, int $userId, int $testId): array
    {
        $categories = (new Query())->select('category_id')->from('{{%test_category}}')->where(['test_id' => $testId]);
        $topics = (new Query())->from('{{%topic}}')->where(['in', 'category_id', $categories])->all($db);
        $recent = (new Query())->select('id')->from('{{%completed_test}}')->where(['user_id' => $userId])->orderBy(['id' => SORT_DESC])->limit(3)->column($db);
        $frequency = (new Query())->select(['question_id', 'frequency' => 'COUNT(*)'])->from('{{%test_question_frequency}}')->where(['test_id' => $testId])->groupBy('question_id')->all($db);
        $frequency = array_column($frequency, 'frequency', 'question_id');
        foreach ($topics as &$topic) {
            $topic['id'] = (int) $topic['id'];
            $history = (new Query())->select('completed_test_id')->from('{{%user_topic_stat}}')
                ->where(['user_id' => $userId, 'topic_id' => $topic['id'], 'completed_test_id' => $recent])->column($db);
            $topic['recency'] = 3;
            foreach ($recent as $rank => $id) if (in_array($id, $history)) { $topic['recency'] = $rank; break; }
            $topic['scores'] = (new Query())->select('score')->from('{{%user_topic_stat}}')
                ->where(['user_id' => $userId, 'topic_id' => $topic['id']])->orderBy(['id' => SORT_DESC])->limit(10)->column($db);
            $ids = (new Query())->select('q.id')->distinct()->from(['q' => '{{%question}}'])
                ->innerJoin(['qt' => '{{%question_topic}}'], '[[qt.question_id]] = [[q.id]]')
                ->innerJoin(['r' => '{{%right_answer}}'], '[[r.question_id]] = [[q.id]]')
                ->where(['qt.topic_id' => $topic['id']])->column($db);
            $topic['questions'] = [];
            foreach ($ids as $id) $topic['questions'][(int) $id] = (int) ($frequency[$id] ?? 0);
        }
        unset($topic);
        return $topics;
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
        $row = (new Query())->select(['q.*', 'type_name' => 't.name', 'category' => 't.category', 't.code', 't.success_score', 't.failure_score'])
            ->from(['q' => '{{%question}}'])->innerJoin(['t' => '{{%question_type}}'], '[[t.id]] = [[q.type]]')->where(['q.id' => $id])->one($db);
        if (!$row) throw new ConflictHttpException('Вопрос больше недоступен.');
        $row['options'] = (new Query())->select(['id', 'answer'])->from('{{%answer_option}}')->where(['question_id' => $id])->orderBy('id')->all($db);
        $row['options'] = array_map(static fn(array $option): array => ['id' => (int) $option['id'], 'answer' => (string) $option['answer']], $row['options']);
        $row['right_answers'] = array_map(fn(mixed $answer): mixed => $this->decodeAnswer($answer),
            (new Query())->select('answer')->from('{{%right_answer}}')->where(['question_id' => $id])->orderBy('id')->column($db));
        return $row;
    }

    private function prepare(array $question): array
    {
        $handler = QuestionHandlerRegistry::resolve((string) $question['code'], (int) $question['category']);
        $prepared = $handler->prepare($question['options'], $question['right_answers']);
        return $prepared + ['question' => $question['question'], 'type_name' => $question['type_name'], 'code' => $question['code'],
            'category' => (int) $question['category'], 'success_score' => (int) $question['success_score'], 'failure_score' => (int) $question['failure_score']];
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
        $handler = QuestionHandlerRegistry::resolve($q['code'], (int) $q['category']);
        $result['question'] = ['id' => (int) $current['id'], 'text' => $q['question'], 'category' => (int) $q['category'],
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
            $handler = QuestionHandlerRegistry::resolve((string) $q['code'], (int) $q['category']);
            $options = $this->decodeAnswer($saved['presented_options']);
            $answer = $handler->normalize($answer, array_column($options, 'id'));
        }
        $db->createCommand()->update('{{%active_test_answer}}', ['answer' => $answer === null ? null : $this->jsonValue($answer)], ['id' => $saved['id']])->execute();
    }

    private function score(array $q, mixed $answer): int
    {
        $correct = false;
        if ($answer !== null) {
            $ids = array_map('intval', array_column($q['options'], 'id'));
            $handler = QuestionHandlerRegistry::resolve($q['code'], (int) $q['category']);
            $answer = $handler->normalize($answer, $ids);
            foreach ($q['right_answers'] as $right) {
                $right = $handler->normalize($right, $q['bank_ids']);
                if ($handler->matches($answer, $right)) { $correct = true; break; }
            }
        }
        return (int) $q[$correct ? 'success_score' : 'failure_score'];
    }

    private function finish(Connection $db, int $userId, array $attempt): array
    {
        $rows = (new Query())->from('{{%active_test_question}}')->where(['active_test_id' => $attempt['id']])->orderBy('id')->all($db);
        if (!$rows) throw new ConflictHttpException('В попытке нет вопросов.');
        $snapshot = [];
        $statistics = [];
        foreach ($rows as $row) {
            $q = $this->question($db, (int) $row['question_id']);
            $q['bank_ids'] = array_column($q['options'], 'id');
            $topics = (new Query())->select(['t.id', 't.name', 'category_name' => 'c.name'])->distinct()->from(['t' => '{{%topic}}'])
                ->innerJoin(['c' => '{{%category}}'], '[[c.id]] = [[t.category_id]]')
                ->innerJoin(['qt' => '{{%question_topic}}'], '[[qt.topic_id]] = [[t.id]]')
                ->where(['qt.question_id' => $row['question_id']])->orderBy('t.id')->all($db);
            $saved = (new Query())->from('{{%active_test_answer}}')->where(['active_test_question_id' => $row['id']])->one($db);
            if (!$saved) throw new ConflictHttpException('Вопрос ещё не показан.');
            $answer = $this->decodeAnswer($saved['answer']);
            $q['options'] = $this->decodeAnswer($saved['presented_options']);
            $score = $this->score($q, $answer);
            foreach ($topics as $topic) $statistics[] = ['user_id' => $userId, 'topic_id' => (int) $topic['id'], 'score' => $score];
            $values = array_column($q['options'], 'answer', 'id');
            $value = $answer === null ? null : ($answer['input'] ?? array_map(static fn(int $id): string => $values[$id], $answer['choice'] ?? $answer['order']));
            $snapshot[] = ['category' => implode(', ', array_unique(array_column($topics, 'category_name'))), 'topic' => implode(', ', array_column($topics, 'name')), 'question' => $q['question'],
                'question_type' => $q['type_name'], 'question_type_category' => match ((int) $q['category']) { 1 => 'choice', 2 => 'input', 3 => 'order' },
                'answer_options' => array_values($values), 'answer' => $value, 'score' => $score];
        }
        $db->createCommand()->insert('{{%completed_test}}', ['user_id' => $userId, 'body' => $this->jsonValue($snapshot), 'completed_at' => gmdate('Y-m-d H:i:s')])->execute();
        $completedId = (int) $db->getLastInsertID();
        // Every answer contributes its full score to every related topic.
        foreach ($statistics as $statistic) {
            $db->createCommand()->insert('{{%user_topic_stat}}', $statistic + ['completed_test_id' => $completedId])->execute();
        }
        foreach ($rows as $row) {
            $db->createCommand()->insert('{{%test_question_frequency}}', ['test_id' => $attempt['test_id'], 'question_id' => $row['question_id']])->execute();
        }
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
