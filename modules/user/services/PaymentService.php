<?php
declare(strict_types=1);
namespace app\modules\user\services;

use yii\db\Connection;
use yii\db\Query;

final class PaymentService
{
    public function __construct(private readonly Connection $db) {}

    public function listing(int $userId, int $requestedPage): array
    {
        $base = (new Query())->from(['p' => '{{%payment}}'])->where(['p.user_id' => $userId]);
        $total = (int) (clone $base)->count('*', $this->db);
        $pages = max(1, (int) ceil($total / 10));
        $page = min(max(1, $requestedPage), $pages);
        $items = $base->select([
            'p.id', 'tariff_name' => 't.name', 't.course_limit', 't.duration_days',
            'p.payed_at', 'p.payment_amount', 'discount_type' => 'd.type', 'discount_value' => 'd.value',
        ])->innerJoin(['t' => '{{%tariff}}'], '[[t.id]] = [[p.tariff_id]]')
            ->leftJoin(['d' => '{{%discount}}'], '[[d.id]] = [[p.discount_id]]')
            ->orderBy(['p.payed_at' => SORT_DESC, 'p.id' => SORT_DESC])->limit(10)->offset(($page - 1) * 10)->all($this->db);
        foreach ($items as &$item) {
            foreach (['id', 'course_limit', 'duration_days'] as $key) $item[$key] = (int) $item[$key];
            $item['payment_amount'] = (string) $item['payment_amount'];
            $item['discount_type'] = $item['discount_type'] ?? 'percent';
            $item['discount_value'] = (string) ($item['discount_value'] ?? '0.00');
        }
        return ['items' => $items, 'pagination' => ['page' => $page, 'page_size' => 10, 'page_count' => $pages, 'total' => $total]];
    }
}
