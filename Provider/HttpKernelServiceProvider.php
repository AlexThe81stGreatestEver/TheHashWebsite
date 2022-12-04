<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
require_once realpath(__DIR__ . '/..').'/Api/EventListenerProviderInterface.php';
require_once realpath(__DIR__ . '/..').'/AppArgumentValueResolver.php';
require_once realpath(__DIR__ . '/..').'/CallbackResolver.php';
require_once realpath(__DIR__ . '/..').'/EventListener/StringToResponseListener.php';
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\HttpHeaderSerializer;

class HttpKernelServiceProvider implements ServiceProviderInterface, \Api\EventListenerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        $app['resolver'] = function ($app) {
            return new ControllerResolver($app['logger']);
        };

        $app['argument_metadata_factory'] = function ($app) {
            return new ArgumentMetadataFactory();
        };
        $app['argument_value_resolvers'] = function ($app) {
            return array_merge([new \AppArgumentValueResolver($app)], ArgumentResolver::getDefaultArgumentValueResolvers());
        };

        $app['argument_resolver'] = function ($app) {
            return new ArgumentResolver($app['argument_metadata_factory'], $app['argument_value_resolvers']);
        };

        $app['kernel'] = function ($app) {
            return new HttpKernel($app['dispatcher'], $app['resolver'], $app['request_stack'], $app['argument_resolver']);
        };

        $app['request_stack'] = function () {
            return new RequestStack();
        };

        $app['dispatcher'] = function () {
            return new EventDispatcher();
        };

        $app['callback_resolver'] = function ($app) {
            return new \CallbackResolver($app);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber(new ResponseListener($app['charset']));
        $dispatcher->addSubscriber(new \EventListener\StringToResponseListener());

        if (class_exists(HttpHeaderSerializer::class)) {
            $dispatcher->addSubscriber(new AddLinkHeaderListener());
        }
    }
}
