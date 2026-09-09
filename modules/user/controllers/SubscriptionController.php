<?php
declare(strict_types=1);

namespace app\modules\user\controllers;

use Yii;
use yii\db\Query;
use yii\web\BadRequestHttpException;

final class SubscriptionController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET'], 'options' => ['OPTIONS']];
    }

    public function actionIndex(): array
    {
        $requestedPage = Yii::$app->request->get('page', '1');
        if (!is_scalar($requestedPage) || filter_var($requestedPage, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new BadRequestHttpException('Номер страницы должен быть положительным целым числом.');
        }
        $userId = $this->module->get('user')->id;
        $base = (new Query())->from(['s' => '{{%user_subscription}}'])->where(['s.user_id' => $userId]);
        $total = (int) (clone $base)->count();
        $pageCount = max(1, (int) ceil($total / 10));
        $page = min((int) $requestedPage, $pageCount);
        $items = (clone $base)->select([
            's.id', 'tariff_name' => 't.name', 's.starts_at', 's.expires_at', 's.course_limit', 's.status',
        ])->innerJoin(['t' => '{{%tariff}}'], '[[t.id]] = [[s.tariff_id]]')
            ->orderBy(['s.starts_at' => SORT_DESC, 's.id' => SORT_DESC])
            ->limit(10)->offset(($page - 1) * 10)->all();
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['course_limit'] = (int) $item['course_limit'];
        }
        unset($item);

        $now = date('Y-m-d H:i:s');
        $coupons = (int) (clone $base)->andWhere(['s.status' => 'active'])
            ->andWhere(['<=', 's.starts_at', $now])->andWhere(['>', 's.expires_at', $now])
            ->sum('s.course_limit');
        $used = (int) (new Query())->from('{{%user_course}}')
            ->where(['user_id' => $userId, 'status' => 'active'])->count();

        return [
            'items' => $items,
            'coupons' => ['total' => $coupons, 'used' => $used, 'available' => max(0, $coupons - $used)],
            'pagination' => ['page' => $page, 'page_size' => 10, 'total' => $total, 'page_count' => $pageCount],
        ];
    }
}
