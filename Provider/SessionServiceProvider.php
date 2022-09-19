<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Session;

require_once 'Session/SessionListener.php';

class SessionServiceProvider implements ServiceProviderInterface {

    public function register(Container $app) {

        $app['session'] = function ($app) {
            return new Session($app['session.storage'], $app['session.attribute_bag'], $app['session.flash_bag']);
        };

        $app['session.storage'] = function ($app) {
            return $app['session.storage.native'];
        };

        $app['session.storage.handler'] = function ($app) {
            return new NativeFileSessionHandler($app['session.storage.save_path']);
        };

        $app['session.storage.native'] = function ($app) {
            return new NativeSessionStorage(
                $app['session.storage.options'],
                $app['session.storage.handler']
            );
        };

        $app['session.listener'] = function ($app) {
            return new \Provider\Session\SessionListener($app);
        };

        $app['session.storage.options'] = [];
        $app['session.storage.save_path'] = null;
        $app['session.attribute_bag'] = null;
        $app['session.flash_bag'] = null;

        $app['dispatcher']->addSubscriber($app['session.listener']);
    }
}
