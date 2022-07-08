<?php

namespace Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;

class DoctrineServiceProvider implements ServiceProviderInterface {

    public function register(Container $app) {

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

        $app['dbs'] = function ($app) {
            $dbs = new Container();
            foreach ($app['dbs.options'] as $name => $options) {
                $config = $app['dbs.config'][$name];
                $manager = $app['dbs.event_manager'][$name];

                $dbs[$name] = function ($dbs) use ($options, $config, $manager) {
                    return DriverManager::getConnection($options, $config, $manager);
                };
            }

            return $dbs;
        };

        $app['dbs.config'] = function ($app) {
            $configs = new Container();
            $addLogger = isset($app['logger']) && null !== $app['logger'] && class_exists('Symfony\Bridge\Doctrine\Logger\DbalLogger');
            foreach ($app['dbs.options'] as $name => $options) {
                $configs[$name] = new Configuration();
                if ($addLogger) {
                    $configs[$name]->setSQLLogger(new DbalLogger($app['logger'], isset($app['stopwatch']) ? $app['stopwatch'] : null));
                }
            }

            return $configs;
        };

        $app['dbs.event_manager'] = function ($app) {
            $managers = new Container();
            foreach ($app['dbs.options'] as $name => $options) {
                $managers[$name] = new EventManager();
            }

            return $managers;
        };

        // shortcuts for the "first" DB
        $app['db'] = function ($app) {
            $dbs = $app['dbs'];

            return $dbs[$app['dbs.default']];
        };

        $app['db.config'] = function ($app) {
            $dbs = $app['dbs.config'];

            return $dbs[$app['dbs.default']];
        };

        $app['db.event_manager'] = function ($app) {
            $dbs = $app['dbs.event_manager'];

            return $dbs[$app['dbs.default']];
        };
    }
}
