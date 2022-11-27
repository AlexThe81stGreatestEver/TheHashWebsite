<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
require_once realpath(__DIR__ . '/..') . '/Api/EventListenerProviderInterface.php';
require_once realpath(__DIR__ . '/..') . '/ExceptionHandler.php';
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ExceptionHandlerServiceProvider implements ServiceProviderInterface, \Api\EventListenerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        $app['exception_handler'] = function ($app) {
            return new \ExceptionHandler($app['debug']);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        if (isset($app['exception_handler'])) {
            $dispatcher->addSubscriber($app['exception_handler']);
        }
    }
}
