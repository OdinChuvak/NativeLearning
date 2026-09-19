<?php
declare(strict_types=1);
namespace app\modules\user\services;

use yii\db\Connection;
use yii\db\Query;
use yii\web\BadRequestHttpException;

final class TestService
{
    public function __construct(private readonly Connection $db) {}

    private function categories(array $course): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $course['alias'])) return [];
        $table = '{{%course_' . $course['alias'] . '_category}}';
        if ($this->db->schema->getTableSchema($table) === null) return [];
        return array_map(static fn(array $row): array => ['id' => (int) $row['id'], 'name' => $row['name']],
            (new Query())->select(['id', 'name'])->from($table)->where(['course_id' => $course['id']])->orderBy('id')->all($this->db));
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
        $tests = (new Query())->from('{{%test}}')->where(['user_id' => $userId])->orderBy(['id' => SORT_DESC])->all($this->db);
        $links = (new Query())->from('{{%test_category}}')->where(['test_id' => array_column($tests, 'id')])->orderBy(['course_id' => SORT_ASC, 'category_id' => SORT_ASC])->all($this->db);
        $courses = (new Query())->from('{{%course}}')->where(['id' => array_column($links, 'course_id')])->indexBy('id')->all($this->db);
        $categories = [];
        foreach ($courses as $id => $course) $categories[$id] = array_column($this->categories($course), 'name', 'id');
        $materials = [];
        foreach ($links as $link) {
            $id = (int) $link['course_id'];
            $group = &$materials[$link['test_id']][$id];
            if ($group === null) $group = ['course_id' => $id, 'course_name' => $courses[$id]['name'] ?? 'Курс недоступен', 'categories' => []];
            $group['categories'][] = $categories[$id][$link['category_id']] ?? 'Категория недоступна';
            unset($group);
        }
        return ['items' => array_map(static fn(array $test): array => ['id' => (int) $test['id'], 'name' => $test['name'], 'question_count' => (int) $test['question_count'], 'materials' => array_values($materials[$test['id']] ?? [])], $tests)];
    }

    public function create(int $userId, mixed $name, mixed $selection, mixed $questionCount): array
    {
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255) throw new BadRequestHttpException('Введите название теста, не более 255 символов.');
        if (!is_int($questionCount) || $questionCount < 1 || $questionCount > 2147483647) throw new BadRequestHttpException('Количество вопросов должно быть целым числом от 1 до 2147483647.');
        if (!is_array($selection) || !$selection || count($selection) > 10000) throw new BadRequestHttpException('Выберите хотя бы одну категорию.');
        return $this->db->transaction(function () use ($userId, $name, $selection, $questionCount): array {
            $allowed = [];
            foreach ($this->materials($userId)['items'] as $course) foreach ($course['categories'] as $category) $allowed[$course['id'] . ':' . $category['id']] = true;
            $rows = [];
            foreach ($selection as $item) {
                if (!is_array($item) || !is_int($item['course_id'] ?? null) || !is_int($item['category_id'] ?? null)) throw new BadRequestHttpException('Некорректный материал теста.');
                $key = $item['course_id'] . ':' . $item['category_id'];
                if (!isset($allowed[$key])) throw new BadRequestHttpException('Выбранная категория недоступна. Обновите список материалов.');
                $rows[$key] = [$item['course_id'], $item['category_id']];
            }
            $this->db->createCommand()->insert('{{%test}}', ['user_id' => $userId, 'name' => trim($name), 'question_count' => $questionCount])->execute();
            $id = (int) $this->db->getLastInsertID();
            $this->db->createCommand()->batchInsert('{{%test_category}}', ['test_id', 'course_id', 'category_id'], array_map(static fn(array $row): array => [$id, ...$row], array_values($rows)))->execute();
            return ['id' => $id, 'name' => trim($name), 'question_count' => $questionCount];
        });
    }
}
