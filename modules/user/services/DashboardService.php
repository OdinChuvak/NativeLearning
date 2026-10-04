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
        $periodEnd = $now->format('Y-m-d H:i:s');
        $periodStart = $now->modify('-30 days')->format('Y-m-d H:i:s');
        $recentPositive = $recentAbsolute = $recentTotal = 0;
        $service = new CourseService($this->main, $this->connections);
        $courses = [];
        $review = [];
        $candidates = [];
        $continue = null;
        $latest = '';
        $first = $service->listing($userId, 'mine', 1);
        $active = $first['items'];
        for ($page = 2; $page <= $first['pagination']['page_count']; $page++) {
            array_push($active, ...$service->listing($userId, 'mine', $page)['items']);
        }
        foreach ($active as $course) {
            $details = $service->details($userId, $course['id']);
            $topics = [];
            foreach ($details['categories'] as $category) {
                array_push($topics, ...$category['topics']);
            }
            $rated = count(array_filter($topics, static fn(array $topic): bool => $topic['has_rating']));
            $courses[] = $course + ['topics_total' => count($topics), 'topics_practiced' => $rated];
            foreach ($topics as $topic) {
                if ($topic['has_rating']) {
                    $candidates[] = ['course_id' => $course['id'], 'course_name' => $course['name'],
                        'topic_id' => $topic['id'], 'topic_name' => $topic['name'], 'rating' => $topic['rating']];
                }
                if ($topic['has_rating'] && $topic['rating'] < 70) {
                    $review[] = ['course_id' => $course['id'], 'course_name' => $course['name'],
                        'topic_id' => $topic['id'], 'topic_name' => $topic['name'], 'rating' => $topic['rating']];
                }
            }
            if (!isset($this->connections->groups['course'][$course['alias']])) continue;
            $db = $this->connections->getConnection('course:' . $course['alias']);
            if ($db->schema->getTableSchema('{{%user_topic_stat}}') === null
                || $db->schema->getTableSchema('{{%completed_test}}') === null) continue;
            $last = (new Query())->select(['s.topic_id', 'c.completed_at'])->from(['s' => '{{%user_topic_stat}}'])
                ->innerJoin(['c' => '{{%completed_test}}'], '[[c.id]] = [[s.completed_test_id]]')
                ->where(['s.user_id' => $userId, 'c.user_id' => $userId])
                ->andWhere(['not', ['c.completed_at' => null]])
                ->orderBy(['c.completed_at' => SORT_DESC, 's.id' => SORT_DESC])->one($db);
            if ($last && $last['completed_at'] > $latest) {
                foreach ($topics as $topic) {
                    if ($topic['id'] === (int) $last['topic_id']) {
                        $latest = $last['completed_at'];
                        $continue = ['course_id' => $course['id'], 'course_name' => $course['name'],
                            'topic_id' => $topic['id'], 'topic_name' => $topic['name']];
                        break;
                    }
                }
            }
        }
        usort($review, static fn(array $a, array $b): int => ($a['rating'] <=> $b['rating'])
            ?: ($a['course_id'] <=> $b['course_id']) ?: ($a['topic_id'] <=> $b['topic_id']));
        $positive = $absolute = $total = 0;
        foreach ((new Query())->from('{{%course}}')->all($this->main) as $course) {
            if (!isset($this->connections->groups['course'][$course['alias']])) continue;
            $db = $this->connections->getConnection('course:' . $course['alias']);
            if ($db->schema->getTableSchema('{{%completed_test}}') === null) continue;
            foreach ((new Query())->select(['body', 'completed_at'])->from('{{%completed_test}}')->where(['user_id' => $userId])->each(100, $db) as $row) {
                $total++;
                $inPeriod = $row['completed_at'] !== null && $row['completed_at'] >= $periodStart && $row['completed_at'] <= $periodEnd;
                if ($inPeriod) $recentTotal++;
                $body = $row['body'];
                for ($layer = 0; $layer < 2 && is_string($body); $layer++) {
                    $body = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                }
                foreach ($body as $question) {
                    $score = (int) ($question['score'] ?? 0);
                    $positive += max(0, $score);
                    $absolute += abs($score);
                    if ($inPeriod) {
                        $recentPositive += max(0, $score);
                        $recentAbsolute += abs($score);
                    }
                }
            }
        }
        $history = (new TestHistoryService($this->main, $this->connections))->listing($userId, 1);
        return [
            'stats_30_days' => ['tests_completed' => $recentTotal,
                'answer_result' => $recentAbsolute > 0 ? round(100 * $recentPositive / $recentAbsolute, 1) : null,
                'active_courses' => $first['pagination']['total']],
            'stats' => ['tests_completed' => $total, 'answer_result' => $absolute > 0 ? round(100 * $positive / $absolute, 1) : null,
                'active_courses' => $first['pagination']['total']],
            'continue' => $continue,
            'courses' => array_slice($courses, 0, 4),
            'review_topics' => array_slice($review, 0, 5),
            'study_recommendations' => self::recommendations($candidates),
            'recent_tests' => array_slice($history['items'], 0, 5),
            'subscription' => (new SubscriptionService($this->main))->summary($userId),
        ];
    }

    public static function recommendations(array $topics): array
    {
        usort($topics, static fn(array $a, array $b): int => ($a['rating'] <=> $b['rating'])
            ?: ($a['course_id'] <=> $b['course_id']) ?: ($a['topic_id'] <=> $b['topic_id']));
        $groups = [];
        foreach ($topics as $topic) $groups[$topic['course_id']][] = $topic;
        // Each eligible course receives one place before taking another topic from any course.
        $groups = array_slice(array_values($groups), 0, 3);
        $result = [];
        for ($round = 0; count($result) < 3 && $round < 3; $round++) {
            $next = [];
            foreach ($groups as $group) if (isset($group[$round])) $next[] = $group[$round];
            usort($next, static fn(array $a, array $b): int => ($a['rating'] <=> $b['rating']) ?: ($a['course_id'] <=> $b['course_id']));
            foreach ($next as $topic) {
                $result[] = $topic;
                if (count($result) === 3) break;
            }
        }
        return $result;
    }
}
