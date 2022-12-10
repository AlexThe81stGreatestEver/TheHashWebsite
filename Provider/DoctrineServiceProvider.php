<?php

namespace Provider;

use Pimple\Container as PimpleContainer;
use Pimple\ServiceProviderInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Component\DependencyInjection\Container;

class DoctrineServiceProvider implements ServiceProviderInterface {

    public function register(PimpleContainer $app) {

        $app['dbs.options'] = array(
            'mysql_read' => array(
              'driver'   => DB_DRIVER,
              'dbname'   => DB_NAME,
              'host'     => DB_HOST,
              'port'     => DB_PORT,
              'user'     => DB_READ_ONLY_USER,
              'password' => DB_READ_ONLY_PASSWORD,
              'charset'  => "utf8"),
            'mysql_write' => array(
              'driver'    => DB_DRIVER,
              'dbname'    => DB_NAME,
              'host'      => DB_HOST,
              'port'      => DB_PORT,
              'user'      => DB_USER,
              'password'  => DB_PASSWORD,
              'charset'   => "utf8"));

        $app['dbs.default'] = "mysql_read";

        $app['dbs'] = function() use ($app) {
            $dbs = new Container();
            foreach ($app['dbs.options'] as $name => $options) {
                $config = $app['dbs.config']->getParameter($name);
                $manager = $app['dbs.event_manager']->getParameter($name);

                $dbs->setParameter($name, function () use ($options, $config, $manager) {
                    return DriverManager::getConnection($options, $config, $manager);
                });
            }

            return $dbs;
        };

        $app['dbs.config'] = function() use ($app) {
            $configs = new Container();
            $addLogger = isset($app['logger']) && null !== $app['logger'] && class_exists('Symfony\Bridge\Doctrine\Logger\DbalLogger');
            foreach ($app['dbs.options'] as $name => $options) {
		$config = new Configuration();
                if ($addLogger) {
                    $config->setSQLLogger(new DbalLogger($app['logger'], isset($app['stopwatch']) ? $app['stopwatch'] : null));
                }
                $configs->setParameter($name, $config);
            }

            return $configs;
        };

        $app['dbs.event_manager'] = function() use ($app) {
            $managers = new Container();
            foreach ($app['dbs.options'] as $name => $options) {
                $managers->setParameter($name, new EventManager());
            }

            return $managers;
        };

        // shortcuts for the "first" DB
        $app['db'] = function() use ($app) {
            $dbs = $app['dbs'];

            return $dbs->getParameter($app['dbs.default']);
        };

        $app['db.config'] = function() use ($app) {
            $dbs = $app['dbs.config'];

            return $dbs->getParameter($app['dbs.default']);
        };

        $app['db.event_manager'] = function() use ($app) {
            $dbs = $app['dbs.event_manager'];

            return $dbs->getParameter($app['dbs.default']);
        };
    }
}
