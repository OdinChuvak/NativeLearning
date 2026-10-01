<?php
declare(strict_types=1);

namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\db\Query;
use yii\web\NotFoundHttpException;

final class CourseService
{
    private const PAGE_SIZE = 10;

    public function __construct(
        private readonly Connection $db,
        private readonly ?ConnectionManager $connections = null,
    ) {}

    private function active(int $userId): Query
    {
        return (new Query())->select('s.course_id')->from(['s' => '{{%subscription_slot}}'])
            ->innerJoin(['p' => '{{%subscription}}'], '[[p.id]] = [[s.subscription_id]]')
            ->where(['p.user_id' => $userId, 's.deactivated_at' => null])
            ->andWhere('[[s.activated_at]] <= CURRENT_TIMESTAMP')
            ->andWhere('[[p.starts_at]] <= CURRENT_TIMESTAMP')
            ->andWhere('[[p.expires_at]] > CURRENT_TIMESTAMP');
    }

    public function listing(int $userId, string $scope, int $requestedPage): array
    {
        $query = (new Query())->from('{{%course}}');
        if ($scope === 'mine') {
            $query->where(['in', 'id', $this->active($userId)]);
        }
        $total = (int) (clone $query)->count('*', $this->db);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($requestedPage, $pages);
        $rows = $query->orderBy(['id' => SORT_ASC])->limit(self::PAGE_SIZE)->offset(($page - 1) * self::PAGE_SIZE)->all($this->db);
        $activeIds = array_map('intval', $this->active($userId)->column($this->db));
        return [
            'items' => array_map(fn(array $row): array => $this->present($row, $activeIds), $rows),
            'pagination' => ['page' => $page, 'page_size' => self::PAGE_SIZE, 'page_count' => $pages, 'total' => $total],
        ];
    }

    private function present(array $row, array $activeIds): array
    {
        $description = trim(strip_tags((string) $row['description']));
        $url = $row['preview_url'] ?? null;
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $url = null;
        }
        return [
            'id' => (int) $row['id'], 'name' => $row['name'], 'alias' => $row['alias'],
            'description' => $description,
            'short_description' => mb_strlen($description) > 180 ? mb_substr($description, 0, 177) . '…' : $description,
            'preview_url' => $url, 'is_active' => in_array((int) $row['id'], $activeIds, true),
        ];
    }

    private function course(int $id): array
    {
        $row = (new Query())->from('{{%course}}')->where(['id' => $id])->one($this->db);
        if (!$row) {
            throw new NotFoundHttpException('Курс не найден.');
        }
        return $row;
    }

    public function details(int $userId, int $id): array
    {
        $row = $this->course($id);
        $result = $this->present($row, array_map('intval', $this->active($userId)->column($this->db)));
        $result['categories'] = [];
        $connections = $this->connections ?? \Yii::$app->get('dbManager');
        // Courses not yet provisioned have no program. Resolve only registered members.
        if (!isset($connections->groups['course'][$row['alias']])) {
            return $result;
        }
        $courseDb = $connections->getConnection('course:' . $row['alias']);
        $categoryTable = '{{%category}}';
        $topicTable = '{{%topic}}';
        if ($courseDb->schema->getTableSchema($categoryTable) === null || $courseDb->schema->getTableSchema($topicTable) === null) {
            return $result;
        }
        $categories = (new Query())->from($categoryTable)->orderBy('id')->all($courseDb);
        $topics = (new Query())->select(['id', 'category_id', 'name', 'description'])->from($topicTable)
            ->where(['category_id' => array_column($categories, 'id')])->orderBy('id')->all($courseDb);
        $hasStats = $courseDb->schema->getTableSchema('{{%user_topic_stat}}') !== null;
        foreach ($topics as &$topic) {
            $scores = $hasStats ? (new Query())->select('score')->from('{{%user_topic_stat}}')
                ->where(['user_id' => $userId, 'topic_id' => $topic['id']])
                ->orderBy(['id' => SORT_DESC])->limit(10)->column($courseDb) : [];
            $positive = $negative = 0;
            foreach ($scores as $score) {
                if ($score > 0) $positive += $score;
                else $negative -= $score;
            }
            $topic['rating'] = $positive + $negative === 0 ? 50.0 : round(100 * $positive / ($positive + $negative), 1);
            $topic['has_rating'] = $scores !== [];
        }
        unset($topic);
        foreach ($categories as $category) {
            $result['categories'][] = ['id' => (int) $category['id'], 'name' => $category['name'], 'topics' => array_values(array_map(
                static fn(array $t): array => ['id' => (int) $t['id'], 'name' => $t['name'], 'description' => (string) $t['description'], 'rating' => $t['rating'], 'has_rating' => $t['has_rating']],
                array_filter($topics, static fn(array $t): bool => (int) $t['category_id'] === (int) $category['id']),
            ))];
        }
        return $result;
    }

}
