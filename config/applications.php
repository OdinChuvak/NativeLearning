<?php

use Application\Admin;
use Application\ApplicationConfig;
use Application\UI;

return [
    'admin' => new ApplicationConfig(
        class: Admin::class,
        config: new Admin\Config(),
        definitions: [],
    ),
    'UI' => new ApplicationConfig(
        class: UI::class,
        config: new UI\Config(),
        definitions: [],
    ),
];
