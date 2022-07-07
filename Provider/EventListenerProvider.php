<?php

namespace Provider;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Silex\Api\EventListenerProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

require_once realpath(__DIR__ . '/..').'/Subscriber/KernelEventSubscriber.php';

class EventListenerProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    public function register(Container $app) {
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher) {
        $dispatcher->addSubscriber(new \Subscriber\KernelEventSubscriber());
    }
}
