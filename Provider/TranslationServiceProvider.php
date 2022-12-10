<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;

class TranslationServiceProvider implements ServiceProviderInterface {

    public function register(Container $app) {
        $app['translator'] = function ($app) {
            $translator = new Translator($app['locale'], $app['translator.message_selector'], null, $app['debug']);
            $translator->addLoader('array', new ArrayLoader());
	};

        $app['translator.message_selector'] = function () {
            return new MessageFormatter();
        };
    }
}
