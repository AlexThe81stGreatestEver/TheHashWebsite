<?php

namespace Provider;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

require_once realpath(__DIR__ . '/..').'/Subscriber/KernelEventSubscriber.php';

class EventListenerProvider implements ServiceProviderInterface
{
    public function register(Container $app) {
        $app['dispatcher']->addSubscriber(new \Subscriber\KernelEventSubscriber());
    }
}
