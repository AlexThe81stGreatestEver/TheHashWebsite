<?php

/* 
 * Lifted from:
namespace Rabus\Psr11ServiceProvider;
 */

use Pimple\Container;
use Pimple\Psr11\Container as Psr11Container;
use Pimple\ServiceProviderInterface;

class Psr11ServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['service_container'] = static function (Container $c) {
            return new Psr11Container($c);
        };
    }
}
