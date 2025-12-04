<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Laminas\ServiceManager\ServiceManager;
use Illuminate\Events\Dispatcher;

$config = require __DIR__ . '/config.php';

$dependencies = $config['dependencies'];
$dependencies['services']['config'] = $config;

$container = new ServiceManager($dependencies);

$capsule = new Capsule();
$capsule->addConnection($config['database']);
$capsule->setEventDispatcher(new Dispatcher($capsule->getContainer()));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container->setService(Capsule::class, $capsule);

return $container;
