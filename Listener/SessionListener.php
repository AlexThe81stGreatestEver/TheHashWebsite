<?php

namespace Listener;

use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;

class SessionListener extends BaseSessionListener {

    private $app;

    public function __construct(Container $app) {
        $this->app = $app;
    }

    protected function getSession() {
        if (!isset($this->app['session'])) {
            return;
        }

        return $this->app['session'];
    }
}
