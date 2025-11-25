<?php

declare(strict_types=1);

use Phpmig\Adapter;
use Pimple\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

$container = new Container();
$container['config'] = require __DIR__ . '/config.php';

$container['db'] = function ($c) {
    $capsule = new Capsule();
    $capsule->addConnection($c['config']['database']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
};

$container['phpmig.adapter'] = function($c) {
    return new Adapter\Illuminate\Database($c['db'], 'migrations');
};
$container['phpmig.migrations_path'] = __DIR__ . '/../migrations';

return $container;
