<?php

namespace Api;

use Pimple\Container;

require_once realpath(__DIR__ . '/..') . '/ControllerCollection.php';

/**
 * Interface for controller providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ControllerProviderInterface
{
    /**
     * Returns routes to connect to the given application.
     *
     * @param Container $app A Container instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Container $app);
}
