<?php

use Symfony\Component\Routing\Route;

/**
 * A wrapper for a controller, mapped to a route.
 *
 * __call() forwards method-calls to Route, but returns instance of Controller
 * listing Route's methods below, so that IDEs know they are valid
 *
 * @method Controller assert(string $variable, string $regexp)
 * @method Controller value(string $variable, mixed $default)
 * @method Controller convert(string $variable, mixed $callback)
 * @method Controller method(string $method)
 * @method Controller requireHttp()
 * @method Controller requireHttps()
 * @method Controller before(mixed $callback)
 * @method Controller after(mixed $callback)
 * @method Controller when(string $condition)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Controller
{
    private $route;

    /**
     * Constructor.
     *
     * @param Route $route
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Gets the controller's route.
     *
     * @return Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Gets the controller's route name.
     *
     * @return string
     */
    public function getRouteName()
    {
        return $this->route->getOption("routeName");
    }

    /**
     * Sets the controller's route.
     *
     * @param string $routeName
     *
     * @return Controller $this The current Controller instance
     */
    public function bind($routeName)
    {
        if ($this->getRouteName() != null) {
            throw new \RuntimeException(sprintf('Calling %s on frozen %s instance.', __METHOD__, __CLASS__));
        }

        $this->route->setOption("routeName", $routeName);
        return $this;
    }

    public function __call($method, $arguments)
    {
        if (!method_exists($this->route, $method)) {
            throw new \BadMethodCallException(sprintf('Method "%s::%s" does not exist.', get_class($this->route), $method));
        }

        call_user_func_array([$this->route, $method], $arguments);
        return $this;
    }
}
