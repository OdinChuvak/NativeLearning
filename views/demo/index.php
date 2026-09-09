<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
$this->title = 'Набор для пикника';
$money = static fn(int $kopecks): string => number_format($kopecks / 100, 2, ',', ' ') . ' ₽';
?>
<div class="demo-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="lead">Соберите набор и сравните стоимость в магазине и клубе.</p>
    <p>Это расчёт: товары не резервируются, данные не сохраняются. База данных и внешние сервисы не нужны.</p>
    <div class="d-flex gap-2 my-4">
        <?php foreach (['shop' => 'Магазин', 'club' => 'Клуб'] as $id => $label): ?>
            <?= Html::a($label, ['demo/index', 'application' => $id], [
                'class' => 'btn ' . ($applicationId === $id ? 'btn-primary' : 'btn-outline-primary'),
            ]) ?>
        <?php endforeach ?>
    </div>
    <p><?= $applicationId === 'club'
        ? 'Клуб: чай по 200 ₽ и скидка 10% на весь набор.'
        : 'Магазин: чай по 250 ₽, стандартные цены без скидки.' ?></p>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= Html::encode($error) ?></div>
    <?php endif ?>
    <?= Html::beginForm(['demo/index', 'application' => $applicationId], 'post') ?>
        <div class="mb-3">
            <?= Html::label('Ваше имя', 'customer', ['class' => 'form-label']) ?>
            <?= Html::textInput('customer', $customer, ['id' => 'customer', 'class' => 'form-control', 'required' => true, 'maxlength' => 100]) ?>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Товар</th><th>Цена</th><th>Доступно</th><th>Количество</th></tr></thead>
                <tbody>
                <?php foreach ($catalog as $code => $product): ?>
                    <tr>
                        <td><?= Html::encode($product['name']) ?></td>
                        <td><?= $money($product['price']) ?></td>
                        <td><?= $product['stock'] ?></td>
                        <td><?= Html::input('number', 'items[' . $code . ']', is_scalar($quantities[$code] ?? null) ? $quantities[$code] : 0, [
                            'class' => 'form-control', 'min' => 0, 'max' => 100, 'step' => 1,
                            'aria-label' => 'Количество: ' . $product['name'],
                        ]) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= Html::submitButton('Рассчитать набор', ['class' => 'btn btn-success']) ?>
    <?= Html::endForm() ?>
    <?php if ($quote !== null): ?>
        <section class="card my-4"><div class="card-body">
            <h2 class="h4">Расчёт для <?= Html::encode($quote['customer']) ?></h2>
            <ul>
                <?php foreach ($quote['lines'] as $line): ?>
                    <li><?= Html::encode($line['name']) ?> × <?= $line['quantity'] ?> — <?= $money($line['total']) ?></li>
                <?php endforeach ?>
            </ul>
            <p>Стоимость: <?= $money($quote['subtotal']) ?> · Скидка <?= $quote['discountPercent'] ?>%: <?= $money($quote['discount']) ?></p>
            <strong class="fs-4">Итого: <?= $money($quote['total']) ?></strong>
        </div></section>
    <?php endif ?>
    <p class="text-body-secondary mt-4">Попробуйте заказать 4 пледа при остатке 3 или установить все количества в 0 — приложение объяснит ошибку.</p>
</div>
