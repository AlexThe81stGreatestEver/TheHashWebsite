<?php

namespace HASH\Controller;

require_once realpath(__DIR__ . '/../..').'/config/SQL_Queries.php';
require_once "BaseController.php";
use Silex\Application;
#use HASH\Utils;
require_once realpath(__DIR__ . '/..').'/Utils/Helper.php';
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use \Datetime;

class HashController extends BaseController
{


  private function obtainKennelKeyFromKennelAbbreviation(Request $request, Application $app, string $kennel_abbreviation){

    #Define the SQL to RuntimeException
    $sql = "SELECT KENNEL_KY FROM KENNELS WHERE KENNEL_ABBREVIATION = ?";

    #Query the database
    $kennelValue = $app['db']->fetchAssoc($sql, array((string) $kennel_abbreviation));

    #Obtain the kennel ky from the returned object
    $returnValue = $kennelValue['KENNEL_KY'];

    #return the return value
    return $returnValue;

  }

  #Define the action
  public function logonScreenAction(Request $request, Application $app){

    #$app['monolog']->addDebug('Entering the logonScreenAction');

    # Establisht the last error
    $lastError = $app['security.last_error']($request);
    #$app['monolog']->addDebug($lastError);

    # Establish the last username
    $lastUserName = $app['session']->get('_security.last_username');
    #$lastUserName = $app['session']->get('_security.last_username');
    #$app['monolog']->addDebug($lastUserName);

    # Establish the return value
    $returnValue =  $app['twig']->render('logon_screen.twig', array (
      'pageTitle' => 'Stats Logon',
      'pageHeader' => 'Please log on!',
      'error' => $lastError,
      'last_username' => $lastUserName,
    ));

    #$app['monolog']->addDebug('Leaving the logonScreenAction');

    # Return the return value;
    return $returnValue;
  }






  public function logoutAction(Request $request, Application $app){

    # Invalidate the session
    $app['session']->invalidate();

    # Redirect the user to the root url
    return $app->redirect('/');

  }

  #Define the action
  public function helloAction(Request $request, Application $app){

      #return $app->redirect('/');
      return $app['twig']->render('admin_landing.twig', array (
        'pageTitle' => 'This is the admin landing screen',
        'subTitle1' => 'This is the admin landing screen'));
  }

  #Define the action
  public function adminHelloAction(Request $request, Application $app){

      #return $app->redirect('/');
      return $app['twig']->render('admin_landing.twig', array (
        'pageTitle' => 'This is the admin hello landing screen (page title)',
        'subTitle1' => 'This is the admin hello landing screen (sub title 1)'));
  }

  #Define the action
  public function slashAction(Request $request, Application $app){

    #Set the return value
    $returnValue = $this->slashKennelAction2($request,$app,DEFAULT_KENNEL_ABBREVIATION);

    #Return the return value
    return $returnValue;

  }

  #Define the action
  public function slashKennelAction(Request $request, Application $app, string $kennel_abbreviation){

    #Establish the page title
    $pageTitle = "$kennel_abbreviation Stats";

    #Set the return value
    $returnValue = $app['twig']->render('slash.twig',array(
      'pageTitle' => $pageTitle,
      'pageCaption' => "Provide page caption",
      'subTitle1' => 'Standard Statistics',
      'subTitle2' => 'Analversary Statistics',
      'subTitle3' => 'Hare Statistics',
      'subTitle4' => 'Other Statistics',
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;

  }

  private function getHareTypeName($app, $hare_type) {
    $sql = "SELECT HARE_TYPE_NAME
              FROM HARE_TYPES
             WHERE HARE_TYPES.HARE_TYPE = ?";

    #Query the database
    $result = $app['db']->fetchAssoc($sql, array((int) $hare_type));

    #return the return value
    return $result['HARE_TYPE_NAME'];
  }

  private function getHareTypes($app, $kennelKy) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HARE_TYPE, HARE_TYPE_NAME, CHART_COLOR
              FROM HARE_TYPES
              JOIN KENNELS
                ON KENNELS.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
             WHERE KENNELS.KENNEL_KY = ?
             ORDER BY SEQ";

    #Query the database
    $hareTypes = $app['db']->fetchAll($sql, array((int) $kennelKy));

    #return the return value
    return $hareTypes;
  }

  private function getHashTypes($app, $kennelKy, $hare_type) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HASH_TYPES.HASH_TYPE, HASH_TYPES.HASH_TYPE_NAME
	      FROM HASH_TYPES
	      JOIN KENNELS
		ON HASH_TYPES.HASH_TYPE & KENNELS.HASH_TYPE_MASK != 0
	     WHERE KENNELS.KENNEL_KY = ?".
	     ($hare_type == 0 ? "" : "AND HASH_TYPES.HARE_TYPE_MASK & ? != 0")."
	     ORDER BY HASH_TYPES.SEQ";

    #Query the database
    $args = array((int) $kennelKy);
    if($hare_type != 0) array_push($args, $hare_type);
    $hashTypes = $app['db']->fetchAll($sql, $args);

    #return the return value
    return $hashTypes;
  }

  #Define the action
  public function slashKennelAction2(Request $request, Application $app, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    $hareTypes = $this->getHareTypes($app, $kennelKy);

    #Establish the page title
    $pageTitle = "$kennel_abbreviation Stats";

    #Get hound counts
    $baseSql = $this->getHashingCountsQuery();
    $sql = "$baseSql  LIMIT 10";

    $baseSql = HARE_TYPE_HARING_COUNTS;
    $sql2 = "$baseSql  LIMIT 10";

    #Get Top (Overall) Hare Counts
    $baseSql4 = HARING_COUNTS;
    $sql4 = "$baseSql4 LIMIT 10";

    $baseSql5 = HASHING_COUNTS_THIS_YEAR;
    $sql5 = "$baseSql5 LIMIT 10";

    $baseSql6 = HASHING_COUNTS_LAST_YEAR;
    $sql6 = "$baseSql6 LIMIT 10";

    $baseSql7 = HARING_COUNTS_THIS_YEAR;
    $sql7 = "$baseSql7 LIMIT 10";

    $baseSql8 = HARING_COUNTS_LAST_YEAR;
    $sql8 = "$baseSql8 LIMIT 10";

    $top_hares = array();

    if(count($hareTypes) > 1) {
      foreach ($hareTypes as &$hareType) {
        $hareResults = $app['db']->fetchAll($sql2, array((int) $hareType['HARE_TYPE'], (int) $kennelKy));
          array_push($top_hares, array('data' => $hareResults,
            'label' => $hareType['HARE_TYPE_NAME'],
            'hare_type' => $hareType['HARE_TYPE']));
      }
    }

    #Execute the SQL statement; create an array of rows
    $topHashersList = $app['db']->fetchAll($sql, array((int) $kennelKy));
    $topOverallHareList = $app['db']->fetchAll($sql4, array((int) $kennelKy));
    $topHashersThisYear = $app['db']->fetchAll($sql5, array((int) $kennelKy));
    $topHashersLastYear = $app['db']->fetchAll($sql6, array((int) $kennelKy));
    $topHaresThisYear = $app['db']->fetchAll($sql7, array((int) $kennelKy));
    $topHaresLastYear = $app['db']->fetchAll($sql8, array((int) $kennelKy));

    #Get the quickest to 5 hashes
    $theQuickestToXNumber = 5;
    $theSql = str_replace("XLIMITX",$theQuickestToXNumber-1,FASTEST_HASHERS_TO_ANALVERSARIES2);
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theQuickestToXResults = $app['db']->fetchAll($theSql, array((int) $kennelKy,(int) $kennelKy));

    #Get the quickest to 100 hashes
    $theQuickestToYNumber = 100;
    $theSql = str_replace("XLIMITX",$theQuickestToYNumber-1,FASTEST_HASHERS_TO_ANALVERSARIES2);
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theQuickestToYResults = $app['db']->fetchAll($theSql, array((int) $kennelKy,(int) $kennelKy));

    #Get the slowest to 5 hashes
    $theSlowestToXNumber = 5;
    $theSql = str_replace("XLIMITX",$theSlowestToXNumber-1,FASTEST_HASHERS_TO_ANALVERSARIES2);
    $theSql = str_replace("XORDERX","DESC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theSlowestToXResults = $app['db']->fetchAll($theSql, array((int) $kennelKy,(int) $kennelKy));

    $quickest_hares = array();
    $theQuickestToXHaringsNumber = 5;
    $theSql = str_replace("XLIMITX",$theQuickestToXHaringsNumber-1,FASTEST_HARES_TO_ANALVERSARIES2);
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";

    foreach ($hareTypes as &$hareType) {
    #Get the quickest to 5 true harings
      $theQuickestToXHaringsResults = $app['db']->fetchAll($theSql, array((int) $kennelKy, $hareType['HARE_TYPE'],(int) $kennelKy, $hareType['HARE_TYPE']));
      array_push($quickest_hares,
        array('data' => $theQuickestToXHaringsResults, 'label' => $hareType['HARE_TYPE_NAME'], 'hare_type' => $hareType['HARE_TYPE']));
    }

    #Query for the event tag summary
    $eventTagSql = "SELECT HT.TAG_TEXT, HT.HASHES_TAGS_KY,COUNT(HTJ.HASHES_KY) AS THE_COUNT
      FROM
        HASHES_TAGS HT
          LEFT JOIN HASHES_TAG_JUNCTION HTJ ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
          JOIN HASHES ON HTJ.HASHES_KY = HASHES.HASH_KY
      WHERE
        HASHES.KENNEL_KY = ?
      GROUP BY HT.TAG_TEXT,HT.HASHES_TAGS_KY
      ORDER BY THE_COUNT DESC";
    $eventTagSummaries = $app['db']->fetchAll($eventTagSql, array((int) $kennelKy));

    #Set the return value
    $returnValue = $app['twig']->render('slash2.twig',array(
      'pageTitle' => $pageTitle,
      'pageCaption' => "Provide page caption",
      'subTitle1' => 'Standard Statistics',
      'subTitle2' => 'Analversary Statistics',
      'subTitle3' => 'Hare Statistics',
      'subTitle4' => 'Other Statistics',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hare_types' => count($hareTypes) == 1 ? array() : $hareTypes,
      'top_alltime_hashers' =>$topHashersList,
      'top_hares' => $top_hares,
      'top_overall_hares' => $topOverallHareList,
      'the_quickest_to_x_number' => $theQuickestToXNumber,
      'the_quickest_to_x_results' => $theQuickestToXResults,
      'the_quickest_to_y_number' => $theQuickestToYNumber,
      'the_quickest_to_y_results' => $theQuickestToYResults,
      'the_slowest_to_x_number' => $theSlowestToXNumber,
      'the_slowest_to_x_results' => $theSlowestToXResults,
      'the_quickest_to_x_harings_number' => $theQuickestToXHaringsNumber,
      'quickest_hares' => $quickest_hares,
      'top_hashers_this_year' => $topHashersThisYear,
      'top_hashers_last_year' => $topHashersLastYear,
      'top_hares_this_year' => $topHaresThisYear,
      'top_hares_last_year' => $topHaresLastYear,
      'event_tag_summaries' => $eventTagSummaries,
      'overall_hares_title' =>
        count($hareTypes) > 1 ? "Top 10 (Overall) Hares" : "Top 10 Hares",
      'overall_haring_counts_title' =>
        count($hareTypes) > 1 ? "Overall Haring Counts" : "Haring Counts"
    ));

    #Return the return value
    return $returnValue;

  }

  public function listStreakersByHashAction(Request $request, Application $app, string $kennel_abbreviation, int $hash_id){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $theList = $app['db']->fetchAll(STREAKERS_LIST,array((int) $hash_id,(int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Establish and set the return value
    $returnValue = $app['twig']->render('streaker_results.twig',array(
      'pageTitle' => 'The Streakers!',
      'pageSubTitle' => '...',
      'theList' => $theList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'theHashValue' => $theHashValue,
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }


  #Define the action
  public function listHashersPreActionJson(Request $request, Application $app, string $kennel_abbreviation){

    # Establish and set the return value
    $returnValue = $app['twig']->render('hasher_list_json.twig',array(
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => '',
      #'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }

  #Define the action
  public function listVirginHaringsPreActionJson(Request $request, Application $app, int $hare_type, string $kennel_abbreviation){

    $hareTypeName = $this->getHareTypeName($app, $hare_type);

    # Establish and set the return value
    $returnValue = $app['twig']->render('virgin_haring_list_json.twig',array(
      'pageTitle' => 'The List of Virgin ('.$hareTypeName.') Harings',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "",
      'hare_type' => $hare_type
    ));

    #Return the return value
    return $returnValue;

  }

  public function cohareCountsPreActionJson(Request $request, Application $app, string $kennel_abbreviation, string $hare_type){

    # Establish and set the return value
    $returnValue = $app['twig']->render('cohare_list_json.twig',array(
      'pageTitle' => ($hare_type == "all" ? "Overall" : $this->getHareTypeName($app, $hare_type)).' Co-Hare Counts',
      'pageSubTitle' => 'Total number of events where two hashers have hared together.',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hare_type' => $hare_type,
      'pageTracking' => 'CoHareCounts'
    ));

    #Return the return value
    return $returnValue;
  }

  public function allCohareCountsPreActionJson(Request $request, Application $app, string $kennel_abbreviation){
    return $this->cohareCountsPreActionJson($request, $app, $kennel_abbreviation, "all");
  }

  public function getCohareCountsJson(Request $request, Application $app, string $kennel_abbreviation){

    #$app['monolog']->addDebug("Entering the function------------------------");

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    #$inputDraw = $_POST['draw'] ;
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $hare_type = $_POST['hare_type'];
    $inputSearchValue = $inputSearch['value'];

    $typeClause = $hare_type=="all" ? "" : "AND b.HARE_TYPE & ? != 0 AND c.HARE_TYPE & ? != 0";

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      #$app['monolog']->addDebug("input start is not numeric: $inputStart");
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      #$app['monolog']->addDebug("input length is not numeric");
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      #$app['monolog']->addDebug("input length is negative one (all rows selected)");
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }else{
      $inputOrderColumnExtracted = "2";
      $inputOrderDirectionExtracted = "desc";
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
    }
    $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql =
      "SELECT a.HASHER_NAME AS HASHER_NAME1, d.HASHER_NAME AS HASHER_NAME2,
              COUNT(*) AS THE_COUNT,
              a.HASHER_KY AS HASHER_KY1, d.HASHER_KY AS HASHER_KY2
         FROM HASHERS a
         JOIN HARINGS b
           ON b.HARINGS_HASHER_KY=a.HASHER_KY
         JOIN HARINGS c
           ON b.HARINGS_HASH_KY = c.HARINGS_HASH_KY
         JOIN HASHERS d
           ON c.HARINGS_HASHER_KY = d.HASHER_KY
          AND a.HASHER_KY < d.HASHER_KY
         JOIN HASHES e
           ON e.HASH_KY = c.HARINGS_HASH_KY
        WHERE e.KENNEL_KY = ?
          AND (a.HASHER_NAME LIKE ? OR a.HASHER_ABBREVIATION LIKE ?
           OR  d.HASHER_NAME LIKE ? OR d.HASHER_ABBREVIATION LIKE ?)
          $typeClause
        GROUP BY a.HASHER_NAME, d.HASHER_NAME, a.HASHER_KY, d.HASHER_KY
        ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
        LIMIT $inputStart,$inputLength";

    #$app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM (
       SELECT 1
         FROM HASHERS a
         JOIN HARINGS b
           ON b.HARINGS_HASHER_KY=a.HASHER_KY
         JOIN HARINGS c
           ON b.HARINGS_HASH_KY = c.HARINGS_HASH_KY
         JOIN HASHERS d
           ON c.HARINGS_HASHER_KY = d.HASHER_KY
          AND a.HASHER_KY < d.HASHER_KY
         JOIN HASHES e
           ON e.HASH_KY = c.HARINGS_HASH_KY
        WHERE e.KENNEL_KY = ?
          AND (a.HASHER_NAME LIKE ? OR a.HASHER_ABBREVIATION LIKE ?
           OR  d.HASHER_NAME LIKE ? OR d.HASHER_ABBREVIATION LIKE ?)
          $typeClause
        GROUP BY a.HASHER_NAME, d.HASHER_NAME, a.HASHER_KY, d.HASHER_KY
      ) AS INNER_QUERY";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM (
       SELECT 1
         FROM HASHERS a
         JOIN HARINGS b
           ON b.HARINGS_HASHER_KY=a.HASHER_KY
         JOIN HARINGS c
           ON b.HARINGS_HASH_KY = c.HARINGS_HASH_KY
         JOIN HASHERS d
           ON c.HARINGS_HASHER_KY = d.HASHER_KY
          AND a.HASHER_KY < d.HASHER_KY
         JOIN HASHES e
           ON e.HASH_KY = c.HARINGS_HASH_KY
        WHERE e.KENNEL_KY = ?
          $typeClause
        GROUP BY a.HASHER_NAME, d.HASHER_NAME, a.HASHER_KY, d.HASHER_KY
      ) AS INNER_QUERY";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    $args = array(
      $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified);

    $args2 = array($kennelKy);

    if($hare_type!="all") {
        array_push($args, $hare_type);
        array_push($args, $hare_type);
        array_push($args2, $hare_type);
        array_push($args2, $hare_type);
    }

    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql, $args);

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,$args2))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = $app['db']->fetchAssoc($sqlFilteredCount,$args)['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }

  #Define the action
  public function listLocationCountsPreActionJson(Request $request, Application $app, string $kennel_abbreviation){

    # Establish and set the return value
    $returnValue = $app['twig']->render('location_counts_json.twig',array(
      'pageTitle' => 'The List of Event Locations',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }

  public function miaPreActionJson(Request $request, Application $app, string $kennel_abbreviation){

    # Establish and set the return value
    $returnValue = $app['twig']->render('hasher_mia.twig',array(
      'pageTitle' => 'Hashers Missing In Action',
      'pageSubTitle' => '',
      #'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }

  public function attendancePercentagesPreActionJson(Request $request, Application $app, string $kennel_abbreviation){

    # Establish and set the return value
    $returnValue = $app['twig']->render('attendance_percentages_list_json.twig',array(
      'pageTitle' => 'Attendance Percentages',
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;
  }

  public function listHashersByHashAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Define the SQL to execute
    $sql = "SELECT
      HASHERS.HASHER_KY AS THE_KEY,
      HASHERS.HASHER_NAME AS NAME,
      HASHERS.HASHER_ABBREVIATION
      FROM HASHERS JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY WHERE HASHINGS.HASH_KY = ?";

    #Execute the SQL statement; create an array of rows
    $hasherList = $app['db']->fetchAll($sql,array((int) $hash_id));

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $theHashEventLocation = $theHashValue['EVENT_LOCATION'];
    $theSubTitle = "Hashers at Hash Number $theHashEventNumber ($theHashEventLocation) ";

    # Establish and set the return value
    $returnValue = $app['twig']->render('hasher_list.twig',array(
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => $theSubTitle,
      'theList' => $hasherList,
      'tableCaption' => $theSubTitle,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;

  }

  public function listHaresByHashAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){


    #Define the SQL to execute
    $sql = "
      SELECT THE_KEY, NAME, HASHER_ABBREVIATION,
             GROUP_CONCAT(HARE_TYPE_NAME SEPARATOR ', ') AS HARE_TYPE_NAME
        FROM (
      SELECT HASHERS.HASHER_KY AS THE_KEY,
             HASHERS.HASHER_NAME AS NAME,
             HASHERS.HASHER_ABBREVIATION,
             HARE_TYPES.HARE_TYPE_NAME
        FROM HASHERS
        JOIN HARINGS
          ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
       WHERE HARINGS.HARINGS_HASH_KY = ?
       ORDER BY HASHERS.HASHER_NAME, HARE_TYPES.SEQ) THE_TABLE
       GROUP BY THE_KEY, NAME, HASHER_ABBREVIATION";

    #Execute the SQL statement; create an array of rows
    $hasherList = $app['db']->fetchAll($sql,array((int) $hash_id));

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $theHashEventLocation = $theHashValue['EVENT_LOCATION'];
    $theSubTitle = "Hares at Hash Number $theHashEventNumber ($theHashEventLocation) ";

    # Establish and set the return value
    $returnValue = $app['twig']->render('hare_list.twig',array(
      'pageTitle' => 'The List of Hares',
      'pageSubTitle' => $theSubTitle,
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;
  }

  public function getHasherListJson(Request $request, Application $app, string $kennel_abbreviation){

    #$app['monolog']->addDebug("Entering the function------------------------");

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    #$inputDraw = $_POST['draw'] ;
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      #$app['monolog']->addDebug("input start is not numeric: $inputStart");
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      #$app['monolog']->addDebug("input length is not numeric");
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      #$app['monolog']->addDebug("input length is negative one (all rows selected)");
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "1";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }else{
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT
      HASHER_NAME AS NAME,
      HASHER_ABBREVIATION,
      COUNT(HASHINGS.HASHER_KY) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
      HASHINGS.HASHER_KY AS THE_KEY
      FROM HASHERS
      JOIN HASHINGS
        ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
      JOIN HASHES
        ON HASHES.HASH_KY = HASHINGS.HASH_KY
      WHERE
          KENNEL_KY = ? AND (
          HASHER_NAME LIKE ? OR
          HASHER_ABBREVIATION LIKE ?)
      GROUP BY HASHINGS.HASHER_KY
      ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
      LIMIT $inputStart,$inputLength";
      #$app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM (
    SELECT 1
      FROM HASHERS
      JOIN HASHINGS
        ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
      JOIN HASHES
        ON HASHES.HASH_KY = HASHINGS.HASH_KY
      WHERE
          KENNEL_KY = ? AND (
          HASHER_NAME LIKE ? OR
          HASHER_ABBREVIATION LIKE ?)
      GROUP BY HASHINGS.HASHER_KY) AS INNER_QUERY";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM (
    SELECT 1
      FROM HASHERS
      JOIN HASHINGS
        ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
      JOIN HASHES
        ON HASHES.HASH_KY = HASHINGS.HASH_KY
      WHERE
          KENNEL_KY = ?
      GROUP BY HASHINGS.HASHER_KY) AS INNER_QUERY";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array(
      $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array($kennelKy)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
      $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }


  public function getVirginHaringsListJson(Request $request, Application $app, int $hare_type, string $kennel_abbreviation){

    #$app['monolog']->addDebug("Entering the function------------------------");

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    #$inputDraw = $_POST['draw'] ;
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      #$app['monolog']->addDebug("input start is not numeric: $inputStart");
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      #$app['monolog']->addDebug("input length is not numeric");
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      #$app['monolog']->addDebug("input length is negative one (all rows selected)");
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "2";
    $inputOrderColumnIncremented = "2";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }else{
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
                   FIRST_HARING_EVENT_TABLE.FIRST_HASH_DATE AS FIRST_HARING_DATE,
                   HASHERS.HASHER_KY AS HASHER_KY,
                   FIRST_HARING_EVENT_TABLE.FIRST_HASH_KEY AS FIRST_HARING_KEY
          FROM HASHERS
          JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY,
                       MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE,
                       MIN(HASHES.HASH_KY) AS FIRST_HASH_KEY
                 FROM HARINGS
                 JOIN HASHES
                   ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                WHERE HASHES.KENNEL_KY = ?
                  AND HARINGS.HARE_TYPE & ? != 0
                GROUP BY HARINGS.HARINGS_HASHER_KY) FIRST_HARING_EVENT_TABLE
            ON HASHERS.HASHER_KY = FIRST_HARING_EVENT_TABLE.HASHER_KY
         WHERE HASHERS.HASHER_NAME LIKE ?
         ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
         LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHERS
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY,
                     MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE,
                     MIN(HASHES.HASH_KY) AS FIRST_HASH_KEY
               FROM HARINGS
               JOIN HASHES
                 ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
              WHERE HASHES.KENNEL_KY = ?
                AND HARINGS.HARE_TYPE & ? != 0
              GROUP BY HARINGS.HARINGS_HASHER_KY) FIRST_HARING_EVENT_TABLE
          ON HASHERS.HASHER_KY = FIRST_HARING_EVENT_TABLE.HASHER_KY
       WHERE HASHERS.HASHER_NAME LIKE ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHERS
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY,
                     MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE,
                     MIN(HASHES.HASH_KY) AS FIRST_HASH_KEY
                FROM HARINGS
                JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
               WHERE HASHES.KENNEL_KY = ?
                 AND HARINGS.HARE_TYPE & ? != 0
               GROUP BY HARINGS.HARINGS_HASHER_KY) FIRST_HARING_EVENT_TABLE
          ON ((HASHERS.HASHER_KY = FIRST_HARING_EVENT_TABLE.HASHER_KY))";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array($kennelKy, $hare_type, (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array($kennelKy, $hare_type)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array($kennelKy, $hare_type, (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }

  public function getLocationCountsJson(Request $request, Application $app, string $kennel_abbreviation){

    #$app['monolog']->addDebug("Entering the function------------------------");

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    #$inputDraw = $_POST['draw'] ;
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      #$app['monolog']->addDebug("input start is not numeric: $inputStart");
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      #$app['monolog']->addDebug("input length is not numeric");
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      #$app['monolog']->addDebug("input length is negative one (all rows selected)");
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column']+1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    } else {
      $inputOrderColumnExtracted = "2";
      $inputOrderDirectionExtracted = "desc";
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
       SELECT (
       SELECT CONCAT(CASE WHEN EVENT_LOCATION!='' THEN CONCAT(EVENT_LOCATION,', ') ELSE '' END,FORMATTED_ADDRESS)
         FROM HASHES I
        WHERE I.PLACE_ID = O.PLACE_ID
        ORDER BY KENNEL_EVENT_NUMBER DESC
        LIMIT 1) AS LOCATION, COUNT(*) AS THE_COUNT
         FROM HASHES O
        WHERE KENNEL_KY=?
          AND PLACE_ID != ''
          AND (EVENT_LOCATION!=''
           OR FORMATTED_ADDRESS!='')
          AND (EVENT_LOCATION LIKE ?
           OR FORMATTED_ADDRESS LIKE ?)
        GROUP BY PLACE_ID
        ORDER BY $inputOrderColumnExtracted $inputOrderDirectionExtracted
        LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount =
       "SELECT COUNT(*) AS THE_COUNT
          FROM (
        SELECT 1
          FROM HASHES O
         WHERE KENNEL_KY=?
           AND PLACE_ID != ''
           AND (EVENT_LOCATION!=''
            OR FORMATTED_ADDRESS!='')
           AND (EVENT_LOCATION LIKE ?
            OR FORMATTED_ADDRESS LIKE ?)
         GROUP BY PLACE_ID) I";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount =
       "SELECT COUNT(*) AS THE_COUNT
         FROM (
       SELECT 1
         FROM HASHES O
        WHERE KENNEL_KY=?
          AND PLACE_ID != ''
          AND (EVENT_LOCATION!=''
           OR FORMATTED_ADDRESS!='')
        GROUP BY PLACE_ID) I";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array(
      $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array($kennelKy)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
      $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }


  public function miaPostActionJson(Request $request, Application $app, string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    #$inputDraw = $_POST['draw'] ;
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      #$app['monolog']->addDebug("input start is not numeric: $inputStart");
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      #$app['monolog']->addDebug("input length is not numeric");
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      #$app['monolog']->addDebug("input length is negative one (all rows selected)");
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "1";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }else{
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
      $inputOrderColumnIncremented = "DAYS_MIA";
      $inputOrderDirectionExtracted = "DESC";
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql =
	    "SELECT HASHER_NAME, LAST_SEEN_EVENT, LAST_SEEN_DATE, NUM_HASHES_MISSED,
	       DATEDIFF(CURDATE(), LAST_SEEN_DATE) AS DAYS_MIA, (
        SELECT MAX(HASH_KY)
          FROM HASHES
         WHERE KENNEL_EVENT_NUMBER = LAST_SEEN_EVENT
           AND KENNEL_KY = $kennelKy) AS HASH_KY,
	    HASHER_KY AS THE_KEY, HASHER_ABBREVIATION
	  FROM (
	SELECT HASHER_NAME, HASHER_KY, HASHER_ABBREVIATION, LAST_SEEN_DATE, (
	       SELECT COUNT(*)
		 FROM HASHES
		WHERE KENNEL_KY = $kennelKy
		  AND HASHES.EVENT_DATE > LAST_SEEN_DATE) AS NUM_HASHES_MISSED, (
	       SELECT MAX(KENNEL_EVENT_NUMBER)
		 FROM HASHES
		WHERE HASHES.EVENT_DATE = LAST_SEEN_DATE
		  AND HASHES.HASH_KY IN (
		      SELECT HASH_KY
			FROM HASHINGS
		       WHERE KENNEL_KY = $kennelKy
			 AND HASHINGS.HASHER_KY = HASHER_KY)) AS LAST_SEEN_EVENT
	  FROM (
	SELECT HASHER_NAME, HASHER_ABBREVIATION, HASHERS.HASHER_KY AS HASHER_KY, (
		SELECT MAX(EVENT_DATE)
		  FROM HASHES
		 WHERE HASHES.HASH_KY IN (
		       SELECT HASH_KY
			 FROM HASHINGS
			WHERE KENNEL_KY = $kennelKy
			  AND HASHINGS.HASHER_KY = HASHERS.HASHER_KY)) AS LAST_SEEN_DATE
	  FROM HASHERS
	 WHERE HASHER_NAME NOT LIKE 'Just %'
	   AND HASHER_NAME NOT LIKE 'NHN %'
	   AND DECEASED = 0) INNER1
	 WHERE LAST_SEEN_DATE IS NOT NULL) INNER2
	 WHERE NUM_HASHES_MISSED > 0";

    $sql2 = "$sql
          AND (HASHER_NAME LIKE ? OR
          HASHER_ABBREVIATION LIKE ?)";

    $sql3 = "$sql2
         ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
         LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM ($sql2) A";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM ($sql) A";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql3,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }

  public function attendancePercentagesPostActionJson(Request $request, Application $app, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    #Validate input start
    if(!is_numeric($inputStart)){
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)){
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search
    #We are using database parameterized statements, so we are good already...

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumn = "2";
    $inputOrderDirection = "desc";
    if(!is_null($inputOrderRaw)){
      $inputOrderColumn = $inputOrderRaw[0]['column'] + 1;
      $inputOrderDirection = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql =
      "SELECT HASHER_NAME,
	      100 * (NUM_HASHES / ALL_EVENTS_COUNT) AS OVERALL_PERCENTAGE,
              100 * (NUM_HASHES / HASHER_EVENTS_TO_DATE) AS CURRENT_PERCENTAGE,
              100 * (NUM_HASHES / CAREER_EVENTS) AS CAREER_PERCENTAGE,
              NUM_HASHES, HASHER_KY
          FROM (
	SELECT HASHERS.HASHER_NAME AS HASHER_NAME, HASHERS.HASHER_KY AS HASHER_KY,
               HASHERS.HASHER_ABBREVIATION AS HASHER_ABBREVIATION,
               ALL_EVENTS.THE_COUNT AS ALL_EVENTS_COUNT,
               HASHER_DETAILS.THE_COUNT AS NUM_HASHES,
               (SELECT COUNT(*)
                  FROM HASHES
                 WHERE HASHES.KENNEL_KY=?
                   AND HASHES.EVENT_DATE >= HASHER_DETAILS.FIRST_HASH_DATE) AS HASHER_EVENTS_TO_DATE,
               (SELECT COUNT(*)
                  FROM HASHES
                 WHERE HASHES.KENNEL_KY=?
                   AND HASHES.EVENT_DATE >= HASHER_DETAILS.FIRST_HASH_DATE
                   AND HASHES.EVENT_DATE <= HASHER_DETAILS.LAST_HASH_DATE) AS CAREER_EVENTS
	  FROM HASHERS
         CROSS JOIN
               (SELECT COUNT(*) AS THE_COUNT
                  FROM HASHES
                 WHERE HASHES.KENNEL_KY=?) AS ALL_EVENTS
         JOIN (SELECT HASHER_KY, MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE, MAX(HASHES.EVENT_DATE) AS LAST_HASH_DATE,
                      COUNT(*) AS THE_COUNT
                 FROM HASHINGS
                 JOIN HASHES
                   ON HASHES.HASH_KY=HASHINGS.HASH_KY
                WHERE HASHES.KENNEL_KY=?
                GROUP BY HASHER_KY
               HAVING COUNT(*)>=10) AS HASHER_DETAILS
           ON HASHER_DETAILS.HASHER_KY=HASHERS.HASHER_KY
           ) AS INNER_QUERY
         WHERE (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
         ORDER BY $inputOrderColumn $inputOrderDirection
         LIMIT $inputStart,$inputLength";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount =
      "SELECT COUNT(*) AS THE_COUNT
         FROM HASHERS
         JOIN (SELECT HASHER_KY
                 FROM HASHINGS
                 JOIN HASHES
                   ON HASHES.HASH_KY=HASHINGS.HASH_KY
                WHERE HASHES.KENNEL_KY=?
                GROUP BY HASHER_KY
               HAVING COUNT(*)>=10) AS HASHER_DETAILS
	   ON HASHER_DETAILS.HASHER_KY=HASHERS.HASHER_KY ";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "$sqlUnfilteredCount
      WHERE (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array(
      $kennelKy, $kennelKy, $kennelKy, $kennelKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array($kennelKy)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
      $kennelKy, (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    #Set the return value
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }



  public function listHashesByHasherAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
          HASHES.HASH_KY,
          KENNEL_EVENT_NUMBER,
          EVENT_DATE,
          DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME,
          EVENT_LOCATION,
          EVENT_CITY,
          SPECIAL_EVENT_DESCRIPTION,
          HASH_TYPE_NAME
    FROM HASHES
    JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
    JOIN HASH_TYPES ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
    WHERE HASHINGS.HASHER_KY = ? AND HASHES.KENNEL_KY = ?
    ORDER BY HASHES.EVENT_DATE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $hasher_id, (int)$kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes $hasherName has done";
    $returnValue = $app['twig']->render('hash_list.twig',array(
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;
  }

  public function attendanceRecordForHasherAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll(HASHER_ATTENDANCE_RECORD_LIST,array((int)$kennelKy,(int) $hasher_id, (int)$kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes attended by  $hasherName";
    $returnValue = $app['twig']->render('hasher_attendance_list.twig',array(
      'pageTitle' => 'Attendance Record',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;

  }



  public function listHashesByHareAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
        HASHES.HASH_KY,
        KENNEL_EVENT_NUMBER,
        EVENT_DATE,
        DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME,
        EVENT_LOCATION,
        EVENT_CITY,
        SPECIAL_EVENT_DESCRIPTION,
        HASH_TYPE_NAME
      FROM HASHES
      JOIN HARINGS
        ON HASHES.HASH_KY = HARINGS.HARINGS_HASH_KY
      JOIN HASH_TYPES
        ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
      WHERE HARINGS.HARINGS_HASHER_KY = ? AND HASHES.KENNEL_KY = ?
      ORDER BY EVENT_DATE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $hasher_id, (int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ? ";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes $hasherName has hared";
    $returnValue = $app['twig']->render('hash_list.twig',array(
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;
  }



  public function hashedWithAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ? ";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the sql statement to execute
    $theSql = "
      SELECT HASHERS.HASHER_NAME AS NAME, HASHERS.HASHER_KY AS THE_KEY, COUNT(*) AS VALUE
	FROM HASHERS
	JOIN HASHINGS ON HASHERS.HASHER_KY=HASHINGS.HASHER_KY
       WHERE HASHINGS.HASH_KY IN (
      SELECT HASHES.HASH_KY
	FROM HASHINGS
	JOIN HASHES ON HASHINGS.HASH_KY=HASHES.HASH_KY
       WHERE HASHINGS.HASHER_KY=?
	 AND HASHES.KENNEL_KY=?)
         AND HASHINGS.HASHER_KY!=?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY
       ORDER BY VALUE DESC, NAME";

    #Query the database
    $theResults = $app['db']->fetchAll($theSql, array($hasher_id, (int) $kennelKy, $hasher_id));

    #Define the page title
    $pageTitle = "Hashers that have hashed with $hasherName";

    #Set the return value
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => $pageTitle,
      'tableCaption' => '',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Count',
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HashedWith'
    ));

    return $returnValue;
  }


  public function viewHasherChartsAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    # Declare the SQL used to retrieve this information
    $sql = "SELECT HASHER_KY, HASHER_NAME, HASHER_ABBREVIATION, FIRST_NAME, LAST_NAME, DECEASED FROM HASHERS WHERE HASHER_KY = ?";

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql, array((int) $hasher_id));

    # Obtain their hashes
    $sqlTheHashes = "SELECT KENNEL_EVENT_NUMBER, LAT, LNG, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE, HASHINGS.HASH_KY FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
    WHERE HASHER_KY = ? AND KENNEL_KY = ? and LAT is not null and LNG is not null";
    $theHashes = $app['db']->fetchAll($sqlTheHashes, array((int) $hasher_id, (int) $kennelKy));

    #Obtain the average lat
    $sqlTheAverageLatLong = "SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
    WHERE HASHER_KY = ? AND KENNEL_KY = ? and LAT is not null and LNG is not null";
    $theAverageLatLong = $app['db']->fetchAssoc($sqlTheAverageLatLong, array((int) $hasher_id, (int) $kennelKy));
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    # Obtain the number of hashings
    $hashCountValue = $app['db']->fetchAssoc($this->getPersonsHashingCountQuery(), array((int) $hasher_id, (int) $kennelKy));

    # Obtain the number of harings
    $hareCountValue = $app['db']->fetchAssoc(PERSONS_HARING_COUNT, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by month (name)
    $theHashesByMonthNameList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_MONTH_NAME, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by quarter
    $theHashesByQuarterList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_QUARTER, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by quarter
    $theHashesByStateList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_STATE, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by county
    $theHashesByCountyList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_COUNTY, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by postal code
    $theHashesByPostalCodeList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_POSTAL_CODE, array((int) $hasher_id, (int) $kennelKy));

    # Obtain the hashes by day name
    $theHashesByDayNameList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_DAYNAME, array((int) $hasher_id, (int) $kennelKy));

    #Obtain the hashes by year
    $sqlHashesByYear = "SELECT YEAR(EVENT_DATE) AS THE_VALUE, COUNT(*) AS THE_COUNT
     FROM
    	HASHINGS
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
      WHERE
    	HASHINGS.HASHER_KY = ? AND
        HASHES.KENNEL_KY = ?
    GROUP BY YEAR(EVENT_DATE)
    ORDER BY YEAR(EVENT_DATE)";
    $hashesByYearList = $app['db']->fetchAll($sqlHashesByYear, array((int) $hasher_id,(int) $kennelKy));

    #Obtain the harings by year
    $sqlHaringsByYear = "SELECT
    	  YEAR(EVENT_DATE) AS THE_VALUE,
        COUNT(*) AS TOTAL_HARING_COUNT
    FROM
        HARINGS
    	  JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
    WHERE
        HARINGS.HARINGS_HASHER_KY = ? AND
        HASHES.KENNEL_KY = ?
    GROUP BY YEAR(EVENT_DATE)
    ORDER BY YEAR(EVENT_DATE)";
    $haringsByYearList = $app['db']->fetchAll($sqlHaringsByYear, array((int) $hasher_id,(int) $kennelKy));

    #Query the database
    $cityHashingsCountList = $app['db']->fetchAll(HASHER_HASH_COUNTS_BY_CITY, array((int) $hasher_id, (int) $kennelKy));

    #Obtain largest entry from the list
    $cityHashingsCountMax = 1;
    if(isset($cityHashingsCountList[0]['THE_COUNT'])){
      $cityHashingsCountMax = $cityHashingsCountList[0]['THE_COUNT'];
    }

    #Obtain their largest streak
    $longestStreakValue = $app['db']->fetchAssoc(THE_LONGEST_STREAKS_FOR_HASHER, array((int) $kennelKy , (int) $hasher_id));

    #By Quarter/ Month ---------------------------------------------------
    $quarterMonthSql = "SELECT CONCAT (THE_QUARTER,'/',MONTH_NAME,'/',THE_COUNT) AS THE_VALUE, THE_COUNT
      FROM (
      	SELECT
      		CASE
      			WHEN THE_VALUE IN ('1','2','3')  THEN 'Q1'
      			WHEN THE_VALUE IN ('4','5','6') THEN 'Q2'
      			WHEN THE_VALUE IN ('7','8','9') THEN 'Q3'
      			WHEN THE_VALUE IN ('10','11','12') THEN 'Q4'
      			ELSE 'XXX'
      		END AS THE_QUARTER,
      		CASE THE_VALUE
      			WHEN '1' THEN 'January'
      			WHEN '2' THEN 'February'
      			WHEN '3' THEN 'March'
      			WHEN '4' THEN 'April'
      			WHEN '5' THEN 'May'
      			WHEN '6' THEN 'June'
      			WHEN '7' THEN 'July'
      			WHEN '8' THEN 'August'
      			WHEN '9' THEN 'September'
      			WHEN '10' THEN 'October'
      			WHEN '11' THEN 'November'
      			WHEN '12' THEN 'December'
      		END AS MONTH_NAME,
      		THE_COUNT
      	FROM
      	(
      		SELECT MONTH(EVENT_DATE) AS THE_VALUE, COUNT(*) AS THE_COUNT
      		FROM
      			HASHINGS
      			JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
      		WHERE
      			HASHINGS.HASHER_KY = ? AND
      			HASHES.KENNEL_KY = ?
      		GROUP BY MONTH(EVENT_DATE)
      		ORDER BY MONTH(EVENT_DATE)
      	) TEMP_TABLE
      ) ASDF";


    #Query the db
    $quarterMonthValues = $app['db']->fetchAll($quarterMonthSql, array((int) $hasher_id , (int) $kennelKy));
    $quarterMonthFormattedData = convertToFormattedHiarchy($quarterMonthValues);

    # End by Quarter Month ------------------------------------------------

    #Obtain the state/county/city data for the sunburst chart
    $sunburstSqlA = "SELECT
	     CONCAT(EVENT_STATE,'/',COUNTY,'/',EVENT_CITY,'/',THE_COUNT) AS THE_VALUE, THE_COUNT
       FROM (
	        SELECT
		        EVENT_STATE, COUNTY, EVENT_CITY,  COUNT(*) AS THE_COUNT
	        FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
	        WHERE HASHINGS.HASHER_KY = ? AND HASHES.KENNEL_KY = ?
	        GROUP BY EVENT_STATE, COUNTY, EVENT_CITY
          ORDER BY EVENT_STATE, COUNTY, EVENT_CITY
      ) TEMPTABLE
      WHERE
        EVENT_STATE IS NOT NULL AND EVENT_STATE != '' AND
    	  COUNTY IS NOT NULL AND COUNTY != '' AND
    	  EVENT_CITY IS NOT NULL AND EVENT_CITY != ''";

    #Obtain their sunburst data
    $sunburstValuesA = $app['db']->fetchAll($sunburstSqlA, array((int) $hasher_id , (int) $kennelKy));
    $sunburstFormattedData = convertToFormattedHiarchy($sunburstValuesA);

    $hareTypes = $this->getHareTypes($app, $kennelKy);

    # Establish and set the return value
    $returnValue = $app['twig']->render('hasher_chart_details.twig',array(
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : array(),
      'overall_hare_details' => (count($hareTypes) > 1 ? "Overall " : "").
        "Hare Details",
      'sunburst_formatted_data' => $sunburstFormattedData,
      'quarter_month_formatted_data' => $quarterMonthFormattedData,
      'pageTitle' => 'Hasher Charts and Details',
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'hasherValue' => $hasher,
      'hashCount' => $hashCountValue['THE_COUNT'],
      'hareCount' => $hareCountValue['THE_COUNT'],
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashes_by_year_list' => $hashesByYearList,
      'harings_by_year_list' => $haringsByYearList,
      'hashes_by_month_name_list' => $theHashesByMonthNameList,
      'hashes_by_quarter_list' => $theHashesByQuarterList,
      'hashes_by_state_list' => $theHashesByStateList,
      'hashes_by_county_list' => $theHashesByCountyList,
      'hashes_by_postal_code_list' => $theHashesByPostalCodeList,
      'hashes_by_day_name_list' => $theHashesByDayNameList,
      'city_hashings_count_list' => $cityHashingsCountList,
      'city_hashings_max_value' => $cityHashingsCountMax,
      'the_hashes' => $theHashes,
      'geocode_api_value' => GOOGLE_MAPS_JAVASCRIPT_API_KEY,
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng,
      'longest_streak' => $longestStreakValue['MAX_STREAK'],
      //'sunburst_values_a' => $sunburstValuesA
    ));

    # Return the return value
    return $returnValue;

  }


  public function viewHashAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Obtain the hound count
    $houndCountSQL = HOUND_COUNT_BY_HASH_KEY;
    $theHoundCountValue = $app['db']->fetchAssoc($houndCountSQL, array((int) $hash_id));
    $theHoundCount = $theHoundCountValue['THE_COUNT'];

    $hareCountSQL = HARE_COUNT_BY_HASH_KEY;
    $theHareCountValue = $app['db']->fetchAssoc($hareCountSQL, array((int) $hash_id));
    $theHareCount = $theHareCountValue['THE_COUNT'];

    # Determine previous hash
    $previousHashSql = "SELECT hash_ky AS THE_COUNT FROM HASHES WHERE kennel_ky=? AND event_date < (SELECT event_date FROM HASHES WHERE hash_ky = ?) ORDER BY event_date DESC LIMIT 1";
    $previousHashId = $app['db']->fetchAssoc($previousHashSql, array($kennelKy, $hash_id))['THE_COUNT'];

    # Determine next hash
    $nextHashSql = "SELECT hash_ky AS THE_COUNT FROM HASHES WHERE kennel_ky=? AND event_date > (SELECT event_date FROM HASHES WHERE hash_ky = ?) ORDER BY event_date LIMIT 1";
    $nextHashId = $app['db']->fetchAssoc($nextHashSql, array($kennelKy, $hash_id))['THE_COUNT'];


    # Make a database call to obtain the hasher information
    $sql = "SELECT PLACE_ID, EVENT_STATE, COUNTY, EVENT_CITY, EVENT_LOCATION, STREET_NUMBER, ROUTE, FORMATTED_ADDRESS, NEIGHBORHOOD, POSTAL_CODE, COUNTRY, LAT, LNG, KENNEL_EVENT_NUMBER, EVENT_DATE, SPECIAL_EVENT_DESCRIPTION, HASH_KY, HASH_TYPE_NAME
              FROM HASHES
              JOIN HASH_TYPES
                ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
             WHERE HASH_KY = ?";
    $theHashValue = $app['db']->fetchAssoc($sql, array((int) $hash_id));

    $state = $theHashValue['EVENT_STATE'];
    $county =$theHashValue['COUNTY'];
    $city = $theHashValue['EVENT_CITY'];
    $neighborhood = $theHashValue['NEIGHBORHOOD'];
    $postalCode = $theHashValue['POSTAL_CODE'];

    $showState = true;
    $showCounty = true;
    $showCity = true;
    $showNeighborhood = true;
    $showPostalCode = true;

    if(strlen($state)==0){
      $showState = false;
    }

    if(strlen($county)==0){
      $showCounty = false;
    }

    if(strlen($city)==0){
      $showCity = false;
    }

    if(strlen($neighborhood)==0){
      $showNeighborhood = false;
    }

    if(strlen($postalCode)==0){
      $showPostalCode = false;
    }

    # Establish and set the return value
    $returnValue = $app['twig']->render('hash_details.twig',array(
      'pageTitle' => 'Hash Details',
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'hashValue' => $theHashValue,
      'kennel_abbreviation' => $kennel_abbreviation,
      'geocode_api_value' => GOOGLE_MAPS_JAVASCRIPT_API_KEY,
      'showStateCountList' => $showState,
      'showCountyCountList' => $showCounty,
      'showCityCountList' => $showCity,
      'showNeighborhoodCountList' => $showNeighborhood,
      'showPostalCodeCountList' => $showPostalCode,
      'theHoundCount' => $theHoundCount,
      'theHareCount' => $theHareCount,
      'nextHashId' => $nextHashId,
      'previousHashId' => $previousHashId
    ));

    # Return the return value
    return $returnValue;

  }

    public function consolidatedEventAnalversariesAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);


      # Make a database call to obtain the hasher information
      $houndAnalversaryList = $app['db']->fetchAll($this->getHoundAnalversariesForEvent(), array((int) $hash_id,(int) $kennelKy, (int) $hash_id));
      $consolidatedHareAnalversaryList = $app['db']->fetchAll(CONSOLIDATED_HARE_ANALVERSARIES_FOR_EVENT, array(
        (int) $hash_id,(int) $kennelKy, (int) $hash_id,
        (int) $hash_id,(int) $kennelKy, (int) $hash_id));

      # Declare the SQL used to retrieve this information
      $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

      # Make a database call to obtain the hasher information
      $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

      $sqlHoundAnalversaryTemplate = "SELECT * FROM (
        SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
	(COUNT(*)) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
        AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY,
        'AAA' AS ANV_TYPE,
        (SELECT XXX FROM HASHES WHERE HASH_KY = ?) AS ANV_VALUE
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = (SELECT KENNEL_KY FROM HASHES WHERE HASH_KY = ?) AND
        HASHES.XXX = (SELECT XXX FROM HASHES WHERE HASH_KY = ?)
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC)
    DERIVED_TABLE WHERE ANV_VALUE !=''";

    $sqlHoundAnalversaryDateBasedTemplate = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
	(COUNT(*)) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
        AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY,
		    'AAA' AS ANV_TYPE,
        (SELECT XXX(HASHES.EVENT_DATE) FROM HASHES WHERE HASH_KY = ?) AS ANV_VALUE
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = (SELECT KENNEL_KY FROM HASHES WHERE HASH_KY = ?) AND
        XXX(HASHES.EVENT_DATE) = (SELECT XXX(EVENT_DATE) FROM HASHES WHERE HASH_KY = ?)
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

      #Obtain the state analversaries (hound)
      $theSqlHoundState = str_replace("AAA","State",str_replace("XXX","EVENT_STATE",$sqlHoundAnalversaryTemplate));
      $theHoundStateList = $app['db']->fetchAll($theSqlHoundState, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the city analversaries (hound)
      $theSqlHoundCity = str_replace("AAA","City",str_replace("XXX","EVENT_CITY",$sqlHoundAnalversaryTemplate));
      $theHoundCityList = $app['db']->fetchAll($theSqlHoundCity, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the neighborhood analversaries (hound)
      $theSqlHoundNeighborhood = str_replace("AAA","Neighborhood",str_replace("XXX","NEIGHBORHOOD",$sqlHoundAnalversaryTemplate));
      $theHoundNeighborhoodList = $app['db']->fetchAll($theSqlHoundNeighborhood, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the county analversaries (hound)
      $theSqlHoundCounty = str_replace("AAA","County",str_replace("XXX","COUNTY",$sqlHoundAnalversaryTemplate));
      $theHoundCountyList = $app['db']->fetchAll($theSqlHoundCounty, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the postal code analversaries (hound)
      $theSqlHoundPostalCode = str_replace("AAA","Zip Code",str_replace("XXX","POSTAL_CODE",$sqlHoundAnalversaryTemplate));
      $theHoundPostalCodeList = $app['db']->fetchAll($theSqlHoundPostalCode, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the postal code analversaries (hound)
      $theSqlHoundRoute = str_replace("AAA","Street",str_replace("XXX","ROUTE",$sqlHoundAnalversaryTemplate));
      $theHoundRouteList = $app['db']->fetchAll($theSqlHoundRoute, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));


      #Obtain the year analversaries (hound)
      $theSqlHoundYear = str_replace("AAA","Year",str_replace("XXX","YEAR",$sqlHoundAnalversaryDateBasedTemplate));
      $theHoundYearList = $app['db']->fetchAll($theSqlHoundYear, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the month analversaries (hound)
      $theSqlHoundMonth = str_replace("AAA","Month",str_replace("XXX","MONTHNAME",$sqlHoundAnalversaryDateBasedTemplate));
      $theHoundMonthList = $app['db']->fetchAll($theSqlHoundMonth, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Obtain the day analversaries (hound)
      $theSqlHoundDay = str_replace("AAA","Day",str_replace("XXX","DAYNAME",$sqlHoundAnalversaryDateBasedTemplate));
      $theHoundDayList = $app['db']->fetchAll($theSqlHoundDay, array((int) $hash_id,(int) $hash_id, (int) $hash_id ,(int) $hash_id,(int) $hash_id));

      #Merge the arrays
      $geolocationHoundAnalversaryList = array_merge(
        $theHoundStateList,
        $theHoundCityList,
        $theHoundNeighborhoodList,
        $theHoundCountyList,
        $theHoundPostalCodeList,
        $theHoundRouteList
      );

      #Merge the arrays
      $dateHoundAnalversaryList = array_merge(
        $theHoundYearList,
        $theHoundMonthList,
        $theHoundDayList
      );

      #Sort the arrays
      $theCountArray = array();
      foreach($geolocationHoundAnalversaryList as $key => $row){
        $theCountArray[$key] = $row['THE_COUNT'];
      }
      array_multisort($theCountArray, SORT_DESC,$geolocationHoundAnalversaryList );

      #Sort the arrays
      $theCountDateArray = array();
      foreach($dateHoundAnalversaryList as $key => $row){
        $theCountDateArray[$key] = $row['THE_COUNT'];
      }
      array_multisort($theCountDateArray, SORT_DESC,$dateHoundAnalversaryList );

      #Obtain the streakers
      $theStreakersList = $app['db']->fetchAll(STREAKERS_LIST,array((int) $hash_id,(int) $kennelKy));

      #Obtain the backsliders
      $backSliderList = $app['db']->fetchAll(BACKSLIDERS_FOR_SPECIFIC_HASH_EVENT, array((int) $kennelKy,(int) $hash_id,(int) $kennelKy, (int) $hash_id));


      # Establish and set the return value
      $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
      $hashLocation = $theHashValue['EVENT_LOCATION'];
      $pageSubtitle = "Analversaries at the $hashNumber ($hashLocation) Hash";

      # Establish the return value
      $returnValue = $app['twig']->render('consolidated_event_analversaries.twig',array(
        'pageTitle' => 'Consolidated Analversaries',
        'pageSubTitle' => $pageSubtitle,
        'houndAnalversaryList' => $houndAnalversaryList,
        'consolidatedHareAnalversaryList' => $consolidatedHareAnalversaryList,
        'kennel_abbreviation' => $kennel_abbreviation,
        'geolocationHoundAnalversaryList' => $geolocationHoundAnalversaryList,
        'dateHoundAnalversaryList' => $dateHoundAnalversaryList,
        'theHashValue' => $theHashValue,
        'theStreakersList' => $theStreakersList,
        'theBackslidersList' => $backSliderList
      ));

      # Return the return value
      return $returnValue;
    }





  public function omniAnalversariesForEventAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);


    # Make a database call to obtain the hasher information
    $analversaryListHounds = $app['db']->fetchAll($this->getHoundAnalversariesForEvent(), array((int) $hash_id,(int) $kennelKy, (int) $hash_id));
    $analversaryListHares = $app['db']->fetchAll(OVERALL_HARE_ANALVERSARIES_FOR_EVENT, array((int) $hash_id,(int) $kennelKy, (int) $hash_id));

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_STATE, EVENT_CITY, NEIGHBORHOOD, COUNTY, POSTAL_CODE, ROUTE, YEAR(EVENT_DATE) AS THE_YEAR, MONTHNAME(EVENT_DATE) AS THE_MONTH, DAYNAME(EVENT_DATE) AS THE_DAY FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventState = $theHashValue['EVENT_STATE'];
    if(strlen($theHashEventState)==0){
      $theHashEventState = "UNKNOWN";
    }

    $theHashYear = $theHashValue['THE_YEAR'];
    if(strlen($theHashYear)==0){
      $theHashYear = "UNKNOWN";
    }

    $theHashMonth = $theHashValue['THE_MONTH'];
    if(strlen($theHashMonth)==0){
      $theHashMonth = "UNKNOWN";
    }

    $theHashDay = $theHashValue['THE_DAY'];
    if(strlen($theHashDay)==0){
      $theHashDay = "UNKNOWN";
    }

    $theHashEventCity = $theHashValue['EVENT_CITY'];
    if(strlen($theHashEventCity)==0){
      $theHashEventCity = "UNKNOWN";
    }

    $theHashEventNeighborhood = $theHashValue['NEIGHBORHOOD'];
    if(strlen($theHashEventNeighborhood)==0){
      $theHashEventNeighborhood = "UNKNOWN";
    }

    $theHashEventCounty = $theHashValue['COUNTY'];
    if(strlen($theHashEventCounty)==0){
      $theHashEventCounty = "UNKNOWN";
    }

    $theHashEventZip = $theHashValue['POSTAL_CODE'];
    if(strlen($theHashEventZip)==0){
      $theHashEventZip = "UNKNOWN";
    }

    $theHashEventRoute = $theHashValue['ROUTE'];
    if(strlen($theHashEventRoute)==0){
      $theHashEventRoute = "UNKNOWN";
    }

    # Declare the SQL used to retrieve this information
    $sqlHoundAnalversaryTemplate = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.XXX = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    $sqlHoundAnalversaryTemplateDateBased = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        XXX(HASHES.EVENT_DATE) = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Declare the SQL used to retrieve this information
    $sqlHareAnalversaryTemplate = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HARINGS.HARINGS_HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HARINGS ON ((HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY)))
        JOIN HASHES ON ((HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.XXX = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    $sqlHareAnalversaryTemplateDateBased = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HARINGS.HARINGS_HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HARINGS ON ((HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY)))
        JOIN HASHES ON ((HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        XXX(HASHES.EVENT_DATE) = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING ((((THE_COUNT % 5) = 0)
        OR ((THE_COUNT % 69) = 0)
        OR ((THE_COUNT % 666) = 0)
        OR (((THE_COUNT - 69) % 100) = 0)))
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Derive the various SQL statements
    $theSqlHoundState = str_replace("XXX","EVENT_STATE",$sqlHoundAnalversaryTemplate);
    $theSqlHoundCity = str_replace("XXX","EVENT_CITY",$sqlHoundAnalversaryTemplate);
    $theSqlHoundNeighborhood = str_replace("XXX","NEIGHBORHOOD",$sqlHoundAnalversaryTemplate);
    $theSqlHoundCounty = str_replace("XXX","COUNTY",$sqlHoundAnalversaryTemplate);
    $theSqlHoundZip = str_replace("XXX","POSTAL_CODE",$sqlHoundAnalversaryTemplate);
    $theSqlHoundRoad = str_replace("XXX","ROUTE",$sqlHoundAnalversaryTemplate);
    $theSqlHoundYear = str_replace("XXX","YEAR",$sqlHoundAnalversaryTemplateDateBased);
    $theSqlHoundMonth = str_replace("XXX","MONTHNAME",$sqlHoundAnalversaryTemplateDateBased);
    $theSqlHoundDayName = str_replace("XXX","DAYNAME",$sqlHoundAnalversaryTemplateDateBased);

    $theSqlHareState = str_replace("XXX","EVENT_STATE",$sqlHareAnalversaryTemplate);
    $theSqlHareCity = str_replace("XXX","EVENT_CITY",$sqlHareAnalversaryTemplate);
    $theSqlHareNeighborhood = str_replace("XXX","NEIGHBORHOOD",$sqlHareAnalversaryTemplate);
    $theSqlHareCounty = str_replace("XXX","COUNTY",$sqlHareAnalversaryTemplate);
    $theSqlHareZip = str_replace("XXX","POSTAL_CODE",$sqlHareAnalversaryTemplate);
    $theSqlHareRoad = str_replace("XXX","ROUTE",$sqlHareAnalversaryTemplate);
    $theSqlHareYear = str_replace("XXX","YEAR",$sqlHareAnalversaryTemplateDateBased);
    $theSqlHareMonth = str_replace("XXX","MONTHNAME",$sqlHareAnalversaryTemplateDateBased);
    $theSqlHareDayName = str_replace("XXX","DAYNAME",$sqlHareAnalversaryTemplateDateBased);

    # Query the datbase a bunch of times
    $theHoundStateList = $app['db']->fetchAll($theSqlHoundState, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventState ,(int) $hash_id));
    $theHoundCityList = $app['db']->fetchAll($theSqlHoundCity, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventCity ,(int) $hash_id));
    $theHoundNeighborhoodList = $app['db']->fetchAll($theSqlHoundNeighborhood, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventNeighborhood ,(int) $hash_id));
    $theHoundCountyList = $app['db']->fetchAll($theSqlHoundCounty, array((int) $hash_id,(int) $kennelKy,(string) $theHashEventCounty , (int) $hash_id));
    $theHoundZipList = $app['db']->fetchAll($theSqlHoundZip, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventZip ,(int) $hash_id));
    $theHoundRoadList = $app['db']->fetchAll($theSqlHoundRoad, array((int) $hash_id,(int) $kennelKy,(string) $theHashEventRoute , (int) $hash_id));
    $theHoundYearList = $app['db']->fetchAll($theSqlHoundYear, array((int) $hash_id,(int) $kennelKy,(string) $theHashYear , (int) $hash_id));
    $theHoundMonthList = $app['db']->fetchAll($theSqlHoundMonth, array((int) $hash_id,(int) $kennelKy,(string) $theHashMonth , (int) $hash_id));
    $theHoundDayNameList = $app['db']->fetchAll($theSqlHoundDayName, array((int) $hash_id,(int) $kennelKy,(string) $theHashDay , (int) $hash_id));

    $theHareStateList = $app['db']->fetchAll($theSqlHareState, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventState ,(int) $hash_id));
    $theHareCityList = $app['db']->fetchAll($theSqlHareCity, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventCity ,(int) $hash_id));
    $theHareNeighborhoodList = $app['db']->fetchAll($theSqlHareNeighborhood, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventNeighborhood ,(int) $hash_id));
    $theHareCountyList = $app['db']->fetchAll($theSqlHareCounty, array((int) $hash_id,(int) $kennelKy,(string) $theHashEventCounty , (int) $hash_id));
    $theHareZipList = $app['db']->fetchAll($theSqlHareZip, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventZip ,(int) $hash_id));
    $theHareRoadList = $app['db']->fetchAll($theSqlHareRoad, array((int) $hash_id,(int) $kennelKy,(string) $theHashEventRoute , (int) $hash_id));
    $theHareYearList = $app['db']->fetchAll($theSqlHareYear, array((int) $hash_id,(int) $kennelKy,(string) $theHashYear , (int) $hash_id));
    $theHareMonthList = $app['db']->fetchAll($theSqlHareMonth, array((int) $hash_id,(int) $kennelKy,(string) $theHashMonth , (int) $hash_id));
    $theHareDayNameList = $app['db']->fetchAll($theSqlHareDayName, array((int) $hash_id,(int) $kennelKy,(string) $theHashDay , (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageSubtitle = "All Analversaries at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('omni_analversary_list.twig',array(
      'pageTitle' => 'All Analversaries for this Hash',
      'pageSubTitle' => $pageSubtitle,
      'theHoundListOverall' => $analversaryListHounds,
      'theHoundListState' => $theHoundStateList,
      'theHoundListCity' => $theHoundCityList,
      'theHoundListNeighborhood' => $theHoundNeighborhoodList,
      'theHoundListCounty' => $theHoundCountyList,
      'theHoundListZip' => $theHoundZipList,
      'theHoundListRoad' => $theHoundRoadList,
      'theHoundListYear' => $theHoundYearList,
      'theHoundListMonth' => $theHoundMonthList,
      'theHoundListDay' => $theHoundDayNameList,

      'theHareListOverall' => $analversaryListHares,
      'theHareListState' => $theHareStateList,
      'theHareListCity' => $theHareCityList,
      'theHareListNeighborhood' => $theHareNeighborhoodList,
      'theHareListCounty' => $theHareCountyList,
      'theHareListZip' => $theHareZipList,
      'theHareListRoad' => $theHareRoadList,
      'theHareListYear' => $theHareYearList,
      'theHareListMonth' => $theHareMonthList,
      'theHareListDay' => $theHareDayNameList,

      'kennel_abbreviation' => $kennel_abbreviation,
      'theState' => $theHashEventState,
      'theCity' => $theHashEventCity,
      'theNeighborhood' => $theHashEventNeighborhood,
      'theCounty' => $theHashEventCounty,
      'theZip' => $theHashEventZip,
      'theRoad' => $theHashEventRoute,
      'theYear' => $theHashYear,
      'theMonth' => $theHashMonth,
      'theDay' => $theHashDay
    ));

    # Return the return value
    return $returnValue;
  }






  public function hasherCountsForEventAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (int) $hash_id));

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageSubtitle = "Hasher Counts at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => 'Hasher Counts',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }


  public function hasherCountsForEventCountyAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT COUNTY, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventCounty = $theHashValue['COUNTY'];
    if(strlen($theHashEventCounty)==0){
      $theHashEventCounty = "UNKNOWN";
    }

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.COUNTY = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventCounty, (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventCounty";
    $pageSubtitle = "Hasher Counts in $theHashEventCounty at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }

  public function hasherCountsForEventPostalCodeAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT POSTAL_CODE, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventPostalCode = $theHashValue['POSTAL_CODE'];
    if(strlen($theHashEventPostalCode)==0){
      $theHashEventPostalCode = "UNKNOWN";
    }

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.POSTAL_CODE = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventPostalCode, (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventPostalCode postal code";
    $pageSubtitle = "Hasher Counts in $theHashEventPostalCode postal code at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }


  public function hasherCountsForEventStateAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_STATE FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventState = $theHashValue['EVENT_STATE'];
    if(strlen($theHashEventState)==0){
      $theHashEventState = "UNKNOWN";
    }

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.EVENT_STATE = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventState, (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventState state";
    $pageSubtitle = "Hasher Counts in $theHashEventState state at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }


  public function hasherCountsForEventNeighborhoodAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT NEIGHBORHOOD, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventNeighborhood = $theHashValue['NEIGHBORHOOD'];
    if(strlen($theHashEventNeighborhood)==0){
      $theHashEventNeighborhood = "UNKNOWN";
    }

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.NEIGHBORHOOD = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventNeighborhood, (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventNeighborhood neighborhood";
    $pageSubtitle = "Hasher Counts in $theHashEventNeighborhood neighborhood at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }

  public function hasherCountsForEventCityAction(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    # Declare the SQL used to retrieve this information
    $sql_for_hash_event = "SELECT EVENT_CITY, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

    # Obtain information for this particular hash
    $theHashEventCity = $theHashValue['EVENT_CITY'];

    # Declare the SQL used to retrieve this information
    $sql = "SELECT
        HASHERS.HASHER_NAME AS HASHER_NAME,
        COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
        MAX(HASHINGS.HASH_KY) AS MAX_HASH_KY
    FROM
        ((HASHERS
        JOIN HASHINGS ON ((HASHERS.HASHER_KY = HASHINGS.HASHER_KY)))
        JOIN HASHES ON ((HASHINGS.HASH_KY = HASHES.HASH_KY)))
    WHERE
        (HASHERS.DECEASED = 0) AND
        HASHES.HASH_KY <= ? AND
        HASHES.KENNEL_KY = ? AND
        HASHES.EVENT_CITY = ?
    GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
    HAVING (
          (THE_COUNT % 1) = 0
      )
        AND MAX_HASH_KY = ?
    ORDER BY THE_COUNT DESC";

    # Make a database call to obtain the hasher information
    $analversaryList = $app['db']->fetchAll($sql, array((int) $hash_id,(int) $kennelKy, (string) $theHashEventCity, (int) $hash_id));

    # Establish and set the return value
    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventCity city";
    $pageSubtitle = "Hasher Counts in $theHashEventCity city at the $hashNumber ($hashLocation) Hash";

    # Establish the return value
    $returnValue = $app['twig']->render('analversary_list.twig',array(
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    # Return the return value
    return $returnValue;
  }

      public function backSlidersForEventV2Action(Request $request, Application $app, int $hash_id, string $kennel_abbreviation){

        #Obtain the kennel key
        $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

        # Declare the SQL used to retrieve this information
        $sql = BACKSLIDERS_FOR_SPECIFIC_HASH_EVENT;

        # Make a database call to obtain the hasher information
        $backSliderList = $app['db']->fetchAll($sql, array((int) $kennelKy,(int) $hash_id,(int) $kennelKy, (int) $hash_id));

        # Declare the SQL used to retrieve this information
        $sql_for_hash_event = "SELECT EVENT_DATE, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $theHashValue = $app['db']->fetchAssoc($sql_for_hash_event, array((int) $hash_id));

        # Establish and set the return value
        $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
        $hashLocation = $theHashValue['EVENT_LOCATION'];
        $pageSubtitle = "Back Sliders at the $hashNumber ($hashLocation) Hash";

        # Establish the return value
        $returnValue = $app['twig']->render('backslider_fluid_list.twig',array(
          'pageTitle' => 'Back Sliders',
          'pageSubTitle' => $pageSubtitle,
          'theList' => $backSliderList,
          'kennel_abbreviation' => $kennel_abbreviation,
          'theHashValue' => $theHashValue
        ));

      # Return the return value
      return $returnValue;
    }

public function pendingHasherAnalversariesAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->getPendingHasherAnalversariesQuery();

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #The number of harings into the future in which the analversaries will take place
  $fastForwardValue = 1;

  #The number of years absence before removing from the list...
  $yearsAbsenceLimit = 7;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $fastForwardValue, (int) $kennelKy, (int) $yearsAbsenceLimit));

  # Declare the SQL to get the most recent hash
  $sqlMostRecentHash = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION, SPECIAL_EVENT_DESCRIPTION
    FROM HASHES
    JOIN (
        SELECT MAX(HASHINGS.HASH_KY) AS HASH_KY
        FROM HASHINGS
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
        WHERE HASHES.KENNEL_KY = ?
      ) AS TEMPTABLE
    ON HASHES.HASH_KY = TEMPTABLE.HASH_KY";

  # Execute the SQL to get the most recent hash
  $theMostRecentHashValue = $app['db']->fetchAssoc($sqlMostRecentHash, array((int) $kennelKy));

  $tableCaption = "The most recent hash was: $theMostRecentHashValue[KENNEL_EVENT_NUMBER]
  at $theMostRecentHashValue[EVENT_LOCATION]";

  # Establish the return value
  $returnValue = $app['twig']->render('pending_analversary_list.twig',array(
    'pageTitle' => 'Pending Hasher Analversaries',
    'pageSubTitle' => 'The analversaries at their *next* hashes',
    'theList' => $hasherList,
    'tableCaption' => $tableCaption,
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Pending Count',
    'columnThreeName' => 'Years Absent',
    'kennel_abbreviation' => $kennel_abbreviation
  ));


  #Return the return value
  return $returnValue;

}


public function predictedHasherAnalversariesAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->getPredictedHasherAnalversariesQuery();

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $runrate=180;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy, (int) $kennelKy, (int) $kennelKy, $runrate, (int) $kennelKy, $runrate));

  # Establish the return value
  $returnValue = $app['twig']->render('predicted_analversary_list.twig',array(
    'pageTitle' => 'Predicted Hasher Analversaries (experimental)',
    'pageSubTitle' => 'Upcoming analversary predictions based on recent run rate (last '.$runrate.' days).',
    'theList' => $hasherList,
    'tableCaption' => 'Analversary Predictions',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Current Run Count',
    'columnThreeName' => 'Next Milestone',
    'columnFourName' => 'Predicted Date',
    'kennel_abbreviation' => $kennel_abbreviation
  ));


  #Return the return value
  return $returnValue;
}

public function predictedCenturionsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->getPredictedCenturionsQuery();

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $runrate=180;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy, (int) $kennelKy, (int) $kennelKy, $runrate, (int) $kennelKy, $runrate));

  # Establish the return value
  $returnValue = $app['twig']->render('predicted_analversary_list.twig',array(
    'pageTitle' => 'Predicted Centurions (experimental)',
    'pageSubTitle' => 'Upcoming centurion predictions based on recent run rate (last '.$runrate.' days).',
    'theList' => $hasherList,
    'tableCaption' => 'Centurion Predictions',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Current Run Count',
    'columnThreeName' => 'Next Milestone',
    'columnFourName' => 'Predicted Date',
    'kennel_abbreviation' => $kennel_abbreviation
  ));


  #Return the return value
  return $returnValue;
}

public function pendingHareAnalversariesAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = PENDING_HARE_ANALVERSARIES;

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #The number of harings into the future in which the analversaries will take place
  $fastForwardValue = 1;

  #The number of years absence before removing from the list...
  $yearsAbsenceLimit = 7;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $fastForwardValue, (int) $kennelKy, (int) $yearsAbsenceLimit));

  # Declare the SQL to get the most recent hash
  $sqlMostRecentHash = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION, SPECIAL_EVENT_DESCRIPTION
    FROM HASHES
    JOIN
		(
			SELECT MAX(harings_hash_ky) as HASH_KY
            FROM HARINGS
            JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
            WHERE HASHES.KENNEL_KY = ?
		) AS TEMPTABLE
    ON HASHES.HASH_KY = TEMPTABLE.HASH_KY";

  # Execute the SQL to get the most recent hash
  $theMostRecentHashValue = $app['db']->fetchAssoc($sqlMostRecentHash, array((int) $kennelKy));

  $tableCaption = "The most recent hash was: $theMostRecentHashValue[KENNEL_EVENT_NUMBER]
  at $theMostRecentHashValue[EVENT_LOCATION]";

  # Establish the return value
  $returnValue = $app['twig']->render('pending_analversary_list.twig',array(
    'pageTitle' => 'Pending Hare Analversaries',
    'pageSubTitle' => 'The analversaries at their *next* harings',
    'theList' => $hasherList,
    'tableCaption' => $tableCaption,
    'columnOneName' => 'Hare Name',
    'columnTwoName' => 'Pending Count',
    'columnThreeName' => 'Years Absent',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}

public function haringPercentageAllHashesAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->getHaringPercentageAllHashesQuery();

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #define the minimum number of hashes
  $minHashCount = 0;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy,(int) $kennelKy,(int) $minHashCount));

  # Establish the return value
  $returnValue = $app['twig']->render('percentage_list.twig',array(
    'pageTitle' => 'Haring Percentage List',
    'tableCaption' => 'Percentage of harings per hashings for each hasher',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Hashing Count',
    'columnThreeName' => 'Haring Count',
    'columnFourName' => 'Haring Percentage',
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function haringPercentageAction(Request $request, Application $app, int $hare_type, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->getHaringPercentageByHareTypeQuery();

  $hare_type_name = $this->getHareTypeName($app, $hare_type);

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #define the minimum number of hashes
  $minHashCount = 0;

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy,(int) $kennelKy, $hare_type, (int) $minHashCount));

  # Establish the return value
  $returnValue = $app['twig']->render('percentage_list.twig',array(
    'pageTitle' => $hare_type_name . ' Haring Percentage List',
    'tableCaption' => 'Percentage Of ' . $hare_type_name . ' Harings Per Hashings For Each Hasher',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Hashing Count',
    'columnThreeName' => 'Haring Count',
    'columnFourName' => 'Haring Percentage',
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;
}



public function percentageHarings(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  $args = array($kennelKy);
  $columnNames = array('Hasher Name', 'Haring Count (All)');

  # Declare the SQL used to retrieve this information
  $sql = "
    SELECT ";

  foreach ($hareTypes as &$hareType) {
    $sql .=
      'COALESCE('.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT_TEMP_TABLE.'.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT,0) AS '.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT,
      (COALESCE('.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT_TEMP_TABLE.'.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT / ALL_HARING_COUNT_TEMP_TABLE.ALL_HARING_COUNT,0) * 100) AS '.$hareType['HARE_TYPE_NAME'].'_HARINGS_PERCENTAGE,';
  }
  $sql .=" HASHERS.HASHER_NAME, HASHERS.HASHER_KY, ALL_HARING_COUNT_TEMP_TABLE.ALL_HARING_COUNT
      FROM HASHERS
      JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY, COUNT(HARINGS.HARINGS_HASHER_KY) AS ALL_HARING_COUNT
              FROM HARINGS
	      JOIN HASHES
	        ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
	     WHERE HASHES.KENNEL_KY = ?
             GROUP BY HARINGS.HARINGS_HASHER_KY) ALL_HARING_COUNT_TEMP_TABLE
        ON (HASHERS.HASHER_KY = ALL_HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY)";
  foreach ($hareTypes as &$hareType) {
    $sql .="
      LEFT JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY, COUNT(HARINGS.HARINGS_HASHER_KY) AS ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT
	      FROM HARINGS
	      JOIN HASHES
	        ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
	     WHERE HARINGS.HARE_TYPE & ? != 0
	       AND HASHES.KENNEL_KY = ?
             GROUP BY HARINGS.HARINGS_HASHER_KY) ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT_TEMP_TABLE
	ON (HASHERS.HASHER_KY = ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY)";
    array_push($args, $hareType['HARE_TYPE']);
    array_push($args, $kennelKy);
    array_push($columnNames, 'Haring Count ('.$hareType['HARE_TYPE_NAME'].')');
    array_push($columnNames, $hareType['HARE_TYPE_NAME'].' Haring Percentage');
  }
  $sql .="
     ORDER BY HASHERS.HASHER_NAME";

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, $args);

  # Establish the return value
  $returnValue = $app['twig']->render('percentage_list_multiple_values.twig',array(
    'pageTitle' => 'Haring Percentages',
    'tableCaption' => 'This shows the percentage of haring types for each hasher.',
    'columnNames' => $columnNames,
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'hareTypes' => $hareTypes
  ));

  #Return the return value
  return $returnValue;
}


function addRankToQuery(string $query, string $selectClause, string $countColumn) {
  return
    "SELECT IF($countColumn=@_last_count,@curRank:=@curRank,@curRank:=@_sequence) AS THE_RANK,
            @_sequence:=@_sequence+1,@_last_count:=$countColumn,
            $selectClause
       FROM ($query) AS INNER_QUERY,
     (SELECT @curRank:=1, @_sequence:=1, @_last_count:=0) AS VARS";
}



public function hashingCountsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->addRankToQuery($this->getHashingCountsQuery(), "THE_KEY, NAME, VALUE", "VALUE");

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('name_number_rank_list.twig',array(
    'pageTitle' => 'Hasher Counts',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Hash Count',
    'tableCaption' => 'Hashers, and the number of hashes they have done. More is better.',
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => 'HashCounts'
  ));

  #Return the return value
  return $returnValue;

}


public function haringCountsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Declare the SQL used to retrieve this information
  $sql = $this->addRankToQuery(HARING_COUNTS, "THE_KEY, NAME, VALUE", "VALUE");

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('name_number_rank_list.twig',array(
    'pageTitle' => 'Haring Counts',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Haring Count',
    'tableCaption' => 'Hares, and the number of times they have hared. More is better.',
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => 'HoundCounts'
  ));

  #Return the return value
  return $returnValue;

}

public function haringTypeCountsAction(Request $request, Application $app, string $kennel_abbreviation, int $hare_type) {

  # Declare the SQL used to retrieve this information
  $sql = $this->addRankToQuery(HARE_TYPE_HARING_COUNTS, "THE_KEY, NAME, VALUE", "VALUE");

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hare_type_name = $this->getHareTypeName($app, $hare_type);

  #Execute the SQL statement; create an array of rows
  $hasherList = $app['db']->fetchAll($sql, array((int) $hare_type, (int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('name_number_rank_list.twig',array(
    'pageTitle' => $hare_type_name.' Haring Counts',
    'columnOneName' => 'Hare Name',
    'columnTwoName' => 'Hash Count',
    'tableCaption' => 'Hares, and the number of hashes they have hared. More is better.',
    'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => $hare_type_name.'HareCounts'
  ));

  #Return the return value
  return $returnValue;
}

  public function coharelistByHareAllHashesAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
      	TEMPTABLE.HASHER_NAME,TEMPTABLE.HARINGS_HASHER_KY AS HASHER_KY,
          HASHES.KENNEL_EVENT_NUMBER,
          HASHES.SPECIAL_EVENT_DESCRIPTION,
          HASHES.EVENT_LOCATION,
          HASHES.HASH_KY
      FROM
      	HARINGS JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
          JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
          JOIN (
      		SELECT
      			HARINGS_HASH_KY,
                  HASHER_NAME,
                  HARINGS_HASHER_KY
      		FROM
      			HARINGS
                  JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
      		) TEMPTABLE ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
      WHERE
      	HARINGS.HARINGS_HASHER_KY = ?
          AND TEMPTABLE.HARINGS_HASHER_KY <> ?
          AND HASHES.KENNEL_KY = ?
      ORDER BY HASHES.EVENT_DATE, TEMPTABLE.HASHER_NAME ASC";

    #Execute the SQL statement; create an array of rows
    $cohareList = $app['db']->fetchAll($sql,array((int) $hasher_id, (int) $hasher_id, (int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've had the shame of haring with $hasherName";
    $returnValue = $app['twig']->render('cohare_list.twig',array(
      'pageTitle' => 'Cohare List (All Hashes)',
      'pageSubTitle' => 'All Hashes',
      'tableCaption' => $captionValue,
      'theList' => $cohareList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));



    #Return the return value
    return $returnValue;

  }


  public function coharelistByHareAction(Request $request, Application $app, int $hasher_id, int $hare_type, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
      	TEMPTABLE.HASHER_NAME, TEMPTABLE.HARINGS_HASHER_KY AS HASHER_KY,
          HASHES.KENNEL_EVENT_NUMBER,
          HASHES.SPECIAL_EVENT_DESCRIPTION,
          HASHES.EVENT_LOCATION,
          HASHES.HASH_KY
      FROM
      	HARINGS JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
          JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
          JOIN (
      		SELECT
      			HARINGS_HASH_KY,
                  HASHER_NAME,
                  HARINGS_HASHER_KY
      		FROM
      			HARINGS
                  JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
      		) TEMPTABLE ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
      WHERE
      	HARINGS.HARINGS_HASHER_KY = ?
          AND TEMPTABLE.HARINGS_HASHER_KY <> ?
          AND HARINGS.HARE_TYPE & ? != 0 AND HASHES.KENNEL_KY = ?
      ORDER BY HASHES.EVENT_DATE, TEMPTABLE.HASHER_NAME ASC";

    #Execute the SQL statement; create an array of rows
    $cohareList = $app['db']->fetchAll($sql,array($hasher_id, $hasher_id, $hare_type, (int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    $hare_type_name = $this->getHareTypeName($app, $hare_type);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've had the shame of haring with $hasherName";
    $returnValue = $app['twig']->render('cohare_list.twig',array(
      'pageTitle' => $hare_type_name . ' Cohare List',
      'pageSubTitle' => '',
      'tableCaption' => $captionValue,
      'theList' => $cohareList,
      'kennel_abbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;

  }


  public function cohareCountByHareAllHashesAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
           TEMPTABLE.HARINGS_HASHER_KY AS THE_KEY,
      	   TEMPTABLE.HASHER_NAME AS NAME,
           COUNT(*) AS VALUE
      FROM
      	HARINGS
          JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
          JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
          JOIN (
      		SELECT
      			HARINGS_HASH_KY,
                  HASHER_NAME,
                  HARINGS_HASHER_KY
      		FROM
      			HARINGS
                  JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
      		) TEMPTABLE ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
      WHERE
      	HARINGS.HARINGS_HASHER_KY = ?
          AND TEMPTABLE.HARINGS_HASHER_KY <> ?
          AND HASHES.KENNEL_KY = ?
      GROUP BY TEMPTABLE.HARINGS_HASHER_KY, TEMPTABLE.HASHER_NAME
      ORDER BY VALUE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $hasher_id, (int) $hasher_id, (int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've hared with  $hasherName";
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => 'Hare Counts (All Hashes)',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hare Count',
      'tableCaption' => $captionValue,
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'CoHareList'
    ));

    #Return the return value
    return $returnValue;

  }

  public function cohareCountByHareAction(Request $request, Application $app, int $hasher_id, int $hare_type, string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Define the SQL to execute
    $sql = "SELECT
        TEMPTABLE.HARINGS_HASHER_KY AS THE_KEY,
      	TEMPTABLE.HASHER_NAME AS NAME,
          COUNT(*) AS VALUE
      FROM
      	HARINGS
          JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
          JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
          JOIN (
      		SELECT
      			HARINGS_HASH_KY,
                  HASHER_NAME,
                  HARINGS_HASHER_KY
      		FROM
      			HARINGS
                  JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
      		) TEMPTABLE ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
      WHERE
      	HARINGS.HARINGS_HASHER_KY = ?
          AND TEMPTABLE.HARINGS_HASHER_KY <> ?
          AND HARINGS.HARE_TYPE & ? != 0 AND HASHES.KENNEL_KY = ?
      GROUP BY TEMPTABLE.HARINGS_HASHER_KY, TEMPTABLE.HASHER_NAME
      ORDER BY VALUE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array($hasher_id, $hasher_id, $hare_type, (int) $kennelKy));

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    $hare_type_name = $this->getHareTypeName($app, $hare_type);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've hared with  $hasherName";
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => $hare_type_name.' Hare Counts',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hare Count',
      'tableCaption' => $captionValue,
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'CoHareList'.$hare_type_name.'Harings'
    ));

    #Return the return value
    return $returnValue;
  }

  public function hashAttendanceByHareLowestAction(Request $request, Application $app, string $kennel_abbreviation){

    #Define the SQL to execute
    $sql = LOWEST_HASH_ATTENDANCE_BY_HARE;

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $kennelKy));

    # Establish and set the return value
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => 'Lowest hash attendance by hare',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hasher Count',
      'tableCaption' => 'The lowest hash attendance for each hare.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'LowestHashAttendanceByHare'
    ));

    #Return the return value
    return $returnValue;

  }


public function hashAttendanceByHareHighestAction(Request $request, Application $app, string $kennel_abbreviation){

  #Define the SQL to execute
  $sql = HIGHEST_HASH_ATTENDANCE_BY_HARE;

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Execute the SQL statement; create an array of rows
  $hashList = $app['db']->fetchAll($sql,array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('name_number_list.twig',array(
    'pageTitle' => 'Highest attended hashes by hare',
    'columnOneName' => 'Hare Name',
    'columnTwoName' => 'Hasher Count',
    'tableCaption' => 'The highest attended hashes for each hare.',
    'theList' => $hashList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => 'HighestHashAttendanceByHare'
  ));

  #Return the return value
  return $returnValue;
}



  public function hashAttendanceByHareAverageAction(Request $request, Application $app, string $kennel_abbreviation){

    #Define the SQL to execute
    $sql = AVERAGE_HASH_ATTENDANCE_BY_HARE;

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $kennelKy));

    # Establish and set the return value
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => 'Average hash attendance by hare',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hasher Count',
      'tableCaption' => 'The average hash attendance for each hare.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'AverageHashAttendanceByHare'
    ));

    #Return the return value
    return $returnValue;
  }


  public function hashAttendanceByHareGrandTotalNonDistinctHashersAction(Request $request, Application $app, string $kennel_abbreviation){

    #Define the SQL to execute
    $sql = GRANDTOTAL_NONDISTINCT_HASH_ATTENDANCE_BY_HARE;

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $app['db']->fetchAll($sql,array((int) $kennelKy));

    # Establish and set the return value
    $returnValue = $app['twig']->render('name_number_list.twig',array(
      'pageTitle' => 'Total (non distinct) hashers at their hashes',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => 'If hasher X has done 100 of hare Y\'s events, they contribute 100 to the hash count.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'TotalHashAttendanceByHareNonDistinct'
    ));

    #Return the return value
    return $returnValue;
  }

public function hashAttendanceByHareGrandTotalDistinctHashersAction(Request $request, Application $app, string $kennel_abbreviation){

  #Define the SQL to execute
  $sql = GRANDTOTAL_DISTINCT_HASH_ATTENDANCE_BY_HARE;

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Execute the SQL statement; create an array of rows
  $hashList = $app['db']->fetchAll($sql,array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('name_number_list.twig',array(
    'pageTitle' => 'Total distinct hashers at their hashes',
    'columnOneName' => 'Hare Name',
    'columnTwoName' => 'Hash Count',
    'tableCaption' => 'If hasher X has done 100 of hare Y\'s events, they contribute 1 to the hash count.',
    'theList' => $hashList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => 'TotalHashAttendanceByHareDistinct'
  ));

  #Return the return value
  return $returnValue;

}

public function hasherCountsByHareAction(Request $request, Application $app, int $hare_id, int $hare_type, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Define the SQL to execute
  $sql = "SELECT
      HASHERS.HASHER_KY AS THE_KEY,
    	HASHERS.HASHER_NAME AS NAME,
        COUNT(*) AS VALUE
    FROM
    	HARINGS
        JOIN HASHINGS ON HARINGS.HARINGS_HASH_KY = HASHINGS.HASH_KY
        JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
    WHERE
    	HARINGS.HARINGS_HASHER_KY = ?
        AND HASHINGS.HASHER_KY != ?
        AND HASHES.KENNEL_KY = ? " .
        ($hare_type != 0 ? "AND HARINGS.HARE_TYPE & ? != 0 " : "AND HARINGS.HARE_TYPE != ?") . "
    GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
    ORDER BY VALUE DESC, NAME";

  #Execute the SQL statement; create an array of rows
  $hashList = $app['db']->fetchAll($sql,array($hare_id, $hare_id, (int) $kennelKy, $hare_type));

  # Declare the SQL used to retrieve this information
  $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

  # Make a database call to obtain the hasher information
  $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hare_id));

  $hare_type_name = $this->getHareTypeName($app, $hare_type);

  # Establish and set the return value
  $hasherName = $hasher['HASHER_NAME'];
  $captionValue = "The hashers who've hashed under the " . $hare_type_name . " hare, $hasherName";
  $returnValue = $app['twig']->render('name_number_list.twig',array(
    'pageTitle' => 'Hasher Counts',
    'columnOneName' => 'Hasher Name',
    'columnTwoName' => 'Hash Count',
    'tableCaption' => $captionValue,
    'theList' => $hashList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageTracking' => 'HasherCountsByHare'
  ));

  #Return the return value
  return $returnValue;

}




public function basicStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #SQL to determine the distinct year values
  $sql = "SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
  FROM HASHES
  WHERE
    KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE) DESC";

  #Execute the SQL statement; create an array of rows
  $yearValues = $app['db']->fetchAll($sql,array( (int) $kennelKy));

  #Obtain the first hash
  $firstHashSQL = "SELECT * FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE ASC LIMIT 1";
  $firstHashValue = $app['db']->fetchAssoc($firstHashSQL, array((int) $kennelKy));

  #Obtain the most recent hash
  $mostRecentHashSQL = "SELECT * FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE DESC LIMIT 1";
  $mostRecentHashValue = $app['db']->fetchAssoc($mostRecentHashSQL, array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('basic_stats.twig',array(
    'pageTitle' => 'Basic Information and Statistics',
    'kennel_abbreviation' => $kennel_abbreviation,
    'first_hash' => $firstHashValue,
    'latest_hash' => $mostRecentHashValue,
    'theYearValues' => $yearValues
  ));

  #Return the return value
  return $returnValue;

}


public function peopleStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  # Establish and set the return value
  $returnValue = $app['twig']->render('section_people.twig',array(
    'pageTitle' => 'People Stats',
    'hare_types' => count($hareTypes) > 1 ? $hareTypes : "",
    'overall' => count($hareTypes) > 1 ? "Overall " : "",
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function analversariesStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Determine the number of hashes already held for this kennel
  $sql2 = $this->getHashingCountsQuery();
  $sql2 = "$sql2 LIMIT 1";
  $theCount2 = $app['db']->fetchAssoc($sql2, array((int) $kennelKy));
  $theCount2 = $theCount2['VALUE'];

  # Establish and set the return value
  $returnValue = $app['twig']->render('section_analversaries.twig',array(
    'pageTitle' => 'Analversary Stats',
    'kennel_abbreviation' => $kennel_abbreviation,
    'the_count' => $theCount2
  ));

  #Return the return value
  return $returnValue;

}

public function yearByYearStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #SQL to determine the distinct year values
  $sql = "SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
  FROM HASHES
  WHERE
    KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE) DESC";

  #Execute the SQL statement; create an array of rows
  $yearValues = $app['db']->fetchAll($sql,array( (int) $kennelKy));

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  # Establish and set the return value
  $returnValue = $app['twig']->render('section_year_by_year.twig',array(
    'pageTitle' => 'Year Summary Stats',
    'kennel_abbreviation' => $kennel_abbreviation,
    'year_values' => $yearValues,
    'hare_types' => count($hareTypes) > 1 ? $hareTypes : array(),
    'overall' => count($hareTypes) > 1 ? " (Overall)" : ""
  ));

  #Return the return value
  return $returnValue;

}

public function kennelRecordsStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  # Establish and set the return value
  $returnValue = $app['twig']->render('section_kennel_records.twig',array(
    'pageTitle' => 'Kennel Records',
    'kennel_abbreviation' => $kennel_abbreviation,
    "hare_types" => count($hareTypes) > 1 ? $hareTypes : array()
  ));

  #Return the return value
  return $returnValue;

}


public function kennelGeneralInfoStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  #Obtain the first hash
  $firstHashSQL = "SELECT HASH_KY, EVENT_DATE, KENNEL_EVENT_NUMBER FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE ASC LIMIT 1";
  $firstHashValue = $app['db']->fetchAssoc($firstHashSQL, array((int) $kennelKy));

  #Obtain the most recent hash
  $mostRecentHashSQL = "SELECT HASH_KY, EVENT_DATE, KENNEL_EVENT_NUMBER FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE DESC LIMIT 1";
  $mostRecentHashValue = $app['db']->fetchAssoc($mostRecentHashSQL, array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('section_kennel_general_info.twig',array(
    'pageTitle' => 'Kennel General Info',
    'kennel_abbreviation' => $kennel_abbreviation,
    'first_hash' => $firstHashValue,
    'latest_hash' => $mostRecentHashValue,
    'hare_types' => $hareTypes
  ));

  #Return the return value
  return $returnValue;

}


public function hashingStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Establish and set the return value
  $returnValue = $app['twig']->render('hashing_stats.twig',array(
    'pageTitle' => 'Hashing Statistics',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function haringStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Establish and set the return value
  $returnValue = $app['twig']->render('haring_stats.twig',array(
    'pageTitle' => 'Haring Statistics',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function analversaryStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  # Establish and set the return value
  $returnValue = $app['twig']->render('analversary_stats.twig',array(
    'pageTitle' => 'Analversary Statistics',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function cautionaryStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Establish the hasher keys for all hares for this kennel
  $hareKeysSQL = "SELECT HARINGS_HASHER_KY AS HARE_KEY
    FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
    WHERE HASHES.KENNEL_KY = ? ORDER BY RAND() LIMIT 5";

  #Execute the SQL statement; create an array of rows
  $hareKeys = $app['db']->fetchAll($hareKeysSQL,array( (int) $kennelKy));

  #Establish an array of ridiculous statistics
  $arrayOfRidiculousness = array(
    "Hashes where VD was contracted",
    "Hashes where someone got pregnant",
    "Hashes where someone coveted their neighbor's wife",
    "Hashes where hashers were mocked for their Kentucky heritage",
    "Hashes where hashers were mocked for their Michigan heritage",
    "Hashes where people did it on trail",
    "Hashes where a hasher was arrested",
    "Hashes where the police showed up",
    "Hashes where the streams were crossed",
    "Hashes where no harriettes showed up",
    "Hashes that could have used better beer",
    "Hashes that could have used a better trail",
    "Hashes that could have used better hares",
    "Hashes that caused somebody to move away",
    "Hashes where someone shat on trail",
    "Hashes where someone shat themselves",
    "Hashes where someone called the police on us",
    "Hashes that brought great shame to everyone involved",
    "Hashes where dogs did it on trail"

  );

  #Establish the keys of the random values to display
  $randomKeysForRidiculousStats = array_rand($arrayOfRidiculousness, 5);

  # Establish and set the return value
  $returnValue = $app['twig']->render('cautionary_stats.twig',array(
    'listOfRidiculousness' => $arrayOfRidiculousness,
    'randomKeysForRidiculousStats' => $randomKeysForRidiculousStats,
    'pageTitle' => 'Cautionary Statistics',
    'kennel_abbreviation' => $kennel_abbreviation,
    'hareKeys' => $hareKeys
  ));

  #Return the return value
  return $returnValue;

}


public function miscellaneousStatsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #SQL to determine the distinct year values
  $sql = "SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
  FROM HASHES
  WHERE
  	KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE) DESC";

  #Execute the SQL statement; create an array of rows
  $yearValues = $app['db']->fetchAll($sql,array( (int) $kennelKy));

  #Obtain the first hash
  $firstHashSQL = "SELECT * FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE ASC LIMIT 1";
  $firstHashValue = $app['db']->fetchAssoc($firstHashSQL, array((int) $kennelKy));

  #Obtain the most recent hash
  $mostRecentHashSQL = "SELECT * FROM HASHES WHERE KENNEL_KY = ? ORDER BY EVENT_DATE DESC LIMIT 1";
  $mostRecentHashValue = $app['db']->fetchAssoc($mostRecentHashSQL, array((int) $kennelKy));

  #Obtain the kennels that are being tracked in this website instance
  $listOfKennelsSQL = "
    SELECT KENNEL_ABBREVIATION, KENNEL_NAME, IN_RECORD_KEEPING, SITE_ADDRESS
    FROM KENNELS WHERE IN_RECORD_KEEPING = 1 OR SITE_ADDRESS IS NOT NULL
    ORDER BY IN_RECORD_KEEPING DESC, KENNEL_ABBREVIATION ASC";
  $kennelValues = $app['db']->fetchAll($listOfKennelsSQL);

  # Establish and set the return value
  $returnValue = $app['twig']->render('switch_kennel_screen.twig',array(
    'firstEvent' => $firstHashValue,
    'mostRecentEvent' => $mostRecentHashValue,
    'theYearValues' => $yearValues,
    'pageTitle' => 'Switch Kennel',
    'kennel_abbreviation' => $kennel_abbreviation,
    'kennelValues' => $kennelValues
  ));

  #Return the return value
  return $returnValue;

}


public function highestAttendedHashesAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Define the sql
  $theSql = HASH_EVENTS_WITH_COUNTS;
  $theSql = str_replace("XLIMITX","25",$theSql);
  $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

  #Execute the SQL statement; create an array of rows
  $theList = $app['db']->fetchAll($theSql,array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('hash_events_with_participation_counts.twig',array(
    'theList' => $theList,
    'pageTitle' => 'The Hashes',
    'pageSubTitle' => '...with the best attendances',
    'tableCaption' => '',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}


public function lowestAttendedHashesAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Define the sql
  $theSql = HASH_EVENTS_WITH_COUNTS;
  $theSql = str_replace("XLIMITX","25",$theSql);
  $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

  #Execute the SQL statement; create an array of rows
  $theList = $app['db']->fetchAll($theSql,array((int) $kennelKy));

  # Establish and set the return value
  $returnValue = $app['twig']->render('hash_events_with_participation_counts.twig',array(
    'theList' => $theList,
    'pageTitle' => 'The Hashes',
    'pageSubTitle' => '...with the worst attendances',
    'tableCaption' => '',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}

public function hashersOfTheYearsAction(Request $request, Application $app, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #SQL to determine the distinct year values
  $distinctYearsSql = "SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
  FROM HASHES
  WHERE
  	KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE) DESC";

  #Execute the SQL statement; create an array of rows
  $yearValues = $app['db']->fetchAll($distinctYearsSql,array( (int) $kennelKy));

  #Define the sql
  $topHashersSql = "SELECT HASHER_KY, HASHER_NAME, THE_COUNT, ? AS THE_YEAR,
	  (SELECT COUNT(*) AS THE_HASH_COUNT FROM HASHES WHERE KENNEL_KY = ? AND YEAR(HASHES.EVENT_DATE) = ?) AS THE_YEARS_HASH_COUNT,
    (THE_TEMPORARY_TABLE.THE_COUNT / (SELECT COUNT(*) AS THE_HASH_COUNT FROM HASHES WHERE KENNEL_KY = ? AND YEAR(HASHES.EVENT_DATE) = ?))*100 AS HASHING_PERCENTAGE
  FROM HASHERS JOIN (
    	SELECT HASHERS.HASHER_KY AS THE_HASHER_KY, COUNT(*) AS THE_COUNT
    	FROM HASHINGS
    		JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
    		JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
    	WHERE
    		HASHES.KENNEL_KY = ?
    		AND YEAR(HASHES.EVENT_DATE) = ?
    	GROUP BY HASHERS.HASHER_KY
    	ORDER BY THE_COUNT DESC
    	LIMIT XLIMITX
    ) AS THE_TEMPORARY_TABLE on HASHERS.HASHER_KY = THE_TEMPORARY_TABLE.THE_HASHER_KY";
  $topHashersSql = str_replace("XLIMITX","12",$topHashersSql);


  #Initialize the array of arrays
  $array = array();

  #Loop through the year values
  for ($tempCounter = 1; $tempCounter <= sizeof($yearValues); $tempCounter++){

    #Establish the year for this loop iteration
    $tempYear = $yearValues[$tempCounter-1]["YEAR"];

    #Make a database call passing in this iteration's year value
    $tempResult = $app['db']->fetchAll($topHashersSql,array(
      (int) $tempYear,
      (int) $kennelKy,
      (int) $tempYear,
      (int) $kennelKy,
      (int) $tempYear,
      (int) $kennelKy,
      (int) $tempYear));

    #Add the database result set to the array of arrays
    $array[] = $tempResult;

  }



  # Establish and set the return value
  $returnValue = $app['twig']->render('top_hashers_by_years.twig',array(
    'theListOfLists' => $array,
    #'tempList' => $tempResult,
    'pageTitle' => 'Top Hashers Per Year',
    'pageSubTitle' => '',
    'tableCaption' => '',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  #Return the return value
  return $returnValue;

}



public function HaresOfTheYearsAction(Request $request, Application $app, int $hare_type, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #SQL to determine the distinct year values
  $distinctYearsSql = "SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
  FROM HASHES
  WHERE
  	KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE) DESC";

  #Execute the SQL statement; create an array of rows
  $yearValues = $app['db']->fetchAll($distinctYearsSql,array( (int) $kennelKy));

  $hashTypes = $this->getHashTypes($app, $kennelKy, $hare_type);

  #Define the sql
  $topHaresSql = "SELECT HASHER_KY, HASHER_NAME, THE_COUNT, ? AS THE_YEAR,";
  foreach ($hashTypes as &$hashType) {
    $topHaresSql .=
      "(SELECT COUNT(*) AS THE_HASH_COUNT FROM HASHES WHERE KENNEL_KY = ? AND YEAR(HASHES.EVENT_DATE) = ? AND HASHES.HASH_TYPE = ?) AS THE_YEARS_".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
    }
    $topHaresSql .=
        "(SELECT COUNT(*) AS THE_HASH_COUNT
            FROM HASHES ".
         ($hare_type == 0 ? "" : "JOIN KENNELS ON HASHES.KENNEL_KY = KENNELS.KENNEL_KY
                                  JOIN HASH_TYPES ON HASH_TYPES.HASH_TYPE & KENNELS.HASH_TYPE_MASK != 0 AND HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE")."
           WHERE HASHES.KENNEL_KY = ? ".
         ($hare_type == 0 ? "" : "AND HASH_TYPES.HARE_TYPE_MASK & ? != 0")."
             AND YEAR(HASHES.EVENT_DATE) = ? )
              AS THE_YEARS_OVERALL_HASH_COUNT
    FROM
    HASHERS JOIN (
    	SELECT HASHERS.HASHER_KY AS THE_HASHER_KY, COUNT(*) AS THE_COUNT
    	FROM HARINGS
    		JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
    		JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
    	WHERE
    		HASHES.KENNEL_KY = ?
    		AND YEAR(HASHES.EVENT_DATE) = ? ".
                ($hare_type == 0 ? "" : "AND HARINGS.HARE_TYPE & ? != 0 ")."
    	GROUP BY HASHERS.HASHER_KY
    	ORDER BY THE_COUNT DESC
    	LIMIT XLIMITX
    ) AS THE_TEMPORARY_TABLE on HASHERS.HASHER_KY = THE_TEMPORARY_TABLE.THE_HASHER_KY";

  $topHaresSql = str_replace("XLIMITX","12",$topHaresSql);

  #Initialize the array of arrays
  $array = array();

  #Loop through the year values
  for ($tempCounter = 1; $tempCounter <= sizeof($yearValues); $tempCounter++){

    #Establish the year for this loop iteration
    $tempYear = $yearValues[$tempCounter-1]["YEAR"];

    $args = array((int) $tempYear);
    foreach ($hashTypes as &$hashType) {
      array_push($args, (int) $kennelKy);
      array_push($args, (int) $tempYear);
      array_push($args, (int) $hashType['HASH_TYPE']);
    }
    array_push($args, (int) $kennelKy);
    if($hare_type != 0) array_push($args, $hare_type);
    array_push($args, (int) $tempYear);
    array_push($args, (int) $kennelKy);
    array_push($args, (int) $tempYear);
    if($hare_type != 0) array_push($args, $hare_type);

    #Make a database call passing in this iteration's year value
    $tempResult = $app['db']->fetchAll($topHaresSql,$args);

    #Add the database result set to the array of arrays
    $array[] = $tempResult;
  }

  if($hare_type != 0) {
    $hare_type_name = $this->getHareTypeName($app, $hare_type);
  }

  # Establish and set the return value
  $returnValue = $app['twig']->render('top_hares_by_years.twig',array(
    'theListOfLists' => $array,
    'pageTitle' => $hare_type == 0 ? 'Top Hares Per Year (All harings)' : 'Top '.$hare_type_name.' Hares Per Year',
    'pageSubTitle' => $hare_type == 0 ? '(All hashes included)' : '',
    'tableCaption' => '',
    'kennel_abbreviation' => $kennel_abbreviation,
    'participant_column_header' => 'Hasher',
    'number_column_header' => $hare_type == 0 ? 'Number Of Overall Harings' : 'Number Of '.$hare_type_name.' Harings',
    'percentage_column_header' => $hare_type == 0 ? 'Percentage of overall hashes hared' : 'Percentage of hashes hared',
    'hash_types' => $hashTypes
  ));

  #Return the return value
  return $returnValue;
}


public function getHasherAnalversariesAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  # Define the SQL to retrieve all of their hashes
  $sql_all_hashes_for_this_hasher = "	SELECT
	HASHERS.HASHER_KY, HASHERS.HASHER_NAME, HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_DATE, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION
	FROM HASHINGS
		JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
		JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
	WHERE
		HASHERS.HASHER_KY = ?
		AND HASHES.KENNEL_KY = ?
	ORDER By HASHES.EVENT_DATE ASC";

  #Retrieve all of this hasher's hashes
  $theInitialListOfHashes = $app['db']->fetchAll($sql_all_hashes_for_this_hasher,array(
        (int) $hasher_id,
        (int) $kennelKy));

  # Add a count into their list of hashes
  $destinationArray = array();
  $tempCounter = 1;
  foreach ($theInitialListOfHashes as &$individualHash) {
    $individualHash['ANALVERSARY_NUMBER'] = $tempCounter;
    if(
      ($tempCounter % 5 == 0) ||
      ($tempCounter % 69 == 0) ||
      ($tempCounter % 666 == 0) ||
      (($tempCounter - 69) % 100 == 0)
      ){
      array_push($destinationArray,$individualHash);
    }
    $tempCounter ++;
  }

  # Establish and set the return value
  $hasherName = $theInitialListOfHashes[0]['HASHER_NAME'];
  $pageTitle = "Hashing Analversaries: $hasherName";
  $returnValue = $app['twig']->render('hasher_analversary_list.twig',array(
    'theList' => $destinationArray,
    'pageTitle' => $pageTitle,
    'pageSubTitle' => '',
    'tableCaption' => '',
    'kennel_abbreviation' => $kennel_abbreviation,
    'participant_column_header' => 'Hasher',
    'overall_boolean' => 'FALSE'
  ));

  #Return the return value
  return $returnValue;



}


public function getProjectedHasherAnalversariesAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation){

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  # Declare the SQL used to retrieve this information
  $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

  # Make a database call to obtain the hasher information
  $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

  #Define the sql that performs the filtering
  $sql = "SELECT
      HASHER_NAME,
      HASH_COUNT,
      LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE,
      FIRST_HASH_KEY,
  	  FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
      FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE,
      LATEST_HASH_KEY,
      LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER,
      HASHER_KY,
      (DATEDIFF(CURDATE(),FIRST_HASH.EVENT_DATE)) AS DAYS_SINCE_FIRST_HASH,
      ((DATEDIFF(CURDATE(),FIRST_HASH.EVENT_DATE)) / HASH_COUNT) AS DAYS_BETWEEN_HASHES
  FROM
  	(
  	SELECT
  		HASHER_NAME, HASHER_KY,
  		HASHERS.HASHER_KY AS OUTER_HASHER_KY,
  		(
  			SELECT COUNT(*)
  			FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
  			WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
          AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)) AS HASH_COUNT,
  		(
  			SELECT HASHES.HASH_KY
  			FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
  			WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
          AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
              ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY,
  		(
  			SELECT HASHES.HASH_KY
  			FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
  			WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
          AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
              ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
  	FROM
  		HASHERS
  )
  MAIN_TABLE
  JOIN HASHES LATEST_HASH ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
  JOIN HASHES FIRST_HASH ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
  WHERE HASHER_KY = ? ";

  # Make a database call to obtain the hasher information
  $numberOfDaysInDateRange = 360000;
  $hasherStatsObject = $app['db']->fetchAssoc($sql, array(
    (int) $kennelKy,
    (int) $numberOfDaysInDateRange,
    (int) $kennelKy,
    (int) $numberOfDaysInDateRange,
    (int) $kennelKy,
    (int) $numberOfDaysInDateRange,
    (int) $hasher_id));
  $hasherStatsHashCount = $hasherStatsObject['HASH_COUNT'];
  $hasherStatsDaysSinceFirstHash = $hasherStatsObject['DAYS_SINCE_FIRST_HASH'];
  $hasherStatsDaysPerHash = $hasherStatsObject['DAYS_BETWEEN_HASHES'];

  $numberOfDaysInRecentDateRange = 365;
  $hasherRecentStatsObject = $app['db']->fetchAssoc($sql, array(
    (int) $kennelKy,
    (int) $numberOfDaysInRecentDateRange,
    (int) $kennelKy,
    (int) $numberOfDaysInRecentDateRange,
    (int) $kennelKy,
    (int) $numberOfDaysInRecentDateRange,
    (int) $hasher_id));
  if(empty($hasherRecentStatsObject)){
    $recentEventCount = 0;
    $recentDaysPerHash =  "Infinity";
  }else{
    $recentEventCount = $hasherRecentStatsObject['HASH_COUNT'];
    $recentDaysPerHash =  $hasherRecentStatsObject['DAYS_BETWEEN_HASHES'];
  }





  #Project out the next bunch of hash analversaries

  # Add a count into their list of hashes
  $destinationArray = array();

  #Loop through 500 events
  for ($x = 1; $x <= 750; $x++) {
    $incrementedHashCount = $hasherStatsHashCount + $x;
    if(
      ($incrementedHashCount % 25 == 0) ||
      ($incrementedHashCount % 69 == 0) ||
      ($incrementedHashCount % 666 == 0) ||
      (($incrementedHashCount - 69) % 100 == 0)
      ){

        $daysToAdd = round($hasherStatsDaysPerHash * $x);
        $nowDate = date("Y/m/d");
        #$app['monolog']->addDebug("XX:nowDate $nowDate");
        #$incrementedDate = strtotime($nowDate."+ 2 days");

        $incrementedDateOverall = date('Y-m-d',strtotime($nowDate) + (24*3600*$daysToAdd));

        if(empty($hasherRecentStatsObject)){
          $daysToAddRecent = "infinity";
          $incrementedDateRecent = null;
        }else{
          $daysToAddRecent = round($recentDaysPerHash * $x);
          $incrementedDateRecent = date('Y-m-d',strtotime($nowDate) + (24*3600*$daysToAddRecent));
        }

        #$app['monolog']->addDebug("XD:incrementedHashCount $incrementedHashCount");
        #$app['monolog']->addDebug("XE:daysToAdd $daysToAdd");
        #$app['monolog']->addDebug("XF:date $date");

        $obj = [
          'incrementedHashCount' => $incrementedHashCount,
          'incrementedDateOverall' => $incrementedDateOverall,
          'daysAddedOverall' => $daysToAdd,
          'incrementedDateRecent' => $incrementedDateRecent,
          'daysAddedRecent' => $daysToAddRecent
        ];


      array_push($destinationArray,$obj);
    }
  }

  # Establish and set the return value
  $hasherName = $hasher['HASHER_NAME'];
  $pageTitle = "Projected Hashing Analversaries";
  $returnValue = $app['twig']->render('projected_hasher_analversary_list.twig',array(
    'theList' => $destinationArray,
    'pageTitle' => $pageTitle,
    'pageSubTitle' => $hasherName,
    'tableCaption' => 'The projected analversaries are based on how many hashes this hasher has done, and how frequently this hasher has hashed them. It applies their days between hashes average and projects out when they might hit certain analversaries.',
    'kennel_abbreviation' => $kennel_abbreviation,
    'participant_column_header' => 'Hasher',
    'overall_boolean' => 'FALSE',
    'firstHashKey' => $hasherStatsObject['FIRST_HASH_KEY'],
    'firstKennelEventNumber' => $hasherStatsObject['FIRST_KENNEL_EVENT_NUMBER'],
    'firstEventDate' => $hasherStatsObject['FIRST_EVENT_DATE'],
    'overallRunRate' => $hasherStatsDaysPerHash,
    'recentDateRangeInDays' => $numberOfDaysInRecentDateRange,
    'recentRunRate' => $recentDaysPerHash,
    'overallHashCount' => $hasherStatsHashCount,
    'recentHashCount' => $recentEventCount
  ));

  #Return the return value
  return $returnValue;



}



#Define the action
public function jumboCountsTablePreActionJson(Request $request, Application $app, string $kennel_abbreviation){

  #Establish the subTitle
  $minimumHashCount = JUMBO_COUNTS_MINIMUM_HASH_COUNT;
  $subTitle = "Minimum of $minimumHashCount hashes";
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);
  $hareTypes = $this->getHareTypes($app, $kennelKy);
  $hashTypes = $this->getHashTypes($app, $kennelKy, 0);

  # Establish and set the return value
  $returnValue = $app['twig']->render('jumbo_counts_list_json.twig',array(
    'pageTitle' => 'The Jumbo List of Counts (Experimental Page)',
    'pageSubTitle' => $subTitle,
    #'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageCaption' => "",
    'tableCaption' => "",
    "hareTypes" => count($hareTypes) > 1 ? $hareTypes : array(),
    "hashTypes" => count($hashTypes) > 1 ? $hashTypes : array()
  ));

  #Return the return value
  return $returnValue;

}


public function jumboCountsTablePostActionJson(Request $request, Application $app, string $kennel_abbreviation){

  #$app['monolog']->addDebug("Entering the function jumboStatsTablePostActionJson------------------------");

  #Establish he minimum hash count
  $minimumHashCount = JUMBO_COUNTS_MINIMUM_HASH_COUNT;

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);
  $hashTypes = $this->getHashTypes($app, $kennelKy, 0);

  if(count($hareTypes) == 1) {
    $hareTypes = array();
  }

  if(count($hashTypes) == 1) {
    $hashTypes = array();
  }

  #Obtain the post parameters
  #$inputDraw = $_POST['draw'] ;
  $inputStart = $_POST['start'] ;
  $inputLength = $_POST['length'] ;
  $inputColumns = $_POST['columns'];
  $inputSearch = $_POST['search'];
  $inputSearchValue = $inputSearch['value'];

  #-------------- Begin: Validate the post parameters ------------------------
  #Validate input start
  if(!is_numeric($inputStart)){
    #$app['monolog']->addDebug("input start is not numeric: $inputStart");
    $inputStart = 0;
  }

  #Validate input length
  if(!is_numeric($inputLength)){
    #$app['monolog']->addDebug("input length is not numeric");
    $inputStart = "0";
    $inputLength = "50";
  } else if($inputLength == "-1"){
    #$app['monolog']->addDebug("input length is negative one (all rows selected)");
    $inputStart = "0";
    $inputLength = "1000000000";
  }

  #Validate input search
  #We are using database parameterized statements, so we are good already...

  #---------------- End: Validate the post parameters ------------------------

  #-------------- Begin: Modify the input parameters  ------------------------
  #Modify the search string
  $inputSearchValueModified = "%$inputSearchValue%";

  #Obtain the column/order information
  $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
  $inputOrderColumnExtracted = "3";
  $inputOrderColumnIncremented = "3";
  $inputOrderDirectionExtracted = "desc";
  if(!is_null($inputOrderRaw)){
    #$app['monolog']->addDebug("inside inputOrderRaw not null");
    $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
    $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
    $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
  }else{
    #$app['monolog']->addDebug("inside inputOrderRaw is null");
  }

  #-------------- End: Modify the input parameters  --------------------------


  #-------------- Begin: Define the SQL used here   --------------------------

  #Define the sql that performs the filtering
  $sql = "SELECT
      HASHER_NAME,
      HASH_COUNT,
      HARE_COUNT,";

  foreach ($hashTypes as &$hashType) {
    $sql .= $hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
  }

  foreach ($hareTypes as &$hareType) {
    $sql .= $hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
  }

  $args = array($kennelKy, $kennelKy);

  $sql .= "
      LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE,
      FIRST_HASH_KEY,
      FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
      FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE,
      LATEST_HASH_KEY,
      LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER,
      OUTER_HASHER_KY AS HASHER_KY
  FROM
        (
        SELECT
                HASHERS.HASHER_NAME,
                HASHERS.HASHER_KY AS OUTER_HASHER_KY,
                (
                        SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT,
                (
                        SELECT COUNT(*)
                        FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                        WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HARE_COUNT,";

  foreach ($hareTypes as &$hareType) {
    array_push($args, $kennelKy);
    array_push($args, $hareType['HARE_TYPE']);
    $sql .= "
                (
                        SELECT COUNT(*)
                        FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                        WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HARINGS.HARE_TYPE & ? != 0) AS ".$hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
  }

  foreach ($hashTypes as &$hashType) {
    array_push($args, $kennelKy);
    array_push($args, $hashType['HASH_TYPE']);
    $sql .= "
                (
                        SELECT COUNT(*)
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.HASH_TYPE = ?) AS ".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
  }

  array_push($args, $kennelKy);
  array_push($args, $kennelKy);
  array_push($args, $minimumHashCount);
  array_push($args, $inputSearchValueModified);

  $sql .= "
                (
                        SELECT HASHES.HASH_KY
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
              ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY,
                (
                        SELECT HASHES.HASH_KY
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
              ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
        FROM
                HASHERS
  )
  MAIN_TABLE
  JOIN HASHES LATEST_HASH ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
  JOIN HASHES FIRST_HASH ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
  WHERE HASH_COUNT > ? AND (HASHER_NAME LIKE ? )
  ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
  LIMIT $inputStart,$inputLength";
  #$app['monolog']->addDebug("sql: $sql");

  #Define the SQL that gets the count for the filtered results
  $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
  FROM
    (
    SELECT
      HASHERS.HASHER_NAME AS HASHER_NAME,
      HASHERS.HASHER_KY AS OUTER_HASHER_KY,
      (
        SELECT COUNT(*)
        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
    FROM
      HASHERS
  )
  MAIN_TABLE
  WHERE HASH_COUNT > ? AND HASHER_NAME LIKE ?";

  #Define the sql that gets the overall counts
  $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT
  FROM
      (
      SELECT
        HASHERS.HASHER_KY AS OUTER_HASHER_KY,
        (
          SELECT COUNT(*)
          FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
          WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
      FROM
        HASHERS
    )
    MAIN_TABLE
    WHERE HASH_COUNT > ?";

  #-------------- End: Define the SQL used here   ----------------------------

  #-------------- Begin: Query the database   --------------------------------
  #$app['monolog']->addDebug("Point A");

  #Perform the filtered search
  $theResults = $app['db']->fetchAll($sql,$args);
  #$app['monolog']->addDebug("Point B");

  #Perform the untiltered count
  $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array(
    (int) $kennelKy,
    $minimumHashCount
  )))['THE_COUNT'];
  #$app['monolog']->addDebug("Point C");

  #Perform the filtered count
  $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
    (int) $kennelKy,
    $minimumHashCount,
    (string) $inputSearchValueModified)))['THE_COUNT'];
  #$app['monolog']->addDebug("Point D");
  #-------------- End: Query the database   --------------------------------

  #$app['monolog']->addDebug("Point theUnfilteredCount $theUnfilteredCount");
  #$app['monolog']->addDebug("Point theFilteredCount $theFilteredCount");

  #Establish the output
  $output = array(
    "sEcho" => "foo",
    "iTotalRecords" => $theUnfilteredCount,
    "iTotalDisplayRecords" => $theFilteredCount,
    "aaData" => $theResults
  );

  #Set the return value
  $returnValue = $app->json($output,200);

  #Return the return value
  return $returnValue;
}










#Define the action
public function jumboPercentagesTablePreActionJson(Request $request, Application $app, string $kennel_abbreviation){

  #Establish the sub title
  $minimumHashCount = JUMBO_PERCENTAGES_MINIMUM_HASH_COUNT;
  $subTitle = "Minimum of $minimumHashCount hashes";
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);
  $hareTypes = $this->getHareTypes($app, $kennelKy);
  $hashTypes = $this->getHashTypes($app, $kennelKy, 0);

  # Establish and set the return value
  $returnValue = $app['twig']->render('jumbo_percentages_list_json.twig',array(
    'pageTitle' => 'The Jumbo List of Percentages (Experimental Page)',
    'pageSubTitle' => $subTitle,
    #'theList' => $hasherList,
    'kennel_abbreviation' => $kennel_abbreviation,
    'pageCaption' => "",
    'tableCaption' => "",
    "hareTypes" => count($hareTypes) > 1 ? $hareTypes : array(),
    'hashTypes' => count($hashTypes) > 1 ? $hashTypes : array()
  ));

  #Return the return value
  return $returnValue;

}


public function jumboPercentagesTablePostActionJson(Request $request, Application $app, string $kennel_abbreviation){

  #$app['monolog']->addDebug("Entering the function jumboPercentagesTablePostActionJson------------------------");

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  $hareTypes = $this->getHareTypes($app, $kennelKy);
  $hashTypes = $this->getHashTypes($app, $kennelKy, $hare_type);

  if(count($hareTypes) == 1) {
    $hareTypes = array();
  }

  if(count($hashTypes) == 1) {
    $hashTypes = array();
  }

  #Define the minimum hash count
  $minimumHashCount = JUMBO_PERCENTAGES_MINIMUM_HASH_COUNT;

  #Obtain the post parameters
  #$inputDraw = $_POST['draw'] ;
  $inputStart = $_POST['start'] ;
  $inputLength = $_POST['length'] ;
  $inputColumns = $_POST['columns'];
  $inputSearch = $_POST['search'];
  $inputSearchValue = $inputSearch['value'];

  #-------------- Begin: Validate the post parameters ------------------------
  #Validate input start
  if(!is_numeric($inputStart)){
    #$app['monolog']->addDebug("input start is not numeric: $inputStart");
    $inputStart = 0;
  }

  #Validate input length
  if(!is_numeric($inputLength)){
    #$app['monolog']->addDebug("input length is not numeric");
    $inputStart = "0";
    $inputLength = "50";
  } else if($inputLength == "-1"){
    #$app['monolog']->addDebug("input length is negative one (all rows selected)");
    $inputStart = "0";
    $inputLength = "1000000000";
  }

  #Validate input search
  #We are using database parameterized statements, so we are good already...

  #---------------- End: Validate the post parameters ------------------------

  #-------------- Begin: Modify the input parameters  ------------------------
  #Modify the search string
  $inputSearchValueModified = "%$inputSearchValue%";

  #Obtain the column/order information
  $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
  $inputOrderColumnExtracted = "3";
  $inputOrderColumnIncremented = "3";
  $inputOrderDirectionExtracted = "desc";
  if(!is_null($inputOrderRaw)){
    #$app['monolog']->addDebug("inside inputOrderRaw not null");
    $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
    $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
    $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
  }else{
    #$app['monolog']->addDebug("inside inputOrderRaw is null");
  }

  #-------------- End: Modify the input parameters  --------------------------


  #-------------- Begin: Define the SQL used here   --------------------------

  #Define the sql that performs the filtering
  $sql = "SELECT
      HASHER_NAME,
      HASH_COUNT,
      HARE_COUNT,
      (HARE_COUNT/HASH_COUNT) AS HARING_TO_HASHING_PERCENTAGE,";

  foreach ($hashTypes as &$hashType) {
    $sql .= $hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
  }

  foreach ($hareTypes as &$hareType) {
    $sql .= $hareType['HARE_TYPE_NAME']."_HARE_COUNT,
      (".$hareType['HARE_TYPE_NAME']."_HARE_COUNT/HASH_COUNT) AS ".$hareType['HARE_TYPE_NAME']."_HARING_TO_HASHING_PERCENTAGE,
      (".$hareType['HARE_TYPE_NAME']."_HARE_COUNT/HARE_COUNT) AS ".$hareType['HARE_TYPE_NAME']."_TO_OVERALL_HARING_PERCENTAGE,";
  }

  $args = array($kennelKy, $kennelKy);

  $sql .= "
      LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE,
      FIRST_HASH_KEY,
      FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
      FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE,
      LATEST_HASH_KEY,
      LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER,
      OUTER_HASHER_KY AS HASHER_KY
  FROM
        (
        SELECT
                HASHERS.HASHER_NAME,
                HASHERS.HASHER_KY AS OUTER_HASHER_KY,
                (
                        SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT,
                (
                        SELECT COUNT(*)
                        FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                        WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HARE_COUNT,";

  foreach ($hareTypes as &$hareType) {
    array_push($args, $kennelKy);
    array_push($args, $hareType['HARE_TYPE']);
    $sql .= "
                (
                        SELECT COUNT(*)
                        FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                        WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HARINGS.HARE_TYPE & ? != 0) AS ".$hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
  }

  foreach ($hashTypes as &$hashType) {
    array_push($args, $kennelKy);
    array_push($args, $hashType['HASH_TYPE']);
    $sql .= "
                (
                        SELECT COUNT(*)
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.HASH_TYPE = ?) AS ".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
  }

  array_push($args, $kennelKy);
  array_push($args, $kennelKy);
  array_push($args, $minimumHashCount);
  array_push($args, $inputSearchValueModified);

  $sql .= "
                (
                        SELECT HASHES.HASH_KY
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
              ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY,
                (
                        SELECT HASHES.HASH_KY
                        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?
              ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
        FROM
                HASHERS
  )
  MAIN_TABLE
  JOIN HASHES LATEST_HASH ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
  JOIN HASHES FIRST_HASH ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
  WHERE HASH_COUNT > ? AND (HASHER_NAME LIKE ? )
  ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
  LIMIT $inputStart,$inputLength";
  #$app['monolog']->addDebug("sql: $sql");

  #Define the SQL that gets the count for the filtered results
  $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
  FROM
    (
    SELECT
      HASHERS.HASHER_NAME,
      HASHERS.HASHER_KY AS OUTER_HASHER_KY,
      (
        SELECT COUNT(*)
        FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
        WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
    FROM
      HASHERS
  )
  MAIN_TABLE
  WHERE HASH_COUNT > ? AND ( HASHER_NAME LIKE ? )";

  #Define the sql that gets the overall counts
  $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT
  FROM
      (
      SELECT
        HASHERS.HASHER_KY AS OUTER_HASHER_KY,
        (
          SELECT COUNT(*)
          FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
          WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
      FROM
        HASHERS
    )
    MAIN_TABLE
    WHERE HASH_COUNT > ?";

  #-------------- End: Define the SQL used here   ----------------------------

  #-------------- Begin: Query the database   --------------------------------
  #$app['monolog']->addDebug("Point A");

  #Perform the filtered search
  $theResults = $app['db']->fetchAll($sql, $args);
  #$app['monolog']->addDebug("Point B");

  #Perform the untiltered count
  $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array(
    (int) $kennelKy,
    $minimumHashCount,
  )))['THE_COUNT'];
  #$app['monolog']->addDebug("Point C");

  #Perform the filtered count
  $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
    (int) $kennelKy,
    $minimumHashCount,
    (string) $inputSearchValueModified)))['THE_COUNT'];
  #$app['monolog']->addDebug("Point D");
  #-------------- End: Query the database   --------------------------------

  #$app['monolog']->addDebug("Point theUnfilteredCount $theUnfilteredCount");
  #$app['monolog']->addDebug("Point theFilteredCount $theFilteredCount");

  #Establish the output
  $output = array(
    "sEcho" => "foo",
    "iTotalRecords" => $theUnfilteredCount,
    "iTotalDisplayRecords" => $theFilteredCount,
    "aaData" => $theResults
  );

  #Set the return value
  $returnValue = $app->json($output,200);

  #Return the return value
  return $returnValue;
}

private function getStandardHareChartsAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation) {

  # Declare the SQL used to retrieve this information
  $sql = "SELECT HASHER_KY, HASHER_NAME, HASHER_ABBREVIATION, FIRST_NAME, LAST_NAME, DECEASED FROM HASHERS WHERE HASHER_KY = ?";

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  # Make a database call to obtain the hasher information
  $hasher = $app['db']->fetchAssoc($sql, array((int) $hasher_id));

  # Obtain the number of harings
  $overallHareCountValue = $app['db']->fetchAssoc(PERSONS_HARING_COUNT, array((int) $hasher_id, (int) $kennelKy));

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  $hareCounts = array();

  foreach ($hareTypes as &$hareType) {
      $total = $app['db']->fetchAssoc(PERSONS_HARING_TYPE_COUNT,
        array((int) $hasher_id, (int) $kennelKy, (int) $hareType['HARE_TYPE']));
    array_push($hareCounts, array(
      'type' => $hareType['HARE_TYPE_NAME'],
      'total' => $total['THE_COUNT']));
  }

  #Obtain the harings by year
  $sqlHaringsByYear = "SELECT
      YEAR(EVENT_DATE) AS THE_VALUE,";
  $args = array();

  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByYear .= "
      SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0  THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    array_push($args, (int) $hareType['HARE_TYPE']);
  }
  array_push($args, (int) $hasher_id);
  array_push($args, (int) $kennelKy);

  $sqlHaringsByYear .= "
      COUNT(*) AS TOTAL_HARING_COUNT
  FROM
      HARINGS
      JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
  WHERE
      HARINGS.HARINGS_HASHER_KY = ? AND
      HASHES.KENNEL_KY = ?
  GROUP BY YEAR(EVENT_DATE)
  ORDER BY YEAR(EVENT_DATE)";

  $haringsByYearList = $app['db']->fetchAll($sqlHaringsByYear, $args);

  # Obtain the hashes by month (name)
  $sqlHaringsByMonth = "SELECT
      THE_VALUE,";

  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByMonth .= $hareType['HARE_TYPE_NAME']."_COUNT, ";
  }

  $sqlHaringsByMonth .= "
        TOTAL_HARING_COUNT,
      CASE THE_VALUE
        WHEN '1' THEN 'January'
        WHEN '2' THEN 'February'
        WHEN '3' THEN 'March'
        WHEN '4' THEN 'April'
        WHEN '5' THEN 'May'
        WHEN '6' THEN 'June'
        WHEN '7' THEN 'July'
        WHEN '8' THEN 'August'
        WHEN '9' THEN 'September'
        WHEN '10' THEN 'October'
        WHEN '11' THEN 'November'
        WHEN '12' THEN 'December'
        END AS MONTH_NAME
    FROM (
      SELECT
          MONTH(EVENT_DATE) AS THE_VALUE,";
  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByMonth .= "
      SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0  THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
  }
    $sqlHaringsByMonth .= "
          COUNT(*) AS TOTAL_HARING_COUNT
        FROM
          HARINGS
          JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        WHERE
          HARINGS.HARINGS_HASHER_KY = ? AND
          HASHES.KENNEL_KY = ?
        GROUP BY MONTH(EVENT_DATE)
        ORDER BY MONTH(EVENT_DATE)
    ) TEMPTABLE";
  $theHaringsByMonthNameList = $app['db']->fetchAll($sqlHaringsByMonth, $args);

  # Obtain the hashes by quarter
      $sqlHaringsByQuarter = "SELECT
        QUARTER(EVENT_DATE) AS THE_VALUE,";
      foreach ($hareTypes as &$hareType) {
	$sqlHaringsByQuarter .= "
	  SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
      }
      $sqlHaringsByQuarter .= "
        COUNT(*) AS TOTAL_HARING_COUNT
      FROM
        HARINGS
        JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
      WHERE
        HARINGS.HARINGS_HASHER_KY = ? AND
        HASHES.KENNEL_KY = ?
      GROUP BY QUARTER(EVENT_DATE)
      ORDER BY QUARTER(EVENT_DATE)
  ";
  $theHaringsByQuarterList = $app['db']->fetchAll($sqlHaringsByQuarter, $args);

  # Obtain the hashes by state
  $sqlHaringsByState = "SELECT
      HASHES.EVENT_STATE,";
  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByState .= "
	  SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }
    $sqlHaringsByState .= "
      COUNT(*) AS TOTAL_HARING_COUNT
    FROM
      HARINGS
      JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
    WHERE
      HARINGS.HARINGS_HASHER_KY = ? AND
      HASHES.KENNEL_KY = ?
    GROUP BY HASHES.EVENT_STATE
    ORDER BY HASHES.EVENT_STATE
  ";
  $theHaringsByStateList = $app['db']->fetchAll($sqlHaringsByState, $args);

  # Obtain the hashes by day name
  $sqlHaringsByDayName = "SELECT
      THE_VALUE,";
  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByDayName .=
      $hareType['HARE_TYPE_NAME']."_COUNT,";
  }
  $sqlHaringsByDayName .= "
        TOTAL_HARING_COUNT,
      CASE THE_VALUE
        WHEN 'Sunday' THEN '0'
        WHEN 'Monday' THEN '1'
        WHEN 'Tuesday' THEN '2'
        WHEN 'Wednesday' THEN '3'
        WHEN 'Thursday' THEN '4'
        WHEN 'Friday' THEN '5'
        WHEN 'Saturday' THEN '6'
      END AS DAYNUMBER
    FROM
    (
      SELECT
        DAYNAME(EVENT_DATE) AS THE_VALUE,";
  foreach ($hareTypes as &$hareType) {
    $sqlHaringsByDayName .= "
	SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }
    $sqlHaringsByDayName .= "
        COUNT(*) AS TOTAL_HARING_COUNT
      FROM
        HARINGS
        JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
      WHERE
        HARINGS.HARINGS_HASHER_KY = ? AND
        HASHES.KENNEL_KY = ?
      GROUP BY DAYNAME(EVENT_DATE)
      ORDER BY DAYNAME(EVENT_DATE)
    )TEMP
    ORDER BY DAYNUMBER ASC";
  $theHaringsByDayNameList = $app['db']->fetchAll($sqlHaringsByDayName, $args);

  # Establish and set the return value
  $returnValue = array(
    'hasherValue' => $hasher,
    'hareCounts' => $hareCounts,
    'overallHareCount' => $overallHareCountValue['THE_COUNT'],
    'kennel_abbreviation' => $kennel_abbreviation,
    'harings_by_year_list' => $haringsByYearList,
    'harings_by_month_list' => $theHaringsByMonthNameList,
    'harings_by_quarter_list' => $theHaringsByQuarterList,
    'harings_by_state_list' => $theHaringsByStateList,
    'harings_by_dayname_list' => $theHaringsByDayNameList
  );

  # Return the return value
  return $returnValue;

}


public function viewOverallHareChartsAction(Request $request, Application $app, int $hasher_id, string $kennel_abbreviation) {

  $commonValues = $this->getStandardHareChartsAction($request, $app, $hasher_id, $kennel_abbreviation);

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Obtain the list of favorite cities to hare in
  $cityHaringCountList = $app['db']->fetchAll(HASHER_ALL_HARING_COUNTS_BY_CITY, array((int) $hasher_id, (int) $kennelKy));

  #Obtain largest entry from the list
  $cityHaringsCountMax = 1;
  if(isset($cityHaringCountList[0]['THE_COUNT'])){
    $cityHaringsCountMax = $cityHaringCountList[0]['THE_COUNT'];
  }

  #Obtain the favorite cohare list
  $cohareCountList = $app['db']->fetchAll(OVERALL_COHARE_COUNT_BY_HARE, array(
    (int) $kennelKy,
    (int) $hasher_id,
    (int) $hasher_id));

  #Obtain the largest entry from the list
  $cohareCountMax = 1;
  if(isset($cohareCountList[0]['THE_COUNT'])){
    $cohareCountMax = $cohareCountList[0]['THE_COUNT'];
  }

  # Obtain their hashes
  $sqlTheHashes = "SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE, HASHES.HASH_KY, LAT, LNG FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
  WHERE HARINGS.HARINGS_HASHER_KY = ? AND KENNEL_KY = ? and LAT is not null and LNG is not null";
  $theHashes = $app['db']->fetchAll($sqlTheHashes, array((int) $hasher_id, (int) $kennelKy));

  #Obtain the average lat
  $sqlTheAverageLatLong = "SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG FROM HARINGS JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
  WHERE HARINGS.HARINGS_HASHER_KY = ? AND KENNEL_KY = ? and LAT is not null and LNG is not null";
  $theAverageLatLong = $app['db']->fetchAssoc($sqlTheAverageLatLong, array((int) $hasher_id, (int) $kennelKy));
  $avgLat = $theAverageLatLong['THE_LAT'];
  $avgLng = $theAverageLatLong['THE_LNG'];

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  $customValues = array(
    'pageTitle' => (count($hareTypes) > 1 ? 'Overall ' : '').
      'Hare Charts and Details',
    'overall_hare_details' => (count($hareTypes) > 1 ? "Overall " : "").
      "Hare Details",
    'hare_types' => count($hareTypes) > 1 ? $hareTypes : array(),
    'firstHeader' => 'Basic Details',
    'secondHeader' => 'Statistics',
    'city_haring_count_list' => $cityHaringCountList,
    'city_harings_max_value' => $cityHaringsCountMax,
    'cohare_count_list' =>$cohareCountList,
    'cohare_count_max' => $cohareCountMax,
    'the_hashes' => $theHashes,
    'geocode_api_value' => GOOGLE_MAPS_JAVASCRIPT_API_KEY,
    'avg_lat' => $avgLat,
    'avg_lng' => $avgLng
  );
  $finalArray = array_merge($commonValues,$customValues);
  $returnValue = $app['twig']->render('hare_chart_overall_details.twig',$finalArray);

  # Return the return value
  return $returnValue;
}



public function viewHareChartsAction(Request $request, Application $app, int $hare_type, int $hasher_id, string $kennel_abbreviation) {

  $commonValues = $this->getStandardHareChartsAction($request, $app, $hasher_id, $kennel_abbreviation);

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  #Obtain the list of favorite cities to hare in
  $cityHaringCountList = $app['db']->fetchAll(HASHER_HARING_COUNTS_BY_CITY, array((int) $hasher_id, (int) $kennelKy, (int) $hare_type));

  #Obtain largest entry from the list
  $cityHaringsCountMax = 1;
  if(isset($cityHaringCountList[0]['THE_COUNT'])){
    $cityHaringsCountMax = $cityHaringCountList[0]['THE_COUNT'];
  }

  #Obtain the favorite cohare list
  $cohareCountList = $app['db']->fetchAll(COHARE_COUNT_BY_HARE, array(
    (int) $kennelKy,
    (int) $hasher_id,
    (int) $hasher_id,
    (int) $hare_type));

  #Obtain the largest entry from the list
  $cohareCountMax = 1;
  if(isset($cohareCountList[0]['THE_COUNT'])){
    $cohareCountMax = $cohareCountList[0]['THE_COUNT'];
  }

  # Obtain their hashes
  $sqlTheHashes = "
    SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE,
           HASHES.HASH_KY, LAT, LNG
      FROM HARINGS
      JOIN HASHES
        ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
     WHERE HARINGS.HARINGS_HASHER_KY = ?
       AND KENNEL_KY = ?
       AND HARINGS.HARE_TYPE & ? != 0
       AND LAT IS NOT NULL
       AND LNG IS NOT NULL";
  $theHashes = $app['db']->fetchAll($sqlTheHashes, array((int) $hasher_id, (int) $kennelKy,
    (int) $hare_type));

  #Obtain the average lat
  $sqlTheAverageLatLong = "
    SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
      FROM HARINGS
      JOIN HASHES
        ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
     WHERE HARINGS.HARINGS_HASHER_KY = ?
       AND KENNEL_KY = ?
       AND HARINGS.HARE_TYPE & ? != 0
       AND LAT IS NOT NULL
       AND LNG IS NOT NULL";
  $theAverageLatLong = $app['db']->fetchAssoc($sqlTheAverageLatLong, array((int) $hasher_id,
    (int) $kennelKy, (int) $hare_type));
  $avgLat = $theAverageLatLong['THE_LAT'];
  $avgLng = $theAverageLatLong['THE_LNG'];

  $hareTypes = $this->getHareTypes($app, $kennelKy);

  $hare_type_name = $this->getHareTypeName($app, $hare_type);

  foreach ($hareTypes as &$hareType) {
    if($hareType['HARE_TYPE'] == $hare_type) {
      $chart_color = $hareType['CHART_COLOR'];
      break;
    }
  }

  $customValues = array(
    'pageTitle' => $hare_type_name.' Hare Charts and Details',
    'hare_types' => $hareTypes,
    'firstHeader' => 'Basic Details',
    'secondHeader' => 'Statistics',
    'city_haring_count_list' => $cityHaringCountList,
    'city_harings_max_value' => $cityHaringsCountMax,
    'cohare_count_list' =>$cohareCountList,
    'cohare_count_max' => $cohareCountMax,
    'the_hashes' => $theHashes,
    'geocode_api_value' => GOOGLE_MAPS_JAVASCRIPT_API_KEY,
    'avg_lat' => $avgLat,
    'avg_lng' => $avgLng,
    'hare_type' => $hare_type,
    'hare_type_name' => $hare_type_name,
    'chart_color' => $chart_color
  );
  $finalArray = array_merge($commonValues,$customValues);
  $returnValue = $app['twig']->render('hare_chart_details.twig',$finalArray);

  # Return the return value
  return $returnValue;
}

public function twoPersonComparisonPreAction(Request $request, Application $app, string $kennel_abbreviation){

  $pageTitle = "Two Person Comparison";

  #Establish the return value
  $returnValue = $app['twig']->render('hasher_comparison_selection_screen.twig', array (
    'pageTitle' => $pageTitle,
    'playerOneDefault' => 'Selection Required',
    'playerTwoDefault' => 'Selection Required',
    'pageSubTitle' => 'Select Your Contestants',
    'pageHeader' => 'Why is this so complicated ?',
    'instructions' => 'You need to select two hashers to compare. Start typing in the search box to find your favorite hasher. When their name shows up, click the "+ player one" link next to their name. Repeat the process of typing in the search box and then click the "+ player two" link. Then, when both hashers have been selected, click on the the giant "submit" button. Enjoy!',
    'kennel_abbreviation' => $kennel_abbreviation
  ));

  # Return the return value
  return $returnValue;

}

private function createComparisonObjectCoreAttributes(string $hasher1, string $hasher2, string $statTitle, string $dataType){

  #Establish the return value object
  $returnValue = array();

  $returnValue = array(
    'statName' => $statTitle,
    'hasher1' => $hasher1,
    'hasher2' => $hasher2,
    'dataType' => $dataType
  );

  #Return the return object
  return $returnValue;
}

private function createComparisonObjectWithStatsAsInts(int $stat1, int $stat2, string $hasher1, string $hasher2, string $statTitle){

  #Establish the return value object
  $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle, "int");

  #Establish the winner
  $verdict = '';
  if($stat1 > $stat2){
    $verdict = 'hasher1';
  }else if ($stat2 > $stat1){
    $verdict = 'hasher2';
  }else{
    $verdict = 'tie';
  }

  #Fill in the return value with more attributes
  $additionalAttributes =   array(
    'val1' => $stat1,
    'val2' => $stat2,
    'verdict' => $verdict);

  #Combine the arrays
  $returnValue = $returnValue + $additionalAttributes;

  #Return the return value
  return $returnValue;
}


private function createComparisonObjectWithStatsAsDoubles(float $stat1, float $stat2, string $hasher1, string $hasher2, string $statTitle){

  #Establish the return value object
  $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle,"float");

  $verdict = '';
  if($stat1 > $stat2){
    $verdict = 'hasher1';
  }else if ($stat2 > $stat1){
    $verdict = 'hasher2';
  }else{
    $verdict = 'tie';
  }

  #Fill in the return value with more attributes
  $additionalAttributes = array(
    'val1' => $stat1,
    'val2' => $stat2,
    'verdict' => $verdict);

  #Combine the arrays
  $returnValue = $returnValue + $additionalAttributes;

  #Return the return value
  return $returnValue;
}

private function createComparisonObjectWithStatsAsDates(string $stat1, string $stat2, string $hasher1, string $hasher2, string $statTitle, bool $greaterIsBetter, int $key1, int $key2){

  #Establish the return value object
  $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle,"date");

  #Establish the verdict variable
  $verdict = '';

  #Establish the date time values
  $date1 = DateTime::createFromFormat('m/d/Y',$stat1);
  $date2 = DateTime::createFromFormat('m/d/Y',$stat2);

  #Populate the verdict value
  if($date1 > $date2){
    $verdict = ($greaterIsBetter ? 'hasher1':'hasher2');
  }else if ($date2 > $date1){
    $verdict = ($greaterIsBetter ? 'hasher2':'hasher1');
  }else {
    $verdict = 'tie';
  }




  #Fill in the return value with more attributes
  $additionalAttributes = array(
    'val1' => $stat1,
    'val2' => $stat2,
    'verdict' => $verdict,
    'hashKey1' => $key1,
    'hashKey2' => $key2);

  #Combine the arrays
  $returnValue = $returnValue + $additionalAttributes;

  #Return the return value
  return $returnValue;

}

private function twoPersonComparisonDataFetch(Request $request, Application $app, int $kennelKy, int $hasher_id1, int $hasher_id2){

  $hareTypes = $this->getHareTypes($app, $kennelKy);
  if(count($hareTypes) == 1) {
    $hareTypes = array();
  }

  #Establish the reurn value array
  $returnValue = array();

  # Declare the SQL used to retrieve this information
  $sql = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

  # Make a database call to obtain the hasher information
  $hasher1 = $app['db']->fetchAssoc($sql, array((int) $hasher_id1));
  $hasher2 = $app['db']->fetchAssoc($sql, array((int) $hasher_id2));


  #Obtain the overall hashing count
  $hashingCountH1 = ($app['db']->fetchAssoc($this->getPersonsHashingCountQuery(), array((int) $hasher_id1, (int) $kennelKy)))['THE_COUNT'];
  $hashingCountH2 = ($app['db']->fetchAssoc($this->getPersonsHashingCountQuery(), array((int) $hasher_id2, (int) $kennelKy)))['THE_COUNT'];
  $statObject = $this-> createComparisonObjectWithStatsAsInts($hashingCountH1, $hashingCountH2,$hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], "Hashing Count");
  $returnValue[] = $statObject;

  #Obtain the overall haring count
  $hareCountOverallH1 = ($app['db']->fetchAssoc(PERSONS_HARING_COUNT, array((int) $hasher_id1, (int) $kennelKy)))['THE_COUNT'];
  $hareCountOverallH2 = ($app['db']->fetchAssoc(PERSONS_HARING_COUNT, array((int) $hasher_id2, (int) $kennelKy)))['THE_COUNT'];
  $statObject = $this-> createComparisonObjectWithStatsAsInts($hareCountOverallH1, $hareCountOverallH2,$hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], "Overall Haring Count");
  $returnValue[] = $statObject;

  #Obtain the haring counts
  foreach ($hareTypes as &$hareType) {
    $hareCountH1[$hareType['HARE_TYPE']] = ($app['db']->fetchAssoc(PERSONS_HARING_TYPE_COUNT, array((int) $hasher_id1, (int) $kennelKy, $hareType['HARE_TYPE'])))['THE_COUNT'];
    $hareCountH2[$hareType['HARE_TYPE']] = ($app['db']->fetchAssoc(PERSONS_HARING_TYPE_COUNT, array((int) $hasher_id2, (int) $kennelKy, $hareType['HARE_TYPE'])))['THE_COUNT'];
    $statObject = $this->createComparisonObjectWithStatsAsInts($hareCountH1[$hareType['HARE_TYPE']], $hareCountH2[$hareType['HARE_TYPE']], $hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], $hareType['HARE_TYPE_NAME']." Haring Count");
    $returnValue[] = $statObject;
  }

  #Obtain the overall haring percentage
  $statObject = $this->createComparisonObjectWithStatsAsDoubles( ($hashingCountH1 == 0 ? 0 : $hareCountOverallH1/$hashingCountH1),
    ($hashingCountH2 == 0 ? 0 : $hareCountOverallH2/$hashingCountH2), $hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], "Overall Haring / Hashing %");
  $returnValue[] = $statObject;

  foreach ($hareTypes as &$hareType) {
    #Obtain the haring percentage
    $statObject = $this->createComparisonObjectWithStatsAsDoubles( ($hashingCountH1 == 0 ? 0 : $hareCountH1[$hareType['HARE_TYPE']]/$hashingCountH1),
      ($hashingCountH2 == 0 ? 0 : $hareCountH2[$hareType['HARE_TYPE']]/$hashingCountH2), $hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], $hareType['HARE_TYPE_NAME']." Haring / Hashing %");
    $returnValue[] = $statObject;

    #Obtain the haring / all haring percentage
    $statObject = $this->createComparisonObjectWithStatsAsDoubles( ($hareCountOverallH1 == 0 ? 0 : $hareCountH1[$hareType['HARE_TYPE']]/$hareCountOverallH1),
      ($hareCountOverallH2 == 0 ? 0 : $hareCountH2[$hareType['HARE_TYPE']]/$hareCountOverallH2), $hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], $hareType['HARE_TYPE_NAME']." Haring / All Haring %");
    $returnValue[] = $statObject;
  }

  #Obtain the virgin hash dates
  $virginHashH1 = $app['db']->fetchAssoc(SELECT_HASHERS_VIRGIN_HASH, array((int) $hasher_id1, (int) $kennelKy));
  $virginHashH2 = $app['db']->fetchAssoc(SELECT_HASHERS_VIRGIN_HASH, array((int) $hasher_id2, (int) $kennelKy));
  $statObject = $this->createComparisonObjectWithStatsAsDates(
    is_null($virginHashH1['EVENT_DATE_FORMATTED']) ? "": $virginHashH1['EVENT_DATE_FORMATTED'] ,
    is_null($virginHashH2['EVENT_DATE_FORMATTED']) ? "": $virginHashH2['EVENT_DATE_FORMATTED'] ,
    $hasher1['HASHER_NAME'],
    $hasher2['HASHER_NAME'],
    "First Hash",
    FALSE,
    is_null($virginHashH1['HASH_KY']) ? 0 : $virginHashH1['HASH_KY'] ,
    is_null($virginHashH2['HASH_KY']) ? 0 : $virginHashH2['HASH_KY']);
  $returnValue[] = $statObject;

  #Obtain the latest hash dates
  $latestHashH1 = $app['db']->fetchAssoc(SELECT_HASHERS_MOST_RECENT_HASH, array((int) $hasher_id1, (int) $kennelKy));
  $latestHashH2 = $app['db']->fetchAssoc(SELECT_HASHERS_MOST_RECENT_HASH, array((int) $hasher_id2, (int) $kennelKy));
  $statObject = $this->createComparisonObjectWithStatsAsDates(
    is_null($latestHashH1['EVENT_DATE_FORMATTED']) ? "": $latestHashH1['EVENT_DATE_FORMATTED'] ,
    is_null($latestHashH2['EVENT_DATE_FORMATTED']) ? "": $latestHashH2['EVENT_DATE_FORMATTED'] ,
    $hasher1['HASHER_NAME'],
    $hasher2['HASHER_NAME'],
    "Latest Hash",
    TRUE,
    is_null($latestHashH1['HASH_KY']) ? 0 : $latestHashH1['HASH_KY'] ,
    is_null($latestHashH2['HASH_KY']) ? 0 : $latestHashH2['HASH_KY']);
  $returnValue[] = $statObject;

  #Return the return value
  return $returnValue;

}

public function twoPersonComparisonAction(Request $request, Application $app, string $kennel_abbreviation, int $hasher_id, int $hasher_id2){

  $pageTitle = "Hasher Showdown";

  # Declare the SQL used to retrieve this information
  $sql = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

  #Obtain the kennel key
  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($request, $app, $kennel_abbreviation);

  # Make a database call to obtain the hasher information
  $hasher1 = $app['db']->fetchAssoc($sql, array((int) $hasher_id));
  $hasher2 = $app['db']->fetchAssoc($sql, array((int) $hasher_id2));
  $pageSubtitle = $hasher1['HASHER_NAME'] . " VS " . $hasher2['HASHER_NAME'];

  $listOfStats = null;
  $listOfStats= $this->twoPersonComparisonDataFetch($request, $app, $kennelKy, $hasher_id, $hasher_id2);


  #Establish the return value
  $returnValue = $app['twig']->render('hasher_comparison_fluid_results.twig', array (
    'pageTitle' => $pageTitle,
    'pageSubTitle' => $pageSubtitle,
    'pageHeader' => 'Why is this so complicated ?',
    'kennel_abbreviation' => $kennel_abbreviation,
    'hasherName1' => $hasher1['HASHER_NAME'],
    'hasherName2' => $hasher2['HASHER_NAME'],
    'tempList' => $listOfStats
  ));

  # Return the return value
  return $returnValue;

}

}
