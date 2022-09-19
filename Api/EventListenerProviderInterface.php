<?php

namespace Api;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Pimple\Container;

/**
 * Interface for event listener providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface EventListenerProviderInterface
{
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher);
}
