<?php
declare(strict_types=1);
namespace app\modules\user\services;

use yii\web\BadRequestHttpException;

final class TopicQuestionSelector
{
    public static function ratingPoints(array $scores): int
    {
        $positive = $negative = 0;
        foreach (array_slice($scores, 0, 10) as $score) {
            if ($score > 0) $positive += $score;
            else $negative -= $score;
        }
        $rating = $positive + $negative === 0 ? 50 : 100 * $positive / ($positive + $negative);
        return $rating >= 40 && $rating <= 60 ? 3 : ($rating >= 20 && $rating <= 80 ? 2 : 1);
    }

    /** Topics carry id, recency (0..3), scores (newest first), questions (id => frequency). */
    public function select(array $topics, int $count): array
    {
        if ($count < 1 || $count > 30) throw new BadRequestHttpException('Количество вопросов должно быть от 1 до 30.');
        shuffle($topics);
        foreach ($topics as &$topic) $topic['priority'] = $topic['recency'] + self::ratingPoints($topic['scores']);
        unset($topic);
        usort($topics, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
        $first = (int) ceil($count / 2);
        $third = (int) floor($count / 6);
        $quotas = [$first, $count - $first - $third, $third];
        $slots = [];
        foreach ($quotas as $rank => $quota) {
            if ($quota === 0) continue;
            if (!isset($topics[$rank])) throw new BadRequestHttpException('Недостаточно тем для формирования теста.');
            $topic = $topics[$rank];
            $ids = array_keys($topic['questions']);
            shuffle($ids);
            usort($ids, static fn(int $a, int $b): int => $topic['questions'][$a] <=> $topic['questions'][$b]);
            for ($i = 0; $i < $quota; $i++) $slots[] = ['topic_id' => $topic['id'], 'candidates' => $ids];
        }
        // Matching handles questions shared by topics without duplicating or losing a valid allocation.
        $owners = [];
        $assign = function (int $slot, array &$seen) use (&$assign, &$owners, $slots): bool {
            foreach ($slots[$slot]['candidates'] as $id) {
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                if (!isset($owners[$id]) || $assign($owners[$id], $seen)) {
                    $owners[$id] = $slot;
                    return true;
                }
            }
            return false;
        };
        foreach (array_keys($slots) as $slot) {
            $seen = [];
            if (!$assign($slot, $seen)) throw new BadRequestHttpException('В выбранных темах недостаточно уникальных вопросов. Тест не создан.');
        }
        $result = [];
        foreach ($owners as $question => $slot) $result[] = ['question_id' => $question, 'topic_id' => $slots[$slot]['topic_id']];
        shuffle($result);
        return $result;
    }
}
