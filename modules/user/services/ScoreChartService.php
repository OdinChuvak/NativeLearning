<?php
declare(strict_types=1);
namespace app\modules\user\services;

use app\components\db\ConnectionManager;
use yii\db\Connection;
use yii\web\HttpException;

final class ScoreChartService
{
    public function __construct(private readonly Connection $main, private readonly ConnectionManager $connections) {}

    public function chart(int $userId, string $period, ?int $courseId, ?\DateTimeImmutable $now = null): array
    {
        throw new HttpException(503, 'График знаний временно недоступен. Отображение новой статистики находится в разработке.');
    }
}
