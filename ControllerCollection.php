<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Builds Silex controllers.
 *
 * It acts as a staging area for routes. You are able to set the route name
 * until flush() is called, at which point all controllers are frozen and
 * converted to a RouteCollection.
 *
 * __call() forwards method-calls to Route, but returns instance of ControllerCollection
 * listing Route's methods below, so that IDEs know they are valid
 *
 * @method ControllerCollection assert(string $variable, string $regexp)
 * @method ControllerCollection value(string $variable, mixed $default)
 * @method ControllerCollection convert(string $variable, mixed $callback)
 * @method ControllerCollection method(string $method)
 * @method ControllerCollection requireHttp()
 * @method ControllerCollection requireHttps()
 * @method ControllerCollection before(mixed $callback)
 * @method ControllerCollection after(mixed $callback)
 * @method ControllerCollection when(string $condition)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ControllerCollection
{
    protected $controllers = [];
    protected $defaultRoute;
    protected $prefix;
    protected $controllersFactory;

    public function __construct(Route $defaultRoute, $controllersFactory = null)
    {
        $this->defaultRoute = $defaultRoute;
        $this->controllersFactory = $controllersFactory;
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                        $prefix      The route prefix
     * @param ControllerCollection|callable $controllers A ControllerCollection instance or a callable for defining routes
     *
     * @throws \LogicException
     */
    public function mount($prefix, ControllerCollection $controllers)
    {
        $controllers->prefix = $prefix;
        $this->controllers[] = $controllers;
    }

    /**
     * Maps a pattern to a callable.
     *
     * You can optionally specify HTTP methods that should be matched.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Controller
     */
    public function match($pattern, $to = null)
    {
        $route = clone $this->defaultRoute;
        $route->setPath($pattern);
        $this->controllers[] = $route;
        $route->setDefault('_controller', $to);

        return $route;
    }

    /**
     * Maps a GET request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Controller
     */
    public function get($pattern, $to = null)
    {
        return $this->match($pattern, $to)->setMethods('GET');
    }

    /**
     * Maps a POST request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Controller
     */
    public function post($pattern, $to = null)
    {
        return $this->match($pattern, $to)->setMethods('POST');
    }

    public function __call($method, $arguments)
    {
        if (!method_exists($this->defaultRoute, $method)) {
            throw new \BadMethodCallException(sprintf('Method "%s::%s" does not exist.', get_class($this->defaultRoute), $method));
        }

        call_user_func_array([$this->defaultRoute, $method], $arguments);

        foreach ($this->controllers as $controller) {
            call_user_func_array([$controller, $method], $arguments);
        }

        return $this;
    }

    /**
     * Persists and freezes staged controllers.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function flush()
    {
        $routes = new RouteCollection();
        return $this->doFlush('', $routes);
    }

    private function generateRouteName($route) {
        $methods = implode('_', $route->getMethods()).'_';

        $routeName = $methods.$route->getPath();
        $routeName = str_replace(['/', ':', '|', '-'], '_', $routeName);
        $routeName = preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        // Collapse consecutive underscores down into a single underscore.
        $routeName = preg_replace('/_+/', '_', $routeName);
        return $routeName;
    }

    private function doFlush($prefix, RouteCollection $routes)
    {
        if ('' !== $prefix) {
            $prefix = '/'.trim(trim($prefix), '/');
        }

        foreach ($this->controllers as $controller) {
            if ($controller instanceof Route) {
                $controller->setPath($prefix.$controller->getPath());
                if (!$name = $controller->getOption("routeName")) {
                    $name = $base = $this->generateRouteName($controller);
                    $i = 0;
                    while ($routes->get($name)) {
                        $name = $base.'_'.++$i;
                    }
                    $controller->setOption("routeName", $name);
                }
                $routes->add($name, $controller);
            } else {
                $controller->doFlush($prefix.$controller->prefix, $routes);
            }
        }

        $this->controllers = [];

        return $routes;
    }
}
