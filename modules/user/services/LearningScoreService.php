<?php
declare(strict_types=1);
namespace app\modules\user\services;

use yii\db\Connection;
use yii\db\Query;
use yii\web\ConflictHttpException;

final class LearningScoreService
{
    public function __construct(private readonly Connection $db) {}

    /**
     * Save this test's totals for every linked learning item and every scoring system.
     * @param array<array{question_id: int, is_correct: bool}> $results
     */
    public function recordTest(int $userId, int $completedTestId, array $results): void
    {
        $this->db->transaction(function () use ($userId, $completedTestId, $results): void {
            $types = (new Query())->from('{{%score_type}}')->orderBy('id')->all($this->db);
            $settings = (new Query())->from('{{%question_score}}')
                ->where(['question_id' => array_column($results, 'question_id')])->all($this->db);
            $byQuestion = [];
            foreach ($settings as $setting) {
                $byQuestion[(int) $setting['question_id']][(int) $setting['learning_item_id']][(int) $setting['score_type_id']] = $setting;
            }
            $totals = [];
            foreach ($results as $result) {
                foreach ($byQuestion[$result['question_id']] ?? [] as $itemId => $overrides) {
                    foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        $field = $result['is_correct'] ? 'success_score' : 'failure_score';
                        $points = (int) ($overrides[$typeId][$field] ?? $type['default_' . $field]);
                        if ($points < 0) throw new ConflictHttpException('Баллы системы оценки должны быть неотрицательными.');
                        $totals[$itemId][$typeId] ??= ['success_score' => 0, 'failure_score' => 0];
                        $totals[$itemId][$typeId][$field] += $points;
                    }
                }
            }
            foreach ($totals as $itemId => $values) {
                $this->db->createCommand()->insert('{{%user_learning_item_score}}', [
                    'user_id' => $userId, 'learning_item_id' => $itemId, 'completed_test_id' => $completedTestId,
                ])->execute();
                $scoreId = (int) $this->db->getLastInsertID();
                foreach ($values as $typeId => $value) {
                    $this->db->createCommand()->insert('{{%user_score_value}}', [
                        'score_id' => $scoreId, 'score_type_id' => $typeId,
                        'total_score' => $value['success_score'] + $value['failure_score'],
                    ] + $value)->execute();
                }
            }
        });
    }
}
