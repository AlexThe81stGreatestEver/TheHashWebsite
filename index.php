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
require_once 'Provider/RoutingServiceProvider.php';
require_once 'Provider/HttpKernelServiceProvider.php';
require_once 'Provider/EventListenerProvider.php';
require_once 'Provider/SecurityServiceProvider.php';
require_once 'Provider/MonologServiceProvider.php';
require_once 'Application.php';
require_once 'Psr11ServiceProvider.php';

use Doctrine\DBAL\Schema\Table;

use Pimple\ServiceProviderInterface;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\DumpExtension;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints as Assert;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\ContainerRuntimeLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

$app = new Application();
$app['locale'] = 'en';
$app['debug'] = defined('DEBUG') && DEBUG;

if($app['debug']) {
  Debug::enable();

  # Register the monolog logging service
  $app->register(new Provider\MonologServiceProvider(), array(
      'monolog.logfile' => __DIR__.'/development.log',
      'monolog.level' => 'debug',
      'monolog.bubble' => true
  ));
} else {
  $app['logger'] = null;
  ErrorHandler::register();
}

$app->register(new Provider\HttpKernelServiceProvider());
$app->register(new Provider\RoutingServiceProvider());
$app->register(new Psr11ServiceProvider());

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

$app->register(new Provider\EventListenerProvider());

$app['form.types'] = function ($app) {
    return [];
};

$app['form.type.extensions'] = function ($app) {
    return [];
};

$app['form.type.guessers'] = function ($app) {
    return [];
};

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


$app->register(new Provider\SecurityServiceProvider());
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
$app['twig.templates'] = [];

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

        if (isset($app['var_dumper.cloner'])) {
            $twig->addExtension(new DumpExtension($app['var_dumper.cloner']));
        }

        $twig->addRuntimeLoader($app['twig.runtime_loader']);
    }

    return $twig;
};

$app['twig.loader.filesystem'] = function ($app) {
    $loader = new FilesystemLoader();
    foreach (is_array($app['twig.path']) ? $app['twig.path'] : [$app['twig.path']] as $key => $val) {
        if (is_string($key)) {
            $loader->addPath($key, $val);
        } else {
            $loader->addPath($val);
        }
    }

    return $loader;
};

$app['twig.loader.array'] = function ($app) {
    return new TwigArrayLoader($app['twig.templates']);
};

$app['twig.loader'] = function ($app) {
    return new ChainLoader([
        $app['twig.loader.array'],
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
$app->get('/',                                                    'HashController:slashAction')->bind('homepage');
$app->get('/{kennel_abbreviation}/rss',                           'HashController:rssAction');
$app->get('/{kennel_abbreviation}/events/rss',                    'HashController:eventsRssAction');

#Admin section logon
$app->get('/logonscreen',                                         'HashController:logonScreenAction');
$app->get('/admin/logoutaction',                                  'AdminController:logoutAction');
$app->get('/admin/hello',                                         'AdminController:helloAction');


#Superadmin section logon
$app->get('/logonscreen/sa',                                        'SuperAdminController:logonScreenAction');
$app->get('/superadmin/logoutaction',                               'SuperAdminController:logoutAction');
$app->get('/superadmin/hello',                                      'SuperAdminController:helloAction');
$app->get('/superadmin/integrity',                                  'SuperAdminController:integrityChecks');
$app->get('/superadmin/{kennel_abbreviation}/editkennel/ajaxform',  'SuperAdminController:modifyKennelAjaxPreAction');
$app->post('/superadmin/{kennel_abbreviation}/editkennel/ajaxform', 'SuperAdminController:modifyKennelAjaxPostAction');
$app->get('/superadmin/{hare_type}/editharetype/ajaxform',          'SuperAdminController:modifyHareTypeAjaxPreAction');
$app->post('/superadmin/{hare_type}/editharetype/ajaxform',         'SuperAdminController:modifyHareTypeAjaxPostAction');
$app->get('/superadmin/{hash_type}/edithashtype/ajaxform',          'SuperAdminController:modifyHashTypeAjaxPreAction');
$app->post('/superadmin/{hash_type}/edithashtype/ajaxform',         'SuperAdminController:modifyHashTypeAjaxPostAction');
$app->get('/superadmin/{user_id}/edituser/ajaxform',                'SuperAdminController:modifyUserAjaxPreAction');
$app->post('/superadmin/{user_id}/edituser/ajaxform',               'SuperAdminController:modifyUserAjaxPostAction');
$app->get('/superadmin/{name}/editsiteconfig/ajaxform',             'SuperAdminController:modifySiteConfigAjaxPreAction');
$app->post('/superadmin/{name}/editsiteconfig/ajaxform',            'SuperAdminController:modifySiteConfigAjaxPostAction');
$app->get('/superadmin/{ridiculous}/editridiculous/ajaxform',       'SuperAdminController:modifyRidiculousAjaxPreAction');
$app->post('/superadmin/{ridiculous}/editridiculous/ajaxform',      'SuperAdminController:modifyRidiculousAjaxPostAction');
$app->post('/superadmin/deleteridiculous',                          'SuperAdminController:deleteRidiculous');
$app->post('/superadmin/deleteuser',                                'SuperAdminController:deleteUser');
$app->post('/superadmin/deletekennel',                              'SuperAdminController:deleteKennel');
$app->post('/superadmin/deletehashtype',                            'SuperAdminController:deleteHashType');
$app->post('/superadmin/deleteharetype',                            'SuperAdminController:deleteHareType');
$app->get('/superadmin/newridiculous/ajaxform',                     'SuperAdminController:newRidiculousAjaxPreAction');
$app->post('/superadmin/newridiculous/ajaxform',                    'SuperAdminController:newRidiculousAjaxPostAction');
$app->get('/superadmin/newuser/ajaxform',                           'SuperAdminController:newUserAjaxPreAction');
$app->post('/superadmin/newuser/ajaxform',                          'SuperAdminController:newUserAjaxPostAction');
$app->get('/superadmin/newkennel/ajaxform',                         'SuperAdminController:newKennelAjaxPreAction');
$app->post('/superadmin/newkennel/ajaxform',                        'SuperAdminController:newKennelAjaxPostAction');
$app->get('/superadmin/newhashtype/ajaxform',                       'SuperAdminController:newHashTypeAjaxPreAction');
$app->post('/superadmin/newhashtype/ajaxform',                      'SuperAdminController:newHashTypeAjaxPostAction');
$app->get('/superadmin/newharetype/ajaxform',                       'SuperAdminController:newHareTypeAjaxPreAction');
$app->post('/superadmin/newharetype/ajaxform',                      'SuperAdminController:newHareTypeAjaxPostAction');
$app->get('/superadmin/export',                                     'SuperAdminController:exportDatabaseAction');

$app->get('/admin/{kennel_abbreviation}/newhash/ajaxform', 'HashEventController:adminCreateHashAjaxPreAction');
$app->post('/admin/{kennel_abbreviation}/newhash/ajaxform', 'HashEventController:adminCreateHashAjaxPostAction');
$app->get('/admin/{hash_id}/duplicateHash',                 'HashEventController:adminDuplicateHash');

# Hash event modification (ajaxified)
$app->get('/admin/edithash/ajaxform/{hash_id}', 'HashEventController:adminModifyHashAjaxPreAction');
$app->post('/admin/edithash/ajaxform/{hash_id}', 'HashEventController:adminModifyHashAjaxPostAction');

# Hash person modification
$app->get('/admin/modifyhasher/form/{hasher_id}',                 'HashPersonController:modifyHashPersonAction');
$app->post('/admin/modifyhasher/form/{hasher_id}',                'HashPersonController:modifyHashPersonAction');

# Hash person deletion
$app->get('/admin/deleteHasher/{hasher_id}',                      'HashPersonController:deleteHashPersonPreAction');
$app->post('/admin/deleteHasherPost',                      'HashPersonController:deleteHashPersonAjaxAction');

# Hash person creation
$app->get('/admin/newhasher/form',                                'HashPersonController:createHashPersonAction');
$app->post('/admin/newhasher/form',                               'HashPersonController:createHashPersonAction');

# Change admin password
$app->get('/admin/newPassword/form',                                'AdminController:newPasswordAction');
$app->post('/admin/newPassword/form',                               'AdminController:newPasswordAction');

# View audit records
$app->get('/admin/viewAuditRecords',                                  'AdminController:viewAuditRecordsPreActionJson');
$app->post('/admin/viewAuditRecords',                                 'AdminController:viewAuditRecordsJson');

# Modify the participation for an event
$app->get('/admin/hash/manageparticipation2/{hash_id}',            'HashEventController:hashParticipationJsonPreAction');
$app->post('/admin/hash/manageparticipation2/{hash_id}',           'HashEventController:hashParticipationJsonPostAction');

# Page to manage the event tags
$app->get('/admin/tags/manageeventtags',                            'TagController:manageEventTagsPreAction');
$app->get('/admin/tags/geteventtagswithcounts',                     'TagController:getEventTagsWithCountsJsonAction');
$app->get('/admin/tags/getalleventtags',                            'TagController:getAllEventTagsJsonAction');
$app->get('/admin/tags/getmatchingeventtags',                       'TagController:getMatchingEventTagsJsonAction');
#$app->post('/admin/tags/manageeventtags',                           'TagController:manageEventTagsJsonPostAction');
$app->post('/admin/tags/addneweventtag',                            'TagController:addNewEventTag');

# Add or remove tags to events
$app->post('/admin/tags/addtagtoevent',                             'TagController:addTagToEventJsonAction');
$app->post('/admin/tags/removetagfromevent',                        'TagController:removeTagFromEventJsonAction');
$app->get('/admin/tags/eventscreen/{hash_id}',                      'TagController:showEventForTaggingPreAction');

# Functions to add and delete hounds and hares to the hashes
$app->post('/admin/hash/addHasherToHash',                         'HashEventController:addHashParticipant');
$app->post('/admin/hash/addHareToHash',                           'HashEventController:addHashOrganizer');
$app->post('/admin/hash/deleteHasherFromHash',                    'HashEventController:deleteHashParticipant');
$app->post('/admin/hash/deleteHareFromHash',                      'HashEventController:deleteHashOrganizer');

$app->post('/admin/hash/getHaresForEvent',                        'HashEventController:getHaresForEvent');
$app->post('/admin/hash/getHashersForEvent',                      'HashEventController:getHashersForEvent');

$app->get('/admin/listOrphanedHashers',                             'AdminController:listOrphanedHashersAction');

$app->get('/admin/legacy',                                          'AdminController:legacy');
$app->get('/admin/{kennel_abbreviation}/legacy',                    'AdminController:legacy');
$app->post('/admin/{kennel_abbreviation}/legacyUpdate',             'AdminController:legacyUpdate');
$app->get('/admin/roster',                                          'AdminController:roster');
$app->get('/admin/{kennel_abbreviation}/roster',                    'AdminController:roster');
$app->get('/admin/awards/{type}',                                   'AdminController:awards');
$app->get('/admin/{kennel_abbreviation}/awards/{type}',             'AdminController:awards');
$app->get('/admin/{kennel_abbreviation}/awards/{type}/{horizon}',   'AdminController:awards');
$app->post('/admin/updateHasherAward',                              'AdminController:updateHasherAwardAjaxAction');

$app->get('/admin/listhashes2',                                    'AdminController:listHashesPreActionJson');
$app->get('/admin/{kennel_abbreviation}/listhashes2',              'AdminController:listHashesPreActionJson');
$app->post('/admin/{kennel_abbreviation}/listhashes2',             'AdminController:getHashListJson');

$app->get('/admin/listhashers2',                                    'AdminController:listHashersPreActionJson');
$app->post('/admin/listhashers2',                                   'AdminController:getHashersListJson');

$app->post('/admin/listhashers3',                                   'AdminController:getHashersParticipationListJson');

$app->get('/admin/hasherDetailsKennelSelection/{hasher_id}',        'AdminController:hasherDetailsKennelSelection');
$app->post('/admin/deleteHash',                                     'AdminController:deleteHash');

#The per event budget screen
$app->get('/admin/eventBudget/{hash_id}','AdminController:eventBudgetPreAction');

$app->get('/{kennel_abbreviation}/mia',                                       'HashController:miaPreActionJson');
$app->post('/{kennel_abbreviation}/mia',                                       'HashController:miaPostActionJson');

$app->post('/{kennel_abbreviation}/listhashers2',                                       'HashController:getHasherListJson');

$app->get('/{kennel_abbreviation}/listvirginharings/{hare_type}',                      'HashController:listVirginHaringsPreActionJson');
$app->post('/{kennel_abbreviation}/listvirginharings/{hare_type}',                     'HashController:getVirginHaringsListJson');

$app->get('/{kennel_abbreviation}/attendancePercentages',                                'HashController:attendancePercentagesPreActionJson');
$app->post('/{kennel_abbreviation}/attendancePercentages',                               'HashController:attendancePercentagesPostActionJson');

$app->get('/{kennel_abbreviation}/CohareCounts/{hare_type}',                                    'HashController:cohareCountsPreActionJson');
$app->get('/{kennel_abbreviation}/allCohareCounts',                                      'HashController:allCohareCountsPreActionJson');
$app->post('/{kennel_abbreviation}/cohareCounts',                                        'HashController:getCohareCountsJson');

$app->get('/{kennel_abbreviation}/locationCounts',                                       'HashController:listLocationCountsPreActionJson');
$app->post('/{kennel_abbreviation}/locationCounts',                                      'HashController:getLocationCountsJson');

$app->get('/{kennel_abbreviation}/listhashes2',                                         'HashEventController:listHashesPreActionJson');
$app->post('/{kennel_abbreviation}/listhashes2',                                        'HashEventController:listHashesPostActionJson');

$app->get('/{kennel_abbreviation}/eventsHeatMap',                                        'ObscureStatisticsController:kennelEventsHeatMap');
$app->get('/{kennel_abbreviation}/eventsClusterMap',                                        'ObscureStatisticsController:kennelEventsClusterMap');
$app->get('/{kennel_abbreviation}/eventsMarkerMap',                                        'ObscureStatisticsController:kennelEventsMarkerMap');

$app->get('/{kennel_abbreviation}/listStreakers/byhash/{hash_id}',              'HashController:listStreakersByHashAction');

$app->get('/{kennel_abbreviation}/attendanceRecordForHasher/{hasher_id}',        'HashController:attendanceRecordForHasherAction');

$app->get('/{kennel_abbreviation}/listhashers/byhash/{hash_id}',                        'HashController:listHashersByHashAction');
$app->get('/{kennel_abbreviation}/listhares/byhash/{hash_id}',                          'HashController:listHaresByHashAction');
$app->get('/{kennel_abbreviation}/listhashes/byhasher/{hasher_id}',                     'HashController:listHashesByHasherAction');
$app->get('/{kennel_abbreviation}/listhashes/byhare/{hasher_id}',                       'HashController:listHashesByHareAction');
$app->get('/{kennel_abbreviation}/hashers/{hasher_id}',                                 'HashController:viewHasherChartsAction');
$app->get('/{kennel_abbreviation}/hashedWith/{hasher_id}',                                 'HashController:hashedWithAction');

$app->get('/{kennel_abbreviation}/hares/overall/{hasher_id}',     'HashController:viewOverallHareChartsAction');
$app->get('/{kennel_abbreviation}/hares/{hare_type}/{hasher_id}',        'HashController:viewHareChartsAction');


$app->get('/{kennel_abbreviation}/chartsAndDetails',                                 'ObscureStatisticsController:viewKennelChartsAction');


$app->get('/{kennel_abbreviation}/attendanceStatistics',                                'ObscureStatisticsController:viewAttendanceChartsAction');

#First timers / last timers
$app->get('/{kennel_abbreviation}/firstTimersStatistics/{min_hash_count}',              'ObscureStatisticsController:viewFirstTimersChartsAction');
$app->get('/{kennel_abbreviation}/lastTimersStatistics/{min_hash_count}/{month_count}', 'ObscureStatisticsController:viewLastTimersChartsAction');

#Virgin harings charts
$app->get('/{kennel_abbreviation}/virginHaringsStatistics/{hare_type}',  'ObscureStatisticsController:virginHaringsChartsAction');

#Distinct Hasher hashings charts
$app->get('/{kennel_abbreviation}/distinctHasherStatistics',              'ObscureStatisticsController:distinctHasherChartsAction');

$app->get('/{kennel_abbreviation}/distinctHareStatistics/{hare_type}',        'ObscureStatisticsController:distinctHaresChartsAction');

$app->get('/{kennel_abbreviation}/hashes/{hash_id}',                                    'HashController:viewHashAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEvent/{hash_id}',               'HashController:hasherCountsForEventAction');

$app->get('/{kennel_abbreviation}/omniAnalversariesForEvent/{hash_id}',               'HashController:omniAnalversariesForEventAction');

$app->get('/{kennel_abbreviation}/hasherCountsForEventCounty/{hash_id}',               'HashController:hasherCountsForEventCountyAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventPostalCode/{hash_id}',               'HashController:hasherCountsForEventPostalCodeAction');

$app->get('/{kennel_abbreviation}/hasherCountsForEventState/{hash_id}',            'HashController:hasherCountsForEventStateAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventCity/{hash_id}',             'HashController:hasherCountsForEventCityAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventNeighborhood/{hash_id}',     'HashController:hasherCountsForEventNeighborhoodAction');

$app->get('/{kennel_abbreviation}/backSlidersForEventV2/{hash_id}',                     'HashController:backSlidersForEventV2Action');

$app->get('/{kennel_abbreviation}/consolidatedEventAnalversaries/{hash_id}',            'HashController:consolidatedEventAnalversariesAction');

$app->get('/{kennel_abbreviation}/trendingHashers/{day_count}',                         'ObscureStatisticsController:trendingHashersAction');
$app->get('/{kennel_abbreviation}/trendingHares/{hare_type}/{day_count}',               'ObscureStatisticsController:trendingHaresAction');

#Ajax version of untrending hares graphs
$app->get('/{kennel_abbreviation}/unTrendingHaresJsonPre/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'ObscureStatisticsController:unTrendingHaresJsonPreAction');
$app->get('/{kennel_abbreviation}/unTrendingHaresJsonPost/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'ObscureStatisticsController:unTrendingHaresJsonPostAction');

$app->get('/{kennel_abbreviation}/pendingHasherAnalversaries',                          'HashController:pendingHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/predictedHasherAnalversaries',                        'HashController:predictedHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/predictedCenturions',                                 'HashController:predictedCenturionsAction');
$app->get('/{kennel_abbreviation}/pendingHareAnalversaries',                            'HashController:pendingHareAnalversariesAction');
$app->get('/{kennel_abbreviation}/haringPercentageAllHashes',                           'HashController:haringPercentageAllHashesAction');
$app->get('/{kennel_abbreviation}/haringPercentage/{hare_type}',                        'HashController:haringPercentageAction');
$app->get('/{kennel_abbreviation}/hashingCounts',                                       'HashController:hashingCountsAction');
$app->get('/{kennel_abbreviation}/haringCounts',                                        'HashController:haringCountsAction');
$app->get('/{kennel_abbreviation}/haringCounts/{hare_type}',                            'HashController:haringTypeCountsAction');
$app->get('/{kennel_abbreviation}/coharelist/byhare/allhashes/{hasher_id}',             'HashController:coharelistByHareAllHashesAction');
$app->get('/{kennel_abbreviation}/coharelist/byhare/{hare_type}/{hasher_id}',           'HashController:coharelistByHareAction');
$app->get('/{kennel_abbreviation}/coharecount/byhare/allhashes/{hasher_id}',            'HashController:cohareCountByHareAllHashesAction');
$app->get('/{kennel_abbreviation}/coharecount/byhare/{hare_type}/{hasher_id}',          'HashController:cohareCountByHareAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/lowest',                        'HashController:hashAttendanceByHareLowestAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/highest',                       'HashController:hashAttendanceByHareHighestAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/average',                       'HashController:hashAttendanceByHareAverageAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/nondistincthashers', 'HashController:hashAttendanceByHareGrandTotalNonDistinctHashersAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/distincthashers',    'HashController:hashAttendanceByHareGrandTotalDistinctHashersAction');
$app->get('/{kennel_abbreviation}/getHasherCountsByHare/{hare_id}/{hare_type}',         'HashController:hasherCountsByHareAction');
$app->get('/{kennel_abbreviation}/percentages/harings',                                 'HashController:percentageHarings');
$app->get('/{kennel_abbreviation}/getHasherAnalversaries/{hasher_id}',                  'HashController:getHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/getHareAnalversaries/all/{hasher_id}',                'HashController:getHareAnalversariesAction');
$app->get('/{kennel_abbreviation}/getHareAnalversaries/{hare_type}/{hasher_id}',      'HashController:getHareAnalversariesByHareTypeAction');
$app->get('/{kennel_abbreviation}/getProjectedHasherAnalversaries/{hasher_id}',         'HashController:getProjectedHasherAnalversariesAction');


$app->get('/{kennel_abbreviation}/longestStreaks',                                      'ObscureStatisticsController:getLongestStreaksAction');
$app->get('/{kennel_abbreviation}/aboutContact',                                        'ObscureStatisticsController:aboutContactAction');

# Hash name (substring) analysis
$app->get('/{kennel_abbreviation}/hasherNameAnalysis',            'ObscureStatisticsController:hasherNameAnalysisAction');
$app->get('/{kennel_abbreviation}/hasherNameAnalysis2',            'ObscureStatisticsController:hasherNameAnalysisAction2');
$app->get('/{kennel_abbreviation}/hasherNameAnalysisWordCloud',            'ObscureStatisticsController:hasherNameAnalysisWordCloudAction');



# View the jumbo counts table
$app->get('/{kennel_abbreviation}/jumboCountsTable',                 'HashController:jumboCountsTablePreActionJson');
$app->post('/{kennel_abbreviation}/jumboCountsTable',                'HashController:jumboCountsTablePostActionJson');

# View the jumbo percentages table
$app->get('/{kennel_abbreviation}/jumboPercentagesTable',                 'HashController:jumboPercentagesTablePreActionJson');
$app->post('/{kennel_abbreviation}/jumboPercentagesTable',                'HashController:jumboPercentagesTablePostActionJson');


#Show events by event tag
$app->get('/{kennel_abbreviation}/listhashes/byeventtag/{event_tag_ky}', 'TagController:listHashesByEventTagAction');
$app->get('/{kennel_abbreviation}/chartsGraphs/byeventtag/{event_tag_ky}', 'TagController:chartsGraphsByEventTagAction');


# Functions for the "by year" statistics
$app->get('/{kennel_abbreviation}/statistics/getYearInReview/{year_value}',               'ObscureStatisticsController:getYearInReviewAction');
$app->post('/{kennel_abbreviation}/statistics/getHasherCountsByYear',                     'ObscureStatisticsController:getHasherCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getTotalHareCountsByYear',                  'ObscureStatisticsController:getTotalHareCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getHareCountsByYear/{hare_type}',           'ObscureStatisticsController:getHareCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieHasherListByYear',                 'ObscureStatisticsController:getNewbieHasherListByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieHareListByYear/{hare_type}',       'ObscureStatisticsController:getNewbieHareListByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieOverallHareListByYear',            'ObscureStatisticsController:getNewbieOverallHareListByYear');



# Mappings for hasher specific statistics
$app->post('/{kennel_abbreviation}/statistics/hasher/firstHash',                           'ObscureStatisticsController:getHashersVirginHash');
$app->post('/{kennel_abbreviation}/statistics/hasher/mostRecentHash',                      'ObscureStatisticsController:getHashersLatestHash');
$app->post('/{kennel_abbreviation}/statistics/hasher/firstHare',                           'ObscureStatisticsController:getHashersVirginHare');
$app->post('/{kennel_abbreviation}/statistics/hasher/mostRecentHare',                      'ObscureStatisticsController:getHashersLatestHare');

# Mappings for kennel specific statistics
$app->post('/{kennel_abbreviation}/statistics/kennel/firstHash',                           'ObscureStatisticsController:getKennelsVirginHash');
$app->post('/{kennel_abbreviation}/statistics/kennel/mostRecentHash',                      'ObscureStatisticsController:getKennelsLatestHash');

# Mappings for hasher hashes by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/year',                      'ObscureStatisticsController:getHasherHashesByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/quarter',                   'ObscureStatisticsController:getHasherHashesByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/month',                     'ObscureStatisticsController:getHasherHashesByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/dayname',                   'ObscureStatisticsController:getHasherHashesByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/state',                     'ObscureStatisticsController:getHasherHashesByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/city',                      'ObscureStatisticsController:getHasherHashesByCity');

# Mappings for kennel hashes by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/city',                      'ObscureStatisticsController:getKennelHashesByCity');
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/county',                      'ObscureStatisticsController:getKennelHashesByCounty');
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/postalcode',                      'ObscureStatisticsController:getKennelHashesByPostalcode');

# Mappings for hasher harings by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/year',                      'ObscureStatisticsController:getHasherAllHaringsByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/quarter',                   'ObscureStatisticsController:getHasherAllHaringsByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/month',                     'ObscureStatisticsController:getHasherAllHaringsByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/dayname',                   'ObscureStatisticsController:getHasherAllHaringsByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/state',                     'ObscureStatisticsController:getHasherAllHaringsByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/city',                      'ObscureStatisticsController:getHasherAllHaringsByCity');

# Mappings for hasher harings by (year/month/state/etc) by hare type
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/year',                      'ObscureStatisticsController:getHasherHaringsByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/quarter',                   'ObscureStatisticsController:getHasherHaringsByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/month',                     'ObscureStatisticsController:getHasherHaringsByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/dayname',                   'ObscureStatisticsController:getHasherHaringsByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/state',                     'ObscureStatisticsController:getHasherHaringsByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/city',                      'ObscureStatisticsController:getHasherHaringsByCity');

# Per person stats (more of them)
$app->post('/{kennel_abbreviation}/coharecount/byhare/allhashes','ObscureStatisticsController:getCohareCountByHareAllHashes');
$app->post('/{kennel_abbreviation}/coharecount/byhare/{hare_type}','ObscureStatisticsController:getCohareCountByHare');


$app->get('/{kennel_abbreviation}/basic/stats',         'HashController:basicStatsAction');
$app->get('/{kennel_abbreviation}/cautionary/stats',    'HashController:cautionaryStatsAction');
$app->get('/{kennel_abbreviation}/miscellaneous/stats', 'HashController:miscellaneousStatsAction');

#Revised top level pages
$app->get('/{kennel_abbreviation}/people/stats', 'HashController:peopleStatsAction');
$app->get('/{kennel_abbreviation}/analversaries/stats', 'HashController:analversariesStatsAction');
$app->get('/{kennel_abbreviation}/year_by_year/stats', 'HashController:yearByYearStatsAction');
$app->get('/{kennel_abbreviation}/kennel/records', 'HashController:kennelRecordsStatsAction');
$app->get('/{kennel_abbreviation}/kennel/general_info', 'HashController:kennelGeneralInfoStatsAction');

#URLs for fastest/slowest to reach analversaries
$app->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/bydays', 'ObscureStatisticsController:quickestToReachAnalversaryByDaysAction');
$app->get('/{kennel_abbreviation}/{analversary_number}/slowest/to/reach/bydays',  'ObscureStatisticsController:slowestToReachAnalversaryByDaysAction');
$app->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/date', 'ObscureStatisticsController:quickestToReachAnalversaryByDate');

$app->get('/{kennel_abbreviation}/longest/career','ObscureStatisticsController:longestCareerAction');
$app->get('/{kennel_abbreviation}/highest/averageDaysBetweenHashes','ObscureStatisticsController:highestAverageDaysBetweenHashesAction');
$app->get('/{kennel_abbreviation}/lowest/averageDaysBetweenHashes','ObscureStatisticsController:lowestAverageDaysBetweenHashesAction');
$app->get('/{kennel_abbreviation}/everyones/latest/hashes/{min_hash_count}','ObscureStatisticsController:everyonesLatestHashesAction');
$app->get('/{kennel_abbreviation}/everyones/first/hashes/{min_hash_count}','ObscureStatisticsController:everyonesFirstHashesAction');

$app->get('/{kennel_abbreviation}/highest/allharings/averageDaysBetweenHarings','ObscureStatisticsController:highestAverageDaysBetweenAllHaringsAction');
$app->get('/{kennel_abbreviation}/lowest/allharings/averageDaysBetweenHarings','ObscureStatisticsController:lowestAverageDaysBetweenAllHaringsAction');
$app->get('/{kennel_abbreviation}/highest/{hare_type}/averageDaysBetweenHarings','ObscureStatisticsController:highestAverageDaysBetweenHaringsAction');
$app->get('/{kennel_abbreviation}/lowest/{hare_type}/averageDaysBetweenHarings','ObscureStatisticsController:lowestAverageDaysBetweenHaringsAction');

$app->get('/{kennel_abbreviation}/highest/attendedHashes','HashController:highestAttendedHashesAction');
$app->get('/{kennel_abbreviation}/lowest/attendedHashes','HashController:lowestAttendedHashesAction');

$app->get('/{kennel_abbreviation}/hashers/of/the/years','HashController:hashersOfTheYearsAction');
$app->get('/{kennel_abbreviation}/hares/{hare_type}/of/the/years','HashController:HaresOfTheYearsAction');

#Establish the mortal kombat head to head matchup functionality
$app->get('/{kennel_abbreviation}/hashers/twoHasherComparison',            'HashController:twoPersonComparisonPreAction');
$app->get('/{kennel_abbreviation}/hashers/comparison/{hasher_id}/{hasher_id2}/',     'HashController:twoPersonComparisonAction');
$app->post('/{kennel_abbreviation}/hashers/retrieve',                         'HashPersonController:retrieveHasherAction');

# kennel home page
$app->get('/{kennel_abbreviation}',                               'HashController:slashKennelAction2');

$app->run();
