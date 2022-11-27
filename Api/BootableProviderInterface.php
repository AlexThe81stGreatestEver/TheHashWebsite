<?php

namespace Api;

use Pimple\Container;

/**
 * Interface for bootable service providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface BootableProviderInterface
{
    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @param Container $app
     */
    public function boot(Container $app);
}
