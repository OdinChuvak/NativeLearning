<?php
declare(strict_types=1);
namespace app\modules\user\services;

use yii\db\Connection;
use yii\db\Query;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;

final class SubscriptionService
{
    public function __construct(private readonly Connection $db) {}

    private function now(): string
    {
        return (string) $this->db->createCommand('SELECT CURRENT_TIMESTAMP')->queryScalar();
    }

    // Payments and course activation serialize on the same owner row.
    private function lock(int $userId): void
    {
        if (!$this->db->createCommand('SELECT [[id]] FROM {{%user}} WHERE [[id]] = :id FOR UPDATE', [':id' => $userId])->queryScalar()) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }
    }

    private function current(int $userId, string $now): array|false
    {
        return (new Query())->from('{{%subscription}}')->where(['user_id' => $userId])
            ->andWhere(['<=', 'starts_at', $now])->andWhere(['>', 'expires_at', $now])->orderBy(['id' => SORT_DESC])->one($this->db);
    }

    private function activeSlots(int $userId, string $now): Query
    {
        return (new Query())->from(['slot' => '{{%subscription_slot}}'])
            ->innerJoin(['s' => '{{%subscription}}'], '[[s.id]] = [[slot.subscription_id]]')
            ->where(['s.user_id' => $userId, 'slot.deactivated_at' => null])
            ->andWhere(['<=', 's.starts_at', $now])->andWhere(['>', 's.expires_at', $now])
            ->andWhere(['<=', 'slot.activated_at', $now]);
    }

    public function summary(int $userId): array
    {
        $now = $this->now();
        $subscription = $this->current($userId, $now);
        if (!$subscription) return ['subscription' => null, 'slots' => ['total' => 0, 'used' => 0, 'available' => 0]];
        $tariff = (new Query())->from('{{%tariff}}')->where(['id' => $subscription['tariff_id']])->one($this->db);
        $used = (int) (new Query())->from('{{%subscription_slot}}')->where(['subscription_id' => $subscription['id'], 'deactivated_at' => null])
            ->andWhere(['<=', 'activated_at', $now])->count('*', $this->db);
        return ['subscription' => [
            'id' => (int) $subscription['id'], 'tariff_id' => (int) $subscription['tariff_id'],
            'tariff_name' => $tariff['name'], 'starts_at' => $subscription['starts_at'], 'expires_at' => $subscription['expires_at'],
        ], 'slots' => ['total' => (int) $subscription['course_limit'], 'used' => $used, 'available' => max(0, (int) $subscription['course_limit'] - $used)]];
    }

    public function tariffs(int $userId): array
    {
        $now = $this->now();
        $subscription = $this->current($userId, $now);
        $remaining = $this->remainingValue($subscription, $now);
        $items = (new Query())->from('{{%tariff}}')->orderBy(['price' => SORT_ASC, 'id' => SORT_ASC])->all($this->db);
        foreach ($items as &$item) {
            foreach (['id', 'course_limit', 'duration_days'] as $key) $item[$key] = (int) $item[$key];
            $item['price'] = (string) $item['price'];
            $item += $this->quote($item, $subscription, $remaining);
        }
        return ['items' => $items];
    }

    public static function proratedValue(string $price, string $startsAt, string $expiresAt, string $now): string
    {
        $start = (new \DateTimeImmutable($startsAt))->getTimestamp();
        $end = (new \DateTimeImmutable($expiresAt))->getTimestamp();
        $time = (new \DateTimeImmutable($now))->getTimestamp();
        if ($end <= $start || $time < $start || $time >= $end) return '0.00';
        $parts = explode('.', self::discountedAmount($price, null));
        $priceMinor = (int) $parts[0] * 100 + (int) $parts[1];
        $remainingDays = intdiv($end - $time + 86399, 86400);
        $durationDays = intdiv($end - $start + 86399, 86400);
        // Calculate in kopecks; split the product to avoid overflowing on large prices.
        $remainingMinor = intdiv($priceMinor, $durationDays) * $remainingDays
            + intdiv(($priceMinor % $durationDays) * $remainingDays + intdiv($durationDays, 2), $durationDays);
        return intdiv($remainingMinor, 100) . '.' . str_pad((string) ($remainingMinor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function remainingValue(array|false $subscription, string $now): string
    {
        if (!$subscription) return '0.00';
        $price = (new Query())->select('price')->from('{{%tariff}}')->where(['id' => $subscription['tariff_id']])->scalar($this->db);
        return self::proratedValue((string) $price, $subscription['starts_at'], $subscription['expires_at'], $now);
    }

    private function quote(array $tariff, array|false $subscription, string $remaining, ?array $discount = null): array
    {
        $switch = $subscription && (int) $subscription['tariff_id'] !== (int) $tariff['id'];
        $credit = $switch && (float) $remaining > 100 ? $remaining : '0.00';
        $price = self::discountedAmount((string) $tariff['price'], $discount['type'] ?? null, (string) ($discount['value'] ?? '0'));
        return [
            'is_switch' => $switch,
            'remaining_value' => $remaining,
            'credit_amount' => $credit,
            'payment_amount' => self::discountedAmount($price, 'amount', $credit),
        ];
    }

    public static function discountedAmount(string $price, ?string $type, string $value = '0'): string
    {
        $minor = static function (string $amount): int {
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) throw new BadRequestHttpException('Некорректная сумма.');
            $parts = explode('.', $amount);
            return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        };
        $priceMinor = $minor($price);
        $discount = $minor($value);
        if ($type === 'percent') {
            if ($discount > 10000) throw new BadRequestHttpException('Скидка не может превышать 100%.');
            $discount = intdiv($priceMinor * $discount + 5000, 10000);
        } elseif ($type !== 'amount' && $type !== null) {
            throw new BadRequestHttpException('Неизвестный тип скидки.');
        }
        $amount = max(0, $priceMinor - ($type === null ? 0 : $discount));
        return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public function pay(int $userId, int $tariffId, ?string $code, ?string $expectedAmount = null): array
    {
        return $this->db->transaction(function () use ($userId, $tariffId, $code, $expectedAmount): array {
            $this->lock($userId);
            $tariff = (new Query())->from('{{%tariff}}')->where(['id' => $tariffId])->one($this->db);
            if (!$tariff) throw new NotFoundHttpException('Тариф не найден.');
            $now = $this->now();
            $slots = $this->activeSlots($userId, $now)->select(['slot.id', 'slot.course_id'])->orderBy(['slot.activated_at' => SORT_ASC, 'slot.id' => SORT_ASC])->all($this->db);
            $discount = false;
            if ($code !== null && $code !== '') {
                $discount = $this->db->createCommand('SELECT * FROM {{%discount}} WHERE [[code]] = :code FOR UPDATE', [':code' => $code])->queryOne();
                if (!$discount || $discount['status'] !== 'new') throw new BadRequestHttpException('Промокод недействителен или уже использован.');
            }
            $current = $this->current($userId, $now);
            $quote = $this->quote($tariff, $current, $this->remainingValue($current, $now), $discount ?: null);
            $amount = $quote['payment_amount'];
            if ($expectedAmount !== null && $expectedAmount !== $amount) {
                throw new ConflictHttpException('Стоимость перехода изменилась. Откройте тарифы заново, чтобы увидеть актуальную сумму.');
            }
            $this->db->createCommand()->insert('{{%payment}}', [
                'user_id' => $userId, 'tariff_id' => $tariffId,
                'discount_id' => $discount ? $discount['id'] : null,
                'payment_amount' => $amount, 'payed_at' => $now,
            ])->execute();
            $paymentId = (int) $this->db->getLastInsertID();
            if ($discount) $this->db->createCommand()->update('{{%discount}}', ['status' => 'used'], ['id' => $discount['id']])->execute();
            $subscription = (new Query())->from('{{%subscription}}')->where(['user_id' => $userId])->one($this->db);
            $expires = (new \DateTimeImmutable($now))->modify('+' . (int) $tariff['duration_days'] . ' days')->format('Y-m-d H:i:s');
            $values = ['tariff_id' => $tariffId, 'course_limit' => $tariff['course_limit'], 'starts_at' => $now, 'expires_at' => $expires];
            if ($subscription) {
                // Keep the earliest activations; an expired plan does not revive its old courses.
                $retainedIds = array_column(array_slice($slots, 0, (int) $tariff['course_limit']), 'id');
                $deactivatedAt = $subscription['expires_at'] <= $now ? $subscription['expires_at'] : $now;
                $this->db->createCommand()->update('{{%subscription_slot}}', ['deactivated_at' => $deactivatedAt], [
                    'and', ['subscription_id' => $subscription['id'], 'deactivated_at' => null],
                    ['not in', 'id', $retainedIds],
                ])->execute();
                $this->db->createCommand()->update('{{%subscription}}', $values, ['id' => $subscription['id']])->execute();
            } else {
                $this->db->createCommand()->insert('{{%subscription}}', ['user_id' => $userId] + $values)->execute();
            }
            return ['payment_id' => $paymentId] + $this->summary($userId);
        });
    }

    public function activate(int $userId, int $courseId): array
    {
        return $this->db->transaction(function () use ($userId, $courseId): array {
            $this->lock($userId);
            if (!(new Query())->from('{{%course}}')->where(['id' => $courseId])->exists($this->db)) throw new NotFoundHttpException('Курс не найден.');
            $now = $this->now();
            $existing = $this->activeSlots($userId, $now)->andWhere(['slot.course_id' => $courseId])->select(['slot.*', 'expires_at' => 's.expires_at'])->one($this->db);
            if ($existing) return ['slot_id' => (int) $existing['id'], 'expires_at' => $existing['expires_at']];
            $subscription = $this->current($userId, $now);
            if (!$subscription) throw new ConflictHttpException('Для активации курса подключите тариф.');
            $used = (int) (new Query())->from('{{%subscription_slot}}')->where(['subscription_id' => $subscription['id'], 'deactivated_at' => null])->andWhere(['<=', 'activated_at', $now])->count('*', $this->db);
            if ($used >= (int) $subscription['course_limit']) throw new ConflictHttpException('Нет свободных слотов. Выберите другой тариф.');
            $this->db->createCommand()->insert('{{%subscription_slot}}', ['subscription_id' => $subscription['id'], 'course_id' => $courseId, 'activated_at' => $now])->execute();
            return ['slot_id' => (int) $this->db->getLastInsertID(), 'expires_at' => $subscription['expires_at']];
        });
    }
}
