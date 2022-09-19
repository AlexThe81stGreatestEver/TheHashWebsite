<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
require_once realpath(__DIR__ . '/..') . '/ServiceControllerResolver.php';

class ServiceControllerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app->extend('resolver', function ($resolver, $app) {
            return new \ServiceControllerResolver($resolver, $app['callback_resolver']);
        });
    }
}
