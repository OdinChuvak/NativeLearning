<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\db\Query;

final class DashboardService
{
    public function __construct(private readonly Connection $main, private readonly ConnectionManager $connections) {}

    public function summary(int $userId, ?\DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
        $periodStart = $now->modify('-30 days')->format('Y-m-d H:i:s');
        $periodEnd = $now->format('Y-m-d H:i:s');
        $service = new CourseService($this->main, $this->connections);
        $first = $service->listing($userId, 'mine', 1);
        $courses = [];
        foreach (array_slice($first['items'], 0, 4) as $course) {
            $courses[] = $course + ['topics_total' => $course['topic_count'], 'topics_practiced' => null];
        }
        $total = $recentTotal = 0;
        foreach ((new Query())->from('{{%course}}')->all($this->main) as $course) {
            if (!isset($this->connections->groups['course'][$course['alias']])) continue;
            $db = $this->connections->getConnection('course:' . $course['alias']);
            if ($db->schema->getTableSchema('{{%completed_test}}') === null) continue;
            $query = (new Query())->from('{{%completed_test}}')->where(['user_id' => $userId]);
            $total += (int) (clone $query)->count('*', $db);
            $recentTotal += (int) $query->andWhere(['>=', 'completed_at', $periodStart])
                ->andWhere(['<=', 'completed_at', $periodEnd])->count('*', $db);
        }
        $history = (new TestHistoryService($this->main, $this->connections))->listing($userId, 1);
        return [
            'statistics_available' => false,
            'stats_30_days' => ['tests_completed' => $recentTotal, 'answer_result' => null,
                'active_courses' => $first['pagination']['total']],
            'stats' => ['tests_completed' => $total, 'answer_result' => null,
                'active_courses' => $first['pagination']['total']],
            'continue' => null,
            'courses' => $courses,
            'review_topics' => [],
            'study_recommendations' => [],
            'recent_tests' => array_slice($history['items'], 0, 5),
            'subscription' => (new SubscriptionService($this->main))->summary($userId),
        ];
    }
}
