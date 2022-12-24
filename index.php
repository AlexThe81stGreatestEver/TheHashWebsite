<?php

// web/index.php
require_once 'vendor/autoload.php';
require_once 'config/ProdConfig.php';
require_once 'HASH/Controller/HashController.php';
require_once 'HASH/Controller/TagController.php';
require_once 'HASH/Controller/HashEventController.php';
require_once 'HASH/Controller/HashPersonController.php';
require_once 'HASH/Controller/AdminController.php';
require_once 'HASH/Controller/SuperAdminController.php';
require_once 'HASH/Controller/ObscureStatisticsController.php';
require_once 'HASH/UserProvider.php';
require_once 'Subscriber/KernelEventSubscriber.php';
require_once 'ControllerCollection.php';

use Doctrine\DBAL\Schema\Table;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Monolog\ErrorHandler as MonologErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler;
use Monolog\Logger;
use Pimple\Container as PimpleContainer;
use Pimple\Psr11\Container as Psr11Container;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Monolog\Handler\FingersCrossed\NotFoundActivationStrategy;
use Symfony\Bridge\Monolog\Logger as BridgeLogger;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension as TwigHttpFoundationExtension ;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension as FormValidatorExtension;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symfony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;
use Symfony\Component\Security\Http\EntryPoint\RetryAuthenticationEntryPoint;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\AbstractAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\ChannelListener;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\FirewallMap;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;
use Symfony\Component\Security\Http\Logout\SessionLogoutHandler;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints as Assert;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\ContainerRuntimeLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class HttpKernelImpl implements HttpKernelInterface 
{
  private $container;

  public function __construct($container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   *
   * If you call this method directly instead of run(), you must call the
   * terminate() method yourself if you want the finish filters to be run.
   */
  public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true) {
    $this->container['routes']->addCollection($this->container['controllers']->flush());
    return $this->container['kernel']->handle($request, $type, $catch);
  }
}

$fakeRoutes = [];

$app = new PimpleContainer();
$httpKernelImpl = new HttpKernelImpl($app);
$app['request.http_port'] = 80;
$app['request.https_port'] = 443;
$app['charset'] = 'UTF-8';
$app['locale'] = 'en';
$app['debug'] = defined('DEBUG') && DEBUG;

if($app['debug']) {
  Debug::enable();

  # Register the monolog logging service
  $app['monolog.logfile'] = __DIR__.'/development.log';
  $app['monolog.level'] = 'debug';
  $app['monolog.bubble'] = true;

  $app['logger'] = function () use ($app) {
    return $app['monolog'];
  };

  $app['monolog.logger.class'] = 'Symfony\Bridge\Monolog\Logger';

  $app['monolog'] = function ($app) {
    $log = new $app['monolog.logger.class']($app['monolog.name']);

    $handler = new Handler\GroupHandler($app['monolog.handlers']);
    $log->pushHandler($handler);
    $log->pushProcessor(new DebugProcessor());

    return $log;
  };

  $app['monolog.formatter'] = function () {
    return new LineFormatter();
  };

  $app['monolog.handler'] = $defaultHandler = function () use ($app) {
    $level = Logger::toMonologLevel($app['monolog.level']);

    $handler = new Handler\StreamHandler($app['monolog.logfile'], $level, $app['monolog.bubble'], $app['monolog.permission']);
    $handler->setFormatter($app['monolog.formatter']);

    return $handler;
  };

  $app['monolog.handlers'] = function () use ($app, $defaultHandler) {
    $handlers = [];
    $handlers[] = $app['monolog.handler'];
    return $handlers;
  };

  $app['monolog.name'] = 'app';
  $app['monolog.permission'] = null;
} else {
  $app['logger'] = null;
  // TODO: how do we log errors in production mode?
  ErrorHandler::register();
}

$app['resolver'] = function ($app) {
  return new ControllerResolver($app['logger']);
};

$app['argument_metadata_factory'] = function ($app) {
  return new ArgumentMetadataFactory();
};

$app['kernel'] = function ($app) {
  return new HttpKernel($app['dispatcher'], $app['resolver'], $app['request_stack'], null);
};

$app['request_stack'] = function () {
  return new RequestStack();
};

$app['dispatcher'] = function () {
  return new EventDispatcher();
};

$app['route_class'] = 'Symfony\Component\Routing\Route';

$app['request_context'] = function ($app) {
  $context = new RequestContext();

  $context->setHttpPort(isset($app['request.http_port']) ? $app['request.http_port'] : 80);
  $context->setHttpsPort(isset($app['request.https_port']) ? $app['request.https_port'] : 443);

  return $context;
};

$app['route_factory'] = $app->factory(function ($app) {
  return new $app['route_class']('/');
});

$app['routes_factory'] = $app->factory(function () {
  return new RouteCollection();
});

$app['routes'] = function ($app) {
  return $app['routes_factory'];
};

$app['url_generator'] = function ($app) {
  return new UrlGenerator($app['routes'], $app['request_context']);
};

$app['request_matcher'] = function ($app) {
  return new UrlMatcher($app['routes'], $app['request_context']);
};

$app['controllers'] = function ($app) {
  return $app['controllers_factory'];
};

$controllers_factory = function () use ($app, &$controllers_factory) {
  return new \ControllerCollection($app['route_factory'], $app['routes_factory'], $controllers_factory);
};
$app['controllers_factory'] = $app->factory($controllers_factory);

$app['routing.listener'] = function ($app) {
  return new RouterListener($app['request_matcher'], $app['request_stack'], $app['request_context'], $app['logger'], null, $app['debug']);
};

$app['dispatcher']->addSubscriber($app['routing.listener']);

$app['service_container'] = new Psr11Container($app);

$app->extend('resolver', function ($resolver, $app) {
  return new ContainerControllerResolver($app['service_container'], $app['logger']);
});

$app['csrf.token_manager'] = function ($app) {
  return new CsrfTokenManager($app['csrf.token_generator'], $app['csrf.token_storage']);
};

$app['csrf.token_storage'] = function ($app) {
  if (isset($app['session'])) {
    return new SessionTokenStorage($app['session'], $app['csrf.session_namespace']);
  }

  return new NativeSessionTokenStorage($app['csrf.session_namespace']);
};

$app['csrf.token_generator'] = function ($app) {
  return new UriSafeTokenGenerator();
};

$app['csrf.session_namespace'] = '_csrf';

$app['dispatcher']->addSubscriber(new \Subscriber\KernelEventSubscriber());

$app['form.extension.csrf'] = function ($app) {
  if (isset($app['translator'])) {
    $translationDomain = isset($app['validator.translation_domain']) ? $app['validator.translation_domain'] : null;
    return new CsrfExtension($app['csrf.token_manager'], $app['translator'], $translationDomain);
  }

  return new CsrfExtension($app['csrf.token_manager']);
};

$app['form.extensions'] = function ($app) {
  $extensions = [ new HttpFoundationExtension() ];

  if (isset($app['csrf.token_manager'])) {
    $extensions[] = $app['form.extension.csrf'];
  }

  if (isset($app['validator'])) {
    $extensions[] = new FormValidatorExtension($app['validator']);
  }

  return $extensions;
};

$app['form.factory'] = function ($app) {
  return new FormFactory($app['form.registry'], $app['form.resolved_type_factory']);
};

$app['form.registry'] = function ($app) {
  return new FormRegistry($app['form.extensions'], $app['form.resolved_type_factory']);
};

$app['form.resolved_type_factory'] = function ($app) {
  return new ResolvedFormTypeFactory();
};

$app['translator'] = function ($app) {
  $translator = new Translator($app['locale'], $app['translator.message_selector'], null, $app['debug']);
  $translator->addLoader('array', new ArrayLoader());
};

$app['translator.message_selector'] = function () {
  return new MessageFormatter();
};

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


$app['session'] = function ($app) {
  return new Session($app['session.storage'], $app['session.attribute_bag'], $app['session.flash_bag']);
};

$app['session.storage'] = function ($app) {
  return $app['session.storage.native'];
};

$app['session.storage.handler'] = function ($app) {
  return new NativeFileSessionHandler($app['session.storage.save_path']);
};

$app['session.storage.native'] = function ($app) {
  return new NativeSessionStorage($app['session.storage.options'], $app['session.storage.handler']);
};

$app['session.listener'] = function ($app) {
  return new SessionListener($app['service_container']);
};

$app['session.storage.options'] = [];
$app['session.storage.save_path'] = null;
$app['session.attribute_bag'] = null;
$app['session.flash_bag'] = null;

$app['dispatcher']->addSubscriber($app['session.listener']);

$app['HashController'] = function() use($app) { return new \HASH\Controller\HashController($app['service_container']); };
$app['HashPersonController'] = function() use($app) { return new \HASH\Controller\HashPersonController($app['service_container']); };
$app['HashEventController'] = function() use($app) { return new \HASH\Controller\HashEventController($app['service_container']); };
$app['AdminController'] = function() use($app) { return new \HASH\Controller\AdminController($app['service_container']); };
$app['SuperAdminController'] = function() use($app) { return new \HASH\Controller\SuperAdminController($app['service_container']); };
$app['TagController'] = function() use($app) { return new \HASH\Controller\TagController($app['service_container']); };
$app['ObscureStatisticsController'] = function() use($app) { return new \HASH\Controller\ObscureStatisticsController($app['service_container']); };

# Begin: Set the security firewalls --------------------------------------------

$app['UserProvider'] = function() use($app) { return new UserProvider($app['db']()); };

$app['security.firewalls'] = array(
  'login' => array(
    'pattern' => '^/logonscreen$',
  ),
  'supersecured' => array(
    'pattern' => '^/superadmin',
    'form' => array('login_path' => '/logonscreen/sa', 'check_path' => '/superadmin/login_check'),
    'logout' => array('logout_path' => '/superadmin/logoutaction'),
    'users' => function () use ($app) {return $app['UserProvider'];},
    'logout' => array('logout_path' => '/superadmin/logoutaction', 'invalidate_session' => true),
  ),
  'secured' => array(
    'pattern' => '^/admin',
    'form' => array('login_path' => '/logonscreen', 'check_path' => '/admin/login_check'),
    'logout' => array('logout_path' => '/logoutaction'),
    'users' => function () use ($app) {return $app['UserProvider'];},
    'logout' => array('logout_path' => '/admin/logoutaction', 'invalidate_session' => true),
  ),
  'unsecured' => array(
    'pattern' => '^.*$',
  )
);

$app['security.access_rules'] = array(
  array('^/superadmin',   'ROLE_SUPERADMIN',),
  array('^/admin',        'ROLE_ADMIN',),
);

$app['security.role_hierarchy'] = [];
$app['security.hide_user_not_found'] = true;

$app['security.authorization_checker'] = function ($app) {
  return new AuthorizationChecker($app['security.token_storage'], $app['security.authentication_manager'], $app['security.access_manager']);
};

$app['security.token_storage'] = function ($app) {
  return new TokenStorage();
};

$app['user'] = $app->factory(function ($app) {
  if (null === $token = $app['security.token_storage']->getToken()) {
    return;
  }

  if (!is_object($user = $token->getUser())) {
    return;
  }

  return $user;
});

$app['security.authentication_manager'] = function ($app) {
  $manager = new AuthenticationProviderManager($app['security.authentication_providers']);
  $manager->setEventDispatcher($app['dispatcher']);
  return $manager;
};

// by default, all users use the digest encoder
$app['security.encoder_factory'] = function ($app) {
  return new EncoderFactory([
    'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder(),
  ]);
};

$app['security.user_checker'] = function ($app) {
  return new UserChecker();
};

$app['security.access_manager'] = function ($app) {
  return new AccessDecisionManager($app['security.voters']);
};

$app['security.voters'] = function ($app) {
  return [
    new RoleHierarchyVoter(new RoleHierarchy($app['security.role_hierarchy'])),
  ];
};

$app['security.firewall'] = function ($app) {
  if (isset($app['validator'])) {
    $app['security.validator.user_password_validator'] = function ($app) {
      return new UserPasswordValidator($app['security.token_storage'], $app['security.encoder_factory']);
    };
  }

  return new Firewall($app['security.firewall_map'], $app['dispatcher']);
};

$app['security.channel_listener'] = function ($app) {
  return new ChannelListener(
    $app['security.access_map'],
    new RetryAuthenticationEntryPoint(
      isset($app['request.http_port']) ? $app['request.http_port'] : 80,
      isset($app['request.https_port']) ? $app['request.https_port'] : 443
    ),
    $app['logger']
  );
};

// generate the build-in authentication factories
foreach (['logout', 'form' ] as $type) {
  $entryPoint = null;
  if ('form' === $type) {
    $entryPoint = 'form';
  }

  $app['security.authentication_listener.factory.'.$type] = $app->protect(function ($name, $options) use ($type, $app, $entryPoint) {
    if ($entryPoint && !isset($app['security.entry_point.'.$name.'.'.$entryPoint])) {
      $app['security.entry_point.'.$name.'.'.$entryPoint] = $app['security.entry_point.'.$entryPoint.'._proto']($name, $options);
    }

    if (!isset($app['security.authentication_listener.'.$name.'.'.$type])) {
      $app['security.authentication_listener.'.$name.'.'.$type] = $app['security.authentication_listener.'.$type.'._proto']($name, $options);
    }

    $provider = 'dao';
    if (!isset($app['security.authentication_provider.'.$name.'.'.$provider])) {
      $app['security.authentication_provider.'.$name.'.'.$provider] = $app['security.authentication_provider.'.$provider.'._proto']($name, $options);
    }

    return [
      'security.authentication_provider.'.$name.'.'.$provider,
      'security.authentication_listener.'.$name.'.'.$type,
      $entryPoint ? 'security.entry_point.'.$name.'.'.$entryPoint : null,
      $type,
    ];
  });
}

$app['security.firewall_map'] = function ($app) {
  $positions = ['logout', 'form' ];
  $providers = [];
  $configs = [];
  foreach ($app['security.firewalls'] as $name => $firewall) {
    $entryPoint = null;
    $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
    $users = isset($firewall['users']) ? $firewall['users'] : [];
    $security = isset($firewall['security']) ? (bool) $firewall['security'] : true;
    $stateless = isset($firewall['stateless']) ? (bool) $firewall['stateless'] : false;
    $context = isset($firewall['context']) ? $firewall['context'] : $name;
    $hosts = isset($firewall['hosts']) ? $firewall['hosts'] : null;
    $methods = isset($firewall['methods']) ? $firewall['methods'] : null;
    unset($firewall['pattern'], $firewall['users'], $firewall['security'], $firewall['stateless'], $firewall['context'], $firewall['methods'], $firewall['hosts']);
    $protected = false === $security ? false : count($firewall);
    $listeners = ['security.channel_listener'];

    if (is_string($users)) {
      $users = function () use ($app, $users) {
        return $app[$users];
      };
    }

    if ($protected) {
      if (!isset($app['security.user_provider.'.$name])) {
        $app['security.user_provider.'.$name] = is_array($users) ? $app['security.user_provider.inmemory._proto']($users) : $users;
      }
      if (!isset($app['security.context_listener.'.$context])) {
        $app['security.context_listener.'.$context] = $app['security.context_listener._proto']($name, [$app['security.user_provider.'.$name]]);
      }

      if (false === $stateless) {
        $listeners[] = 'security.context_listener.'.$context;
      }

      $factories = [];
      foreach ($positions as $position) {
        $factories[$position] = [];
      }

      foreach ($firewall as $type => $options) {

        // normalize options
        if (!is_array($options)) {
          if (!$options) {
            continue;
          }

          $options = [];
        }

        if (!isset($app['security.authentication_listener.factory.'.$type])) {
          throw new \LogicException(sprintf('The "%s" authentication entry is not registered.', $type));
        }

        $options['stateless'] = $stateless;

        list($providerId, $listenerId, $entryPointId, $position) = $app['security.authentication_listener.factory.'.$type]($name, $options);

        if (null !== $entryPointId) {
          $entryPoint = $entryPointId;
        }

        $factories[$position][] = $listenerId;
        $providers[] = $providerId;
      }

      foreach ($positions as $position) {
        foreach ($factories[$position] as $listener) {
          $listeners[] = $listener;
        }
      }

      $listeners[] = 'security.access_listener';

      if (!isset($app['security.exception_listener.'.$name])) {
        if (null === $entryPoint) {
          $app[$entryPoint = 'security.entry_point.'.$name.'.form'] = $app['security.entry_point.form._proto']($name, []);
        }
        $accessDeniedHandler = null;
        if (isset($app['security.access_denied_handler.'.$name])) {
          $accessDeniedHandler = $app['security.access_denied_handler.'.$name];
        }
        $app['security.exception_listener.'.$name] = $app['security.exception_listener._proto']($entryPoint, $name, $accessDeniedHandler);
      }
    }

    $configs[$name] = [
      'pattern' => $pattern,
      'listeners' => $listeners,
      'protected' => $protected,
      'methods' => $methods,
      'hosts' => $hosts,
    ];
  }

  $app['security.authentication_providers'] = array_map(function ($provider) use ($app) {
    return $app[$provider];
  }, array_unique($providers));

  $map = new FirewallMap();
  foreach ($configs as $name => $config) {
    if (is_string($config['pattern'])) {
      $requestMatcher = new RequestMatcher($config['pattern'], $config['hosts'], $config['methods']);
    } else {
      $requestMatcher = $config['pattern'];
    }

    $map->add(
      $requestMatcher,
      array_map(function ($listenerId) use ($app, $name) {
        $listener = $app[$listenerId];
          return $listener;
        }, $config['listeners']),
        $config['protected'] ? $app['security.exception_listener.'.$name] : null
    );
  }

  return $map;
};

$app['security.access_listener'] = function ($app) {
  return new AccessListener(
    $app['security.token_storage'],
    $app['security.access_manager'],
    $app['security.access_map'],
    $app['security.authentication_manager'],
    $app['logger']
  );
};

$app['security.access_map'] = function ($app) {
  $map = new AccessMap();

  foreach ($app['security.access_rules'] as $rule) {
    if (is_string($rule[0])) {
      $rule[0] = new RequestMatcher($rule[0]);
    } elseif (is_array($rule[0])) {
      $rule[0] += [
        'path' => null,
        'host' => null,
        'methods' => null,
        'ips' => null,
        'attributes' => [],
        'schemes' => null,
      ];
      $rule[0] = new RequestMatcher($rule[0]['path'], $rule[0]['host'], $rule[0]['methods'], $rule[0]['ips'], $rule[0]['attributes'], $rule[0]['schemes']);
    }
    $map->add($rule[0], (array) $rule[1], isset($rule[2]) ? $rule[2] : null);
  }

  return $map;
};

$app['security.trust_resolver'] = function ($app) {
  return new AuthenticationTrustResolver('Symfony\Component\Security\Core\Authentication\Token\AnonymousToken');
};

$app['security.session_strategy'] = function ($app) {
  return new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
};

$app['security.http_utils'] = function ($app) {
  return new HttpUtils();
};

$app['security.last_error'] = $app->protect(function (Request $request) {
  if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
    return $request->attributes->get(Security::AUTHENTICATION_ERROR)->getMessage();
  }

  $session = $request->getSession();
  if ($session && $session->has(Security::AUTHENTICATION_ERROR)) {
    $message = $session->get(Security::AUTHENTICATION_ERROR)->getMessage();
    $session->remove(Security::AUTHENTICATION_ERROR);

    return $message;
  }
});

// prototypes (used by the Firewall Map)

$app['security.context_listener._proto'] = $app->protect(function ($providerKey, $userProviders) use ($app) {
  return function () use ($app, $userProviders, $providerKey) {
    return new ContextListener(
      $app['security.token_storage'],
      $userProviders,
      $providerKey,
      $app['logger'],
      $app['dispatcher']
    );
  };
});

$app['security.user_provider.inmemory._proto'] = $app->protect(function ($params) use ($app) {
  return function () use ($app, $params) {
    $users = [];
    foreach ($params as $name => $user) {
      $users[$name] = ['roles' => (array) $user[0], 'password' => $user[1]];
    }

    return new InMemoryUserProvider($users);
  };
});

$app['security.exception_listener._proto'] = $app->protect(function ($entryPoint, $name, $accessDeniedHandler = null) use ($app) {
  return function () use ($app, $entryPoint, $name, $accessDeniedHandler) {
    return new ExceptionListener(
      $app['security.token_storage'],
      $app['security.trust_resolver'],
      $app['security.http_utils'],
      $name,
      $app[$entryPoint],
      null, // errorPage
      $accessDeniedHandler,
      $app['logger']
    );
  };
});

$app['security.authentication.success_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
  return function () use ($name, $options, $app) {
    $handler = new DefaultAuthenticationSuccessHandler(
      $app['security.http_utils'],
      $options
    );
    $handler->setProviderKey($name);

    return $handler;
  };
});

$app['security.authentication.failure_handler._proto'] = $app->protect(function ($name, $options) use ($app, $httpKernelImpl) {
  return function () use ($name, $options, $app, $httpKernelImpl) {
    return new DefaultAuthenticationFailureHandler(
      $httpKernelImpl,
      $app['security.http_utils'],
      $options,
      $app['logger']
    );
  };
});

$app['security.authentication_listener.form._proto'] = $app->protect(function ($name, $options) use ($app, &$fakeRoutes) {
  return function () use ($app, $name, $options, &$fakeRoutes) {
    $fakeRoutes[] = [
      'match',
      $tmp = isset($options['check_path']) ? $options['check_path'] : '/login_check',
      str_replace('/', '_', ltrim($tmp, '/'))
    ];

    $class = isset($options['listener_class']) ? $options['listener_class'] : 'Symfony\\Component\\Security\\Http\\Firewall\\UsernamePasswordFormAuthenticationListener';

    if (!isset($app['security.authentication.success_handler.'.$name])) {
      $app['security.authentication.success_handler.'.$name] = $app['security.authentication.success_handler._proto']($name, $options);
    }

    if (!isset($app['security.authentication.failure_handler.'.$name])) {
      $app['security.authentication.failure_handler.'.$name] = $app['security.authentication.failure_handler._proto']($name, $options);
    }

    return new $class(
      $app['security.token_storage'],
      $app['security.authentication_manager'],
      isset($app['security.session_strategy.'.$name]) ? $app['security.session_strategy.'.$name] : $app['security.session_strategy'],
      $app['security.http_utils'],
      $name,
      $app['security.authentication.success_handler.'.$name],
      $app['security.authentication.failure_handler.'.$name],
      $options,
      $app['logger'],
      $app['dispatcher'],
      isset($options['with_csrf']) && $options['with_csrf'] && isset($app['csrf.token_manager']) ? $app['csrf.token_manager'] : null
    );
  };
});

$app['security.authentication_listener.http._proto'] = $app->protect(function ($providerKey, $options) use ($app) {
  return function () use ($app, $providerKey, $options) {
    return new BasicAuthenticationListener(
      $app['security.token_storage'],
      $app['security.authentication_manager'],
      $providerKey,
      $app['security.entry_point.'.$providerKey.'.http'],
      $app['logger']
    );
  };
});

$app['security.authentication.logout_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
  return function () use ($name, $options, $app) {
    return new DefaultLogoutSuccessHandler(
      $app['security.http_utils'],
      isset($options['target_url']) ? $options['target_url'] : '/'
    );
  };
});

$app['security.authentication_listener.logout._proto'] = $app->protect(function ($name, $options) use ($app, &$fakeRoutes) {
  return function () use ($app, $name, $options, &$fakeRoutes) {
    $fakeRoutes[] = [
      'get',
      $tmp = isset($options['logout_path']) ? $options['logout_path'] : '/logout',
      str_replace('/', '_', ltrim($tmp, '/'))
    ];

    if (!isset($app['security.authentication.logout_handler.'.$name])) {
      $app['security.authentication.logout_handler.'.$name] = $app['security.authentication.logout_handler._proto']($name, $options);
    }

    $listener = new LogoutListener(
      $app['security.token_storage'],
      $app['security.http_utils'],
      $app['security.authentication.logout_handler.'.$name],
      $options,
      isset($options['with_csrf']) && $options['with_csrf'] && isset($app['csrf.token_manager']) ? $app['csrf.token_manager'] : null
    );

    $invalidateSession = isset($options['invalidate_session']) ? $options['invalidate_session'] : true;
    if (true === $invalidateSession && false === $options['stateless']) {
      $listener->addHandler(new SessionLogoutHandler());
    }

    return $listener;
  };
});

$app['security.entry_point.form._proto'] = $app->protect(function ($name, array $options) use ($app, $httpKernelImpl) {
  return function () use ($app, $options, $httpKernelImpl) {
    $loginPath = isset($options['login_path']) ? $options['login_path'] : '/login';
    $useForward = isset($options['use_forward']) ? $options['use_forward'] : false;

    return new FormAuthenticationEntryPoint($httpKernelImpl, $app['security.http_utils'], $loginPath, $useForward);
  };
});

$app['security.entry_point.http._proto'] = $app->protect(function ($name, array $options) use ($app) {
  return function () use ($app, $name, $options) {
    return new BasicAuthenticationEntryPoint(isset($options['real_name']) ? $options['real_name'] : 'Secured');
  };
});

$app['security.authentication_provider.dao._proto'] = $app->protect(function ($name, $options) use ($app) {
  return function () use ($app, $name) {
    return new DaoAuthenticationProvider(
      $app['security.user_provider.'.$name],
      $app['security.user_checker'],
      $name,
      $app['security.encoder_factory'],
      $app['security.hide_user_not_found']
    );
  };
});

$app['dispatcher']->addSubscriber($app['security.firewall']);

#-------------------------------------------------------------------------------

#Set your global assertions and stuff ------------------------------------------
$app['controllers']
  ->setRequirement("hash_id", "\d+")
  ->setRequirement("hasher_id", "\d+")
  ->setRequirement("hasher_id2", "\d+")
  ->setRequirement("hare_id", "\d+")
  ->setRequirement("user_id", "\d+")
  ->setRequirement("hare_type", "\d+")
  ->setRequirement("hash_type", "\d+")
  ->setRequirement("event_tag_ky", "\d+")
  ->setRequirement("year_value", "\d+")
  ->setRequirement("day_count","\d+")
  ->setRequirement("month_count","\d+")
  ->setRequirement("min_hash_count","\d+")
  ->setRequirement("max_percentage","\d+")
  ->setRequirement("analversary_number","\d+")
  ->setRequirement("row_limit","\d+")
  ->setRequirement("kennel_ky","\d+")
  ->setRequirement("horizon","\d+")
  ->setRequirement("kennel_abbreviation","^[A-Za-z0-9]+$")
  ->setRequirement("name","^[a-z_]+$")
  ->setRequirement("ridiculous","^ridiculous\d+$");
#-------------------------------------------------------------------------------

$twigClassPath = __DIR__.'vendor/twig/twig/lib';
$twigTemplateSourceDirectory = __DIR__.'/Twig_Templates/source';
$twigTemplateCompiledDirectory = __DIR__.'/Twig_Templates/compiled';

$app['twig.path'] = $twigTemplateSourceDirectory;
$app['twig.class_path'] = $twigClassPath;
$app['twig.options'] = array(
  'cache' => $twigTemplateCompiledDirectory,
  'auto_reload' => true);

$app['twig.form.templates'] = ['form_div_layout.html.twig'];

$app['twig.date.format'] = 'F j, Y H:i';
$app['twig.date.interval_format'] = '%d days';
$app['twig.date.timezone'] = null;

$app['twig.number_format.decimals'] = 0;
$app['twig.number_format.decimal_point'] = '.';
$app['twig.number_format.thousands_separator'] = ',';

$app['twig'] = function ($app) {
  $twig = $app['twig.environment_factory']($app);

  $coreExtension = $twig->getExtension('Twig\Extension\CoreExtension');

  $coreExtension->setDateFormat($app['twig.date.format'], $app['twig.date.interval_format']);

  if (null !== $app['twig.date.timezone']) {
    $coreExtension->setTimezone($app['twig.date.timezone']);
  }

  $coreExtension->setNumberFormat($app['twig.number_format.decimals'], $app['twig.number_format.decimal_point'], $app['twig.number_format.thousands_separator']);

  if ($app['debug']) {
    $twig->addExtension(new DebugExtension());
  }

  if (class_exists('Symfony\Bridge\Twig\Extension\RoutingExtension')) {
    $app['twig.app_variable'] = function ($app) {
      $var = new AppVariable();
      if (isset($app['security.token_storage'])) {
        $var->setTokenStorage($app['security.token_storage']);
      }
      if (isset($app['request_stack'])) {
        $var->setRequestStack($app['request_stack']);
      }
      $var->setDebug($app['debug']);

      return $var;
    };

    $twig->addGlobal('global', $app['twig.app_variable']);

    if (isset($app['request_stack'])) {
      $twig->addExtension(new TwigHttpFoundationExtension(new UrlHelper($app['request_stack'], $app['request_context'])));
      $twig->addExtension(new RoutingExtension($app['url_generator']));
      $twig->addExtension(new WebLinkExtension($app['request_stack']));
    }

    if (isset($app['translator'])) {
      $twig->addExtension(new TranslationExtension($app['translator']));
    }

    if (isset($app['security.authorization_checker'])) {
      $twig->addExtension(new SecurityExtension($app['security.authorization_checker']));
    }

    if (isset($app['fragment.handler'])) {
      $app['fragment.renderer.hinclude']->setTemplating($twig);

      $twig->addExtension(new HttpKernelExtension($app['fragment.handler']));
    }

    if (isset($app['assets.packages'])) {
      $twig->addExtension(new AssetExtension($app['assets.packages']));
    }

    if (isset($app['form.factory'])) {
      $app['twig.form.engine'] = function ($app) use ($twig) {
        return new TwigRendererEngine($app['twig.form.templates'], $twig);
      };

      $app['twig.form.renderer'] = function ($app) {
        $csrfTokenManager = isset($app['csrf.token_manager']) ? $app['csrf.token_manager'] : null;

        return new FormRenderer($app['twig.form.engine'], $csrfTokenManager);
      };

      $twig->addExtension(new FormExtension());

      // add loader for Symfony built-in form templates
      $reflected = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
      $path = dirname($reflected->getFileName()).'/../Resources/views/Form';
      $app['twig.loader']->addLoader(new FilesystemLoader($path));

      $twig->addRuntimeLoader(new FactoryRuntimeLoader(array(
        FormRenderer::class => function() use ($app) {
          return new FormRenderer($app['twig.form.engine'], $app['csrf.token_manager']);
      })));
    }

    $twig->addRuntimeLoader($app['twig.runtime_loader']);
  }

  return $twig;
};

$app['twig.loader.filesystem'] = function ($app) {
  $loader = new FilesystemLoader();
  $loader->addPath($app['twig.path']);
  return $loader;
};

$app['twig.loader'] = function ($app) {
  return new ChainLoader([
    $app['twig.loader.filesystem'],
  ]);
};

$app['twig.environment_factory'] = $app->protect(function ($app) {
  return new Environment($app['twig.loader'], array_replace([
    'charset' => $app['charset'],
    'debug' => $app['debug'],
    'strict_variables' => $app['debug'],
  ], $app['twig.options']));
});

$app['twig.runtime.httpkernel'] = function ($app) {
  return new HttpKernelRuntime($app['fragment.handler']);
};

$app['twig.runtimes'] = function ($app) {
  return [
    HttpKernelRuntime::class => 'twig.runtime.httpkernel',
    FormRenderer::class => 'twig.form.renderer',
  ];
};

$app['twig.runtime_loader'] = function ($app) {
  return new ContainerRuntimeLoader($app['service_container']);
};

#Check users table in database-------------------------------------------------

$schema = $app['dbs']->getParameter('mysql_write')()->getSchemaManager();

if (!$schema->tablesExist('USERS')) {

  // Create Users Table
  $users = new Table('USERS');
  $users->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
  $users->setPrimaryKey(array('id'));
  $users->addColumn('username', 'string', array('length' => 32));
  $users->addUniqueIndex(array('username'));
  $users->addColumn('password', 'string', array('length' => 255));
  $users->addColumn('roles', 'string', array('length' => 255));
  $schema->createTable($users);

  // Array of new users to create
  // admin user will have admin and superadmin privs
  $users = array(new User('admin', null, array('ROLE_ADMIN', 'ROLE_SUPERADMIN'), true, true, true, true));

  foreach ($users as &$user) {

    // find the encoder for a UserInterface instance
    $encoder = $app['security.encoder_factory']->getEncoder($user);

    // compute the encoded password for the new password
    $encodedNewPassword = $encoder->encodePassword(DEFAULT_USER_PASSWORD, $user->getSalt());

    // insert the new user record
    $app['dbs']->getParameter('mysql_write')()->insert('USERS', array(
      'username' => $user->getUsername(),
      'password' => $encodedNewPassword,
      'roles' => implode(',',$user->getRoles())));
  }
}

# Register the URls
$controllers = $app['controllers'];

$controllers->get('/',                                                    'HashController:slashAction')->bind('homepage');
$controllers->get('/{kennel_abbreviation}/rss',                           'HashController:rssAction');
$controllers->get('/{kennel_abbreviation}/events/rss',                    'HashController:eventsRssAction');

#Admin section logon
$controllers->get('/logonscreen',                                         'HashController:logonScreenAction');
$controllers->get('/admin/logoutaction',                                  'AdminController:logoutAction');
$controllers->get('/admin/hello',                                         'AdminController:helloAction');

#Superadmin section logon
$controllers->get('/logonscreen/sa',                                        'SuperAdminController:logonScreenAction');
$controllers->get('/superadmin/logoutaction',                               'SuperAdminController:logoutAction');
$controllers->get('/superadmin/hello',                                      'SuperAdminController:helloAction');
$controllers->get('/superadmin/integrity',                                  'SuperAdminController:integrityChecks');
$controllers->get('/superadmin/{kennel_abbreviation}/editkennel/ajaxform',  'SuperAdminController:modifyKennelAjaxPreAction');
$controllers->post('/superadmin/{kennel_abbreviation}/editkennel/ajaxform', 'SuperAdminController:modifyKennelAjaxPostAction');
$controllers->get('/superadmin/{hare_type}/editharetype/ajaxform',          'SuperAdminController:modifyHareTypeAjaxPreAction');
$controllers->post('/superadmin/{hare_type}/editharetype/ajaxform',         'SuperAdminController:modifyHareTypeAjaxPostAction');
$controllers->get('/superadmin/{hash_type}/edithashtype/ajaxform',          'SuperAdminController:modifyHashTypeAjaxPreAction');
$controllers->post('/superadmin/{hash_type}/edithashtype/ajaxform',         'SuperAdminController:modifyHashTypeAjaxPostAction');
$controllers->get('/superadmin/{user_id}/edituser/ajaxform',                'SuperAdminController:modifyUserAjaxPreAction');
$controllers->post('/superadmin/{user_id}/edituser/ajaxform',               'SuperAdminController:modifyUserAjaxPostAction');
$controllers->get('/superadmin/{name}/editsiteconfig/ajaxform',             'SuperAdminController:modifySiteConfigAjaxPreAction');
$controllers->post('/superadmin/{name}/editsiteconfig/ajaxform',            'SuperAdminController:modifySiteConfigAjaxPostAction');
$controllers->get('/superadmin/{ridiculous}/editridiculous/ajaxform',       'SuperAdminController:modifyRidiculousAjaxPreAction');
$controllers->post('/superadmin/{ridiculous}/editridiculous/ajaxform',      'SuperAdminController:modifyRidiculousAjaxPostAction');
$controllers->post('/superadmin/deleteridiculous',                          'SuperAdminController:deleteRidiculous');
$controllers->post('/superadmin/deleteuser',                                'SuperAdminController:deleteUser');
$controllers->post('/superadmin/deletekennel',                              'SuperAdminController:deleteKennel');
$controllers->post('/superadmin/deletehashtype',                            'SuperAdminController:deleteHashType');
$controllers->post('/superadmin/deleteharetype',                            'SuperAdminController:deleteHareType');
$controllers->get('/superadmin/newridiculous/ajaxform',                     'SuperAdminController:newRidiculousAjaxPreAction');
$controllers->post('/superadmin/newridiculous/ajaxform',                    'SuperAdminController:newRidiculousAjaxPostAction');
$controllers->get('/superadmin/newuser/ajaxform',                           'SuperAdminController:newUserAjaxPreAction');
$controllers->post('/superadmin/newuser/ajaxform',                          'SuperAdminController:newUserAjaxPostAction');
$controllers->get('/superadmin/newkennel/ajaxform',                         'SuperAdminController:newKennelAjaxPreAction');
$controllers->post('/superadmin/newkennel/ajaxform',                        'SuperAdminController:newKennelAjaxPostAction');
$controllers->get('/superadmin/newhashtype/ajaxform',                       'SuperAdminController:newHashTypeAjaxPreAction');
$controllers->post('/superadmin/newhashtype/ajaxform',                      'SuperAdminController:newHashTypeAjaxPostAction');
$controllers->get('/superadmin/newharetype/ajaxform',                       'SuperAdminController:newHareTypeAjaxPreAction');
$controllers->post('/superadmin/newharetype/ajaxform',                      'SuperAdminController:newHareTypeAjaxPostAction');
$controllers->get('/superadmin/export',                                     'SuperAdminController:exportDatabaseAction');

$controllers->get('/admin/{kennel_abbreviation}/newhash/ajaxform', 'HashEventController:adminCreateHashAjaxPreAction');
$controllers->post('/admin/{kennel_abbreviation}/newhash/ajaxform', 'HashEventController:adminCreateHashAjaxPostAction');
$controllers->get('/admin/{hash_id}/duplicateHash',                 'HashEventController:adminDuplicateHash');

# Hash event modification (ajaxified)
$controllers->get('/admin/edithash/ajaxform/{hash_id}', 'HashEventController:adminModifyHashAjaxPreAction');
$controllers->post('/admin/edithash/ajaxform/{hash_id}', 'HashEventController:adminModifyHashAjaxPostAction');

# Hash person modification
$controllers->get('/admin/modifyhasher/form/{hasher_id}',                 'HashPersonController:modifyHashPersonAction');
$controllers->post('/admin/modifyhasher/form/{hasher_id}',                'HashPersonController:modifyHashPersonAction');

# Hash person deletion
$controllers->get('/admin/deleteHasher/{hasher_id}',                      'HashPersonController:deleteHashPersonPreAction');
$controllers->post('/admin/deleteHasherPost',                      'HashPersonController:deleteHashPersonAjaxAction');

# Hash person creation
$controllers->get('/admin/newhasher/form',                                'HashPersonController:createHashPersonAction');
$controllers->post('/admin/newhasher/form',                               'HashPersonController:createHashPersonAction');

# Change admin password
$controllers->get('/admin/newPassword/form',                                'AdminController:newPasswordAction');
$controllers->post('/admin/newPassword/form',                               'AdminController:newPasswordAction');

# View audit records
$controllers->get('/admin/viewAuditRecords',                                  'AdminController:viewAuditRecordsPreActionJson');
$controllers->post('/admin/viewAuditRecords',                                 'AdminController:viewAuditRecordsJson');

# Modify the participation for an event
$controllers->get('/admin/hash/manageparticipation2/{hash_id}',            'HashEventController:hashParticipationJsonPreAction');
$controllers->post('/admin/hash/manageparticipation2/{hash_id}',           'HashEventController:hashParticipationJsonPostAction');

# Page to manage the event tags
$controllers->get('/admin/tags/manageeventtags',                            'TagController:manageEventTagsPreAction');
$controllers->get('/admin/tags/geteventtagswithcounts',                     'TagController:getEventTagsWithCountsJsonAction');
$controllers->get('/admin/tags/getalleventtags',                            'TagController:getAllEventTagsJsonAction');
$controllers->get('/admin/tags/getmatchingeventtags',                       'TagController:getMatchingEventTagsJsonAction');
#$controllers->post('/admin/tags/manageeventtags',                           'TagController:manageEventTagsJsonPostAction');
$controllers->post('/admin/tags/addneweventtag',                            'TagController:addNewEventTag');

# Add or remove tags to events
$controllers->post('/admin/tags/addtagtoevent',                             'TagController:addTagToEventJsonAction');
$controllers->post('/admin/tags/removetagfromevent',                        'TagController:removeTagFromEventJsonAction');
$controllers->get('/admin/tags/eventscreen/{hash_id}',                      'TagController:showEventForTaggingPreAction');

# Functions to add and delete hounds and hares to the hashes
$controllers->post('/admin/hash/addHasherToHash',                         'HashEventController:addHashParticipant');
$controllers->post('/admin/hash/addHareToHash',                           'HashEventController:addHashOrganizer');
$controllers->post('/admin/hash/deleteHasherFromHash',                    'HashEventController:deleteHashParticipant');
$controllers->post('/admin/hash/deleteHareFromHash',                      'HashEventController:deleteHashOrganizer');

$controllers->post('/admin/hash/getHaresForEvent',                        'HashEventController:getHaresForEvent');
$controllers->post('/admin/hash/getHashersForEvent',                      'HashEventController:getHashersForEvent');

$controllers->get('/admin/listOrphanedHashers',                             'AdminController:listOrphanedHashersAction');

$controllers->get('/admin/legacy',                                          'AdminController:legacy');
$controllers->get('/admin/{kennel_abbreviation}/legacy',                    'AdminController:legacy');
$controllers->post('/admin/{kennel_abbreviation}/legacyUpdate',             'AdminController:legacyUpdate');
$controllers->get('/admin/roster',                                          'AdminController:roster');
$controllers->get('/admin/{kennel_abbreviation}/roster',                    'AdminController:roster');
$controllers->get('/admin/awards/{type}',                                   'AdminController:awards');
$controllers->get('/admin/{kennel_abbreviation}/awards/{type}',             'AdminController:awards');
$controllers->get('/admin/{kennel_abbreviation}/awards/{type}/{horizon}',   'AdminController:awards');
$controllers->post('/admin/updateHasherAward',                              'AdminController:updateHasherAwardAjaxAction');

$controllers->get('/admin/listhashes2',                                    'AdminController:listHashesPreActionJson');
$controllers->get('/admin/{kennel_abbreviation}/listhashes2',              'AdminController:listHashesPreActionJson');
$controllers->post('/admin/{kennel_abbreviation}/listhashes2',             'AdminController:getHashListJson');

$controllers->get('/admin/listhashers2',                                    'AdminController:listHashersPreActionJson');
$controllers->post('/admin/listhashers2',                                   'AdminController:getHashersListJson');

$controllers->post('/admin/listhashers3',                                   'AdminController:getHashersParticipationListJson');

$controllers->get('/admin/hasherDetailsKennelSelection/{hasher_id}',        'AdminController:hasherDetailsKennelSelection');
$controllers->post('/admin/deleteHash',                                     'AdminController:deleteHash');

#The per event budget screen
$controllers->get('/admin/eventBudget/{hash_id}','AdminController:eventBudgetPreAction');

$controllers->get('/{kennel_abbreviation}/mia',                                       'HashController:miaPreActionJson');
$controllers->post('/{kennel_abbreviation}/mia',                                       'HashController:miaPostActionJson');

$controllers->post('/{kennel_abbreviation}/listhashers2',                                       'HashController:getHasherListJson');

$controllers->get('/{kennel_abbreviation}/listvirginharings/{hare_type}',                      'HashController:listVirginHaringsPreActionJson');
$controllers->post('/{kennel_abbreviation}/listvirginharings/{hare_type}',                     'HashController:getVirginHaringsListJson');

$controllers->get('/{kennel_abbreviation}/attendancePercentages',                                'HashController:attendancePercentagesPreActionJson');
$controllers->post('/{kennel_abbreviation}/attendancePercentages',                               'HashController:attendancePercentagesPostActionJson');

$controllers->get('/{kennel_abbreviation}/CohareCounts/{hare_type}',                                    'HashController:cohareCountsPreActionJson');
$controllers->get('/{kennel_abbreviation}/allCohareCounts',                                      'HashController:allCohareCountsPreActionJson');
$controllers->post('/{kennel_abbreviation}/cohareCounts',                                        'HashController:getCohareCountsJson');

$controllers->get('/{kennel_abbreviation}/locationCounts',                                       'HashController:listLocationCountsPreActionJson');
$controllers->post('/{kennel_abbreviation}/locationCounts',                                      'HashController:getLocationCountsJson');

$controllers->get('/{kennel_abbreviation}/listhashes2',                                         'HashEventController:listHashesPreActionJson');
$controllers->post('/{kennel_abbreviation}/listhashes2',                                        'HashEventController:listHashesPostActionJson');

$controllers->get('/{kennel_abbreviation}/eventsHeatMap',                                        'ObscureStatisticsController:kennelEventsHeatMap');
$controllers->get('/{kennel_abbreviation}/eventsClusterMap',                                        'ObscureStatisticsController:kennelEventsClusterMap');
$controllers->get('/{kennel_abbreviation}/eventsMarkerMap',                                        'ObscureStatisticsController:kennelEventsMarkerMap');

$controllers->get('/{kennel_abbreviation}/listStreakers/byhash/{hash_id}',              'HashController:listStreakersByHashAction');

$controllers->get('/{kennel_abbreviation}/attendanceRecordForHasher/{hasher_id}',        'HashController:attendanceRecordForHasherAction');

$controllers->get('/{kennel_abbreviation}/listhashers/byhash/{hash_id}',                        'HashController:listHashersByHashAction');
$controllers->get('/{kennel_abbreviation}/listhares/byhash/{hash_id}',                          'HashController:listHaresByHashAction');
$controllers->get('/{kennel_abbreviation}/listhashes/byhasher/{hasher_id}',                     'HashController:listHashesByHasherAction');
$controllers->get('/{kennel_abbreviation}/listhashes/byhare/{hasher_id}',                       'HashController:listHashesByHareAction');
$controllers->get('/{kennel_abbreviation}/hashers/{hasher_id}',                                 'HashController:viewHasherChartsAction');
$controllers->get('/{kennel_abbreviation}/hashedWith/{hasher_id}',                                 'HashController:hashedWithAction');

$controllers->get('/{kennel_abbreviation}/hares/overall/{hasher_id}',     'HashController:viewOverallHareChartsAction');
$controllers->get('/{kennel_abbreviation}/hares/{hare_type}/{hasher_id}',        'HashController:viewHareChartsAction');

$controllers->get('/{kennel_abbreviation}/chartsAndDetails',                                 'ObscureStatisticsController:viewKennelChartsAction');

$controllers->get('/{kennel_abbreviation}/attendanceStatistics',                                'ObscureStatisticsController:viewAttendanceChartsAction');

#First timers / last timers
$controllers->get('/{kennel_abbreviation}/firstTimersStatistics/{min_hash_count}',              'ObscureStatisticsController:viewFirstTimersChartsAction');
$controllers->get('/{kennel_abbreviation}/lastTimersStatistics/{min_hash_count}/{month_count}', 'ObscureStatisticsController:viewLastTimersChartsAction');

#Virgin harings charts
$controllers->get('/{kennel_abbreviation}/virginHaringsStatistics/{hare_type}',  'ObscureStatisticsController:virginHaringsChartsAction');

#Distinct Hasher hashings charts
$controllers->get('/{kennel_abbreviation}/distinctHasherStatistics',              'ObscureStatisticsController:distinctHasherChartsAction');

$controllers->get('/{kennel_abbreviation}/distinctHareStatistics/{hare_type}',        'ObscureStatisticsController:distinctHaresChartsAction');

$controllers->get('/{kennel_abbreviation}/hashes/{hash_id}',                                    'HashController:viewHashAction');
$controllers->get('/{kennel_abbreviation}/hasherCountsForEvent/{hash_id}',               'HashController:hasherCountsForEventAction');

$controllers->get('/{kennel_abbreviation}/omniAnalversariesForEvent/{hash_id}',               'HashController:omniAnalversariesForEventAction');

$controllers->get('/{kennel_abbreviation}/hasherCountsForEventCounty/{hash_id}',               'HashController:hasherCountsForEventCountyAction');
$controllers->get('/{kennel_abbreviation}/hasherCountsForEventPostalCode/{hash_id}',               'HashController:hasherCountsForEventPostalCodeAction');

$controllers->get('/{kennel_abbreviation}/hasherCountsForEventState/{hash_id}',            'HashController:hasherCountsForEventStateAction');
$controllers->get('/{kennel_abbreviation}/hasherCountsForEventCity/{hash_id}',             'HashController:hasherCountsForEventCityAction');
$controllers->get('/{kennel_abbreviation}/hasherCountsForEventNeighborhood/{hash_id}',     'HashController:hasherCountsForEventNeighborhoodAction');

$controllers->get('/{kennel_abbreviation}/backSlidersForEventV2/{hash_id}',                     'HashController:backSlidersForEventV2Action');

$controllers->get('/{kennel_abbreviation}/consolidatedEventAnalversaries/{hash_id}',            'HashController:consolidatedEventAnalversariesAction');

$controllers->get('/{kennel_abbreviation}/trendingHashers/{day_count}',                         'ObscureStatisticsController:trendingHashersAction');
$controllers->get('/{kennel_abbreviation}/trendingHares/{hare_type}/{day_count}',               'ObscureStatisticsController:trendingHaresAction');

#Ajax version of untrending hares graphs
$controllers->get('/{kennel_abbreviation}/unTrendingHaresJsonPre/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'ObscureStatisticsController:unTrendingHaresJsonPreAction');
$controllers->get('/{kennel_abbreviation}/unTrendingHaresJsonPost/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'ObscureStatisticsController:unTrendingHaresJsonPostAction');

$controllers->get('/{kennel_abbreviation}/pendingHasherAnalversaries',                          'HashController:pendingHasherAnalversariesAction');
$controllers->get('/{kennel_abbreviation}/predictedHasherAnalversaries',                        'HashController:predictedHasherAnalversariesAction');
$controllers->get('/{kennel_abbreviation}/predictedCenturions',                                 'HashController:predictedCenturionsAction');
$controllers->get('/{kennel_abbreviation}/pendingHareAnalversaries',                            'HashController:pendingHareAnalversariesAction');
$controllers->get('/{kennel_abbreviation}/haringPercentageAllHashes',                           'HashController:haringPercentageAllHashesAction');
$controllers->get('/{kennel_abbreviation}/haringPercentage/{hare_type}',                        'HashController:haringPercentageAction');
$controllers->get('/{kennel_abbreviation}/hashingCounts',                                       'HashController:hashingCountsAction');
$controllers->get('/{kennel_abbreviation}/haringCounts',                                        'HashController:haringCountsAction');
$controllers->get('/{kennel_abbreviation}/haringCounts/{hare_type}',                            'HashController:haringTypeCountsAction');
$controllers->get('/{kennel_abbreviation}/coharelist/byhare/allhashes/{hasher_id}',             'HashController:coharelistByHareAllHashesAction');
$controllers->get('/{kennel_abbreviation}/coharelist/byhare/{hare_type}/{hasher_id}',           'HashController:coharelistByHareAction');
$controllers->get('/{kennel_abbreviation}/coharecount/byhare/allhashes/{hasher_id}',            'HashController:cohareCountByHareAllHashesAction');
$controllers->get('/{kennel_abbreviation}/coharecount/byhare/{hare_type}/{hasher_id}',          'HashController:cohareCountByHareAction');
$controllers->get('/{kennel_abbreviation}/hashattendance/byhare/lowest',                        'HashController:hashAttendanceByHareLowestAction');
$controllers->get('/{kennel_abbreviation}/hashattendance/byhare/highest',                       'HashController:hashAttendanceByHareHighestAction');
$controllers->get('/{kennel_abbreviation}/hashattendance/byhare/average',                       'HashController:hashAttendanceByHareAverageAction');
$controllers->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/nondistincthashers', 'HashController:hashAttendanceByHareGrandTotalNonDistinctHashersAction');
$controllers->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/distincthashers',    'HashController:hashAttendanceByHareGrandTotalDistinctHashersAction');
$controllers->get('/{kennel_abbreviation}/getHasherCountsByHare/{hare_id}/{hare_type}',         'HashController:hasherCountsByHareAction');
$controllers->get('/{kennel_abbreviation}/percentages/harings',                                 'HashController:percentageHarings');
$controllers->get('/{kennel_abbreviation}/getHasherAnalversaries/{hasher_id}',                  'HashController:getHasherAnalversariesAction');
$controllers->get('/{kennel_abbreviation}/getHareAnalversaries/all/{hasher_id}',                'HashController:getHareAnalversariesAction');
$controllers->get('/{kennel_abbreviation}/getHareAnalversaries/{hare_type}/{hasher_id}',      'HashController:getHareAnalversariesByHareTypeAction');
$controllers->get('/{kennel_abbreviation}/getProjectedHasherAnalversaries/{hasher_id}',         'HashController:getProjectedHasherAnalversariesAction');

$controllers->get('/{kennel_abbreviation}/longestStreaks',                                      'ObscureStatisticsController:getLongestStreaksAction');
$controllers->get('/{kennel_abbreviation}/aboutContact',                                        'ObscureStatisticsController:aboutContactAction');

# Hash name (substring) analysis
$controllers->get('/{kennel_abbreviation}/hasherNameAnalysis',            'ObscureStatisticsController:hasherNameAnalysisAction');
$controllers->get('/{kennel_abbreviation}/hasherNameAnalysis2',            'ObscureStatisticsController:hasherNameAnalysisAction2');
$controllers->get('/{kennel_abbreviation}/hasherNameAnalysisWordCloud',            'ObscureStatisticsController:hasherNameAnalysisWordCloudAction');

# View the jumbo counts table
$controllers->get('/{kennel_abbreviation}/jumboCountsTable',                 'HashController:jumboCountsTablePreActionJson');
$controllers->post('/{kennel_abbreviation}/jumboCountsTable',                'HashController:jumboCountsTablePostActionJson');

# View the jumbo percentages table
$controllers->get('/{kennel_abbreviation}/jumboPercentagesTable',                 'HashController:jumboPercentagesTablePreActionJson');
$controllers->post('/{kennel_abbreviation}/jumboPercentagesTable',                'HashController:jumboPercentagesTablePostActionJson');

#Show events by event tag
$controllers->get('/{kennel_abbreviation}/listhashes/byeventtag/{event_tag_ky}', 'TagController:listHashesByEventTagAction');
$controllers->get('/{kennel_abbreviation}/chartsGraphs/byeventtag/{event_tag_ky}', 'TagController:chartsGraphsByEventTagAction');

# Functions for the "by year" statistics
$controllers->get('/{kennel_abbreviation}/statistics/getYearInReview/{year_value}',               'ObscureStatisticsController:getYearInReviewAction');
$controllers->post('/{kennel_abbreviation}/statistics/getHasherCountsByYear',                     'ObscureStatisticsController:getHasherCountsByYear');
$controllers->post('/{kennel_abbreviation}/statistics/getTotalHareCountsByYear',                  'ObscureStatisticsController:getTotalHareCountsByYear');
$controllers->post('/{kennel_abbreviation}/statistics/getHareCountsByYear/{hare_type}',           'ObscureStatisticsController:getHareCountsByYear');
$controllers->post('/{kennel_abbreviation}/statistics/getNewbieHasherListByYear',                 'ObscureStatisticsController:getNewbieHasherListByYear');
$controllers->post('/{kennel_abbreviation}/statistics/getNewbieHareListByYear/{hare_type}',       'ObscureStatisticsController:getNewbieHareListByYear');
$controllers->post('/{kennel_abbreviation}/statistics/getNewbieOverallHareListByYear',            'ObscureStatisticsController:getNewbieOverallHareListByYear');

# Mappings for hasher specific statistics
$controllers->post('/{kennel_abbreviation}/statistics/hasher/firstHash',                           'ObscureStatisticsController:getHashersVirginHash');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/mostRecentHash',                      'ObscureStatisticsController:getHashersLatestHash');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/firstHare',                           'ObscureStatisticsController:getHashersVirginHare');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/mostRecentHare',                      'ObscureStatisticsController:getHashersLatestHare');

# Mappings for kennel specific statistics
$controllers->post('/{kennel_abbreviation}/statistics/kennel/firstHash',                           'ObscureStatisticsController:getKennelsVirginHash');
$controllers->post('/{kennel_abbreviation}/statistics/kennel/mostRecentHash',                      'ObscureStatisticsController:getKennelsLatestHash');

# Mappings for hasher hashes by (year/month/state/etc)
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/year',                      'ObscureStatisticsController:getHasherHashesByYear');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/quarter',                   'ObscureStatisticsController:getHasherHashesByQuarter');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/month',                     'ObscureStatisticsController:getHasherHashesByMonth');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/dayname',                   'ObscureStatisticsController:getHasherHashesByDayName');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/state',                     'ObscureStatisticsController:getHasherHashesByState');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/city',                      'ObscureStatisticsController:getHasherHashesByCity');

# Mappings for kennel hashes by (year/month/state/etc)
$controllers->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/city',                      'ObscureStatisticsController:getKennelHashesByCity');
$controllers->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/county',                      'ObscureStatisticsController:getKennelHashesByCounty');
$controllers->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/postalcode',                      'ObscureStatisticsController:getKennelHashesByPostalcode');

# Mappings for hasher harings by (year/month/state/etc)
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/year',                      'ObscureStatisticsController:getHasherAllHaringsByYear');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/quarter',                   'ObscureStatisticsController:getHasherAllHaringsByQuarter');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/month',                     'ObscureStatisticsController:getHasherAllHaringsByMonth');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/dayname',                   'ObscureStatisticsController:getHasherAllHaringsByDayName');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/state',                     'ObscureStatisticsController:getHasherAllHaringsByState');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/city',                      'ObscureStatisticsController:getHasherAllHaringsByCity');

# Mappings for hasher harings by (year/month/state/etc) by hare type
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/year',                      'ObscureStatisticsController:getHasherHaringsByYear');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/quarter',                   'ObscureStatisticsController:getHasherHaringsByQuarter');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/month',                     'ObscureStatisticsController:getHasherHaringsByMonth');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/dayname',                   'ObscureStatisticsController:getHasherHaringsByDayName');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/state',                     'ObscureStatisticsController:getHasherHaringsByState');
$controllers->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/city',                      'ObscureStatisticsController:getHasherHaringsByCity');

# Per person stats (more of them)
$controllers->post('/{kennel_abbreviation}/coharecount/byhare/allhashes','ObscureStatisticsController:getCohareCountByHareAllHashes');
$controllers->post('/{kennel_abbreviation}/coharecount/byhare/{hare_type}','ObscureStatisticsController:getCohareCountByHare');

$controllers->get('/{kennel_abbreviation}/basic/stats',         'HashController:basicStatsAction');
$controllers->get('/{kennel_abbreviation}/cautionary/stats',    'HashController:cautionaryStatsAction');
$controllers->get('/{kennel_abbreviation}/miscellaneous/stats', 'HashController:miscellaneousStatsAction');

#Revised top level pages
$controllers->get('/{kennel_abbreviation}/people/stats', 'HashController:peopleStatsAction');
$controllers->get('/{kennel_abbreviation}/analversaries/stats', 'HashController:analversariesStatsAction');
$controllers->get('/{kennel_abbreviation}/year_by_year/stats', 'HashController:yearByYearStatsAction');
$controllers->get('/{kennel_abbreviation}/kennel/records', 'HashController:kennelRecordsStatsAction');
$controllers->get('/{kennel_abbreviation}/kennel/general_info', 'HashController:kennelGeneralInfoStatsAction');

#URLs for fastest/slowest to reach analversaries
$controllers->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/bydays', 'ObscureStatisticsController:quickestToReachAnalversaryByDaysAction');
$controllers->get('/{kennel_abbreviation}/{analversary_number}/slowest/to/reach/bydays',  'ObscureStatisticsController:slowestToReachAnalversaryByDaysAction');
$controllers->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/date', 'ObscureStatisticsController:quickestToReachAnalversaryByDate');

$controllers->get('/{kennel_abbreviation}/longest/career','ObscureStatisticsController:longestCareerAction');
$controllers->get('/{kennel_abbreviation}/highest/averageDaysBetweenHashes','ObscureStatisticsController:highestAverageDaysBetweenHashesAction');
$controllers->get('/{kennel_abbreviation}/lowest/averageDaysBetweenHashes','ObscureStatisticsController:lowestAverageDaysBetweenHashesAction');
$controllers->get('/{kennel_abbreviation}/everyones/latest/hashes/{min_hash_count}','ObscureStatisticsController:everyonesLatestHashesAction');
$controllers->get('/{kennel_abbreviation}/everyones/first/hashes/{min_hash_count}','ObscureStatisticsController:everyonesFirstHashesAction');

$controllers->get('/{kennel_abbreviation}/highest/allharings/averageDaysBetweenHarings','ObscureStatisticsController:highestAverageDaysBetweenAllHaringsAction');
$controllers->get('/{kennel_abbreviation}/lowest/allharings/averageDaysBetweenHarings','ObscureStatisticsController:lowestAverageDaysBetweenAllHaringsAction');
$controllers->get('/{kennel_abbreviation}/highest/{hare_type}/averageDaysBetweenHarings','ObscureStatisticsController:highestAverageDaysBetweenHaringsAction');
$controllers->get('/{kennel_abbreviation}/lowest/{hare_type}/averageDaysBetweenHarings','ObscureStatisticsController:lowestAverageDaysBetweenHaringsAction');

$controllers->get('/{kennel_abbreviation}/highest/attendedHashes','HashController:highestAttendedHashesAction');
$controllers->get('/{kennel_abbreviation}/lowest/attendedHashes','HashController:lowestAttendedHashesAction');

$controllers->get('/{kennel_abbreviation}/hashers/of/the/years','HashController:hashersOfTheYearsAction');
$controllers->get('/{kennel_abbreviation}/hares/{hare_type}/of/the/years','HashController:HaresOfTheYearsAction');

#Establish the mortal kombat head to head matchup functionality
$controllers->get('/{kennel_abbreviation}/hashers/twoHasherComparison',            'HashController:twoPersonComparisonPreAction');
$controllers->get('/{kennel_abbreviation}/hashers/comparison/{hasher_id}/{hasher_id2}/',     'HashController:twoPersonComparisonAction');
$controllers->post('/{kennel_abbreviation}/hashers/retrieve',                         'HashPersonController:retrieveHasherAction');

# kennel home page
$controllers->get('/{kennel_abbreviation}',                               'HashController:slashKennelAction2');

$app['dispatcher']->addSubscriber(new ResponseListener($app['charset']));

$controllersFactory = $app['controllers_factory'];
foreach ($fakeRoutes as $route) {
  list($method, $pattern, $name) = $route;
  $controllersFactory->$method($pattern)->setDefault('_controller', null)->bind($name);
}
$controllers->mount('/', $controllersFactory);

$request = Request::createFromGlobals();
$response = $httpKernelImpl->handle($request);
$response->send();
$app['kernel']->terminate($request, $response);
