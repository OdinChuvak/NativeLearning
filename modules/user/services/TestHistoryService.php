<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\db\Query;
use yii\web\NotFoundHttpException;

final class TestHistoryService
{
    public function __construct(private readonly Connection $main, private readonly ConnectionManager $connections) {}

    public function listing(int $userId, int $page): array
    {
        $page = max(1, $page);
        $items = [];
        $total = 0;
        foreach ((new Query())->from('{{%course}}')->all($this->main) as $course) {
            if (!isset($this->connections->groups['course'][$course['alias']])) continue;
            $db = $this->connections->getConnection('course:' . $course['alias']);
            if ($db->schema->getTableSchema('{{%completed_test}}') === null) continue;
            $query = (new Query())->from('{{%completed_test}}')->where(['user_id' => $userId]);
            $total += (int) (clone $query)->count('*', $db);
            foreach ($query->select(['id', 'completed_at', 'body'])->orderBy(['completed_at' => SORT_DESC, 'id' => SORT_DESC])->limit($page * 7)->all($db) as $row) {
                $items[] = $row + ['course_id' => (int) $course['id'], 'course_name' => $course['name']];
            }
        }
        usort($items, static fn(array $a, array $b): int => strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? '') ?: ((int) $b['id'] <=> (int) $a['id']) ?: ($a['course_id'] <=> $b['course_id']));
        $items = array_slice($items, ($page - 1) * 7, 7);
        foreach ($items as &$item) {
            $questions = $this->decode($item['body']);
            $categories = [];
            $positive = 0;
            $absolute = 0;
            foreach ($questions as $question) {
                if (!empty($question['category'])) $categories[] = $question['category'];
                $score = (int) ($question['score'] ?? 0);
                $positive += max(0, $score);
                $absolute += abs($score);
            }
            $item['categories'] = array_values(array_unique($categories));
            $item['result'] = $absolute > 0 ? round($positive / $absolute * 100, 1) : null;
            $item['positive_score'] = $positive;
            $item['negative_score'] = $absolute - $positive;
            unset($item['body']);
        }
        unset($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => 7];
    }

    public function view(int $userId, int $courseId, int $id): array
    {
        $course = (new Query())->from('{{%course}}')->where(['id' => $courseId])->one($this->main);
        if (!$course || !isset($this->connections->groups['course'][$course['alias']])) throw new NotFoundHttpException('Результат не найден.');
        $db = $this->connections->getConnection('course:' . $course['alias']);
        $row = (new Query())->from('{{%completed_test}}')->where(['id' => $id, 'user_id' => $userId])->one($db);
        if (!$row) throw new NotFoundHttpException('Результат не найден.');
        $body = $this->decode($row['body']);
        return ['id' => (int) $row['id'], 'course_id' => $courseId, 'course_name' => $course['name'], 'completed_at' => $row['completed_at'], 'questions' => $body];
    }

    private function decode(mixed $body): array
    {
        for ($layer = 0; $layer < 2 && is_string($body); $layer++) $body = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return $body;
    }
}
