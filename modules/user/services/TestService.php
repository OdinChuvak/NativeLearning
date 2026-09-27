<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\db\Query;
use yii\web\BadRequestHttpException;

final class TestService
{
    public function __construct(private readonly Connection $db, private readonly ?ConnectionManager $connections = null) {}

    private function courseDb(array $course): ?Connection
    {
        $manager = $this->connections ?? \Yii::$app->get('dbManager');
        return isset($manager->groups['course'][$course['alias']])
            ? $manager->getConnection('course:' . $course['alias']) : null;
    }

    private function categories(array $course): array
    {
        $db = $this->courseDb($course);
        $table = '{{%category}}';
        if ($db === null || $db->schema->getTableSchema($table) === null) return [];
        return array_map(static fn(array $row): array => ['id' => (int) $row['id'], 'name' => $row['name']],
            (new Query())->select(['id', 'name'])->from($table)->orderBy('id')->all($db));
    }

    public function materials(int $userId): array
    {
        $active = (new Query())->select('s.course_id')->from(['s' => '{{%subscription_slot}}'])
            ->innerJoin(['p' => '{{%subscription}}'], '[[p.id]] = [[s.subscription_id]]')
            ->where(['p.user_id' => $userId, 's.deactivated_at' => null])
            ->andWhere('[[s.activated_at]] <= CURRENT_TIMESTAMP')
            ->andWhere('[[p.starts_at]] <= CURRENT_TIMESTAMP')->andWhere('[[p.expires_at]] > CURRENT_TIMESTAMP');
        $items = [];
        foreach ((new Query())->from('{{%course}}')->where(['in', 'id', $active])->orderBy('id')->all($this->db) as $course) {
            $items[] = ['id' => (int) $course['id'], 'name' => $course['name'], 'categories' => $this->categories($course)];
        }
        return ['items' => $items];
    }

    public function listing(int $userId): array
    {
        $items = [];
        foreach ((new Query())->from('{{%course}}')->orderBy('id')->all($this->db) as $course) {
            $db = $this->courseDb($course);
            if ($db === null || $db->schema->getTableSchema('{{%test}}') === null || $db->schema->getTableSchema('{{%test_category}}') === null) continue;
            $tests = (new Query())->from('{{%test}}')->where(['user_id' => $userId])->orderBy(['id' => SORT_DESC])->all($db);
            if (!$tests) continue;
            $links = (new Query())->from('{{%test_category}}')->where(['test_id' => array_column($tests, 'id')])->orderBy('category_id')->all($db);
            $categories = array_column($this->categories($course), 'name', 'id');
            $names = [];
            foreach ($links as $link) $names[$link['test_id']][] = $categories[$link['category_id']] ?? 'Категория недоступна';
            foreach ($tests as $test) {
                $items[] = ['id' => (int) $test['id'], 'course_id' => (int) $course['id'],
                    'name' => $test['name'], 'question_count' => (int) $test['question_count'],
                    'materials' => [['course_id' => (int) $course['id'], 'course_name' => $course['name'], 'categories' => $names[$test['id']] ?? []]]];
            }
        }
        return ['items' => $items];
    }

    public function create(int $userId, mixed $name, mixed $selection, mixed $questionCount): array
    {
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255) throw new BadRequestHttpException('Введите название теста, не более 255 символов.');
        if (!is_int($questionCount) || $questionCount < 1 || $questionCount > 2147483647) throw new BadRequestHttpException('Количество вопросов должно быть целым числом от 1 до 2147483647.');
        if (!is_array($selection) || !$selection || count($selection) > 10000) throw new BadRequestHttpException('Выберите хотя бы одну категорию.');
        $allowed = [];
        foreach ($this->materials($userId)['items'] as $course) foreach ($course['categories'] as $category) $allowed[$course['id'] . ':' . $category['id']] = true;
        $rows = [];
        foreach ($selection as $item) {
            if (!is_array($item) || !is_int($item['course_id'] ?? null) || !is_int($item['category_id'] ?? null)) throw new BadRequestHttpException('Некорректный материал теста.');
            $key = $item['course_id'] . ':' . $item['category_id'];
            if (!isset($allowed[$key])) throw new BadRequestHttpException('Выбранная категория недоступна. Обновите список материалов.');
            $rows[$key] = [$item['course_id'], $item['category_id']];
        }
        $courseIds = array_unique(array_column($rows, 0));
        if (count($courseIds) !== 1) throw new BadRequestHttpException('Для одного теста выберите категории одного курса.');
        $courseId = (int) reset($courseIds);
        $course = (new Query())->from('{{%course}}')->where(['id' => $courseId])->one($this->db);
        $db = $this->courseDb($course);
        if ($db === null) throw new BadRequestHttpException('База курса недоступна.');
        return $db->transaction(function () use ($db, $courseId, $userId, $name, $rows, $questionCount): array {
            $db->createCommand()->insert('{{%test}}', ['user_id' => $userId, 'name' => trim($name), 'question_count' => $questionCount])->execute();
            $id = (int) $db->getLastInsertID();
            $db->createCommand()->batchInsert('{{%test_category}}', ['test_id', 'category_id'], array_map(static fn(array $row): array => [$id, $row[1]], array_values($rows)))->execute();
            return ['id' => $id, 'course_id' => $courseId, 'name' => trim($name), 'question_count' => $questionCount];
        });
    }
}
