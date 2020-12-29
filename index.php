<?php

// web/index.php
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/HASH/Controller/DatabaseUpdater.php';
require_once __DIR__.'/HASH/Controller/HashController.php';
require_once __DIR__.'/HASH/Controller/TagController.php';
require_once __DIR__.'/HASH/Controller/HashEventController.php';
require_once __DIR__.'/HASH/Controller/HashPersonController.php';
require_once __DIR__.'/HASH/Controller/AdminController.php';
require_once __DIR__.'/config/ProdConfig.php';
require_once __DIR__.'/vendor/twig/twig/lib/Twig/Autoloader.php';
require_once __DIR__.'/HASH/Controller/SuperAdminController.php';
require_once __DIR__.'/HASH/Controller/ObscureStatisticsController.php';
//test comment


require_once './HASH/UserProvider.php';

use Doctrine\DBAL\Schema\Table;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\FormServiceProvider;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

$app = new Silex\Application();

#TWIG Constants
$twigClassPath = __DIR__.'vendor/twig/twig/lib';
$twigTemplateSourceDirectory = __DIR__.'/Twig_Templates/source';
$twigTemplateCompiledDirectory = __DIR__.'/Twig_Templates/compiled';
# End TWIG Configurations-------------------------------------------------------

#Registers a database connection -----------------------------------------------
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
  'dbs.options' => array(
    'mysql_read' => array(
      'driver'   	=> DB_DRIVER,
      'dbname'	=> DB_NAME,
      'host'		=> DB_HOST,
      'port'		=> DB_PORT,
      'user'		=> DB_READ_ONLY_USER,
      'password'	=> DB_READ_ONLY_PASSWORD
    ),
    'mysql_write' => array(
      'driver'   	=> DB_DRIVER,
    	'dbname'	=> DB_NAME,
    	'host'		=> DB_HOST,
    	'port'		=> DB_PORT,
    	'user'		=> DB_USER,
    	'password'	=> DB_PASSWORD
    ))));


#Create users table in database-------------------------------------------------
$schema = $app['dbs']['mysql_write']->getSchemaManager();
if (!$schema->tablesExist('USERS')) {
    $users = new Table('USERS');
    $users->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
    $users->setPrimaryKey(array('id'));
    $users->addColumn('username', 'string', array('length' => 32));
    $users->addUniqueIndex(array('username'));
    $users->addColumn('password', 'string', array('length' => 255));
    $users->addColumn('roles', 'string', array('length' => 255));

    $schema->createTable($users);

    $app['dbs']['mysql_write']->insert('USERS', array(
      'username' => 'fabien',
      'password' => DEFAULT_USER_PASSWORD,
      'roles' => 'ROLE_USER'
    ));

    $app['dbs']['mysql_write']->insert('USERS', array(
      'username' => 'admin',
      'password' => DEFAULT_USER_PASSWORD,
      'roles' => 'ROLE_ADMIN'
    ));

    $app['dbs']['mysql_write']->insert('USERS', array(
      'username' => 'superadmin',
      'password' => DEFAULT_USER_PASSWORD,
      'roles' => 'ROLE_SUPERADMIN'
    ));

}


#-------------------------------------------------------------------------------





/*
#Is something goes wrong, show them these messages....
$app->error(function (Exception $exception, $code){
  switch($code){
    case 404:
      $message = "$code: The page is not there";
      break;
    default:
      $message = "$code: Something weird happened";
  }

  return $message;
});
*/


# Begin: Register the session management stuff ---------------------------------
$app->register(new Silex\Provider\SessionServiceProvider());
# End: -------------------------------------------------------------------------


# Begin: Set the security firewalls --------------------------------------------

$app['debug'] = true;


$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'login' => array(
            'pattern' => '^/logonscreen$',
        ),
        'supersecured' => array(
            'pattern' => '^/superadmin',
            'form' => array('login_path' => '/logonscreen/sa', 'check_path' => '/superadmin/login_check'),
            'logout' => array('logout_path' => '/superadmin/logoutaction'),
            'users' => function () use ($app) {return new UserProvider($app['db']);},
            'logout' => array('logout_path' => '/superadmin/logoutaction', 'invalidate_session' => true),
          ),
        'secured' => array(
            'pattern' => '^/admin',
            'form' => array('login_path' => '/logonscreen', 'check_path' => '/admin/login_check'),
            'logout' => array('logout_path' => '/logoutaction'),
            'users' => function () use ($app) {return new UserProvider($app['db']);},
            'logout' => array('logout_path' => '/admin/logoutaction', 'invalidate_session' => true),
        ),
        #'secured' => array(
        #    'pattern' => '^/superadmin|/admin',
        #    'form' => array('login_path' => '/logonscreen', 'check_path' => '/admin/login_check'),
        #    'logout' => array('logout_path' => '/logoutaction'),
        #    'users' => function () use ($app) {return new UserProvider($app['db']);},
        #    'logout' => array('logout_path' => '/admin/logoutaction', 'invalidate_session' => true),
        #),
        'unsecured' => array(
          'pattern' => '^.*$',
        )
    )
));

// Fallback to default password encoder used in SILEX 1.3
$app['security.default_encoder'] = $app['security.encoder.digest'];

$app['security.access_rules'] = array(
    array('^/superadmin',   'ROLE_SUPERADMIN',),
    array('^/admin',        'ROLE_ADMIN',),
);


$app->register(new Silex\Provider\RoutingServiceProvider());
$app->register(new FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array('translator.messages' => array(),));
#-------------------------------------------------------------------------------


#Set your global assertions and stuff ------------------------------------------
$kennelAssertion = "^(".VALID_KENNEL_ABBREVIATIONS.")$";
$app['controllers']
  ->assert("hash_id", "\d+")
  ->assert("hasher_id", "\d+")
  ->assert("hasher_id2", "\d+")
  ->assert("hare_id", "\d+")
  ->assert("event_tag_ky", "\d+")
  ->assert("year_value", "\d+")
  ->assert("kennel_id","\d+")
  ->assert("day_count","\d+")
  ->assert("month_count","\d+")
  ->assert("min_hash_count","\d+")
  ->assert("max_percentage","\d+")
  ->assert("analversary_number","\d+")
  ->assert("row_limit","\d+")
  ->assert("kennel_abbreviation",$kennelAssertion)
  ;
#-------------------------------------------------------------------------------

# Not sure if this should be used here
$app->boot();


$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => $twigTemplateSourceDirectory,
  'twig.class_path' =>$twigClassPath,
  'twig.options' => array(
    'cache' => $twigTemplateCompiledDirectory,
    'auto_reload' => true)));

# Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
    'monolog.level' => 'debug',
    'monolog.bubble' => true
));


# End: -------------------------------------------------------------------------





# Register the URls
$app->get('/',                                                    'HASH\Controller\HashController::slashAction')->bind('homepage');
$app->get('/{kennel_abbreviation}',                               'HASH\Controller\HashController::slashKennelAction2');


#Admin section logon
$app->get('/logonscreen',                                         'HASH\Controller\HashController::logonScreenAction');
$app->get('/admin/logoutaction',                                  'HASH\Controller\AdminController::logoutAction');
$app->get('/admin/hello',                                         'HASH\Controller\AdminController::helloAction');

#Superadmin section logon
$app->get('/logonscreen/sa',                                      'HASH\Controller\SuperAdminController::logonScreenAction');
$app->get('/superadmin/logoutaction',                             'HASH\Controller\SuperAdminController::logoutAction');
$app->get('/superadmin/hello',                                    'HASH\Controller\SuperAdminController::helloAction');

$app->get('/user/hello',                                          'HASH\Controller\AdminController::userHelloAction');

$app->get('/{kennel_abbreviation}/mia',                                       'HASH\Controller\HashController::miaPreActionJson');
$app->post('/{kennel_abbreviation}/mia',                                       'HASH\Controller\HashController::miaPostActionJson');

$app->get('/{kennel_abbreviation}/listhashers2',                                       'HASH\Controller\HashController::listHashersPreActionJson');
$app->post('/{kennel_abbreviation}/listhashers2',                                       'HASH\Controller\HashController::getHasherListJson');

$app->get('/{kennel_abbreviation}/listvirginharings',                                       'HASH\Controller\HashController::listVirginHaringsPreActionJson');
$app->post('/{kennel_abbreviation}/listvirginharings',                                       'HASH\Controller\HashController::getVirginHaringsListJson');

$app->get('/{kennel_abbreviation}/attendancePercentages',                                'HASH\Controller\HashController::attendancePercentagesPreActionJson');
$app->post('/{kennel_abbreviation}/attendancePercentages',                               'HASH\Controller\HashController::attendancePercentagesPostActionJson');

$app->get('/{kennel_abbreviation}/trueCohareCounts',                                     'HASH\Controller\HashController::trueCohareCountsPreActionJson');
$app->get('/{kennel_abbreviation}/hyperCohareCounts',                                    'HASH\Controller\HashController::hyperCohareCountsPreActionJson');
$app->get('/{kennel_abbreviation}/allCohareCounts',                                      'HASH\Controller\HashController::allCohareCountsPreActionJson');
$app->post('/{kennel_abbreviation}/cohareCounts',                                        'HASH\Controller\HashController::getCohareCountsJson');

$app->get('/{kennel_abbreviation}/locationCounts',                                       'HASH\Controller\HashController::listLocationCountsPreActionJson');
$app->post('/{kennel_abbreviation}/locationCounts',                                      'HASH\Controller\HashController::getLocationCountsJson');

$app->get('/{kennel_abbreviation}/listhashes2',                                         'HASH\Controller\HashEventController::listHashesPreActionJson');
$app->post('/{kennel_abbreviation}/listhashes2',                                        'HASH\Controller\HashEventController::listHashesPostActionJson');

$app->get('/{kennel_abbreviation}/eventsHeatMap',                                        'HASH\Controller\ObscureStatisticsController::kennelEventsHeatMap');
$app->get('/{kennel_abbreviation}/eventsClusterMap',                                        'HASH\Controller\ObscureStatisticsController::kennelEventsClusterMap');
$app->get('/{kennel_abbreviation}/eventsMarkerMap',                                        'HASH\Controller\ObscureStatisticsController::kennelEventsMarkerMap');

$app->get('/{kennel_abbreviation}/listStreakers/byhash/{hash_id}',              'HASH\Controller\HashController::listStreakersByHashAction');

$app->get('/{kennel_abbreviation}/attendanceRecordForHasher/{hasher_id}',        'HASH\Controller\HashController::attendanceRecordForHasherAction');

$app->get('/{kennel_abbreviation}/listhashers/byhash/{hash_id}',                        'HASH\Controller\HashController::listHashersByHashAction');
$app->get('/{kennel_abbreviation}/listhares/byhash/{hash_id}',                          'HASH\Controller\HashController::listHaresByHashAction');
$app->get('/{kennel_abbreviation}/listhashes/byhasher/{hasher_id}',                     'HASH\Controller\HashController::listHashesByHasherAction');
$app->get('/{kennel_abbreviation}/listhashes/byhare/{hasher_id}',                       'HASH\Controller\HashController::listHashesByHareAction');
$app->get('/{kennel_abbreviation}/hashers/{hasher_id}',                                 'HASH\Controller\HashController::viewHasherChartsAction');
$app->get('/{kennel_abbreviation}/hashedWith/{hasher_id}',                                 'HASH\Controller\HashController::hashedWithAction');

$app->get('/{kennel_abbreviation}/hares/overall/{hasher_id}',     'HASH\Controller\HashController::viewOverallHareChartsAction');
$app->get('/{kennel_abbreviation}/hares/true/{hasher_id}',        'HASH\Controller\HashController::viewTrueHareChartsAction');
$app->get('/{kennel_abbreviation}/hares/hyper/{hasher_id}',       'HASH\Controller\HashController::viewHyperHareChartsAction');


$app->get('/{kennel_abbreviation}/chartsAndDetails',                                 'HASH\Controller\ObscureStatisticsController::viewKennelChartsAction');


$app->get('/{kennel_abbreviation}/attendanceStatistics',                                'HASH\Controller\ObscureStatisticsController::viewAttendanceChartsAction');

#First timers / last timers
$app->get('/{kennel_abbreviation}/firstTimersStatistics/{min_hash_count}',              'HASH\Controller\ObscureStatisticsController::viewFirstTimersChartsAction');
$app->get('/{kennel_abbreviation}/lastTimersStatistics/{min_hash_count}/{month_count}', 'HASH\Controller\ObscureStatisticsController::viewLastTimersChartsAction');

#Virgin harings charts
$app->get('/{kennel_abbreviation}/virginHaringsStatistics',              'HASH\Controller\ObscureStatisticsController::virginHaringsChartsAction');

#Distinct Hasher hashings charts
$app->get('/{kennel_abbreviation}/distinctHasherStatistics',              'HASH\Controller\ObscureStatisticsController::distinctHasherChartsAction');

#Distinct True Hares charts
$app->get('/{kennel_abbreviation}/distinctTrueHareStatistics',              'HASH\Controller\ObscureStatisticsController::distinctTrueHaresChartsAction');
#Distinct Hyper Hares charts
$app->get('/{kennel_abbreviation}/distinctHyperHareStatistics',              'HASH\Controller\ObscureStatisticsController::distinctHyperHaresChartsAction');

$app->get('/{kennel_abbreviation}/hashes/{hash_id}',                                    'HASH\Controller\HashController::viewHashAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEvent/{hash_id}',               'HASH\Controller\HashController::hasherCountsForEventAction');

$app->get('/{kennel_abbreviation}/omniAnalversariesForEvent/{hash_id}',               'HASH\Controller\HashController::omniAnalversariesForEventAction');

$app->get('/{kennel_abbreviation}/hasherCountsForEventCounty/{hash_id}',               'HASH\Controller\HashController::hasherCountsForEventCountyAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventPostalCode/{hash_id}',               'HASH\Controller\HashController::hasherCountsForEventPostalCodeAction');

$app->get('/{kennel_abbreviation}/hasherCountsForEventState/{hash_id}',            'HASH\Controller\HashController::hasherCountsForEventStateAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventCity/{hash_id}',             'HASH\Controller\HashController::hasherCountsForEventCityAction');
$app->get('/{kennel_abbreviation}/hasherCountsForEventNeighborhood/{hash_id}',     'HASH\Controller\HashController::hasherCountsForEventNeighborhoodAction');

$app->get('/{kennel_abbreviation}/backSlidersForEventV2/{hash_id}',                     'HASH\Controller\HashController::backSlidersForEventV2Action');

$app->get('/{kennel_abbreviation}/consolidatedEventAnalversaries/{hash_id}',            'HASH\Controller\HashController::consolidatedEventAnalversariesAction');

$app->get('/{kennel_abbreviation}/trendingHashers/{day_count}',                         'HASH\Controller\ObscureStatisticsController::trendingHashersAction');
$app->get('/{kennel_abbreviation}/trendingTrueHares/{day_count}',                       'HASH\Controller\ObscureStatisticsController::trendingTrueHaresAction');

#Ajax version of untrending hares graphs
$app->get('/{kennel_abbreviation}/unTrendingTrueHaresJsonPre/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'HASH\Controller\ObscureStatisticsController::unTrendingTrueHaresJsonPreAction');
$app->get('/{kennel_abbreviation}/unTrendingTrueHaresJsonPost/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',                       'HASH\Controller\ObscureStatisticsController::unTrendingTrueHaresJsonPostAction');



$app->get('/{kennel_abbreviation}/pendingHasherAnalversaries',                          'HASH\Controller\HashController::pendingHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/predictedHasherAnalversaries',                        'HASH\Controller\HashController::predictedHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/predictedCenturions',                                 'HASH\Controller\HashController::predictedCenturionsAction');
$app->get('/{kennel_abbreviation}/pendingHareAnalversaries',                            'HASH\Controller\HashController::pendingHareAnalversariesAction');
$app->get('/{kennel_abbreviation}/haringPercentageAllHashes',                           'HASH\Controller\HashController::haringPercentageAllHashesAction');
$app->get('/{kennel_abbreviation}/haringPercentageNonHypers',                           'HASH\Controller\HashController::haringPercentageNonHypersAction');
$app->get('/{kennel_abbreviation}/hashingCounts',                                       'HASH\Controller\HashController::hashingCountsAction');
$app->get('/{kennel_abbreviation}/haringCounts',                                        'HASH\Controller\HashController::haringCountsAction');
$app->get('/{kennel_abbreviation}/nonHyperharingCounts',                                'HASH\Controller\HashController::trueHaringCountsAction');
$app->get('/{kennel_abbreviation}/hyperharingCounts',                                   'HASH\Controller\HashController::hyperHaringCountsAction');
$app->get('/{kennel_abbreviation}/coharelist/byhare/allhashes/{hasher_id}',             'HASH\Controller\HashController::coharelistByHareAllHashesAction');
$app->get('/{kennel_abbreviation}/coharelist/byhare/nonhypers/{hasher_id}',             'HASH\Controller\HashController::coharelistByHareNonHypersAction');
$app->get('/{kennel_abbreviation}/coharecount/byhare/allhashes/{hasher_id}',            'HASH\Controller\HashController::cohareCountByHareAllHashesAction');
$app->get('/{kennel_abbreviation}/coharecount/byhare/nonhypers/{hasher_id}',            'HASH\Controller\HashController::cohareCountByHareNonHypersAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/lowest',                        'HASH\Controller\HashController::hashAttendanceByHareLowestAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/highest',                       'HASH\Controller\HashController::hashAttendanceByHareHighestAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/average',                       'HASH\Controller\HashController::hashAttendanceByHareAverageAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/nondistincthashers', 'HASH\Controller\HashController::hashAttendanceByHareGrandTotalNonDistinctHashersAction');
$app->get('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/distincthashers',    'HASH\Controller\HashController::hashAttendanceByHareGrandTotalDistinctHashersAction');
$app->get('/{kennel_abbreviation}/getHasherCountsByHare/{hare_id}',                     'HASH\Controller\HashController::hasherCountsByHareAction');
$app->get('/{kennel_abbreviation}/percentages/percentageofharingsthatwerehypers',       'HASH\Controller\HashController::percentageHaringsHypersVsNonHypers');
$app->get('/{kennel_abbreviation}/getHasherAnalversaries/{hasher_id}',                  'HASH\Controller\HashController::getHasherAnalversariesAction');
$app->get('/{kennel_abbreviation}/getProjectedHasherAnalversaries/{hasher_id}',         'HASH\Controller\HashController::getProjectedHasherAnalversariesAction');


$app->get('/{kennel_abbreviation}/longestStreaks',                                      'HASH\Controller\ObscureStatisticsController::getLongestStreaksAction');
$app->get('/{kennel_abbreviation}/aboutContact',                                        'HASH\Controller\ObscureStatisticsController::aboutContactAction');

# Hash name (substring) analysis
$app->get('/{kennel_abbreviation}/hasherNameAnalysis',            'HASH\Controller\ObscureStatisticsController::hasherNameAnalysisAction');
$app->get('/{kennel_abbreviation}/hasherNameAnalysis2',            'HASH\Controller\ObscureStatisticsController::hasherNameAnalysisAction2');
$app->get('/{kennel_abbreviation}/hasherNameAnalysisWordCloud',            'HASH\Controller\ObscureStatisticsController::hasherNameAnalysisWordCloudAction');

# Hash event creation (ajaxified)
$app->get('/admin/newhash/ajaxform', 'HASH\Controller\HashEventController::adminCreateHashAjaxPreAction');
$app->post('/admin/newhash/ajaxform', 'HASH\Controller\HashEventController::adminCreateHashAjaxPostAction');

# Hash event modification (ajaxified)
$app->get('/admin/edithash/ajaxform/{hash_id}', 'HASH\Controller\HashEventController::adminModifyHashAjaxPreAction');
$app->post('/admin/edithash/ajaxform/{hash_id}', 'HASH\Controller\HashEventController::adminModifyHashAjaxPostAction');


# Hash person modification
$app->get('/admin/modifyhasher/form/{hasher_id}',                 'HASH\Controller\HashPersonController::modifyHashPersonAction');
$app->post('/admin/modifyhasher/form/{hasher_id}',                'HASH\Controller\HashPersonController::modifyHashPersonAction');

# Hash person deletion
$app->get('/admin/deleteHasher/{hasher_id}',                      'HASH\Controller\HashPersonController::deleteHashPersonPreAction');
$app->post('/admin/deleteHasherPost',                      'HASH\Controller\HashPersonController::deleteHashPersonAjaxAction');

# Hash person creation
$app->get('/admin/newhasher/form',                                'HASH\Controller\HashPersonController::createHashPersonAction');
$app->post('/admin/newhasher/form',                               'HASH\Controller\HashPersonController::createHashPersonAction');

# Change admin password
#XXXXXXX
$app->get('/admin/newPassword/form',                                'HASH\Controller\AdminController::newPasswordAction');
$app->post('/admin/newPassword/form',                               'HASH\Controller\AdminController::newPasswordAction');

# View audit records
$app->get('/admin/viewAuditRecords',                                  'HASH\Controller\AdminController::viewAuditRecordsPreActionJson');
$app->post('/admin/viewAuditRecords',                                 'HASH\Controller\AdminController::viewAuditRecordsJson');


# View the jumbo counts table
$app->get('/{kennel_abbreviation}/jumboCountsTable',                 'HASH\Controller\HashController::jumboCountsTablePreActionJson');
$app->post('/{kennel_abbreviation}/jumboCountsTable',                'HASH\Controller\HashController::jumboCountsTablePostActionJson');

# View the jumbo percentages table
$app->get('/{kennel_abbreviation}/jumboPercentagesTable',                 'HASH\Controller\HashController::jumboPercentagesTablePreActionJson');
$app->post('/{kennel_abbreviation}/jumboPercentagesTable',                'HASH\Controller\HashController::jumboPercentagesTablePostActionJson');


# Modify the participation for an event
#$app->get('/admin/event/manageparticipation/{hash_id}',            'HASH\Controller\HashEventController::hashParticipationAction');

# Modify the participation for an event
$app->get('/admin/event/manageparticipation2/{hash_id}',            'HASH\Controller\HashEventController::hashParticipationJsonPreAction');
$app->post('/admin/event/manageparticipation2/{hash_id}',           'HASH\Controller\HashEventController::hashParticipationJsonPostAction');

# Page to manage the event tags
$app->get('/admin/tags/manageeventtags',                            'HASH\Controller\TagController::manageEventTagsPreAction');
$app->get('/admin/tags/geteventtagswithcounts',                     'HASH\Controller\TagController::getEventTagsWithCountsJsonAction');
$app->get('/admin/tags/getalleventtags',                            'HASH\Controller\TagController::getAllEventTagsJsonAction');
$app->get('/admin/tags/getmatchingeventtags',                       'HASH\Controller\TagController::getMatchingEventTagsJsonAction');
#$app->post('/admin/tags/manageeventtags',                           'HASH\Controller\TagController::manageEventTagsJsonPostAction');
$app->post('/admin/tags/addneweventtag',                            'HASH\Controller\TagController::addNewEventTag');

# Add or remove tags to events
$app->post('/admin/tags/addtagtoevent',                             'HASH\Controller\TagController::addTagToEventJsonAction');
$app->post('/admin/tags/removetagfromevent',                        'HASH\Controller\TagController::removeTagFromEventJsonAction');
$app->get('/admin/tags/eventscreen/{hash_id}',                      'HASH\Controller\TagController::showEventForTaggingPreAction');

#Show events by event tag
$app->get('/{kennel_abbreviation}/listhashes/byeventtag/{event_tag_ky}', 'HASH\Controller\TagController::listHashesByEventTagAction');
$app->get('/{kennel_abbreviation}/chartsGraphs/byeventtag/{event_tag_ky}', 'HASH\Controller\TagController::chartsGraphsByEventTagAction');


# Functions to add and delete hounds and hares to the hashes
$app->post('/admin/event/addHasherToHash',                         'HASH\Controller\HashEventController::addHashParticipant');
$app->post('/admin/event/addHareToHash',                           'HASH\Controller\HashEventController::addHashOrganizer');
$app->post('/admin/event/deleteHasherFromHash',                    'HASH\Controller\HashEventController::deleteHashParticipant');
$app->post('/admin/event/deleteHareFromHash',                      'HASH\Controller\HashEventController::deleteHashOrganizer');

$app->post('/admin/event/getHaresForEvent',                        'HASH\Controller\HashEventController::getHaresForEvent');
$app->post('/admin/event/getHashersForEvent',                      'HASH\Controller\HashEventController::getHashersForEvent');

$app->get('/admin/listOrphanedHashers',                             'HASH\Controller\AdminController::listOrphanedHashersAction');

$app->get('/admin/listhashes2',                                    'HASH\Controller\AdminController::listHashesPreActionJson');
$app->post('/admin/listhashes2',                                   'HASH\Controller\AdminController::getHashListJson');

$app->get('/admin/listhashers2',                                    'HASH\Controller\AdminController::listHashersPreActionJson');
$app->post('/admin/listhashers2',                                   'HASH\Controller\AdminController::getHashersListJson');

$app->get('/admin/hasherDetailsKennelSelection/{hasher_id}',        'HASH\Controller\AdminController::hasherDetailsKennelSelection');

$app->get('/admin/d3test','HASH\Controller\AdminController::d3testAction');

#The per event budget screen
$app->get('/admin/eventBudget/{hash_id}','HASH\Controller\AdminController::eventBudgetPreAction');

# Functions for the "by year" statistics
$app->get('/{kennel_abbreviation}/statistics/getYearInReview/{year_value}',               'HASH\Controller\ObscureStatisticsController::getYearInReviewAction');
$app->post('/{kennel_abbreviation}/statistics/getHasherCountsByYear',                     'HASH\Controller\ObscureStatisticsController::getHasherCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getTotalHareCountsByYear',                  'HASH\Controller\ObscureStatisticsController::getTotalHareCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getHyperHareCountsByYear',                  'HASH\Controller\ObscureStatisticsController::getHyperHareCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getNonHyperHareCountsByYear',               'HASH\Controller\ObscureStatisticsController::getNonHyperHareCountsByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieHasherListByYear',                 'HASH\Controller\ObscureStatisticsController::getNewbieHasherListByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieTrueHareListByYear',               'HASH\Controller\ObscureStatisticsController::getNewbieTrueHareListByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieHyperHareListByYear',              'HASH\Controller\ObscureStatisticsController::getNewbieHyperHareListByYear');
$app->post('/{kennel_abbreviation}/statistics/getNewbieOverallHareListByYear',            'HASH\Controller\ObscureStatisticsController::getNewbieOverallHareListByYear');



# Mappings for hasher specific statistics
$app->post('/{kennel_abbreviation}/statistics/hasher/firstHash',                           'HASH\Controller\ObscureStatisticsController::getHashersVirginHash');
$app->post('/{kennel_abbreviation}/statistics/hasher/mostRecentHash',                      'HASH\Controller\ObscureStatisticsController::getHashersLatestHash');

# Mappings for kennel specific statistics
$app->post('/{kennel_abbreviation}/statistics/kennel/firstHash',                           'HASH\Controller\ObscureStatisticsController::getKennelsVirginHash');
$app->post('/{kennel_abbreviation}/statistics/kennel/mostRecentHash',                      'HASH\Controller\ObscureStatisticsController::getKennelsLatestHash');

# Mappings for hasher hashes by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/year',                      'HASH\Controller\ObscureStatisticsController::getHasherHashesByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/quarter',                   'HASH\Controller\ObscureStatisticsController::getHasherHashesByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/month',                     'HASH\Controller\ObscureStatisticsController::getHasherHashesByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/dayname',                   'HASH\Controller\ObscureStatisticsController::getHasherHashesByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/state',                     'HASH\Controller\ObscureStatisticsController::getHasherHashesByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/hashes/by/city',                      'HASH\Controller\ObscureStatisticsController::getHasherHashesByCity');

# Mappings for kennel hashes by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/city',                      'HASH\Controller\ObscureStatisticsController::getKennelHashesByCity');
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/county',                      'HASH\Controller\ObscureStatisticsController::getKennelHashesByCounty');
$app->post('/{kennel_abbreviation}/statistics/kennel/hashes/by/postalcode',                      'HASH\Controller\ObscureStatisticsController::getKennelHashesByPostalcode');

# Mappings for hasher harings by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/year',                      'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/quarter',                   'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/month',                     'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/dayname',                   'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/state',                     'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/all/harings/by/city',                      'HASH\Controller\ObscureStatisticsController::getHasherAllHaringsByCity');

# Mappings for hasher (non hyper) harings by (year/month/state/etc)
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/year',                      'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByYear');
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/quarter',                   'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByQuarter');
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/month',                     'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByMonth');
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/dayname',                   'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByDayName');
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/state',                     'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByState');
$app->post('/{kennel_abbreviation}/statistics/hasher/nonhyper/harings/by/city',                      'HASH\Controller\ObscureStatisticsController::getHasherNonHyperHaringsByCity');

# Mappings for hasher (hyper only) haring by year/month/state
$app->post('/{kennel_abbreviation}/statistics/hasher/hyper/harings/by/city',                      'HASH\Controller\ObscureStatisticsController::getHasherHyperHaringsByCity');

# Per person stats (more of them)
$app->post('/{kennel_abbreviation}/coharecount/byhare/nonhypers','HASH\Controller\ObscureStatisticsController::getCohareCountByHareNonHypers');
$app->post('/{kennel_abbreviation}/coharecount/byhare/onlyhypers','HASH\Controller\ObscureStatisticsController::getCohareCountByHareOnlyHypers');
$app->post('/{kennel_abbreviation}/coharecount/byhare/allhashes','HASH\Controller\ObscureStatisticsController::getCohareCountByHareAllHashes');


$app->get('/{kennel_abbreviation}/basic/stats',         'HASH\Controller\HashController::basicStatsAction');
$app->get('/{kennel_abbreviation}/hashing/stats',       'HASH\Controller\HashController::hashingStatsAction');
$app->get('/{kennel_abbreviation}/haring/stats',        'HASH\Controller\HashController::haringStatsAction');
$app->get('/{kennel_abbreviation}/analversary/stats',   'HASH\Controller\HashController::analversaryStatsAction');
$app->get('/{kennel_abbreviation}/cautionary/stats',    'HASH\Controller\HashController::cautionaryStatsAction');
$app->get('/{kennel_abbreviation}/miscellaneous/stats', 'HASH\Controller\HashController::miscellaneousStatsAction');

#Revised top level pages
$app->get('/{kennel_abbreviation}/people/stats', 'HASH\Controller\HashController::peopleStatsAction');
$app->get('/{kennel_abbreviation}/analversaries/stats', 'HASH\Controller\HashController::analversariesStatsAction');
$app->get('/{kennel_abbreviation}/year_by_year/stats', 'HASH\Controller\HashController::yearByYearStatsAction');
$app->get('/{kennel_abbreviation}/kennel/records', 'HASH\Controller\HashController::kennelRecordsStatsAction');
$app->get('/{kennel_abbreviation}/kennel/general_info', 'HASH\Controller\HashController::kennelGeneralInfoStatsAction');

#Wordcloud test
$app->get('/{kennel_abbreviation}/wordcloudtest', 'HASH\Controller\ObscureStatisticsController::wordcloudTestAction');

#Google geocode call test
$app->get('/{kennel_abbreviation}/googlegeocodetest', 'HASH\Controller\ObscureStatisticsController::googleGeoCodeTestAction');

#URLs for fastest/slowest to reach analversaries
$app->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/bydays', 'HASH\Controller\ObscureStatisticsController::quickestToReachAnalversaryByDaysAction');
$app->get('/{kennel_abbreviation}/{analversary_number}/slowest/to/reach/bydays',  'HASH\Controller\ObscureStatisticsController::slowestToReachAnalversaryByDaysAction');
$app->get('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/date', 'HASH\Controller\ObscureStatisticsController::quickestToReachAnalversaryByDate');

$app->get('/{kennel_abbreviation}/longest/career','HASH\Controller\ObscureStatisticsController::longestCareerAction');
$app->get('/{kennel_abbreviation}/highest/averageDaysBetweenHashes','HASH\Controller\ObscureStatisticsController::highestAverageDaysBetweenHashesAction');
$app->get('/{kennel_abbreviation}/lowest/averageDaysBetweenHashes','HASH\Controller\ObscureStatisticsController::lowestAverageDaysBetweenHashesAction');
$app->get('/{kennel_abbreviation}/everyones/latest/hashes/{min_hash_count}','HASH\Controller\ObscureStatisticsController::everyonesLatestHashesAction');
$app->get('/{kennel_abbreviation}/everyones/first/hashes/{min_hash_count}','HASH\Controller\ObscureStatisticsController::everyonesFirstHashesAction');

$app->get('/{kennel_abbreviation}/highest/allharings/averageDaysBetweenHarings','HASH\Controller\ObscureStatisticsController::highestAverageDaysBetweenAllHaringsAction');
$app->get('/{kennel_abbreviation}/lowest/allharings/averageDaysBetweenHarings','HASH\Controller\ObscureStatisticsController::lowestAverageDaysBetweenAllHaringsAction');
$app->get('/{kennel_abbreviation}/highest/nonhyperharings/averageDaysBetweenHarings','HASH\Controller\ObscureStatisticsController::highestAverageDaysBetweenNonHyperHaringsAction');
$app->get('/{kennel_abbreviation}/lowest/nonhyperharings/averageDaysBetweenHarings','HASH\Controller\ObscureStatisticsController::lowestAverageDaysBetweenNonHyperHaringsAction');

$app->get('/{kennel_abbreviation}/highest/attendedHashes','HASH\Controller\HashController::highestAttendedHashesAction');
$app->get('/{kennel_abbreviation}/lowest/attendedHashes','HASH\Controller\HashController::lowestAttendedHashesAction');

$app->get('/{kennel_abbreviation}/hashers/of/the/years','HASH\Controller\HashController::hashersOfTheYearsAction');
$app->get('/{kennel_abbreviation}/hares/overall/of/the/years','HASH\Controller\HashController::overallHaresOfTheYearsAction');
$app->get('/{kennel_abbreviation}/hares/nonhyper/of/the/years','HASH\Controller\HashController::nonHyperHaresOfTheYearsAction');

#Establish the mortal kombat head to head matchup functionality
$app->get('/{kennel_abbreviation}/hashers/twoHasherComparison',            'HASH\Controller\HashController::twoPersonComparisonPreAction');
$app->get('/{kennel_abbreviation}/hashers/comparison/{hasher_id}/{hasher_id2}/',     'HASH\Controller\HashController::twoPersonComparisonAction');
$app->post('/{kennel_abbreviation}/hashers/retrieve',                         'HASH\Controller\HashPersonController::retrieveHasherAction');

#Do magic on the json traffic
$app->before(function (Request $request, Application $app) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
    new DatabaseUpdater($app, DB_NAME);
});

$app->after(function (Request $request, Response $response) {
   $response->headers->set('X-Frame-Options', 'DENY');
   $response->headers->set('X-Content-Type-Options', 'nosniff');
   $response->headers->set('X-XSS-Protection','1; mode=block');
   $response->headers->set('x-frame-options','SAMEORIGIN');
});


/*$app['security.access_rules'] = array(
    array('^/superadmin',   'ROLE_SUPERADMIN',),
    array('^/admin',        'ROLE_ADMIN',),
);
*/

$app->run();
