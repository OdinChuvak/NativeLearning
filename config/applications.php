<?php

use Application\ApplicationConfig;
use Application\Shop;
use Application\Club;
use app\adapters\Catalog\ArrayStorage;
use Service\Catalog\Contract\Storage;
use Service\Order\Contract\Catalog;

// The internal framework starts before Yii creates its application and @app alias.
require_once __DIR__ . '/../adapters/Catalog/ArrayStorage.php';

$products = [
    'tea' => ['name' => 'Чай в термосе', 'price' => 25000, 'stock' => 8],
    'sandwich' => ['name' => 'Сэндвич', 'price' => 18000, 'stock' => 12],
    'blanket' => ['name' => 'Плед', 'price' => 90000, 'stock' => 3],
];
$clubProducts = $products;
$clubProducts['tea']['price'] = 20000;

return [
    'shop' => new ApplicationConfig(
        class: Shop::class,
        config: new Shop\Config(),
        definitions: [
            Storage::class => new ArrayStorage($products),
            Catalog::class => \Adapter\Order\Catalog::class,
        ],
    ),
    'club' => new ApplicationConfig(
        class: Club::class,
        config: new Club\Config(discountPercent: 10),
        definitions: [
            Storage::class => static fn() => new ArrayStorage($clubProducts),
            Catalog::class => \Adapter\Order\Catalog::class,
        ],
    ),
];
