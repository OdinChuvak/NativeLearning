<?php
declare(strict_types=1);

namespace app\modules\user\services;

use yii\db\Connection;
use yii\db\Query;
use yii\web\NotFoundHttpException;

final class CourseService
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly Connection $db) {}

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
        // Table identifiers are never taken from unvalidated request parameters.
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $row['alias']) || $row['alias'] === 'sample') {
            return $result;
        }
        $categoryTable = '{{%course_' . $row['alias'] . '_category}}';
        $topicTable = '{{%course_' . $row['alias'] . '_topic}}';
        if ($this->db->schema->getTableSchema($categoryTable) === null || $this->db->schema->getTableSchema($topicTable) === null) {
            return $result;
        }
        $categories = (new Query())->from($categoryTable)->where(['course_id' => $id])->orderBy('id')->all($this->db);
        $topics = (new Query())->select(['id', 'category_id', 'name', 'description'])->from($topicTable)
            ->where(['category_id' => array_column($categories, 'id')])->orderBy('id')->all($this->db);
        foreach ($categories as $category) {
            $result['categories'][] = ['name' => $category['name'], 'topics' => array_values(array_map(
                static fn(array $t): array => ['id' => (int) $t['id'], 'name' => $t['name'], 'description' => (string) $t['description']],
                array_filter($topics, static fn(array $t): bool => (int) $t['category_id'] === (int) $category['id']),
            ))];
        }
        return $result;
    }

}
