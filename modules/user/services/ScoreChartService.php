<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\db\Query;
use yii\web\NotFoundHttpException;

final class ScoreChartService
{
    public function __construct(private readonly Connection $main, private readonly ConnectionManager $connections) {}

    public function chart(int $userId, string $period, ?int $courseId, ?\DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'));
        $start = $now->modify($period === 'year' ? 'first day of January this year' : 'first day of this month')->setTime(0, 0);
        $end = $start->modify($period === 'year' ? '+1 year' : '+1 month');
        $count = $period === 'year' ? 12 : (int) $start->format('t');
        $buckets = array_fill(0, $count, ['positive' => 0, 'negative' => 0, 'total' => 0]);
        $listing = new CourseService($this->main, $this->connections);
        $page = $listing->listing($userId, 'mine', 1);
        $active = $page['items'];
        for ($i = 2; $i <= $page['pagination']['page_count']; $i++) array_push($active, ...$listing->listing($userId, 'mine', $i)['items']);
        if ($courseId !== null && !in_array($courseId, array_column($active, 'id'), true)) throw new NotFoundHttpException('Курс недоступен.');
        $courses = $courseId === null ? (new Query())->from('{{%course}}')->all($this->main)
            : array_filter($active, static fn(array $course): bool => $course['id'] === $courseId);
        foreach ($courses as $course) {
            if (!isset($this->connections->groups['course'][$course['alias']])) continue;
            $db = $this->connections->getConnection('course:' . $course['alias']);
            $schema = $db->schema->getTableSchema('{{%completed_test}}');
            if ($schema === null) continue;
            $stored = isset($schema->columns['positive_score'], $schema->columns['negative_score']);
            $query = (new Query())->select($stored ? ['completed_at', 'positive_score', 'negative_score'] : ['completed_at', 'body'])
                ->from('{{%completed_test}}')->where(['user_id' => $userId])
                ->andWhere(['>=', 'completed_at', $start->format('Y-m-d H:i:s')])
                ->andWhere(['<', 'completed_at', $end->format('Y-m-d H:i:s')])
                ->andWhere(['<=', 'completed_at', $now->format('Y-m-d H:i:s')]);
            foreach ($query->each(100, $db) as $row) {
                $positive = $negative = 0;
                if ($stored) {
                    $positive = (int) $row['positive_score'];
                    $negative = (int) $row['negative_score'];
                } else {
                    $body = $row['body'];
                    for ($layer = 0; $layer < 2 && is_string($body); $layer++) $body = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    foreach ($body as $question) { $score = (int) ($question['score'] ?? 0); $positive += max(0, $score); $negative += max(0, -$score); }
                }
                $date = new \DateTimeImmutable($row['completed_at'], new \DateTimeZone('UTC'));
                $index = (int) $date->format($period === 'year' ? 'n' : 'j') - 1;
                $buckets[$index]['positive'] += $positive;
                $buckets[$index]['negative'] += $negative;
                $buckets[$index]['total'] += $positive + $negative;
            }
        }
        return ['period' => $period, 'year' => (int) $now->format('Y'), 'month' => (int) $now->format('n'),
            'courses' => array_map(static fn(array $course): array => ['id' => $course['id'], 'name' => $course['name']], $active), 'buckets' => $buckets];
    }
}
