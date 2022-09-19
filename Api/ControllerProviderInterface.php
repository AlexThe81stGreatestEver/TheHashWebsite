<?php

namespace Api;

require_once realpath(__DIR__ . '/..') . '/Application.php';
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
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app);
}
