<?php

declare(strict_types=1);

use yii\db\Migration;
use yii\db\Query;

require_once __DIR__ . '/support/MoyOgorodData.php';

final class m260915_000001_create_moy_ogorod_course extends Migration
{
    private const PREFIX = 'course_moy_ogorod_';
    private const TYPE_NAME = 'Мой огород: один правильный ответ';
    private const TYPE_OWNER = 'Seed owner: m260915_000001_create_moy_ogorod_course; single choice.';
    private const TABLES = ['category', 'topic', 'question', 'answer_option', 'question_topic'];
    private array $created = [];

    // MySQL DDL commits implicitly. Run DDL first, then seed all rows in one real transaction.
    public function up(): void
    {
        $data = MoyOgorodData::load();
        $this->assertNoTransaction();
        foreach (self::TABLES as $suffix) {
            if ($this->db->schema->getTableSchema($this->table($suffix), true) !== null) {
                throw new RuntimeException('Course table already exists: ' . $this->table($suffix));
            }
        }
        if ((new Query())->from('{{%course}}')->where(['alias' => 'moy_ogorod'])->exists($this->db)
            || (new Query())->from('{{%question_type}}')->where(['name' => self::TYPE_NAME])->exists($this->db)
            || (new Query())->from('{{%question_type}}')->where(['description' => self::TYPE_OWNER])->exists($this->db)) {
            throw new RuntimeException('Course or its dedicated question type already exists; no data was changed.');
        }
        $this->created = [];
        try {
            $this->createCourseTables();
            $this->db->transaction(function () use ($data): void {
                $this->insert('{{%course}}', array_intersect_key($data['course'], array_flip(['name', 'alias', 'description'])));
                $courseId = (int) $this->db->getLastInsertID();
                $this->insert('{{%question_type}}', ['name' => self::TYPE_NAME, 'description' => self::TYPE_OWNER, 'score' => 1]);
                $typeId = (int) $this->db->getLastInsertID();
                $questions = $answers = $links = [];
                $answerId = 0;
                foreach ($data['categories'] as $category) {
                    $this->insert($this->table('category'), ['id' => $category['id'], 'course_id' => $courseId, 'name' => $category['name']]);
                    foreach ($category['topics'] as $topic) {
                        $this->insert($this->table('topic'), ['id' => $topic['id'], 'category_id' => $category['id'], 'name' => $topic['name'], 'description' => $topic['description']]);
                        foreach ($topic['questions'] as $question) {
                            $questions[] = [$question['id'], $question['question'], $typeId];
                            $links[] = [$question['id'], $topic['id']];
                            foreach ($question['answers'] as $answer) {
                                $answers[] = [++$answerId, $question['id'], $answer['answer'], (int) $answer['is_correct']];
                            }
                        }
                    }
                }
                $this->insertBatches('question', ['id', 'question', 'type'], $questions);
                $this->insertBatches('answer_option', ['id', 'question_id', 'answer', 'is_correct'], $answers);
                $this->insertBatches('question_topic', ['question_id', 'topic_id'], $links);
            });
        } catch (Throwable $error) {
            // Only tables successfully created by this attempt are eligible for cleanup.
            foreach (array_reverse($this->created) as $suffix) {
                $this->dropTable($this->table($suffix));
            }
            throw $error;
        }
    }

    public function down(): void
    {
        $this->assertNoTransaction();
        $courseId = (new Query())->select('id')->from('{{%course}}')->where(['alias' => 'moy_ogorod'])->scalar($this->db);
        $types = (new Query())->select('id')->from('{{%question_type}}')->where(['name' => self::TYPE_NAME, 'description' => self::TYPE_OWNER])->column($this->db);
        if ($courseId === false || count($types) !== 1) {
            throw new RuntimeException('Seed ownership cannot be verified; rollback stopped before dropping tables.');
        }
        $typeId = $types[0];
        $ownedNames = array_map(fn(string $suffix): string => $this->db->tablePrefix . self::PREFIX . $suffix, self::TABLES);
        // Check every external FK before MySQL starts irreversible DDL (including future tables).
        foreach ($this->db->schema->getTableSchemas() as $schema) {
            if (in_array($schema->name, $ownedNames, true)) {
                continue;
            }
            foreach ($schema->foreignKeys as $key) {
                $target = $key[0];
                $id = match ($target) {
                    $this->db->tablePrefix . 'course' => $courseId,
                    $this->db->tablePrefix . 'question_type' => $typeId,
                    default => null,
                };
                if (in_array($target, $ownedNames, true)) {
                    throw new RuntimeException('External table references course tables: ' . $schema->name);
                }
                if ($id !== null) {
                    unset($key[0]);
                    foreach ($key as $column => $referencedColumn) {
                        if ($referencedColumn === 'id' && (new Query())->from($schema->fullName)->where([$column => $id])->exists($this->db)) {
                            throw new RuntimeException('Course or question type is in use by ' . $schema->name . '; rollback stopped.');
                        }
                    }
                }
            }
        }
        foreach (array_reverse(self::TABLES) as $suffix) {
            $this->dropTable($this->table($suffix));
        }
        $this->db->transaction(function () use ($courseId, $typeId): void {
            $this->delete('{{%course}}', ['id' => $courseId, 'alias' => 'moy_ogorod']);
            $this->delete('{{%question_type}}', ['id' => $typeId, 'description' => self::TYPE_OWNER]);
        });
    }

    private function createCourseTables(): void
    {
        $this->createOwnedTable('category', [
            'id' => $this->primaryKey(), 'course_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(), 'description' => $this->text()->null(),
        ]);
        $this->reference('category', 'course_id', '{{%course}}');
        $this->createOwnedTable('topic', [
            'id' => $this->primaryKey(), 'category_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(), 'description' => $this->text()->null(),
        ]);
        $this->reference('topic', 'category_id', $this->table('category'));
        $this->createOwnedTable('question', [
            'id' => $this->primaryKey(), 'question' => $this->text()->notNull(), 'type' => $this->integer()->notNull(),
        ]);
        $this->reference('question', 'type', '{{%question_type}}');
        $this->createOwnedTable('answer_option', [
            'id' => $this->primaryKey(), 'question_id' => $this->integer()->notNull(),
            'answer' => $this->text()->notNull(),
            'is_correct' => $this->boolean()->notNull()->defaultValue(false)->check('[[is_correct]] IN (0, 1)'),
        ]);
        $this->reference('answer_option', 'question_id', $this->table('question'));
        $this->createOwnedTable('question_topic', [
            'question_id' => $this->integer()->notNull(), 'topic_id' => $this->integer()->notNull(),
            'PRIMARY KEY ([[question_id]], [[topic_id]])',
        ]);
        $this->reference('question_topic', 'question_id', $this->table('question'));
        $this->reference('question_topic', 'topic_id', $this->table('topic'));
    }

    private function createOwnedTable(string $suffix, array $columns): void
    {
        $this->createTable($this->table($suffix), $columns, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->created[] = $suffix;
    }

    private function reference(string $suffix, string $column, string $target): void
    {
        $name = self::PREFIX . $suffix . '-' . $column;
        $this->createIndex('idx-' . $name, $this->table($suffix), $column);
        $this->addForeignKey('fk-' . $name, $this->table($suffix), $column, $target, 'id', 'RESTRICT', 'CASCADE');
    }

    private function table(string $suffix): string
    {
        return '{{%' . self::PREFIX . $suffix . '}}';
    }

    private function insertBatches(string $suffix, array $columns, array $rows): void
    {
        foreach (array_chunk($rows, 250) as $batch) {
            $this->batchInsert($this->table($suffix), $columns, $batch);
        }
    }

    private function assertNoTransaction(): void
    {
        if ($this->db->getTransaction() !== null) {
            throw new RuntimeException('Run this MySQL DDL migration outside an existing transaction.');
        }
    }
}
