<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\DatabaseUpdater;
use App\Helper;
use App\SqlQueries;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use \Datetime;

class HashController extends BaseController
{
  private SqlQueries $sqlQueries;
  private Helper $helper;

  public function __construct(ManagerRegistry $doctrine, SqlQueries $sqlQueries, Helper $helper) {
    parent::__construct($doctrine);
    $this->sqlQueries = $sqlQueries;
    $this->helper = $helper;
  }

  #[Route('/{kennel_abbreviation}/rss',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function rssAction(string $kennel_abbreviation) {
    $args = $this->getSlashTwigArgs($kennel_abbreviation);

    $prefix = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
      "https" : "http") . "://$_SERVER[HTTP_HOST]";

    $args['url_prefix'] = $prefix . "/" . $kennel_abbreviation;

    $args['url'] = $prefix . $_SERVER['REQUEST_URI'];

    $response = new Response($this->render('rss.twig', $args));

    $response->headers->set('Content-Type', 'application/rss+xml');

    return $response;
  }

  #[Route('/{kennel_abbreviation}/events/rss',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function eventsRssAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $prefix = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
      "https" : "http") . "://$_SERVER[HTTP_HOST]";

    $args['url_prefix'] = $prefix . "/" . $kennel_abbreviation;

    $args['url'] = $prefix . $_SERVER['REQUEST_URI'];

    $hashesQuery = "
      SELECT CONCAT(SPECIAL_EVENT_DESCRIPTION, ' on ',
             date_format(EVENT_DATE, '%M %D'), ' was attended by ',
             (SELECT COUNT(*) FROM HASHINGS WHERE HASHINGS.HASH_KY = HASHES.HASH_KY), ' hounds', ' and had ',
             (SELECT COUNT(*) FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY), ' hare',
             CASE WHEN (SELECT COUNT(*) FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY) != 1 THEN 's' ELSE '' END) AS TEXT
        FROM HASHES
       WHERE KENNEL_KY = ?
       ORDER BY EVENT_DATE DESC LIMIT 10";

    $args['hashes'] = $this->fetchAll($hashesQuery, [ $kennelKy ]);

    $response = new Response($this->render('events_rss.twig', $args));

    $response->headers->set('Content-Type', 'application/rss+xml');

    return $response;
  }

  #[Route('/',
    methods: ['GET']
  )]
  public function slashAction() {
    return $this->slashKennelAction2($this->getDefaultKennel($this->container));
  }

  #[Route('/{kennel_abbreviation}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function slashKennelAction2(string $kennel_abbreviation) {
    new DatabaseUpdater($this, $this->getParameter('app.db_name'));
    return $this->render('slash2.twig', $this->getSlashTwigArgs($kennel_abbreviation));
  }

  private function getSlashTwigArgs(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    $pageTitle = "$kennel_abbreviation Stats";

    $sql = $this->getHashingCountsQuery()." LIMIT 10";
    $sql2 = $this->getHaringCountsByTypeQuery(false)." LIMIT 10";
    $sql4 = $this->getHaringCountsQuery(false)." LIMIT 10";
    $sql5 = $this->sqlQueries->getHashingCountsThisYear()." LIMIT 10";
    $sql6 = $this->sqlQueries->getHashingCountsLastYear()." LIMIT 10";
    $sql7 = $this->sqlQueries->getHaringCountsThisYear()." LIMIT 10";
    $sql8 = $this->sqlQueries->getHaringCountsLastYear()." LIMIT 10";

    $top_hares = array();

    if(count($hareTypes) > 1) {
      foreach ($hareTypes as &$hareType) {
        $hareResults = $this->fetchAll($sql2, array((int) $hareType['HARE_TYPE'], $kennelKy));
          array_push($top_hares, array('data' => $hareResults,
            'label' => $hareType['HARE_TYPE_NAME'],
            'hare_type' => $hareType['HARE_TYPE']));
      }
    }

    #Execute the SQL statement; create an array of rows
    $topHashersList = $this->fetchAllIgnoreErrors($sql, array($kennelKy, $kennelKy));
    $topOverallHareList = $this->fetchAllIgnoreErrors($sql4, array($kennelKy));
    $topHashersThisYear = $this->fetchAllIgnoreErrors($sql5, array($kennelKy));
    $topHashersLastYear = $this->fetchAllIgnoreErrors($sql6, array($kennelKy));
    $topHaresThisYear = $this->fetchAllIgnoreErrors($sql7, array($kennelKy));
    $topHaresLastYear = $this->fetchAllIgnoreErrors($sql8, array($kennelKy));

    #Get the quickest to 5 hashes
    $theQuickestToXNumber = 5;
    $theSql = str_replace("XLIMITX",$theQuickestToXNumber-1, $this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theQuickestToXResults = $this->fetchAllIgnoreErrors($theSql, array($kennelKy, $kennelKy, $kennelKy));

    #Get the quickest to 100 hashes
    $theQuickestToYNumber = 100;
    $theSql = str_replace("XLIMITX",$theQuickestToYNumber-1, $this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theQuickestToYResults = $this->fetchAllIgnoreErrors($theSql, array($kennelKy, $kennelKy, $kennelKy));

    #Get the slowest to 5 hashes
    $theSlowestToXNumber = 5;
    $theSql = str_replace("XLIMITX",$theSlowestToXNumber-1, $this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","DESC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";
    $theSlowestToXResults = $this->fetchAllIgnoreErrors($theSql, array($kennelKy, $kennelKy, $kennelKy));

    $quickest_hares = array();
    $theQuickestToXHaringsNumber = 5;
    $theSql = str_replace("XLIMITX",$theQuickestToXHaringsNumber-1, $this->sqlQueries->getFastestHaresToAnalversaries2());
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);
    $theSql = "$theSql LIMIT 10";

    foreach ($hareTypes as &$hareType) {
      #Get the quickest to 5 harings
      $theQuickestToXHaringsResults = $this->fetchAllIgnoreErrors($theSql, array($kennelKy, $kennelKy, $hareType['HARE_TYPE'], $kennelKy, $hareType['HARE_TYPE']));
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
    $eventTagSummaries = $this->fetchAll($eventTagSql, array($kennelKy));

    $topStreakers = $this->fetchAll($this->sqlQueries->getTheLongestStreaks()." LIMIT 10", array($kennelKy));

    $lastEvent = $this->fetchOne("SELECT HASH_KY FROM HASHES WHERE KENNEL_KY=? ORDER BY EVENT_DATE DESC LIMIT 1", array($kennelKy));

    $currentStreakers = $this->fetchAll($this->sqlQueries->getStreakersList()." LIMIT 10", array($lastEvent, $kennelKy));

    $tableColors = array( "#d1f2eb", "#d7bde2", "#eaeded", "#fad7a0", "#fdedec" );

    #Set the return value
    return array(
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
      'top_streakers' => $topStreakers,
      'current_streakers' => $currentStreakers,
      'lastEvent' => $lastEvent,
      'event_tag_summaries' => $eventTagSummaries,
      'overall_hares_title' =>
        count($hareTypes) > 1 ? "Top 10 Overall Hares" : "Top 10 Hares",
      'table_colors' => $tableColors);
  }

  #[Route('/{kennel_abbreviation}/listStreakers/byhash/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function listStreakersByHashAction(string $kennel_abbreviation, int $hash_id) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theList = $this->fetchAll($this->sqlQueries->getStreakersList(), [ $hash_id, $kennelKy ]);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    return $this->render('streaker_results.twig', [
      'pageTitle' => 'The Streakers!',
      'pageSubTitle' => '...',
      'theList' => $theList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'theHashValue' => $theHashValue,
      'pageCaption' => "",
      'tableCaption' => ""
    ]);
  }

  #[Route('/{kennel_abbreviation}/listvirginharings/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function listVirginHaringsPreActionJson(int $hare_type, string $kennel_abbreviation) {

    $hareTypeName = $this->getHareTypeName($hare_type);

    # Establish and set the return value
    return $this->render('virgin_haring_list_json.twig', [
      'pageTitle' => 'The List of Virgin ('.$hareTypeName.') Harings',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "",
      'hare_type' => $hare_type ]);
  }

  #[Route('/{kennel_abbreviation}/CohareCounts/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function cohareCountsPreActionJson(string $kennel_abbreviation, int $hare_type) {

    return $this->render('cohare_list_json.twig', [
      'pageTitle' => ($hare_type == 0 ? "Overall" : $this->getHareTypeName($hare_type)).' Co-Hare Counts',
      'pageSubTitle' => 'Total number of events where two hashers have hared together.',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hare_type' => $hare_type,
      'pageTracking' => 'CoHareCounts' ]);
  }

  #[Route('/{kennel_abbreviation}/allCohareCounts',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function allCohareCountsPreActionJson(string $kennel_abbreviation){
    return $this->cohareCountsPreActionJson($kennel_abbreviation, 0);
  }

  #[Route('/{kennel_abbreviation}/cohareCounts',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getCohareCountsJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $hare_type = $_POST['hare_type'];
    $inputSearchValue = $inputSearch['value'];

    $typeClause = $hare_type == 0 ? "" : "AND b.HARE_TYPE & ? != 0 AND c.HARE_TYPE & ? != 0";

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

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    } else {
      $inputOrderColumnExtracted = "2";
      $inputOrderDirectionExtracted = "desc";
    }

    $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql =
      "SELECT a.HASHER_NAME AS HASHER_NAME1, d.HASHER_NAME AS HASHER_NAME2, COUNT(*) AS THE_COUNT,
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

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT 1
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
               GROUP BY a.HASHER_NAME, d.HASHER_NAME, a.HASHER_KY, d.HASHER_KY) AS INNER_QUERY";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT 1
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
               GROUP BY a.HASHER_NAME, d.HASHER_NAME, a.HASHER_KY, d.HASHER_KY) AS INNER_QUERY";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    $args = [ $kennelKy, $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified ];

    $args2 = [ $kennelKy ];

    if($hare_type != 0) {
      array_push($args, $hare_type);
      array_push($args, $hare_type);
      array_push($args2, $hare_type);
      array_push($args2, $hare_type);
    }

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, $args);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,$args2))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = $this->fetchAssoc($sqlFilteredCount,$args)['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/locationCounts',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function listLocationCountsPreActionJson(string $kennel_abbreviation) {
    return $this->render('location_counts_json.twig', [
      'pageTitle' => 'The List of Event Locations',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "" ]);
  }

  #[Route('/{kennel_abbreviation}/mia',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function miaPreActionJson(string $kennel_abbreviation) {

    return $this->render('hasher_mia.twig', [
      'pageTitle' => 'Hashers Missing In Action',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "" ]);
  }

  #[Route('/{kennel_abbreviation}/attendancePercentages',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function attendancePercentagesPreActionJson(string $kennel_abbreviation) {

    return $this->render('attendance_percentages_list_json.twig', [
      'pageTitle' => 'Attendance Percentages',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/listhashers/byhash/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function listHashersByHashAction(int $hash_id, string $kennel_abbreviation) {

    $sql = "
      SELECT HASHERS.HASHER_KY AS THE_KEY,
             HASHERS.HASHER_NAME AS NAME,
             HASHERS.HASHER_ABBREVIATION
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
       WHERE HASHINGS.HASH_KY = ?";

    $hasherList = $this->fetchAll($sql, [ $hash_id ]);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $theHashEventLocation = $theHashValue['EVENT_LOCATION'];

    $theSubTitle = "Hashers at Hash Number $theHashEventNumber ($theHashEventLocation) ";

    # Establish and set the return value
    return $this->render('hasher_list.twig', [
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => $theSubTitle,
      'theList' => $hasherList,
      'tableCaption' => $theSubTitle,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/listhares/byhash/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function listHaresByHashAction(int $hash_id, string $kennel_abbreviation) {

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

    $hasherList = $this->fetchAll($sql, [ $hash_id ]);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $theHashEventLocation = $theHashValue['EVENT_LOCATION'];

    $theSubTitle = "Hares at Hash Number $theHashEventNumber ($theHashEventLocation) ";

    return $this->render('hare_list.twig', [
      'pageTitle' => 'The List of Hares',
      'pageSubTitle' => $theSubTitle,
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/listhashers2',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHasherListJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------

    #Validate input start
    if(!is_numeric($inputStart)) {
      $inputStart = 0;
    }

    #Validate input length
    if(!is_numeric($inputLength)) {
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1") {
      $inputStart = "0";
      $inputLength = "1000000000";
    }
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
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    if($this->hasLegacyHashCounts()) {
      $sql = "
        SELECT NAME, HASHER_ABBREVIATION, SUM(THE_COUNT) AS THE_COUNT, THE_KEY
          FROM (
        SELECT HASHER_NAME AS NAME, HASHER_ABBREVIATION,
               COUNT(HASHINGS.HASHER_KY) AS THE_COUNT, HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN HASHINGS
            ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          JOIN HASHES
            ON HASHES.HASH_KY = HASHINGS.HASH_KY
         WHERE KENNEL_KY = ?
           AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
         GROUP BY HASHINGS.HASHER_KY
         UNION ALL
        SELECT HASHER_NAME AS NAME, HASHER_ABBREVIATION,
               LEGACY_HASHINGS_COUNT AS THE_COUNT, LEGACY_HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN LEGACY_HASHINGS
            ON HASHERS.HASHER_KY = LEGACY_HASHINGS.HASHER_KY
         WHERE KENNEL_KY = ?
           AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)) AS INNER1
         GROUP BY NAME, HASHER_ABBREVIATION, THE_KEY
         ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
         LIMIT $inputStart,$inputLength";

      #Define the SQL that gets the count for the filtered results
      $sqlFilteredCount = "
        SELECT COUNT(*) AS THE_COUNT
          FROM (
        SELECT THE_KEY
          FROM (
        SELECT HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN HASHINGS
            ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          JOIN HASHES
            ON HASHES.HASH_KY = HASHINGS.HASH_KY
         WHERE KENNEL_KY = ?
           AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
         GROUP BY HASHINGS.HASHER_KY
         UNION ALL
        SELECT LEGACY_HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN LEGACY_HASHINGS
            ON HASHERS.HASHER_KY = LEGACY_HASHINGS.HASHER_KY
         WHERE KENNEL_KY = ?
           AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)) AS INNER1
         GROUP BY THE_KEY) AS INNER_QUERY";

      #Define the sql that gets the overall counts
      $sqlUnfilteredCount = "
        SELECT COUNT(*) AS THE_COUNT
          FROM (
        SELECT THE_KEY
          FROM (
        SELECT HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN HASHINGS
            ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          JOIN HASHES
            ON HASHES.HASH_KY = HASHINGS.HASH_KY
         WHERE KENNEL_KY = ?
         GROUP BY HASHINGS.HASHER_KY
         UNION ALL
        SELECT LEGACY_HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN LEGACY_HASHINGS
            ON HASHERS.HASHER_KY = LEGACY_HASHINGS.HASHER_KY
         WHERE KENNEL_KY = ?) AS INNER1
         GROUP BY THE_KEY) AS INNER_QUERY";

      #Perform the filtered search
      $theResults = $this->fetchAll($sql, [
        $kennelKy, $inputSearchValueModified, $inputSearchValueModified, $kennelKy,
        $inputSearchValueModified, $inputSearchValueModified ]);

      #Perform the untiltered count
      $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy, $kennelKy ]))['THE_COUNT'];

      #Perform the filtered count
      $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [
        $kennelKy, $inputSearchValueModified, $inputSearchValueModified, $kennelKy,
        $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    } else {

      $sql = "
        SELECT HASHER_NAME AS NAME, HASHER_ABBREVIATION,
               COUNT(HASHINGS.HASHER_KY) AS THE_COUNT, HASHINGS.HASHER_KY AS THE_KEY
          FROM HASHERS
          JOIN HASHINGS
            ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          JOIN HASHES
            ON HASHES.HASH_KY = HASHINGS.HASH_KY
         WHERE KENNEL_KY = ?
           AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
         GROUP BY HASHINGS.HASHER_KY
         ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
         LIMIT $inputStart,$inputLength";

      #Define the SQL that gets the count for the filtered results
      $sqlFilteredCount = "
        SELECT COUNT(*) AS THE_COUNT
          FROM (SELECT 1
                  FROM HASHERS
                  JOIN HASHINGS
                    ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
                  JOIN HASHES
                    ON HASHES.HASH_KY = HASHINGS.HASH_KY
                 WHERE KENNEL_KY = ?
                   AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)
                 GROUP BY HASHINGS.HASHER_KY) AS INNER_QUERY";

      #Define the sql that gets the overall counts
      $sqlUnfilteredCount = "
        SELECT COUNT(*) AS THE_COUNT
          FROM (SELECT 1
                  FROM HASHERS
                  JOIN HASHINGS
                    ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
                  JOIN HASHES
                    ON HASHES.HASH_KY = HASHINGS.HASH_KY
                 WHERE KENNEL_KY = ?
                 GROUP BY HASHINGS.HASHER_KY) AS INNER_QUERY";

      #Perform the filtered search
      $theResults = $this->fetchAll($sql, [ $kennelKy, $inputSearchValueModified, $inputSearchValueModified ]);

      #Perform the untiltered count
      $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy ]))['THE_COUNT'];

      #Perform the filtered count
      $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,
        [ $kennelKy, $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];
    }

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/listvirginharings/{hare_type}',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function getVirginHaringsListJson(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

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
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME, FIRST_HARING_EVENT_TABLE.FIRST_HASH_DATE AS FIRST_HARING_DATE,
             HASHERS.HASHER_KY AS HASHER_KY,
             (SELECT HASH_KY
                FROM HASHES
               WHERE EVENT_DATE=FIRST_HARING_EVENT_TABLE.FIRST_HASH_DATE
                 AND HASHES.KENNEL_KY = ?) AS FIRST_HARING_KEY
        FROM HASHERS
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY, MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE
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
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY, MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE
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
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HASHER_KY, MIN(HASHES.EVENT_DATE) AS FIRST_HASH_DATE
                FROM HARINGS
                JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
               WHERE HASHES.KENNEL_KY = ?
                 AND HARINGS.HARE_TYPE & ? != 0
               GROUP BY HARINGS.HARINGS_HASHER_KY) FIRST_HARING_EVENT_TABLE
          ON HASHERS.HASHER_KY = FIRST_HARING_EVENT_TABLE.HASHER_KY";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, [ $kennelKy, $kennelKy, $hare_type, (string) $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy, $hare_type ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $kennelKy, $hare_type, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/locationCounts',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getLocationCountsJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

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

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    if(!is_null($inputOrderRaw)){
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
      SELECT (SELECT CONCAT(CASE 
                            WHEN EVENT_LOCATION!='' THEN CONCAT(EVENT_LOCATION,', ')
                            ELSE '' 
                             END,FORMATTED_ADDRESS)
                FROM HASHES I
               WHERE I.PLACE_ID = O.PLACE_ID
               ORDER BY KENNEL_EVENT_NUMBER DESC
               LIMIT 1) AS LOCATION, COUNT(*) AS THE_COUNT
        FROM HASHES O
       WHERE KENNEL_KY=?
         AND PLACE_ID != ''
         AND (EVENT_LOCATION!='' OR FORMATTED_ADDRESS!='')
         AND (EVENT_LOCATION LIKE ?  OR FORMATTED_ADDRESS LIKE ?)
       GROUP BY PLACE_ID
       ORDER BY $inputOrderColumnExtracted $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT 1
                FROM HASHES O
               WHERE KENNEL_KY=?
                 AND PLACE_ID != ''
                 AND (EVENT_LOCATION!='' OR FORMATTED_ADDRESS!='')
                 AND (EVENT_LOCATION LIKE ?  OR FORMATTED_ADDRESS LIKE ?)
               GROUP BY PLACE_ID) I";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT 1
                FROM HASHES O
               WHERE KENNEL_KY=?
                 AND PLACE_ID != ''
                 AND (EVENT_LOCATION!='' OR FORMATTED_ADDRESS!='')
               GROUP BY PLACE_ID) I";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, [ $kennelKy, $inputSearchValueModified, $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [
      $kennelKy, $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/mia',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function miaPostActionJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

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
    if(!is_numeric($inputLength)) {
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1") {
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #Validate input search

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "1";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    } else {
      $inputOrderColumnIncremented = "DAYS_MIA";
      $inputOrderDirectionExtracted = "DESC";
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME, LAST_SEEN_EVENT, LAST_SEEN_DATE, NUM_HASHES_MISSED,
             DATEDIFF(CURDATE(), LAST_SEEN_DATE) AS DAYS_MIA, (
             SELECT HASH_KY
               FROM HASHES
              WHERE KENNEL_EVENT_NUMBER = LAST_SEEN_EVENT
                AND KENNEL_KY = ?) AS HASH_KY,
             HASHER_KY AS THE_KEY, HASHER_ABBREVIATION
        FROM (SELECT HASHER_NAME, HASHER_KY, HASHER_ABBREVIATION, LAST_SEEN_DATE, (
                     SELECT COUNT(*)
                       FROM HASHES
                      WHERE KENNEL_KY = ?
                        AND HASHES.EVENT_DATE > LAST_SEEN_DATE) AS NUM_HASHES_MISSED, (
                     SELECT MAX(KENNEL_EVENT_NUMBER)
                       FROM HASHES
                      WHERE HASHES.EVENT_DATE = LAST_SEEN_DATE
                        AND HASHES.HASH_KY IN (
                            SELECT HASH_KY
                              FROM HASHINGS
                             WHERE KENNEL_KY = ?
                               AND HASHINGS.HASHER_KY = HASHER_KY)) AS LAST_SEEN_EVENT
                FROM (SELECT HASHER_NAME, HASHER_ABBREVIATION, HASHERS.HASHER_KY AS HASHER_KY, (
                             SELECT MAX(EVENT_DATE)
                               FROM HASHES
                              WHERE HASHES.HASH_KY IN (
                                    SELECT HASH_KY
                                      FROM HASHINGS
                                     WHERE KENNEL_KY = ?
                                       AND HASHINGS.HASHER_KY = HASHERS.HASHER_KY)) AS LAST_SEEN_DATE
                        FROM HASHERS
                       WHERE HASHER_NAME NOT LIKE 'Just %'
                         AND HASHER_NAME NOT LIKE 'NHN %'
                         AND DECEASED = 0) INNER1
               WHERE LAST_SEEN_DATE IS NOT NULL) INNER2
       WHERE NUM_HASHES_MISSED > 0";

    $sql2 = "$sql
         AND (HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?)";

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
    $theResults = $this->fetchAll($sql3, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy,
      $inputSearchValueModified, $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,
      [ $kennelKy, $kennelKy, $kennelKy, $kennelKy ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy,
      $inputSearchValueModified, $inputSearchValueModified ]))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/attendancePercentages',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function attendancePercentagesPostActionJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

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
    $sql = "
      SELECT HASHER_NAME,
             100 * (NUM_HASHES / ALL_EVENTS_COUNT) AS OVERALL_PERCENTAGE,
             100 * (NUM_HASHES / HASHER_EVENTS_TO_DATE) AS CURRENT_PERCENTAGE,
             100 * (NUM_HASHES / CAREER_EVENTS) AS CAREER_PERCENTAGE,
             NUM_HASHES, HASHER_KY
        FROM (SELECT HASHERS.HASHER_NAME AS HASHER_NAME, HASHERS.HASHER_KY AS HASHER_KY,
                     HASHERS.HASHER_ABBREVIATION AS HASHER_ABBREVIATION,
                     ALL_EVENTS.THE_COUNT AS ALL_EVENTS_COUNT,
                     HASHER_DETAILS.THE_COUNT AS NUM_HASHES, (
                     SELECT COUNT(*)
                       FROM HASHES
                      WHERE HASHES.KENNEL_KY=?
                        AND HASHES.EVENT_DATE >= HASHER_DETAILS.FIRST_HASH_DATE) AS HASHER_EVENTS_TO_DATE, (
                     SELECT COUNT(*)
                       FROM HASHES
                      WHERE HASHES.KENNEL_KY=?
                        AND HASHES.EVENT_DATE >= HASHER_DETAILS.FIRST_HASH_DATE
                        AND HASHES.EVENT_DATE <= HASHER_DETAILS.LAST_HASH_DATE) AS CAREER_EVENTS
                FROM HASHERS
               CROSS JOIN (SELECT COUNT(*) AS THE_COUNT
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
                  ON HASHER_DETAILS.HASHER_KY=HASHERS.HASHER_KY) AS INNER_QUERY
       WHERE HASHER_NAME LIKE ? OR HASHER_ABBREVIATION LIKE ?
       ORDER BY $inputOrderColumn $inputOrderDirection
       LIMIT $inputStart,$inputLength";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
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
    $theResults = $this->fetchAll($sql, [
      $kennelKy, $kennelKy, $kennelKy, $kennelKy, $inputSearchValueModified, $inputSearchValueModified ]);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [
      $kennelKy, $inputSearchValueModified, $inputSearchValueModified ] ))['THE_COUNT'];

    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = [
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/listhashes/byhasher/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function listHashesByHasherAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_DATE, DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME,
             EVENT_LOCATION, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION, HASH_TYPE_NAME
        FROM HASHES
        JOIN HASHINGS
          ON HASHES.HASH_KY = HASHINGS.HASH_KY
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
       WHERE HASHINGS.HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE DESC";

    $hashList = $this->fetchAll($sql, [ $hasher_id, $kennelKy ]);

    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes $hasherName has done";

    return $this->render('hash_list.twig', [
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation]);
  }

  #[Route('/{kennel_abbreviation}/attendanceRecordForHasher/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function attendanceRecordForHasherAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hashList = $this->fetchAll($this->sqlQueries->getHasherAttendanceRecordList(),
      [ $kennelKy, $hasher_id, $kennelKy ] );

    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes attended by $hasherName";

    return $this->render('hasher_attendance_list.twig', [
      'pageTitle' => 'Attendance Record',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/listhashes/byhare/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function listHashesByHareAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_DATE, DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME,
             EVENT_LOCATION, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION, HASH_TYPE_NAME
        FROM HASHES
        JOIN HARINGS
          ON HASHES.HASH_KY = HARINGS.HARINGS_HASH_KY
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       ORDER BY EVENT_DATE DESC";

    $hashList = $this->fetchAll($sql, [ $hasher_id, $kennelKy ]);

    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ? ";

    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $pageSubtitle = "The hashes $hasherName has hared";

    return $this->render('hash_list.twig', [
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/hashedWith/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function hashedWithAction(int $hasher_id, string $kennel_abbreviation) {

    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ? ";

    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    $hasherName = $hasher['HASHER_NAME'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = "
      SELECT HASHERS.HASHER_NAME AS NAME, HASHERS.HASHER_KY AS THE_KEY, COUNT(*) AS VALUE
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY=HASHINGS.HASHER_KY
       WHERE HASHINGS.HASH_KY IN (
             SELECT HASHES.HASH_KY
               FROM HASHINGS
               JOIN HASHES
                 ON HASHINGS.HASH_KY = HASHES.HASH_KY
              WHERE HASHINGS.HASHER_KY=?
                AND HASHES.KENNEL_KY=?)
         AND HASHINGS.HASHER_KY!=?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY
       ORDER BY VALUE DESC, NAME";

    $theResults = $this->fetchAll($theSql, [ $hasher_id, $kennelKy, $hasher_id ]);

    $pageTitle = "Hashers that have hashed with $hasherName";

    return $this->render('name_number_list.twig', [
      'pageTitle' => $pageTitle,
      'tableCaption' => '',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Count',
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HashedWith' ]);
  }

  #[Route('/{kennel_abbreviation}/hashers/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function viewHasherChartsAction(int $hasher_id, string $kennel_abbreviation) {

    $sql = "
      SELECT HASHER_KY, HASHER_NAME, HASHER_ABBREVIATION, FIRST_NAME, LAST_NAME, DECEASED 
        FROM HASHERS 
       WHERE HASHER_KY = ?";

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hasher = $this->fetchAssoc($sql, [ $hasher_id ]);

    $sqlTheHashes = "
      SELECT KENNEL_EVENT_NUMBER, LAT, LNG, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE, HASHINGS.HASH_KY
        FROM HASHINGS
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHER_KY = ?
         AND KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";

    $theHashes = $this->fetchAll($sqlTheHashes, [ $hasher_id, $kennelKy ]);

    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHINGS
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHER_KY = ?
         AND KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";

    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $hasher_id, $kennelKy ]);

    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    $hashCountValue = $this->fetchAssoc($this->getPersonsHashingCountQuery(),
      [ $hasher_id, $kennelKy, $hasher_id, $kennelKy ]);

    # Obtain the number of harings
    $hareCountValue = $this->fetchAssoc($this->sqlQueries->getPersonsHaringCount(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by month (name)
    $theHashesByMonthNameList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByMonthName(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by quarter
    $theHashesByQuarterList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByQuarter(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by quarter
    $theHashesByStateList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByState(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by county
    $theHashesByCountyList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByCounty(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by postal code
    $theHashesByPostalCodeList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByPostalCode(), [ $hasher_id, $kennelKy ]);

    # Obtain the hashes by day name
    $theHashesByDayNameList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByDayname(), [ $hasher_id, $kennelKy ]);

    #Obtain the hashes by year
    $sqlHashesByYear = "
      SELECT YEAR(EVENT_DATE) AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHINGS
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHINGS.HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE)";

    $hashesByYearList = $this->fetchAll($sqlHashesByYear, [ $hasher_id, $kennelKy ]);

    #Obtain the harings by year
    $sqlHaringsByYear = "
      SELECT YEAR(EVENT_DATE) AS THE_VALUE,
             COUNT(*) AS TOTAL_HARING_COUNT
        FROM HARINGS
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE)";

    $haringsByYearList = $this->fetchAll($sqlHaringsByYear, [ $hasher_id, $kennelKy ]);

    #Query the database
    $cityHashingsCountList = $this->fetchAll($this->sqlQueries->getHasherHashCountsByCity(), [ $hasher_id, $kennelKy ]);

    #Obtain largest entry from the list
    $cityHashingsCountMax = 1;
    if(isset($cityHashingsCountList[0]['THE_COUNT'])){
      $cityHashingsCountMax = $cityHashingsCountList[0]['THE_COUNT'];
    }

    #Obtain their largest streak
    $longestStreakValue = $this->fetchAssoc($this->sqlQueries->getTheLongestStreaksForHasher(), [ $kennelKy, $hasher_id ]);

    #By Quarter/ Month ---------------------------------------------------
    $quarterMonthSql = "
      SELECT CONCAT (THE_QUARTER,'/',MONTH_NAME,'/',THE_COUNT) AS THE_VALUE, THE_COUNT
        FROM (
              SELECT CASE WHEN THE_VALUE IN ( '1', '2', '3') THEN 'Q1'
                          WHEN THE_VALUE IN ( '4', '5', '6') THEN 'Q2'
                          WHEN THE_VALUE IN ( '7', '8', '9') THEN 'Q3'
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
                FROM (
                      SELECT MONTH(EVENT_DATE) AS THE_VALUE, COUNT(*) AS THE_COUNT
                        FROM HASHINGS
                        JOIN HASHES
                          ON HASHINGS.HASH_KY = HASHES.HASH_KY
                       WHERE HASHINGS.HASHER_KY = ?
                         AND HASHES.KENNEL_KY = ?
                       GROUP BY MONTH(EVENT_DATE)
                       ORDER BY MONTH(EVENT_DATE)) TEMP_TABLE) ASDF";


    #Query the db
    $quarterMonthValues = $this->fetchAll($quarterMonthSql, [ $hasher_id, $kennelKy ]);
    $quarterMonthFormattedData = $this->helper->convertToFormattedHiarchy($quarterMonthValues);

    # End by Quarter Month ------------------------------------------------

    #Obtain the state/county/city data for the sunburst chart
    $sunburstSqlA = "
      SELECT CONCAT(EVENT_STATE,'/',COUNTY,'/',EVENT_CITY,'/',THE_COUNT) AS THE_VALUE, THE_COUNT
        FROM (SELECT EVENT_STATE, COUNTY, EVENT_CITY,  COUNT(*) AS THE_COUNT
                FROM HASHES
                JOIN HASHINGS
                  ON HASHES.HASH_KY = HASHINGS.HASH_KY
               WHERE HASHINGS.HASHER_KY = ?
                 AND HASHES.KENNEL_KY = ?
               GROUP BY EVENT_STATE, COUNTY, EVENT_CITY
               ORDER BY EVENT_STATE, COUNTY, EVENT_CITY) TEMPTABLE
       WHERE EVENT_STATE IS NOT NULL
         AND EVENT_STATE != ''
         AND COUNTY IS NOT NULL
         AND COUNTY != ''
         AND EVENT_CITY IS NOT NULL
         AND EVENT_CITY != ''";

    #Obtain their sunburst data
    $sunburstValuesA = $this->fetchAll($sunburstSqlA, [ $hasher_id, $kennelKy ]);
    $sunburstFormattedData = $this->helper->convertToFormattedHiarchy($sunburstValuesA);

    $hareTypes = $this->getHareTypes($kennelKy);

    if($this->hasLegacyHashCounts()) {
      $sql = "
        SELECT LEGACY_HASHINGS_COUNT
          FROM LEGACY_HASHINGS
         WHERE HASHER_KY = ?
           AND KENNEL_KY = ?";
      $legacy_run_count = $this->fetchOne($sql, [ $hasher_id, $kennelKy ]);
      if(!$legacy_run_count) {
        $legacy_run_count = 0;
      }
    } else {
      $legacy_run_count = 0;
    }

    $hareCounts = [];

    foreach ($hareTypes as &$hareType) {
      $total = $this->fetchAssoc($this->sqlQueries->getPersonsHaringTypeCount(), [ $hasher_id, $kennelKy, $hareType['HARE_TYPE']]);

      array_push($hareCounts, [
        'type' => $hareType['HARE_TYPE_NAME'],
        'total' => $total['THE_COUNT']]);
    }

    return $this->render('hasher_chart_details.twig', [
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : [],
      'overall_hare_details' => (count($hareTypes) > 1 ? "Overall " : "").  "Hare Details",
      'sunburst_formatted_data' => $sunburstFormattedData,
      'quarter_month_formatted_data' => $quarterMonthFormattedData,
      'pageTitle' => 'Hasher Charts and Details',
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'hasherValue' => $hasher,
      'hashCount' => $hashCountValue['THE_COUNT'],
      'hareCount' => $hareCountValue['THE_COUNT'],
      'hareCounts' => $hareCounts,
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
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng,
      'longest_streak' => $longestStreakValue['MAX_STREAK'],
      'legacy_run_count' => $legacy_run_count
    ]);
  }

  #[Route('/{kennel_abbreviation}/hashes/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function viewHashAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $houndCountSQL = $this->sqlQueries->getHoundCountByHashKey();
    $theHoundCountValue = $this->fetchAssoc($houndCountSQL, [ $hash_id ]);
    $theHoundCount = $theHoundCountValue['THE_COUNT'];

    $hareCountSQL = $this->sqlQueries->getHareCountByHashKey();
    $theHareCountValue = $this->fetchAssoc($hareCountSQL, [ $hash_id ]);
    $theHareCount = $theHareCountValue['THE_COUNT'];

    # Determine previous hash
    $previousHashSql = "
      SELECT HASH_KY AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND EVENT_DATE < (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?) 
       ORDER BY EVENT_DATE DESC 
       LIMIT 1";
    $result = $this->fetchAssoc($previousHashSql, [ $kennelKy, $hash_id ]);
    if($result) {
      $previousHashId = $result['THE_COUNT'];
    } else {
      $previousHashId = null;
    }

    # Determine next hash
    $nextHashSql = "
      SELECT HASH_KY AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND EVENT_DATE > (SELECT event_date FROM HASHES WHERE hash_ky = ?)
       ORDER BY EVENT_DATE
       LIMIT 1";
    $result = $this->fetchAssoc($nextHashSql, [ $kennelKy, $hash_id ]);
    if($result) {
      $nextHashId = $result['THE_COUNT'];
    } else {
      $nextHashId = null;
    }

    # Make a database call to obtain the event information
    $sql = "
      SELECT EVENT_STATE, COUNTY, EVENT_CITY, EVENT_LOCATION, STREET_NUMBER, ROUTE, FORMATTED_ADDRESS, NEIGHBORHOOD,
             POSTAL_CODE, COUNTRY, LAT, LNG, KENNEL_EVENT_NUMBER, EVENT_DATE, SPECIAL_EVENT_DESCRIPTION, HASH_KY, HASH_TYPE_NAME
        FROM HASHES
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
       WHERE HASH_KY = ?";
    $theHashValue = $this->fetchAssoc($sql, [ $hash_id ]);

    $state = $theHashValue['EVENT_STATE'];
    $county = $theHashValue['COUNTY'];
    $city = $theHashValue['EVENT_CITY'];
    $neighborhood = $theHashValue['NEIGHBORHOOD'];
    $postalCode = $theHashValue['POSTAL_CODE'];

    $showState = !$this->isNullOrEmpty($state);
    $showCounty = !$this->isNullOrEmpty($county);
    $showCity = !$this->isNullOrEmpty($city);
    $showNeighborhood = !$this->isNullOrEmpty($neighborhood);
    $showPostalCode = !$this->isNullOrEmpty($postalCode);

    return $this->render('hash_details.twig', [
      'pageTitle' => 'Hash Details',
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'hashValue' => $theHashValue,
      'kennel_abbreviation' => $kennel_abbreviation,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'showStateCountList' => $showState,
      'showCountyCountList' => $showCounty,
      'showCityCountList' => $showCity,
      'showNeighborhoodCountList' => $showNeighborhood,
      'showPostalCodeCountList' => $showPostalCode,
      'theHoundCount' => $theHoundCount,
      'theHareCount' => $theHareCount,
      'nextHashId' => $nextHashId,
      'previousHashId' => $previousHashId,
      'showOmniAnalversaryPage' => $this->showOmniAnalversaryPage()
    ]);
  }

  #[Route('/{kennel_abbreviation}/consolidatedEventAnalversaries/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function consolidatedEventAnalversariesAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $houndAnalversaryList = $this->fetchAll($this->getHoundAnalversariesForEvent(), [ $hash_id, $kennelKy, $hash_id ]);
    $consolidatedHareAnalversaryList = $this->fetchAll($this->sqlQueries->getConsolidatedHareAnalversariesForEvent(),
        [ $hash_id, $kennelKy, $hash_id, $hash_id, $kennelKy, $hash_id ]);

    $centurionAlertList = $this->fetchAll($this->getPendingCenturionsForEvent(), [ $hash_id, $kennelKy, $hash_id ]);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $sqlHoundAnalversaryTemplate = "
      SELECT *
        FROM (SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
                     COUNT(*) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")." AS THE_COUNT,
                     MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE,
                     'AAA' AS ANV_TYPE,
                     (SELECT XXX FROM HASHES WHERE HASH_KY = ?) AS ANV_VALUE
                FROM HASHERS
                JOIN HASHINGS
                  ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
                JOIN HASHES
                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
               WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
                 AND HASHES.KENNEL_KY = (SELECT KENNEL_KY FROM HASHES WHERE HASH_KY = ?)
                 AND HASHES.XXX = (SELECT XXX FROM HASHES WHERE HASH_KY = ?)
               GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
              HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
                 AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
               ORDER BY THE_COUNT DESC) DERIVED_TABLE
       WHERE ANV_VALUE !=''";

    $sqlHoundAnalversaryDateBasedTemplate = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             (COUNT(*)) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE,
             'AAA' AS ANV_TYPE,
             (SELECT XXX(HASHES.EVENT_DATE) FROM HASHES WHERE HASH_KY = ?) AS ANV_VALUE
        FROM HASHERS
        JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = (SELECT KENNEL_KY FROM HASHES WHERE HASH_KY = ?)
         AND XXX(HASHES.EVENT_DATE) = (SELECT XXX(EVENT_DATE) FROM HASHES WHERE HASH_KY = ?)
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
         AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

      #Obtain the state analversaries (hound)
      $theSqlHoundState = str_replace("AAA", "State", str_replace("XXX", "EVENT_STATE", $sqlHoundAnalversaryTemplate));
      $theHoundStateList = $this->fetchAll($theSqlHoundState, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the city analversaries (hound)
      $theSqlHoundCity = str_replace("AAA", "City", str_replace("XXX","EVENT_CITY", $sqlHoundAnalversaryTemplate));
      $theHoundCityList = $this->fetchAll($theSqlHoundCity, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the neighborhood analversaries (hound)
      $theSqlHoundNeighborhood = str_replace("AAA", "Neighborhood", str_replace("XXX", "NEIGHBORHOOD", $sqlHoundAnalversaryTemplate));
      $theHoundNeighborhoodList = $this->fetchAll($theSqlHoundNeighborhood, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the county analversaries (hound)
      $theSqlHoundCounty = str_replace("AAA", "County", str_replace("XXX", "COUNTY", $sqlHoundAnalversaryTemplate));
      $theHoundCountyList = $this->fetchAll($theSqlHoundCounty, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the postal code analversaries (hound)
      $theSqlHoundPostalCode = str_replace("AAA", "Zip Code", str_replace("XXX", "POSTAL_CODE", $sqlHoundAnalversaryTemplate));
      $theHoundPostalCodeList = $this->fetchAll($theSqlHoundPostalCode, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the postal code analversaries (hound)
      $theSqlHoundRoute = str_replace("AAA", "Street", str_replace("XXX", "ROUTE", $sqlHoundAnalversaryTemplate));
      $theHoundRouteList = $this->fetchAll($theSqlHoundRoute, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the year analversaries (hound)
      $theSqlHoundYear = str_replace("AAA", "Year", str_replace("XXX", "YEAR", $sqlHoundAnalversaryDateBasedTemplate));
      $theHoundYearList = $this->fetchAll($theSqlHoundYear, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the month analversaries (hound)
      $theSqlHoundMonth = str_replace("AAA", "Month", str_replace("XXX", "MONTHNAME", $sqlHoundAnalversaryDateBasedTemplate));
      $theHoundMonthList = $this->fetchAll($theSqlHoundMonth, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Obtain the day analversaries (hound)
      $theSqlHoundDay = str_replace("AAA","Day", str_replace("XXX", "DAYNAME", $sqlHoundAnalversaryDateBasedTemplate));
      $theHoundDayList = $this->fetchAll($theSqlHoundDay, [ $hash_id, $hash_id, $hash_id, $hash_id, $hash_id ]);

      #Merge the arrays
      $geolocationHoundAnalversaryList = array_merge(
        $theHoundStateList,
        $theHoundCityList,
        $theHoundNeighborhoodList,
        $theHoundCountyList,
        $theHoundPostalCodeList,
        $theHoundRouteList);

      #Merge the arrays
      $dateHoundAnalversaryList = array_merge(
        $theHoundYearList,
        $theHoundMonthList,
        $theHoundDayList);

      #Sort the arrays
      $theCountArray = array();
      foreach($geolocationHoundAnalversaryList as $key => $row){
        $theCountArray[$key] = $row['THE_COUNT'];
      }
      array_multisort($theCountArray, SORT_DESC, $geolocationHoundAnalversaryList);

      #Sort the arrays
      $theCountDateArray = array();
      foreach($dateHoundAnalversaryList as $key => $row){
        $theCountDateArray[$key] = $row['THE_COUNT'];
      }
      array_multisort($theCountDateArray, SORT_DESC, $dateHoundAnalversaryList);

      #Obtain the streakers
      $theStreakersList = $this->fetchAll($this->sqlQueries->getStreakersList(), [ $hash_id, $kennelKy ]);

      #Obtain the backsliders
      $backSliderList = $this->fetchAll($this->sqlQueries->getBackslidersForSpecificHashEvent(), [ $kennelKy, $hash_id, $kennelKy, $hash_id ]);

      # Establish and set the return value
      $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
      $hashLocation = $theHashValue['EVENT_LOCATION'];
      $pageSubtitle = "Analversaries at the $hashNumber ($hashLocation) Hash";

      # Establish the return value
      return $this->render('consolidated_event_analversaries.twig', [
        'pageTitle' => 'Consolidated Analversaries',
        'pageSubTitle' => $pageSubtitle,
        'houndAnalversaryList' => $houndAnalversaryList,
        'centurionAlertList' => $centurionAlertList,
        'consolidatedHareAnalversaryList' => $consolidatedHareAnalversaryList,
        'kennel_abbreviation' => $kennel_abbreviation,
        'geolocationHoundAnalversaryList' => $geolocationHoundAnalversaryList,
        'dateHoundAnalversaryList' => $dateHoundAnalversaryList,
        'theHashValue' => $theHashValue,
        'theStreakersList' => $theStreakersList,
        'theBackslidersList' => $backSliderList
      ]);
    }

  #[Route('/{kennel_abbreviation}/omniAnalversariesForEvent/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function omniAnalversariesForEventAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $analversaryListHounds = $this->fetchAll($this->getHoundAnalversariesForEvent(), [ $hash_id, $kennelKy, $hash_id ]);
    $analversaryListHares = $this->fetchAll($this->sqlQueries->getOverallHareAnalversariesForEvent(), [ $hash_id, $kennelKy, $hash_id ]);

    $sql_for_hash_event = "
      SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_STATE, EVENT_CITY, NEIGHBORHOOD, COUNTY, POSTAL_CODE, ROUTE,
             YEAR(EVENT_DATE) AS THE_YEAR, MONTHNAME(EVENT_DATE) AS THE_MONTH, DAYNAME(EVENT_DATE) AS THE_DAY
        FROM HASHES
       WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventState = $theHashValue['EVENT_STATE'];
    if($this->isNullOrEmpty($theHashEventState)) {
      $theHashEventState = "UNKNOWN";
    }

    $theHashYear = $theHashValue['THE_YEAR'];
    if($this->isNullOrEmpty($theHashYear)) {
      $theHashYear = "UNKNOWN";
    }

    $theHashMonth = $theHashValue['THE_MONTH'];
    if($this->isNullOrEmpty($theHashMonth)) {
      $theHashMonth = "UNKNOWN";
    }

    $theHashDay = $theHashValue['THE_DAY'];
    if($this->isNullOrEmpty($theHashDay)) {
      $theHashDay = "UNKNOWN";
    }

    $theHashEventCity = $theHashValue['EVENT_CITY'];
    if($this->isNullOrEmpty($theHashEventCity)) {
      $theHashEventCity = "UNKNOWN";
    }

    $theHashEventNeighborhood = $theHashValue['NEIGHBORHOOD'];
    if($this->isNullOrEmpty($theHashEventNeighborhood)) {
      $theHashEventNeighborhood = "UNKNOWN";
    }

    $theHashEventCounty = $theHashValue['COUNTY'];
    if($this->isNullOrEmpty($theHashEventCounty)) {
      $theHashEventCounty = "UNKNOWN";
    }

    $theHashEventZip = $theHashValue['POSTAL_CODE'];
    if($this->isNullOrEmpty($theHashEventZip)) {
      $theHashEventZip = "UNKNOWN";
    }

    $theHashEventRoute = $theHashValue['ROUTE'];
    if($this->isNullOrEmpty($theHashEventRoute)) {
      $theHashEventRoute = "UNKNOWN";
    }

    $sqlHoundAnalversaryTemplate = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.XXX = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
         AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $sqlHoundAnalversaryTemplateDateBased = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND XXX(HASHES.EVENT_DATE) = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
         AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $sqlHareAnalversaryTemplate = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HARINGS
          ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.XXX = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
         AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $sqlHareAnalversaryTemplateDateBased = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HARINGS
          ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND XXX(HASHES.EVENT_DATE) = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING ((((THE_COUNT % 5) = 0) OR ((THE_COUNT % 69) = 0) OR ((THE_COUNT % 666) = 0) OR (((THE_COUNT - 69) % 100) = 0)))
         AND MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $theSqlHoundState = str_replace("XXX", "EVENT_STATE", $sqlHoundAnalversaryTemplate);
    $theSqlHoundCity = str_replace("XXX", "EVENT_CITY", $sqlHoundAnalversaryTemplate);
    $theSqlHoundNeighborhood = str_replace("XXX", "NEIGHBORHOOD", $sqlHoundAnalversaryTemplate);
    $theSqlHoundCounty = str_replace("XXX", "COUNTY", $sqlHoundAnalversaryTemplate);
    $theSqlHoundZip = str_replace("XXX", "POSTAL_CODE", $sqlHoundAnalversaryTemplate);
    $theSqlHoundRoad = str_replace("XXX", "ROUTE", $sqlHoundAnalversaryTemplate);
    $theSqlHoundYear = str_replace("XXX", "YEAR", $sqlHoundAnalversaryTemplateDateBased);
    $theSqlHoundMonth = str_replace("XXX", "MONTHNAME", $sqlHoundAnalversaryTemplateDateBased);
    $theSqlHoundDayName = str_replace("XXX", "DAYNAME", $sqlHoundAnalversaryTemplateDateBased);

    $theSqlHareState = str_replace("XXX", "EVENT_STATE", $sqlHareAnalversaryTemplate);
    $theSqlHareCity = str_replace("XXX", "EVENT_CITY", $sqlHareAnalversaryTemplate);
    $theSqlHareNeighborhood = str_replace("XXX", "NEIGHBORHOOD", $sqlHareAnalversaryTemplate);
    $theSqlHareCounty = str_replace("XXX", "COUNTY", $sqlHareAnalversaryTemplate);
    $theSqlHareZip = str_replace("XXX", "POSTAL_CODE", $sqlHareAnalversaryTemplate);
    $theSqlHareRoad = str_replace("XXX", "ROUTE", $sqlHareAnalversaryTemplate);
    $theSqlHareYear = str_replace("XXX", "YEAR", $sqlHareAnalversaryTemplateDateBased);
    $theSqlHareMonth = str_replace("XXX", "MONTHNAME", $sqlHareAnalversaryTemplateDateBased);
    $theSqlHareDayName = str_replace("XXX", "DAYNAME", $sqlHareAnalversaryTemplateDateBased);

    $theHoundStateList = $this->fetchAll($theSqlHoundState, [ $hash_id, $kennelKy,  $theHashEventState, $hash_id ]);
    $theHoundCityList = $this->fetchAll($theSqlHoundCity, [ $hash_id, $kennelKy,  $theHashEventCity, $hash_id ]);
    $theHoundNeighborhoodList = $this->fetchAll($theSqlHoundNeighborhood, [ $hash_id, $kennelKy,  $theHashEventNeighborhood, $hash_id ]);
    $theHoundCountyList = $this->fetchAll($theSqlHoundCounty, [ $hash_id, $kennelKy, $theHashEventCounty, $hash_id ]);
    $theHoundZipList = $this->fetchAll($theSqlHoundZip, [ $hash_id, $kennelKy,  $theHashEventZip, $hash_id ]);
    $theHoundRoadList = $this->fetchAll($theSqlHoundRoad, [ $hash_id, $kennelKy, $theHashEventRoute, $hash_id ]);
    $theHoundYearList = $this->fetchAll($theSqlHoundYear, [ $hash_id, $kennelKy, $theHashYear, $hash_id ]);
    $theHoundMonthList = $this->fetchAll($theSqlHoundMonth, [ $hash_id, $kennelKy, $theHashMonth, $hash_id ]);
    $theHoundDayNameList = $this->fetchAll($theSqlHoundDayName, [ $hash_id, $kennelKy, $theHashDay, $hash_id ]);

    $theHareStateList = $this->fetchAll($theSqlHareState, [ $hash_id, $kennelKy,  $theHashEventState, $hash_id ]);
    $theHareCityList = $this->fetchAll($theSqlHareCity, [ $hash_id, $kennelKy,  $theHashEventCity, $hash_id ]);
    $theHareNeighborhoodList = $this->fetchAll($theSqlHareNeighborhood, [ $hash_id, $kennelKy,  $theHashEventNeighborhood, $hash_id ]);
    $theHareCountyList = $this->fetchAll($theSqlHareCounty, [ $hash_id, $kennelKy, $theHashEventCounty, $hash_id ]);
    $theHareZipList = $this->fetchAll($theSqlHareZip, [ $hash_id, $kennelKy,  $theHashEventZip, $hash_id ]);
    $theHareRoadList = $this->fetchAll($theSqlHareRoad, [ $hash_id, $kennelKy, $theHashEventRoute, $hash_id ]);
    $theHareYearList = $this->fetchAll($theSqlHareYear, [ $hash_id, $kennelKy, $theHashYear, $hash_id ]);
    $theHareMonthList = $this->fetchAll($theSqlHareMonth, [ $hash_id, $kennelKy, $theHashMonth, $hash_id ]);
    $theHareDayNameList = $this->fetchAll($theSqlHareDayName, [ $hash_id, $kennelKy, $theHashDay, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageSubtitle = "All Analversaries at the $hashNumber ($hashLocation) Hash";

    return $this->render('omni_analversary_list.twig', [
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
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEvent/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $counts = $this->fetchAll($sql, [ $hash_id, $kennelKy, $hash_id]);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";
    $theHashEvent = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);
    $hashNumber = $theHashEvent['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashEvent['EVENT_LOCATION'];
    $pageSubtitle = "Hasher Counts at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => 'Hasher Counts',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $counts,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEventCounty/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventCountyAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hash_event = "SELECT COUNTY, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventCounty = $theHashValue['COUNTY'];
    if($this->isNullOrEmpty($theHashEventCounty)) {
      $theHashEventCounty = "UNKNOWN";
    }

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.COUNTY = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $analversaryList = $this->fetchAll($sql, [ $hash_id, $kennelKy, $theHashEventCounty, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventCounty";
    $pageSubtitle = "Hasher Counts in $theHashEventCounty at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEventPostalCode/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventPostalCodeAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hash_event = "SELECT POSTAL_CODE, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventPostalCode = $theHashValue['POSTAL_CODE'];
    if($this->isNullOrEmpty($theHashEventPostalCode)) {
      $theHashEventPostalCode = "UNKNOWN";
    }

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.POSTAL_CODE = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $analversaryList = $this->fetchAll($sql, [ $hash_id, $kennelKy, $theHashEventPostalCode, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventPostalCode postal code";
    $pageSubtitle = "Hasher Counts in $theHashEventPostalCode postal code at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEventState/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventStateAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hash_event = "SELECT KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_STATE FROM HASHES WHERE HASH_KY = ?";
    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventState = $theHashValue['EVENT_STATE'];
    if($this->isNullOrEmpty($theHashEventState)) {
      $theHashEventState = "UNKNOWN";
    }

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.EVENT_STATE = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $analversaryList = $this->fetchAll($sql, [ $hash_id, $kennelKy, $theHashEventState, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventState state";
    $pageSubtitle = "Hasher Counts in $theHashEventState state at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEventNeighborhood/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventNeighborhoodAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hash_event = "SELECT NEIGHBORHOOD, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventNeighborhood = $theHashValue['NEIGHBORHOOD'];
    if($this->isNullOrEmpty($theHashEventNeighborhood)) {
      $theHashEventNeighborhood = "UNKNOWN";
    }

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.NEIGHBORHOOD = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $analversaryList = $this->fetchAll($sql, [ $hash_id, $kennelKy, $theHashEventNeighborhood, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventNeighborhood neighborhood";
    $pageSubtitle = "Hasher Counts in $theHashEventNeighborhood neighborhood at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/hasherCountsForEventCity/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hasherCountsForEventCityAction(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hash_event = "SELECT EVENT_CITY, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $theHashEventCity = $theHashValue['EVENT_CITY'];

    $sql = "
      SELECT HASHERS.HASHER_NAME AS HASHER_NAME,
             COUNT(*) + ".$this->getLegacyHashingsCountSubquery()." AS THE_COUNT,
             MAX(HASHES.EVENT_DATE) AS MAX_EVENT_DATE
        FROM HASHERS
        JOIN HASHINGS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.EVENT_DATE <= (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
         AND HASHES.KENNEL_KY = ?
         AND HASHES.EVENT_CITY = ?
       GROUP BY HASHERS.HASHER_NAME, HASHERS.HASHER_KY, HASHES.KENNEL_KY
      HAVING MAX_EVENT_DATE = (SELECT EVENT_DATE FROM HASHES WHERE HASH_KY = ?)
       ORDER BY THE_COUNT DESC";

    $analversaryList = $this->fetchAll($sql, [ $hash_id, $kennelKy, $theHashEventCity, $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageTitle = "Hasher Counts for $theHashEventCity city";
    $pageSubtitle = "Hasher Counts in $theHashEventCity city at the $hashNumber ($hashLocation) Hash";

    return $this->render('analversary_list.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'theList' => $analversaryList,
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/backSlidersForEventV2/{hash_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function backSlidersForEventV2Action(int $hash_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql = $this->sqlQueries->getBackslidersForSpecificHashEvent();

    $backSliderList = $this->fetchAll($sql, [ $kennelKy, $hash_id, $kennelKy, $hash_id ]);

    $sql_for_hash_event = "SELECT EVENT_DATE, KENNEL_EVENT_NUMBER, EVENT_LOCATION FROM HASHES WHERE HASH_KY = ?";

    $theHashValue = $this->fetchAssoc($sql_for_hash_event, [ $hash_id ]);

    $hashNumber = $theHashValue['KENNEL_EVENT_NUMBER'];
    $hashLocation = $theHashValue['EVENT_LOCATION'];
    $pageSubtitle = "Back Sliders at the $hashNumber ($hashLocation) Hash";

    return $this->render('backslider_fluid_list.twig', [
      'pageTitle' => 'Back Sliders',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $backSliderList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'theHashValue' => $theHashValue
    ]);
  }

  #[Route('/{kennel_abbreviation}/pendingHasherAnalversaries',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function pendingHasherAnalversariesAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->getPendingHasherAnalversariesQuery();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #The number of harings into the future in which the analversaries will take place
    $fastForwardValue = 1;

    #The number of years absence before removing from the list...
    $yearsAbsenceLimit = 7;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $fastForwardValue, $kennelKy, $yearsAbsenceLimit ]);

    $tableCaption = $this->getMostRecentHash($kennelKy);

    return $this->render('pending_analversary_list.twig', [
      'pageTitle' => 'Pending Hasher Analversaries',
      'pageSubTitle' => 'The analversaries at their *next* hashes',
      'theList' => $hasherList,
      'tableCaption' => $tableCaption,
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Pending Count',
      'columnThreeName' => 'Years Absent',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/predictedHasherAnalversaries',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function predictedHasherAnalversariesAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->getPredictedHasherAnalversariesQuery();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $runrate=180;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy, $kennelKy, $runrate, $kennelKy, $runrate ]);

    # Establish the return value
    return $this->render('predicted_analversary_list.twig', [
      'pageTitle' => 'Predicted Hasher Analversaries (experimental)',
      'pageSubTitle' => 'Upcoming analversary predictions based on recent run rate (last '.$runrate.' days).',
      'theList' => $hasherList,
      'tableCaption' => 'Analversary Predictions',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Current Run Count',
      'columnThreeName' => 'Next Milestone',
      'columnFourName' => 'Predicted Date',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/predictedCenturions',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function predictedCenturionsAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->getPredictedCenturionsQuery();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $runrate=180;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, array($kennelKy, $kennelKy, $kennelKy, $runrate, $kennelKy, $runrate));

    return $this->render('predicted_analversary_list.twig', [
      'pageTitle' => 'Predicted Centurions (experimental)',
      'pageSubTitle' => 'Upcoming centurion predictions based on recent run rate (last '.$runrate.' days).',
      'theList' => $hasherList,
      'tableCaption' => 'Centurion Predictions',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Current Run Count',
      'columnThreeName' => 'Next Milestone',
      'columnFourName' => 'Predicted Date',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/pendingHareAnalversaries',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function pendingHareAnalversariesAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->sqlQueries->getPendingHareAnalversaries();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #The number of harings into the future in which the analversaries will take place
    $fastForwardValue = 1;

    #The number of years absence before removing from the list...
    $yearsAbsenceLimit = 7;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $fastForwardValue, $kennelKy, $yearsAbsenceLimit ]);

    $tableCaption = $this->getMostRecentHash($kennelKy);

    return $this->render('pending_analversary_list.twig', [
      'pageTitle' => 'Pending Hare Analversaries',
      'pageSubTitle' => 'The analversaries at their *next* harings',
      'theList' => $hasherList,
      'tableCaption' => $tableCaption,
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Pending Count',
      'columnThreeName' => 'Years Absent',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/haringPercentageAllHashes',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function haringPercentageAllHashesAction(string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->addHasherStatusToQuery($this->getHaringPercentageAllHashesQuery());

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #define the minimum number of hashes
    $minHashCount = 0;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy, $kennelKy,(int) $minHashCount ]);

    # Establish the return value
    return $this->render('percentage_list_filtered.twig', [
      'pageTitle' => 'Haring Percentage List',
      'tableCaption' => 'Percentage of harings per hashings for each hasher',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Hashing Count',
      'columnThreeName' => 'Haring Count',
      'columnFourName' => 'Haring Percentage',
      'pageTracking' => 'haringPercentageAll',
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/haringPercentage/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function haringPercentageAction(int $hare_type, string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = $this->addHasherStatusToQuery($this->getHaringPercentageByHareTypeQuery());

    $hare_type_name = $this->getHareTypeName($hare_type);

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #define the minimum number of hashes
    $minHashCount = 0;

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy, $kennelKy, $hare_type, $minHashCount ]);

    # Establish the return value
    return $this->render('percentage_list_filtered.twig', [
      'pageTitle' => $hare_type_name . ' Haring Percentage List',
      'tableCaption' => 'Percentage Of ' . $hare_type_name . ' Harings Per Hashings For Each Hasher',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Hashing Count',
      'columnThreeName' => 'Haring Count',
      'columnFourName' => 'Haring Percentage',
      'theList' => $hasherList,
      'pageTracking' => 'haringPercentage',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/percentages/harings',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function percentageHarings(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    $args = [ $kennelKy ];
    $columnNames = [ 'Hasher Name', 'Haring Count (All)' ];

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT ";

    foreach ($hareTypes as &$hareType) {
      $sql .=
        '    COALESCE('.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT_TEMP_TABLE.'.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT,0) AS '.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT,
             (COALESCE('.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT_TEMP_TABLE.'.$hareType['HARE_TYPE_NAME'].'_HARING_COUNT / ALL_HARING_COUNT_TEMP_TABLE.ALL_HARING_COUNT,0) * 100) AS '.$hareType['HARE_TYPE_NAME'].'_HARINGS_PERCENTAGE,';
    }
    $sql .=" HASHERS.HASHER_NAME, HASHERS.HASHER_KY, ALL_HARING_COUNT_TEMP_TABLE.ALL_HARING_COUNT
        FROM HASHERS
        JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY, COUNT(HARINGS.HARINGS_HASHER_KY) AS ALL_HARING_COUNT
                FROM HARINGS
                JOIN HARE_TYPES
                  ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
                JOIN HASHES
                  ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
               WHERE HASHES.KENNEL_KY = ?
               GROUP BY HARINGS.HARINGS_HASHER_KY) ALL_HARING_COUNT_TEMP_TABLE
          ON HASHERS.HASHER_KY = ALL_HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY";
    foreach ($hareTypes as &$hareType) {
      $sql .="
        LEFT JOIN (SELECT HARINGS.HARINGS_HASHER_KY AS HARINGS_HASHER_KY, COUNT(HARINGS.HARINGS_HASHER_KY) AS ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT
                     FROM HARINGS
                     JOIN HASHES
                       ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                    WHERE HARINGS.HARE_TYPE & ? != 0
                      AND HASHES.KENNEL_KY = ?
                    GROUP BY HARINGS.HARINGS_HASHER_KY) ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT_TEMP_TABLE
          ON HASHERS.HASHER_KY = ".$hareType['HARE_TYPE_NAME']."_HARING_COUNT_TEMP_TABLE.HARINGS_HASHER_KY";

      array_push($args, $hareType['HARE_TYPE']);
      array_push($args, $kennelKy);
      array_push($columnNames, 'Haring Count ('.$hareType['HARE_TYPE_NAME'].')');
      array_push($columnNames, $hareType['HARE_TYPE_NAME'].' Haring Percentage');
    }
    $sql .="
       ORDER BY HASHERS.HASHER_NAME";

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, $args);

    return $this->render('percentage_list_multiple_values.twig', [
      'pageTitle' => 'Haring Percentages',
      'tableCaption' => 'This shows the percentage of haring types for each hasher.',
      'columnNames' => $columnNames,
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hareTypes' => $hareTypes ]);
  }

  function addRankToQuery(string $query, string $selectClause, string $countColumn) {
    return "SELECT RANK() OVER(ORDER BY $countColumn DESC) AS THE_RANK, $selectClause FROM ($query) AS INNER_QUERY";
  }

  function addHasherStatusToQuery(string $query) {
    return "
      SELECT *
        FROM (SELECT iq.*,
                     CASE WHEN HASHERS.DECEASED = 1 THEN ' (RIP)'
                          WHEN (iq.LATEST_EVENT IS NULL) OR (DATEDIFF(CURDATE(), iq.LATEST_EVENT) >
                               CAST((SELECT value FROM SITE_CONFIG WHERE name='num_days_before_considered_inactive') AS SIGNED))
                               THEN ' (inactive)'
                          ELSE ' ' END AS STATUS
                FROM ($query) iq
                JOIN HASHERS
                  ON HASHERS.HASHER_KY = iq.THE_KEY) iq2
       WHERE 1=1 ".
             (array_key_exists("active", $_GET) && $_GET["active"] == "false" ? " AND STATUS != ' ' " : "").
             (array_key_exists("inactive", $_GET) && $_GET["inactive"] == "false" ? " AND STATUS != ' (inactive)' " : "").
             (array_key_exists("deceased", $_GET) && $_GET["deceased"] == "false" ? " AND STATUS != ' (RIP)' " : "")."
       ORDER BY VALUE DESC";
  }

  #[Route('/{kennel_abbreviation}/hashingCounts',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashingCountsAction(string $kennel_abbreviation) {

    $sql = $this->addHasherStatusToQuery($this->getHashingCountsQuery(true, true));

    # Declare the SQL used to retrieve this information
    $sql = $this->addRankToQuery($sql, "THE_KEY, NAME, VALUE, STATUS", "VALUE");

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [$kennelKy, $kennelKy]);

    # Establish and set the return value
    $returnValue = $this->render('name_number_rank_list.twig', [
      'pageTitle' => 'Hasher Counts',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => 'Hashers, and the number of hashes they have done. More is better.',
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HashCounts'
    ]);

    #Return the return value
    return $returnValue;
  }

  #[Route('/{kennel_abbreviation}/haringCounts',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function haringCountsAction(string $kennel_abbreviation) {

    $sql = $this->addHasherStatusToQuery($this->getHaringCountsQuery(true));

    # Declare the SQL used to retrieve this information
    $sql = $this->addRankToQuery($sql, "THE_KEY, NAME, VALUE, STATUS", "VALUE");

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $kennelKy, $kennelKy ]);

    return $this->render('name_number_rank_list.twig', [
      'pageTitle' => 'Haring Counts',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Haring Count',
      'tableCaption' => 'Hares, and the number of times they have hared. More is better.',
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HoundCounts' ]);
  }

  #[Route('/{kennel_abbreviation}/haringCounts/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function haringTypeCountsAction(string $kennel_abbreviation, int $hare_type) {

    $sql = $this->addHasherStatusToQuery($this->getHaringCountsByTypeQuery(true));

    # Declare the SQL used to retrieve this information
    $sql = $this->addRankToQuery($sql, "THE_KEY, NAME, VALUE, STATUS", "VALUE");

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hare_type_name = $this->getHareTypeName($hare_type);

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, [ $kennelKy, $hare_type, $kennelKy ]);

    return $this->render('name_number_rank_list.twig', [
      'pageTitle' => $hare_type_name.' Haring Counts',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => 'Hares, and the number of hashes they have hared. More is better.',
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => $hare_type_name.'HareCounts' ]);
  }

  #[Route('/{kennel_abbreviation}/coharelist/byhare/allhashes/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function coharelistByHareAllHashesAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT TEMPTABLE.HASHER_NAME,TEMPTABLE.HARINGS_HASHER_KY AS HASHER_KY, HASHES.KENNEL_EVENT_NUMBER, HASHES.SPECIAL_EVENT_DESCRIPTION,
             HASHES.EVENT_LOCATION, HASHES.HASH_KY
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN (SELECT HARINGS_HASH_KY, HASHER_NAME, HARINGS_HASHER_KY
                FROM HARINGS
                JOIN HASHERS
                  ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY) TEMPTABLE
          ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND TEMPTABLE.HARINGS_HASHER_KY <> ?
         AND HASHES.KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE, TEMPTABLE.HASHER_NAME ASC";

    #Execute the SQL statement; create an array of rows
    $cohareList = $this->fetchAll($sql, [ $hasher_id, $hasher_id, $kennelKy ]);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've had the shame of haring with $hasherName";
    return $this->render('cohare_list.twig', [
      'pageTitle' => 'Cohare List (All Hashes)',
      'pageSubTitle' => 'All Hashes',
      'tableCaption' => $captionValue,
      'theList' => $cohareList,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/coharelist/byhare/{hare_type}/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function coharelistByHareAction(int $hasher_id, int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT TEMPTABLE.HASHER_NAME, TEMPTABLE.HARINGS_HASHER_KY AS HASHER_KY, HASHES.KENNEL_EVENT_NUMBER, HASHES.SPECIAL_EVENT_DESCRIPTION,
             HASHES.EVENT_LOCATION, HASHES.HASH_KY
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN (SELECT HARINGS_HASH_KY, HASHER_NAME, HARINGS_HASHER_KY
                FROM HARINGS
                JOIN HASHERS
                  ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY) TEMPTABLE
          ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND TEMPTABLE.HARINGS_HASHER_KY <> ?
         AND HARINGS.HARE_TYPE & ? != 0
         AND HASHES.KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE, TEMPTABLE.HASHER_NAME ASC";

    #Execute the SQL statement; create an array of rows
    $cohareList = $this->fetchAll($sql, [ $hasher_id, $hasher_id, $hare_type, $kennelKy ]);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    $hare_type_name = $this->getHareTypeName($hare_type);

    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've had the shame of haring with $hasherName";

    return $this->render('cohare_list.twig', [
      'pageTitle' => $hare_type_name . ' Cohare List',
      'pageSubTitle' => '',
      'tableCaption' => $captionValue,
      'theList' => $cohareList,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/coharecount/byhare/allhashes/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function cohareCountByHareAllHashesAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT TEMPTABLE.HARINGS_HASHER_KY AS THE_KEY, TEMPTABLE.HASHER_NAME AS NAME, COUNT(*) AS VALUE
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN (SELECT HARINGS_HASH_KY, HASHER_NAME, HARINGS_HASHER_KY
                FROM HARINGS
                JOIN HASHERS
                  ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY) TEMPTABLE
          ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND TEMPTABLE.HARINGS_HASHER_KY <> ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY TEMPTABLE.HARINGS_HASHER_KY, TEMPTABLE.HASHER_NAME
       ORDER BY VALUE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $hasher_id, (int) $hasher_id, $kennelKy ]);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've hared with  $hasherName";

    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Hare Counts (All Hashes)',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hare Count',
      'tableCaption' => $captionValue,
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'CoHareList' ]);
  }

  #[Route('/{kennel_abbreviation}/coharecount/byhare/{hare_type}/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function cohareCountByHareAction(int $hasher_id, int $hare_type, string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT TEMPTABLE.HARINGS_HASHER_KY AS THE_KEY, TEMPTABLE.HASHER_NAME AS NAME, COUNT(*) AS VALUE
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN (SELECT HARINGS_HASH_KY, HASHER_NAME, HARINGS_HASHER_KY
                FROM HARINGS
                JOIN HASHERS
                  ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY) TEMPTABLE
          ON HARINGS.HARINGS_HASH_KY = TEMPTABLE.HARINGS_HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND TEMPTABLE.HARINGS_HASHER_KY <> ?
         AND HARINGS.HARE_TYPE & ? != 0
         AND HASHES.KENNEL_KY = ?
       GROUP BY TEMPTABLE.HARINGS_HASHER_KY, TEMPTABLE.HASHER_NAME
       ORDER BY VALUE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $hasher_id, $hasher_id, $hare_type, $kennelKy ]);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

    $hare_type_name = $this->getHareTypeName($hare_type);

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hares who've hared with  $hasherName";

    return $this->render('name_number_list.twig', [
      'pageTitle' => $hare_type_name.' Hare Counts',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hare Count',
      'tableCaption' => $captionValue,
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'CoHareList'.$hare_type_name.'Harings' ]);
  }

  #[Route('/{kennel_abbreviation}/hashattendance/byhare/lowest',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashAttendanceByHareLowestAction(string $kennel_abbreviation) {

    #Define the SQL to execute
    $sql = $this->sqlQueries->getLowestHashAttendanceByHare();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [$kennelKy] );

    # Establish and set the return value
    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Lowest hash attendance by hare',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hasher Count',
      'tableCaption' => 'The lowest hash attendance for each hare.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'LowestHashAttendanceByHare' ]);
  }

  #[Route('/{kennel_abbreviation}/hashattendance/byhare/highest',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashAttendanceByHareHighestAction(string $kennel_abbreviation) {

    #Define the SQL to execute
    $sql = $this->sqlQueries->getHighestHashAttendanceByHare();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Highest attended hashes by hare',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hasher Count',
      'tableCaption' => 'The highest attended hashes for each hare.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HighestHashAttendanceByHare' ]);
  }

  #[Route('/{kennel_abbreviation}/hashattendance/byhare/average',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashAttendanceByHareAverageAction(string $kennel_abbreviation) {

    #Define the SQL to execute
    $sql = $this->sqlQueries->getAverageHashAttendanceByHare();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Average hash attendance by hare',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hasher Count',
      'tableCaption' => 'The average hash attendance for each hare.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'AverageHashAttendanceByHare' ]);
  }

  #[Route('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/nondistincthashers',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashAttendanceByHareGrandTotalNonDistinctHashersAction(string $kennel_abbreviation){

    #Define the SQL to execute
    $sql = $this->sqlQueries->getGrandtotalNondistinctHashAttendanceByHare();

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $kennelKy ]);

    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Total (non distinct) hashers at their hashes',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => 'If hasher X has done 100 of hare Y\'s events, they contribute 100 to the hash count.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'TotalHashAttendanceByHareNonDistinct' ]);
  }

  #[Route('/{kennel_abbreviation}/hashattendance/byhare/grandtotal/distincthashers',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashAttendanceByHareGrandTotalDistinctHashersAction(string $kennel_abbreviation){

    $sql = $this->sqlQueries->getGrandtotalDistinctHashAttendanceByHare();

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $kennelKy ]);

    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Total distinct hashers at their hashes',
      'columnOneName' => 'Hare Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => 'If hasher X has done 100 of hare Y\'s events, they contribute 1 to the hash count.',
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'TotalHashAttendanceByHareDistinct' ]);
  }

  #[Route('/{kennel_abbreviation}/getHasherCountsByHare/{hare_id}/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_id' => '%app.pattern.hare_id%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function hasherCountsByHareAction(int $hare_id, int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT HASHERS.HASHER_KY AS THE_KEY, HASHERS.HASHER_NAME AS NAME, COUNT(*) AS VALUE
        FROM HARINGS
        JOIN HASHINGS
          ON HARINGS.HARINGS_HASH_KY = HASHINGS.HASH_KY
        JOIN HASHERS
          ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY ".
             ($hare_type != 0 ? "" : "
        JOIN HARE_TYPES ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE ")."
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHINGS.HASHER_KY != ?
         AND HASHES.KENNEL_KY = ? " .
             ($hare_type != 0 ? "
         AND HARINGS.HARE_TYPE & ? != 0 " : "
         AND HARINGS.HARE_TYPE != ?") . "
       GROUP BY HASHERS.HASHER_KY, HASHERS.HASHER_NAME
       ORDER BY VALUE DESC, NAME";

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $hare_id, $hare_id, $kennelKy, $hare_type ]);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hare_id ]);

    if($hare_type != 0) {
      $hare_type_name = $this->getHareTypeName($hare_type);
    } else {
      $hare_type_name = "";
    }

    # Establish and set the return value
    $hasherName = $hasher['HASHER_NAME'];
    $captionValue = "The hashers who've hashed under the " . $hare_type_name . " hare, $hasherName";

    return $this->render('name_number_list.twig', [
      'pageTitle' => 'Hasher Counts',
      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Hash Count',
      'tableCaption' => $captionValue,
      'theList' => $hashList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'HasherCountsByHare' ]);
  }

  #[Route('/{kennel_abbreviation}/basic/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function basicStatsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    #SQL to determine the distinct year values
    $sql = "
      SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE) DESC";

    #Execute the SQL statement; create an array of rows
    $yearValues = $this->fetchAll($sql, [ $kennelKy ]);

    #Obtain the first hash
    $firstHashSQL = "
      SELECT *
        FROM HASHES
       WHERE KENNEL_KY = ?
       ORDER BY EVENT_DATE ASC
       LIMIT 1";

    $firstHashValue = $this->fetchAssoc($firstHashSQL, [ $kennelKy ]);

    #Obtain the most recent hash
    $mostRecentHashSQL = "
      SELECT *
        FROM HASHES
       WHERE KENNEL_KY = ?
       ORDER BY EVENT_DATE DESC
       LIMIT 1";

    $mostRecentHashValue = $this->fetchAssoc($mostRecentHashSQL, [ $kennelKy ]);

    return  $this->render('basic_stats.twig', [
      'pageTitle' => 'Basic Information and Statistics',
      'kennel_abbreviation' => $kennel_abbreviation,
      'first_hash' => $firstHashValue,
      'latest_hash' => $mostRecentHashValue,
      'theYearValues' => $yearValues,
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : "",
      'overall' => count($hareTypes) > 1 ? "Overall " : "" ]);
  }

  #[Route('/{kennel_abbreviation}/people/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function peopleStatsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    return $this->render('section_people.twig', [
      'pageTitle' => 'People Stats',
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : "",
      'overall' => count($hareTypes) > 1 ? "Overall " : "",
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/analversaries/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function analversariesStatsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Determine the number of hashes already held for this kennel
    $sql2 = $this->getHashingCountsQuery(false);
    $sql2 = "$sql2 LIMIT 1";
    $theCount2 = $this->fetchAssoc($sql2, [ $kennelKy, $kennelKy ]);
    $theCount2 = $theCount2['VALUE'];

    return $this->render('section_analversaries.twig', [
      'pageTitle' => 'Analversary Stats',
      'kennel_abbreviation' => $kennel_abbreviation,
      'the_count' => $theCount2 ]);
  }

  #[Route('/{kennel_abbreviation}/year_by_year/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function yearByYearStatsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #SQL to determine the distinct year values
    $sql = "
      SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE) DESC";

    #Execute the SQL statement; create an array of rows
    $yearValues = $this->fetchAll($sql, [ $kennelKy ]);

    $hareTypes = $this->getHareTypes($kennelKy);

    return $this->render('section_year_by_year.twig', [
      'pageTitle' => 'Year Summary Stats',
      'kennel_abbreviation' => $kennel_abbreviation,
      'year_values' => $yearValues,
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : [],
      'overall' => count($hareTypes) > 1 ? " (Overall)" : "" ]);
  }

  #[Route('/{kennel_abbreviation}/kennel/records',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function kennelRecordsStatsAction(string $kennel_abbreviation){

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    return $this->render('section_kennel_records.twig', [
      'pageTitle' => 'Kennel Records',
      'kennel_abbreviation' => $kennel_abbreviation,
      "hare_types" => count($hareTypes) > 1 ? $hareTypes : [] ]);
  }

  #[Route('/{kennel_abbreviation}/kennel/general_info',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function kennelGeneralInfoStatsAction(string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    #Obtain the first hash
    $firstHashSQL = "
      SELECT HASH_KY, EVENT_DATE, KENNEL_EVENT_NUMBER
        FROM HASHES
       WHERE KENNEL_KY = ?
       ORDER BY EVENT_DATE ASC
       LIMIT 1";
    $firstHashValue = $this->fetchAssoc($firstHashSQL, [ $kennelKy ]);

    #Obtain the most recent hash
    $mostRecentHashSQL = "
      SELECT HASH_KY, EVENT_DATE, KENNEL_EVENT_NUMBER
        FROM HASHES
       WHERE KENNEL_KY = ?
      ORDER BY EVENT_DATE DESC
      LIMIT 1";
    $mostRecentHashValue = $this->fetchAssoc($mostRecentHashSQL, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('section_kennel_general_info.twig', [
      'pageTitle' => 'Kennel General Info',
      'kennel_abbreviation' => $kennel_abbreviation,
      'first_hash' => $firstHashValue,
      'latest_hash' => $mostRecentHashValue,
      'hare_types' => $hareTypes ]);
  }

  #[Route('/{kennel_abbreviation}/cautionary/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function cautionaryStatsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Establish the hasher keys for all hares for this kennel
    $hareKeysSQL = "
      SELECT HARINGS_HASHER_KY AS HARE_KEY
        FROM HARINGS
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHES.KENNEL_KY = ?
       ORDER BY RAND() LIMIT 5";

    #Execute the SQL statement; create an array of rows
    $hareKeys = $this->fetchAll($hareKeysSQL, [ $kennelKy ]);

    #Establish an array of ridiculous statistics
    $sql = "SELECT VALUE FROM SITE_CONFIG WHERE NAME LIKE 'ridiculous%'";
    $arrayOfRidiculousness = $this->fetchAll($sql, []);

    #Establish the keys of the random values to display
    $randomKeysForRidiculousStats = array_rand($arrayOfRidiculousness, 5);

    return $this->render('cautionary_stats.twig', [
      'listOfRidiculousness' => $arrayOfRidiculousness,
      'randomKeysForRidiculousStats' => $randomKeysForRidiculousStats,
      'pageTitle' => 'Cautionary Statistics',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hareKeys' => $hareKeys ]);
  }

  #[Route('/{kennel_abbreviation}/miscellaneous/stats',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function miscellaneousStatsAction(string $kennel_abbreviation) {

    $siteNamePattern = $this->getSiteConfigItem("site_domain_name", "bogus");

    #Obtain the kennels that are being tracked in this website instance
    $listOfKennelsSQL = "
      SELECT KENNEL_ABBREVIATION, KENNEL_NAME, IN_RECORD_KEEPING, SITE_ADDRESS,
             CASE WHEN IN_RECORD_KEEPING = 1 THEN ''
                  WHEN INSTR(SITE_ADDRESS, ?) > 0 THEN ''
                  ELSE '*'
              END AS EXTERNAL
        FROM KENNELS
       WHERE IN_RECORD_KEEPING = 1 OR SITE_ADDRESS IS NOT NULL
       ORDER BY IN_RECORD_KEEPING DESC, KENNEL_ABBREVIATION ASC";
    $kennelValues = $this->fetchAll($listOfKennelsSQL, [ $siteNamePattern ]);

    return $this->render('switch_kennel_screen.twig', [
      'pageTitle' => 'Switch Kennel',
      'kennel_abbreviation' => $kennel_abbreviation,
      'kennelValues' => $kennelValues ]);
  }

  #[Route('/{kennel_abbreviation}/highest/attendedHashes',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function highestAttendedHashesAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getHashEventsWithCounts();
    $theSql = str_replace("XLIMITX","25",$theSql);
    $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

    #Execute the SQL statement; create an array of rows
    $theList = $this->fetchAll($theSql, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('hash_events_with_participation_counts.twig', [
      'theList' => $theList,
      'pageTitle' => 'The Hashes',
      'pageSubTitle' => '...with the best attendances',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/lowest/attendedHashes',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function lowestAttendedHashesAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getHashEventsWithCounts();
    $theSql = str_replace("XLIMITX","25",$theSql);
    $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

    #Execute the SQL statement; create an array of rows
    $theList = $this->fetchAll($theSql, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('hash_events_with_participation_counts.twig', [
      'theList' => $theList,
      'pageTitle' => 'The Hashes',
      'pageSubTitle' => '...with the worst attendances',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/hashers/of/the/years',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hashersOfTheYearsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #SQL to determine the distinct year values
    $distinctYearsSql = "
      SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE) DESC";

    #Execute the SQL statement; create an array of rows
    $yearValues = $this->fetchAll($distinctYearsSql,array($kennelKy));

    #Define the sql
    $topHashersSql = "
      SELECT HASHER_KY, HASHER_NAME, THE_COUNT, ? AS THE_YEAR, (
             SELECT COUNT(*) AS THE_HASH_COUNT
               FROM HASHES
              WHERE KENNEL_KY = ?
                AND YEAR(HASHES.EVENT_DATE) = ?) AS THE_YEARS_HASH_COUNT,
              (THE_TEMPORARY_TABLE.THE_COUNT / (SELECT COUNT(*) AS THE_HASH_COUNT
                                                  FROM HASHES
                                                 WHERE KENNEL_KY = ?
                                                   AND YEAR(HASHES.EVENT_DATE) = ?))*100 AS HASHING_PERCENTAGE
        FROM (SELECT HASHERS.HASHER_KY, HASHERS.HASHER_NAME, COUNT(*) AS THE_COUNT
                FROM HASHINGS
                JOIN HASHERS
                  ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
                JOIN HASHES
                  ON HASHINGS.HASH_KY = HASHES.HASH_KY
               WHERE HASHES.KENNEL_KY = ?
                 AND YEAR(HASHES.EVENT_DATE) = ?
               GROUP BY HASHERS.HASHER_KY
               ORDER BY THE_COUNT DESC
               LIMIT XLIMITX) AS THE_TEMPORARY_TABLE";
    $topHashersSql = str_replace("XLIMITX","12",$topHashersSql);

    #Initialize the array of arrays
    $array = [];

    #Loop through the year values
    for ($tempCounter = 1; $tempCounter <= sizeof($yearValues); $tempCounter++) {

      #Establish the year for this loop iteration
      $tempYear = $yearValues[$tempCounter-1]["YEAR"];

      #Make a database call passing in this iteration's year value
      $tempResult = $this->fetchAll($topHashersSql,
        [ $tempYear, $kennelKy, $tempYear, $kennelKy, $tempYear, $kennelKy, $tempYear ]);

      #Add the database result set to the array of arrays
      $array[] = $tempResult;

    }

    return $this->render('top_hashers_by_years.twig', [
      'theListOfLists' => $array,
      'pageTitle' => 'Top Hashers Per Year',
      'pageSubTitle' => '',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/hares/{hare_type}/of/the/years',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function HaresOfTheYearsAction(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #SQL to determine the distinct year values
    $distinctYearsSql = "
      SELECT YEAR(EVENT_DATE) AS YEAR, COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE) DESC";

    #Execute the SQL statement; create an array of rows
    $yearValues = $this->fetchAll($distinctYearsSql, [ $kennelKy ]);

    $hashTypes = $this->getHashTypes($kennelKy, $hare_type);

    #Define the sql
    $topHaresSql = "
      SELECT HASHER_KY, HASHER_NAME, THE_COUNT, ? AS THE_YEAR,";

    foreach ($hashTypes as &$hashType) {
      $topHaresSql .=
        "(SELECT COUNT(*) AS THE_HASH_COUNT
            FROM HASHES
           WHERE KENNEL_KY = ?
             AND YEAR(HASHES.EVENT_DATE) = ?
             AND HASHES.HASH_TYPE = ?) AS THE_YEARS_".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
      }
      $topHaresSql .=
          "(SELECT COUNT(*) AS THE_HASH_COUNT
              FROM HASHES ".
           ($hare_type == 0 ? "" :
             "JOIN KENNELS
                ON HASHES.KENNEL_KY = KENNELS.KENNEL_KY
              JOIN HASH_TYPES
                ON HASH_TYPES.HASH_TYPE & KENNELS.HASH_TYPE_MASK != 0
               AND HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE")."
             WHERE HASHES.KENNEL_KY = ? ".
           ($hare_type == 0 ? "" : "
               AND HASH_TYPES.HARE_TYPE_MASK & ? != 0")."
               AND YEAR(HASHES.EVENT_DATE) = ? )
                AS THE_YEARS_OVERALL_HASH_COUNT
              FROM (SELECT HASHERS.HASHER_KY, HASHERS.HASHER_NAME, COUNT(*) AS THE_COUNT
                      FROM HARINGS
                      JOIN HASHERS
                        ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
                      JOIN HASHES
                        ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY ".
                  ($hare_type == 0 ? "" : "
                      JOIN HARE_TYPES
                        ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE ")."
                     WHERE HASHES.KENNEL_KY = ?
                       AND YEAR(HASHES.EVENT_DATE) = ? ".
                  ($hare_type == 0 ? "" : "
                       AND HARINGS.HARE_TYPE & ? != 0 ")."
                     GROUP BY HASHERS.HASHER_KY
                     ORDER BY THE_COUNT DESC
                     LIMIT XLIMITX) AS THE_TEMPORARY_TABLE";

    $topHaresSql = str_replace("XLIMITX","12",$topHaresSql);

    #Initialize the array of arrays
    $array = [];

    #Loop through the year values
    for ($tempCounter = 1; $tempCounter <= sizeof($yearValues); $tempCounter++){

      #Establish the year for this loop iteration
      $tempYear = $yearValues[$tempCounter-1]["YEAR"];

      $args = [ $tempYear ];
      foreach ($hashTypes as &$hashType) {
        array_push($args, $kennelKy);
        array_push($args, $tempYear);
        array_push($args, $hashType['HASH_TYPE']);
      }
      array_push($args, $kennelKy);
      if($hare_type != 0) array_push($args, $hare_type);
      array_push($args, $tempYear);
      array_push($args, $kennelKy);
      array_push($args, $tempYear);
      if($hare_type != 0) array_push($args, $hare_type);

      #Make a database call passing in this iteration's year value
      $tempResult = $this->fetchAll($topHaresSql,$args);

      #Add the database result set to the array of arrays
      $array[] = $tempResult;
    }

    if($hare_type != 0) {
      $hare_type_name = $this->getHareTypeName($hare_type);
    }

    # Establish and set the return value
    return $this->render('top_hares_by_years.twig', [
      'theListOfLists' => $array,
      'pageTitle' => $hare_type == 0 ? 'Top Hares Per Year (All harings)' : 'Top '.$hare_type_name.' Hares Per Year',
      'pageSubTitle' => $hare_type == 0 ? '(All hashes included)' : '',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation,
      'participant_column_header' => 'Hasher',
      'number_column_header' => $hare_type == 0 ? 'Number Of Overall Harings' : 'Number Of '.$hare_type_name.' Harings',
      'percentage_column_header' => $hare_type == 0 ? 'Percentage of overall hashes hared' : 'Percentage of hashes hared',
      'hash_types' => $hashTypes ]);
  }

  #[Route('/{kennel_abbreviation}/getHasherAnalversaries/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function getHasherAnalversariesAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_hasher_name = "
      SELECT HASHER_NAME
        FROM HASHERS
       WHERE HASHERS.HASHER_KY = ?";

    $hasherName = $this->fetchOne($sql_hasher_name, [ $hasher_id ]);

    # Define the SQL to retrieve all of their hashes
    $sql_all_hashes_for_this_hasher = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_DATE, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION
        FROM HASHINGS
        JOIN HASHERS
          ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHERS.HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE ASC";

    # Retrieve all of this hasher's hashes
    $theInitialListOfHashes = $this->fetchAll($sql_all_hashes_for_this_hasher, [ $hasher_id, $kennelKy ]);

    # Add a count into their list of hashes
    $destinationArray = [];
    $tempCounter = 1;
    foreach ($theInitialListOfHashes as &$individualHash) {
      $individualHash['ANALVERSARY_NUMBER'] = $tempCounter;
      if(($tempCounter % 5 == 0) || ($tempCounter % 69 == 0) ||
          ($tempCounter % 666 == 0) || (($tempCounter - 69) % 100 == 0)) {
        array_push($destinationArray, $individualHash);
      }
      $tempCounter ++;
    }

    $pageTitle = "Hashing Analversaries: $hasherName";

    return $this->render('hasher_analversary_list.twig', [
      'theList' => $destinationArray,
      'pageTitle' => $pageTitle,
      'pageSubTitle' => '',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/getHareAnalversaries/all/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function getHareAnalversariesAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_hasher_name = "
        SELECT HASHER_NAME
          FROM HASHERS
         WHERE HASHERS.HASHER_KY = ?";

    $hasherName = $this->fetchOne($sql_hasher_name, array($hasher_id));

    # Define the SQL to retrieve all of their hashes
    $sql_all_hashes_for_this_hasher = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_DATE, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHERS.HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE ASC";

    #Retrieve all of this hasher's hashes
    $theInitialListOfHashes = $this->fetchAll($sql_all_hashes_for_this_hasher, [ $hasher_id, $kennelKy ]);

    # Add a count into their list of hashes
    $destinationArray = [];
    $tempCounter = 1;
    foreach ($theInitialListOfHashes as &$individualHash) {
      $individualHash['ANALVERSARY_NUMBER'] = $tempCounter;
      if(($tempCounter % 5 == 0) ||
          ($tempCounter % 69 == 0) ||
          ($tempCounter % 666 == 0) ||
          (($tempCounter - 69) % 100 == 0)) {
        array_push($destinationArray,$individualHash);
      }
      $tempCounter ++;
    }

    # Establish and set the return value
    $pageTitle = "Haring Analversaries: $hasherName";

    return $this->render('hasher_analversary_list.twig', [
      'theList' => $destinationArray,
      'pageTitle' => $pageTitle,
      'pageSubTitle' => '',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/getHareAnalversaries/{hare_type}/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function getHareAnalversariesByHareTypeAction(int $hare_type, int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    $sql_hasher_name = "
      SELECT HASHER_NAME
        FROM HASHERS
       WHERE HASHERS.HASHER_KY = ?";

    $hasherName = $this->fetchOne($sql_hasher_name, array($hasher_id));

    # Define the SQL to retrieve all of their hashes
    $sql_all_hashes_for_this_hasher = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_LOCATION, EVENT_DATE, EVENT_CITY, SPECIAL_EVENT_DESCRIPTION
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHERS.HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
         AND HARINGS.HARE_TYPE & ? = ?
       ORDER BY HASHES.EVENT_DATE ASC";

    #Retrieve all of this hasher's hashes
    $theInitialListOfHashes = $this->fetchAll($sql_all_hashes_for_this_hasher, [ $hasher_id, $kennelKy, $hare_type, $hare_type ]);

    # Add a count into their list of hashes
    $destinationArray = [];
    $tempCounter = 1;
    foreach ($theInitialListOfHashes as &$individualHash) {
      $individualHash['ANALVERSARY_NUMBER'] = $tempCounter;
      if(($tempCounter % 5 == 0) ||
          ($tempCounter % 69 == 0) ||
          ($tempCounter % 666 == 0) ||
          (($tempCounter - 69) % 100 == 0)) {
        array_push($destinationArray,$individualHash);
      }
      $tempCounter ++;
    }

    # Establish and set the return value
    $pageTitle = $hareTypeName . " Haring Analversaries: $hasherName";

    return$this->render('hasher_analversary_list.twig', [
      'theList' => $destinationArray,
      'pageTitle' => $pageTitle,
      'pageSubTitle' => '',
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/getProjectedHasherAnalversaries/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function getProjectedHasherAnalversariesAction(int $hasher_id, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, [ $hasher_id ]);

  #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME, HASH_COUNT, LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE, FIRST_HASH_KEY,
             FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
             FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE, LATEST_HASH_KEY,
             LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER, HASHER_KY,
             ((DATEDIFF(CURDATE(),FIRST_HASH.EVENT_DATE)) / HASH_COUNT) AS DAYS_BETWEEN_HASHES
        FROM (SELECT HASHER_NAME, HASHER_KY, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)) AS HASH_COUNT, (
                     SELECT HASHES.HASH_KY
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
                      ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY, (
                     SELECT HASHES.HASH_KY
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.EVENT_DATE >= (CURDATE() - INTERVAL ? DAY)
                      ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
                FROM HASHERS) MAIN_TABLE
        JOIN HASHES LATEST_HASH
          ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
        JOIN HASHES FIRST_HASH
          ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
       WHERE HASHER_KY = ? ";

    # Make a database call to obtain the hasher information
    $numberOfDaysInDateRange = 360000;
    $hasherStatsObject = $this->fetchAssoc($sql, [ $kennelKy, $numberOfDaysInDateRange, $kennelKy,
      $numberOfDaysInDateRange, $kennelKy, $numberOfDaysInDateRange, $hasher_id ]);

    if($hasherStatsObject) {
      $hasherStatsHashCount = $hasherStatsObject['HASH_COUNT'];
      $hasherStatsDaysPerHash = $hasherStatsObject['DAYS_BETWEEN_HASHES'];
      $firstHashKey = $hasherStatsObject['FIRST_HASH_KEY'];
      $firstKennelEventNumber = $hasherStatsObject['FIRST_KENNEL_EVENT_NUMBER'];
      $firstEventDate = $hasherStatsObject['FIRST_EVENT_DATE'];
      $eventsToIterate = 750;
    } else {
      $hasherStatsHashCount = 0;
      $hasherStatsDaysPerHash = 32768;
      $firstHashKey = null;
      $firstKennelEventNumber = null;
      $firstEventDate = null;
      $eventsToIterate = 25;
    }

    $numberOfDaysInRecentDateRange = 365;
    $hasherRecentStatsObject = $this->fetchAssoc($sql, [
      $kennelKy, $numberOfDaysInRecentDateRange, $kennelKy, $numberOfDaysInRecentDateRange,
      $kennelKy, $numberOfDaysInRecentDateRange, $hasher_id ]);

    if(empty($hasherRecentStatsObject)) {
      $recentEventCount = 0;
      $recentDaysPerHash =  "Infinity";
    } else {
      $recentEventCount = $hasherRecentStatsObject['HASH_COUNT'];
      $recentDaysPerHash =  $hasherRecentStatsObject['DAYS_BETWEEN_HASHES'];
    }

    #Project out the next bunch of hash analversaries

    # Add a count into their list of hashes
    $destinationArray = [];

    #Loop through 750 events, or maybe 25
    for ($x = 1; $x <= $eventsToIterate; $x++) {
      $incrementedHashCount = $hasherStatsHashCount + $x;
      if(($incrementedHashCount % 25 == 0) || ($incrementedHashCount % 69 == 0) ||
          ($incrementedHashCount % 666 == 0) || (($incrementedHashCount - 69) % 100 == 0)){

        $daysToAdd = round($hasherStatsDaysPerHash * $x);
        $nowDate = date("Y/m/d");

        $incrementedDateOverall = date('Y-m-d',strtotime($nowDate) + (24*3600*$daysToAdd));

        if(empty($hasherRecentStatsObject)) {
          $daysToAddRecent = "infinity";
          $incrementedDateRecent = null;
        } else {
          $daysToAddRecent = round($recentDaysPerHash * $x);
          $incrementedDateRecent = date('Y-m-d',strtotime($nowDate) + (24*3600*$daysToAddRecent));
        }

        $obj = [
          'incrementedHashCount' => $incrementedHashCount,
          'incrementedDateOverall' => $incrementedDateOverall,
          'daysAddedOverall' => $daysToAdd,
          'incrementedDateRecent' => $incrementedDateRecent,
          'daysAddedRecent' => $daysToAddRecent
        ];

        array_push($destinationArray, $obj);
      }
    }

    $hasherName = $hasher['HASHER_NAME'];
    $pageTitle = "Projected Hashing Analversaries";

    return $this->render('projected_hasher_analversary_list.twig', [
      'theList' => $destinationArray,
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $hasherName,
      'tableCaption' => 'The projected analversaries are based on how many hashes this hasher has done, and how frequently this hasher has hashed them. It applies their days between hashes average and projects out when they might hit certain analversaries.',
      'kennel_abbreviation' => $kennel_abbreviation,
      'participant_column_header' => 'Hasher',
      'overall_boolean' => 'FALSE',
      'firstHashKey' => $firstHashKey,
      'firstKennelEventNumber' => $firstKennelEventNumber,
      'firstEventDate' => $firstEventDate,
      'overallRunRate' => $hasherStatsDaysPerHash,
      'recentDateRangeInDays' => $numberOfDaysInRecentDateRange,
      'recentRunRate' => $recentDaysPerHash,
      'overallHashCount' => $hasherStatsHashCount,
      'recentHashCount' => $recentEventCount
    ]);
  }

  #[Route('/{kennel_abbreviation}/jumboCountsTable',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function jumboCountsTablePreActionJson(string $kennel_abbreviation) {

    $minimumHashCount = $this->getSiteConfigItemAsInt('jumbo_counts_minimum_hash_count', 10);
    $subTitle = "Minimum of $minimumHashCount hashes";
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    $hareTypes = $this->getHareTypes($kennelKy);
    $hashTypes = $this->getHashTypes($kennelKy, 0);

    return $this->render('jumbo_counts_list_json.twig', [
      'pageTitle' => 'The Jumbo List of Counts (Experimental Page)',
      'pageSubTitle' => $subTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "",
      "hareTypes" => count($hareTypes) > 1 ? $hareTypes : [],
      "hashTypes" => count($hashTypes) > 1 ? $hashTypes : [] ]);
  }

  #[Route('/{kennel_abbreviation}/jumboCountsTable',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function jumboCountsTablePostActionJson(string $kennel_abbreviation) {

    $minimumHashCount = $this->getSiteConfigItemAsInt('jumbo_counts_minimum_hash_count', 10);

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);
    $hashTypes = $this->getHashTypes($kennelKy, 0);

    if(count($hareTypes) == 1) {
      $hareTypes = [];
    }

    if(count($hashTypes) == 1) {
      $hashTypes = [];
    }

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

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "2";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME, HASH_COUNT,";

    foreach ($hashTypes as &$hashType) {
      $sql .= $hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
    }

    $sql .= "HARE_COUNT,";

    foreach ($hareTypes as &$hareType) {
      $sql .= $hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
    }

    $args = [ $kennelKy, $kennelKy ];

    $sql .= "
             LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE, FIRST_HASH_KEY,
             FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
             FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE, LATEST_HASH_KEY,
             LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER,
             OUTER_HASHER_KY AS HASHER_KY
        FROM (SELECT HASHERS.HASHER_NAME, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
                      FROM HASHINGS
                      JOIN HASHES
                        ON HASHINGS.HASH_KY = HASHES.HASH_KY
                     WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                       AND HASHES.KENNEL_KY = ?) AS HASH_COUNT, (
                           SELECT COUNT(*)
                             FROM HARINGS
                             JOIN HASHES
                               ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                             JOIN HARE_TYPES
                               ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
                            WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY AND HASHES.KENNEL_KY = ?) AS HARE_COUNT,";

    foreach ($hareTypes as &$hareType) {
      array_push($args, $kennelKy);
      array_push($args, $hareType['HARE_TYPE']);
      $sql .= "(
                           SELECT COUNT(*)
                             FROM HARINGS
                             JOIN HASHES
                               ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                            WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                              AND HASHES.KENNEL_KY = ?
                              AND HARINGS.HARE_TYPE & ? != 0) AS ".$hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
    }

    foreach ($hashTypes as &$hashType) {
      array_push($args, $kennelKy);
      array_push($args, $hashType['HASH_TYPE']);
      $sql .= "(
                           SELECT COUNT(*)
                             FROM HASHINGS
                             JOIN HASHES
                               ON HASHINGS.HASH_KY = HASHES.HASH_KY
                            WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                              AND HASHES.KENNEL_KY = ?
                              AND HASHES.HASH_TYPE = ?) AS ".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
    }

    array_push($args, $kennelKy);
    array_push($args, $kennelKy);
    array_push($args, $minimumHashCount);
    array_push($args, $inputSearchValueModified);

    $sql .= "(
                           SELECT HASHES.HASH_KY
                             FROM HASHINGS
                             JOIN HASHES
                               ON HASHINGS.HASH_KY = HASHES.HASH_KY
                            WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                              AND HASHES.KENNEL_KY = ?
                            ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY, (
                           SELECT HASHES.HASH_KY
                             FROM HASHINGS
                             JOIN HASHES
                               ON HASHINGS.HASH_KY = HASHES.HASH_KY
                            WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                              AND HASHES.KENNEL_KY = ?
                            ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
                FROM HASHERS) MAIN_TABLE
        JOIN HASHES LATEST_HASH
          ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
        JOIN HASHES FIRST_HASH
          ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
       WHERE HASH_COUNT >= ?
         AND HASHER_NAME LIKE ?
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT HASHERS.HASHER_NAME AS HASHER_NAME, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
                FROM HASHERS) MAIN_TABLE
       WHERE HASH_COUNT >= ?
         AND HASHER_NAME LIKE ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES 
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
                FROM HASHERS) MAIN_TABLE
       WHERE HASH_COUNT >= ?";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql,$args);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [
      $kennelKy, $minimumHashCount ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [
      $kennelKy, $minimumHashCount, $inputSearchValueModified ]))['THE_COUNT'];
    
    #-------------- End: Query the database   --------------------------------

    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/jumboPercentagesTable',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function jumboPercentagesTablePreActionJson(string $kennel_abbreviation) {

    $minimumHashCount = $this->getSiteConfigItemAsInt('jumbo_percentages_minimum_hash_count', 10);
    $subTitle = "Minimum of $minimumHashCount hashes";
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    $hareTypes = $this->getHareTypes($kennelKy);
    $hashTypes = $this->getHashTypes($kennelKy, 0);

    return $this->render('jumbo_percentages_list_json.twig', [
      'pageTitle' => 'The Jumbo List of Percentages (Experimental Page)',
      'pageSubTitle' => $subTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageCaption' => "",
      'tableCaption' => "",
      "hareTypes" => count($hareTypes) > 1 ? $hareTypes : [],
      'hashTypes' => count($hashTypes) > 1 ? $hashTypes : [] ]);
  }

  #[Route('/{kennel_abbreviation}/jumboPercentagesTable',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function jumboPercentagesTablePostActionJson(string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);
    $hashTypes = $this->getHashTypes($kennelKy, 0);

    if(count($hareTypes) == 1) {
      $hareTypes = [];
    }

    if(count($hashTypes) == 1) {
      $hashTypes = [];
    }

    #Define the minimum hash count
    $minimumHashCount = $this->getSiteConfigItemAsInt('jumbo_percentages_minimum_hash_count', 10);

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
    } else if($inputLength == "-1") {
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------

    #Modify the search string
    $inputSearchValueModified = "%$inputSearchValue%";

    #Obtain the column/order information
    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "2";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "
      SELECT HASHER_NAME,HASH_COUNT,";

    foreach ($hashTypes as &$hashType) {
      $sql .= $hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
    }

    $sql .= "HARE_COUNT,";

    foreach ($hareTypes as &$hareType) {
      $sql .= $hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
    }

    $sql .= "(HARE_COUNT/HASH_COUNT) AS HARING_TO_HASHING_PERCENTAGE,";

    foreach ($hareTypes as &$hareType) {
      $sql .= "
        (".$hareType['HARE_TYPE_NAME']."_HARE_COUNT/HASH_COUNT) AS ".$hareType['HARE_TYPE_NAME']."_HARING_TO_HASHING_PERCENTAGE,";
    }

    foreach ($hareTypes as &$hareType) {
      $sql .= "
        CASE WHEN HARE_COUNT > 0 THEN (".$hareType['HARE_TYPE_NAME']."_HARE_COUNT/HARE_COUNT) ELSE 0 END AS ".$hareType['HARE_TYPE_NAME']."_TO_OVERALL_HARING_PERCENTAGE,";
    }

    $args = [ $kennelKy, $kennelKy ];

    $sql .= "
             LATEST_HASH.EVENT_DATE AS LATEST_EVENT_DATE, FIRST_HASH_KEY,
             FIRST_HASH.KENNEL_EVENT_NUMBER AS FIRST_KENNEL_EVENT_NUMBER,
             FIRST_HASH.EVENT_DATE AS FIRST_EVENT_DATE, LATEST_HASH_KEY,
             LATEST_HASH.KENNEL_EVENT_NUMBER AS LATEST_KENNEL_EVENT_NUMBER, OUTER_HASHER_KY AS HASHER_KY
        FROM (SELECT HASHERS.HASHER_NAME, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*) + ".$this->getLegacyHashingsCountSubquery("HASHINGS")."
                       FROM HASHINGS JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HASH_COUNT, (
                     SELECT COUNT(*)
                       FROM HARINGS
                       JOIN HASHES
                         ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                       JOIN HARE_TYPES
                         ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
                      WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HARE_COUNT,";

    foreach ($hareTypes as &$hareType) {
      array_push($args, $kennelKy);
      array_push($args, $hareType['HARE_TYPE']);
      $sql .= "(
                     SELECT COUNT(*)
                       FROM HARINGS
                       JOIN HASHES
                         ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                      WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HARINGS.HARE_TYPE & ? != 0) AS ".$hareType['HARE_TYPE_NAME']."_HARE_COUNT,";
    }

    foreach ($hashTypes as &$hashType) {
      array_push($args, $kennelKy);
      array_push($args, $hashType['HASH_TYPE']);
      $sql .= "(
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HASHES.HASH_TYPE = ?) AS ".$hashType['HASH_TYPE_NAME']."_HASH_COUNT,";
    }

    array_push($args, $kennelKy);
    array_push($args, $kennelKy);
    array_push($args, $minimumHashCount);
    array_push($args, $inputSearchValueModified);

    $sql .= "(
                     SELECT HASHES.HASH_KY
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                      ORDER BY HASHES.EVENT_DATE ASC LIMIT 1) AS FIRST_HASH_KEY, (
                     SELECT HASHES.HASH_KY
                       FROM HASHINGS JOIN HASHES ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                      ORDER BY HASHES.EVENT_DATE DESC LIMIT 1) AS LATEST_HASH_KEY
                FROM HASHERS) MAIN_TABLE
        JOIN HASHES LATEST_HASH
          ON LATEST_HASH.HASH_KY = LATEST_HASH_KEY
        JOIN HASHES FIRST_HASH
          ON FIRST_HASH.HASH_KY = FIRST_HASH_KEY
       WHERE HASH_COUNT >= ?
         AND HASHER_NAME LIKE ?
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT HASHERS.HASHER_NAME, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
                FROM HASHERS) MAIN_TABLE
       WHERE HASH_COUNT >= ?
         AND HASHER_NAME LIKE ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM (SELECT HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?) AS HASH_COUNT
                FROM HASHERS) MAIN_TABLE
       WHERE HASH_COUNT >= ?";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------

    #Perform the filtered search
    $theResults = $this->fetchAll($sql, $args);

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [ $kennelKy, $minimumHashCount ]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [ $kennelKy, $minimumHashCount, 
      $inputSearchValueModified ]))['THE_COUNT'];
    
    #-------------- End: Query the database   --------------------------------

    $output = [
      "sEcho" => "foo",
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults ];

    return new JsonResponse($output);
  }

  private function getStandardHareChartsAction(int $hasher_id, string $kennel_abbreviation) {

    $sql = "SELECT HASHER_KY, HASHER_NAME, HASHER_ABBREVIATION, FIRST_NAME, LAST_NAME, DECEASED
              FROM HASHERS
             WHERE HASHER_KY = ?";

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hasher = $this->fetchAssoc($sql, [ $hasher_id ]);

    $overallHareCountValue = $this->fetchAssoc($this->sqlQueries->getPersonsHaringCount(),
      [ $hasher_id, $kennelKy ]);

    $hareTypes = $this->getHareTypes($kennelKy);

    $hareCounts = [];

    foreach ($hareTypes as &$hareType) {
      $total = $this->fetchAssoc($this->sqlQueries->getPersonsHaringTypeCount(),
        [ $hasher_id, $kennelKy, $hareType['HARE_TYPE'] ]);
      array_push($hareCounts, [
        'type' => $hareType['HARE_TYPE_NAME'],
        'total' => $total['THE_COUNT']]);
    }

    #Obtain the harings by year
    $sqlHaringsByYear = "
      SELECT YEAR(EVENT_DATE) AS THE_VALUE,";

    $args = [];

    foreach ($hareTypes as &$hareType) {
      $sqlHaringsByYear .= "
        SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0  THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
      array_push($args, $hareType['HARE_TYPE']);
    }

    array_push($args, $hasher_id);
    array_push($args, $kennelKy);

    $sqlHaringsByYear .= "
             COUNT(*) AS TOTAL_HARING_COUNT
        FROM HARINGS
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
        JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE)";

    $haringsByYearList = $this->fetchAll($sqlHaringsByYear, $args);

    # Obtain the hashes by month (name)
    $sqlHaringsByMonth = "
      SELECT THE_VALUE,";

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
        FROM (SELECT MONTH(EVENT_DATE) AS THE_VALUE,";

    foreach ($hareTypes as &$hareType) {
      $sqlHaringsByMonth .= "
        SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }
    
    $sqlHaringsByMonth .= "
                     COUNT(*) AS TOTAL_HARING_COUNT
                FROM HARINGS
                JOIN HARE_TYPES
                  ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
                JOIN HASHES
                  ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
               WHERE HARINGS.HARINGS_HASHER_KY = ?
                 AND HASHES.KENNEL_KY = ?
               GROUP BY MONTH(EVENT_DATE)
               ORDER BY MONTH(EVENT_DATE)) TEMPTABLE";

    $theHaringsByMonthNameList = $this->fetchAll($sqlHaringsByMonth, $args);

    # Obtain the hashes by quarter
    $sqlHaringsByQuarter = "
      SELECT QUARTER(EVENT_DATE) AS THE_VALUE,";

    foreach ($hareTypes as &$hareType) {
      $sqlHaringsByQuarter .= "
             SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }

    $sqlHaringsByQuarter .= "
             COUNT(*) AS TOTAL_HARING_COUNT
        FROM HARINGS
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY QUARTER(EVENT_DATE)
       ORDER BY QUARTER(EVENT_DATE)";

    $theHaringsByQuarterList = $this->fetchAll($sqlHaringsByQuarter, $args);

    # Obtain the hashes by state
    $sqlHaringsByState = "
      SELECT HASHES.EVENT_STATE,";

    foreach ($hareTypes as &$hareType) {
      $sqlHaringsByState .= "
             SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }

    $sqlHaringsByState .= "
             COUNT(*) AS TOTAL_HARING_COUNT
        FROM HARINGS
        JOIN HARE_TYPES
          ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND HASHES.KENNEL_KY = ?
       GROUP BY HASHES.EVENT_STATE
       ORDER BY HASHES.EVENT_STATE";

    $theHaringsByStateList = $this->fetchAll($sqlHaringsByState, $args);

    # Obtain the hashes by day name
    $sqlHaringsByDayName = "
      SELECT THE_VALUE,";

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
        FROM (SELECT DAYNAME(EVENT_DATE) AS THE_VALUE,";

    foreach ($hareTypes as &$hareType) {
      $sqlHaringsByDayName .= "
                     SUM(CASE WHEN HARINGS.HARE_TYPE & ? != 0 THEN 1 ELSE 0 END) ".$hareType['HARE_TYPE_NAME']."_COUNT,";
    }

    $sqlHaringsByDayName .= "
                     COUNT(*) AS TOTAL_HARING_COUNT
                FROM HARINGS
                JOIN HARE_TYPES
                  ON HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
                JOIN HASHES
                  ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
               WHERE HARINGS.HARINGS_HASHER_KY = ?
                 AND HASHES.KENNEL_KY = ?
               GROUP BY DAYNAME(EVENT_DATE)
               ORDER BY DAYNAME(EVENT_DATE)) TEMP
       ORDER BY DAYNUMBER ASC";

    $theHaringsByDayNameList = $this->fetchAll($sqlHaringsByDayName, $args);

    return [
      'hasherValue' => $hasher,
      'hareCounts' => $hareCounts,
      'overallHareCount' => $overallHareCountValue['THE_COUNT'],
      'kennel_abbreviation' => $kennel_abbreviation,
      'harings_by_year_list' => $haringsByYearList,
      'harings_by_month_list' => $theHaringsByMonthNameList,
      'harings_by_quarter_list' => $theHaringsByQuarterList,
      'harings_by_state_list' => $theHaringsByStateList,
      'harings_by_dayname_list' => $theHaringsByDayNameList ];
  }


  #[Route('/{kennel_abbreviation}/hares/overall/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%']
  )]
  public function viewOverallHareChartsAction(int $hasher_id, string $kennel_abbreviation) {

    $commonValues = $this->getStandardHareChartsAction($hasher_id, $kennel_abbreviation);

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the list of favorite cities to hare in
    $cityHaringCountList = $this->fetchAll($this->sqlQueries->getHasherAllHaringCountsByCity(),
      [ $hasher_id, $kennelKy ]);

    #Obtain largest entry from the list
    $cityHaringsCountMax = 1;
    if(isset($cityHaringCountList[0]['THE_COUNT'])) {
      $cityHaringsCountMax = $cityHaringCountList[0]['THE_COUNT'];
    }

    #Obtain the favorite cohare list
    $cohareCountList = $this->fetchAll($this->sqlQueries->getOverallCohareCountByHare(), [
      $kennelKy, $hasher_id, $hasher_id ]);

    #Obtain the largest entry from the list
    $cohareCountMax = 1;
    if(isset($cohareCountList[0]['THE_COUNT'])) {
      $cohareCountMax = $cohareCountList[0]['THE_COUNT'];
    }

    # Obtain their hashes
    $sqlTheHashes = "
      SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE, HASHES.HASH_KY, LAT, LNG
        FROM HARINGS
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";

    $theHashes = $this->fetchAll($sqlTheHashes, [ $hasher_id, $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HARINGS
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";

    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $hasher_id, $kennelKy ]);

    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    $hareTypes = $this->getHareTypes($kennelKy);

    $customValues = [
      'pageTitle' => (count($hareTypes) > 1 ? 'Overall ' : '').  'Hare Charts and Details',
      'overall_hare_details' => (count($hareTypes) > 1 ? "Overall " : "").  "Hare Details",
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : [],
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'city_haring_count_list' => $cityHaringCountList,
      'city_harings_max_value' => $cityHaringsCountMax,
      'cohare_count_list' =>$cohareCountList,
      'cohare_count_max' => $cohareCountMax,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng ];

    $finalArray = array_merge($commonValues, $customValues);

    return $this->render('hare_chart_overall_details.twig', $finalArray);
  }

  #[Route('/{kennel_abbreviation}/hares/{hare_type}/{hasher_id}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function viewHareChartsAction(int $hare_type, int $hasher_id, string $kennel_abbreviation) {

    $commonValues = $this->getStandardHareChartsAction($hasher_id, $kennel_abbreviation);

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the list of favorite cities to hare in
    $cityHaringCountList = $this->fetchAll($this->sqlQueries->getHasherHaringCountsByCity(),
      [ $hasher_id, $kennelKy, $hare_type ]);

    #Obtain largest entry from the list
    $cityHaringsCountMax = 1;
    if(isset($cityHaringCountList[0]['THE_COUNT'])){
      $cityHaringsCountMax = $cityHaringCountList[0]['THE_COUNT'];
    }

    #Obtain the favorite cohare list
    $cohareCountList = $this->fetchAll($this->sqlQueries->getCohareCountByHare(),
      [ $kennelKy, $hasher_id, $hasher_id, $hare_type ]);

    #Obtain the largest entry from the list
    $cohareCountMax = 1;
    if(isset($cohareCountList[0]['THE_COUNT'])){
      $cohareCountMax = $cohareCountList[0]['THE_COUNT'];
    }

    # Obtain their hashes
    $sqlTheHashes = "
      SELECT KENNEL_EVENT_NUMBER, SPECIAL_EVENT_DESCRIPTION, EVENT_LOCATION, EVENT_DATE, HASHES.HASH_KY, LAT, LNG
        FROM HARINGS
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HARINGS.HARINGS_HASHER_KY = ?
         AND KENNEL_KY = ?
         AND HARINGS.HARE_TYPE & ? != 0
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";

    $theHashes = $this->fetchAll($sqlTheHashes, [ $hasher_id, $kennelKy, $hare_type ]);

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

    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $hasher_id, $kennelKy, $hare_type ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    $hareTypes = $this->getHareTypes($kennelKy);

    $hare_type_name = $this->getHareTypeName($hare_type);

    foreach ($hareTypes as &$hareType) {
      if($hareType['HARE_TYPE'] == $hare_type) {
        $chart_color = $hareType['CHART_COLOR'];
        break;
      }
    }

    $customValues = [
      'pageTitle' => $hare_type_name.' Hare Charts and Details',
      'hare_types' => $hareTypes,
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'city_haring_count_list' => $cityHaringCountList,
      'city_harings_max_value' => $cityHaringsCountMax,
      'cohare_count_list' =>$cohareCountList,
      'cohare_count_max' => $cohareCountMax,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng,
      'hare_type' => $hare_type,
      'hare_type_name' => $hare_type_name,
      'chart_color' => $chart_color
    ];

    $finalArray = array_merge($commonValues, $customValues);
    return $this->render('hare_chart_details.twig', $finalArray);
  }

  #[Route('/{kennel_abbreviation}/hashers/twoHasherComparison',
    methods: ['GET'],
    requirements: ['kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function twoPersonComparisonPreAction(string $kennel_abbreviation) {

    $pageTitle = "Two Person Comparison";

    return $this->render('hasher_comparison_selection_screen.twig', [
      'pageTitle' => $pageTitle,
      'playerOneDefault' => 'Selection Required',
      'playerTwoDefault' => 'Selection Required',
      'pageSubTitle' => 'Select Your Contestants',
      'pageHeader' => 'Why is this so complicated ?',
      'instructions' => 'You need to select two hashers to compare. Start typing in the search box to find your favorite hasher. When their name shows up, click the "+ player one" link next to their name. Repeat the process of typing in the search box and then click the "+ player two" link. Then, when both hashers have been selected, click on the the giant "submit" button. Enjoy!',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  private function createComparisonObjectCoreAttributes(string $hasher1, string $hasher2, string $statTitle, string $dataType) {
    return [ 'statName' => $statTitle, 'hasher1' => $hasher1, 'hasher2' => $hasher2, 'dataType' => $dataType ];
  }

  private function createComparisonObjectWithStatsAsInts(int $stat1, int $stat2, string $hasher1, string $hasher2, string $statTitle) {

    $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle, "int");

    # Establish the winner
    if($stat1 > $stat2) {
      $verdict = 'hasher1';
    } else if ($stat2 > $stat1) {
      $verdict = 'hasher2';
    } else {
      $verdict = 'tie';
    }

    # Fill in the return value with more attributes
    $additionalAttributes = [ 'val1' => $stat1, 'val2' => $stat2, 'verdict' => $verdict ];

    # Combine the arrays
    return $returnValue + $additionalAttributes;
  }

  private function createComparisonObjectWithStatsAsDoubles(float $stat1, float $stat2, string $hasher1, string $hasher2, string $statTitle) {

    $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle, "float");

    if($stat1 > $stat2) {
      $verdict = 'hasher1';
    } else if ($stat2 > $stat1) {
      $verdict = 'hasher2';
    } else {
      $verdict = 'tie';
    }

    # Fill in the return value with more attributes
    $additionalAttributes = [ 'val1' => $stat1, 'val2' => $stat2, 'verdict' => $verdict ];

    # Combine the arrays
    return $returnValue + $additionalAttributes;
  }

  private function createComparisonObjectWithStatsAsDates(string $stat1, string $stat2, string $hasher1, string $hasher2, string $statTitle, bool $greaterIsBetter, int $key1, int $key2) {

    $returnValue = $this->createComparisonObjectCoreAttributes($hasher1, $hasher2, $statTitle, "date");

    # Establish the date time values
    $date1 = DateTime::createFromFormat('m/d/Y', $stat1);
    $date2 = DateTime::createFromFormat('m/d/Y', $stat2);

    # Populate the verdict value
    if($date1 > $date2) {
      $verdict = ($greaterIsBetter ? 'hasher1' : 'hasher2');
    } else if ($date2 > $date1) {
      $verdict = ($greaterIsBetter ? 'hasher2' : 'hasher1');
    } else {
      $verdict = 'tie';
    }

    #Fill in the return value with more attributes
    $additionalAttributes = [ 'val1' => $stat1, 'val2' => $stat2, 'verdict' => $verdict,
      'hashKey1' => $key1, 'hashKey2' => $key2 ];

    #Combine the arrays
    return $returnValue + $additionalAttributes;
  }

  private function twoPersonComparisonDataFetch(int $kennelKy, int $hasher_id1, int $hasher_id2) {

    $hareTypes = $this->getHareTypes($kennelKy);
    if(count($hareTypes) == 1) {
      $hareTypes = [];
    }

    $returnValue = [];

    $sql = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    $hasher1 = $this->fetchAssoc($sql, [ $hasher_id1 ]);
    $hasher2 = $this->fetchAssoc($sql, [ $hasher_id2 ]);

    #Obtain the overall hashing count
    $hashingCountH1 = ($this->fetchAssoc($this->getPersonsHashingCountQuery(), [ $hasher_id1, $kennelKy, $hasher_id1, $kennelKy ]))['THE_COUNT'];
    $hashingCountH2 = ($this->fetchAssoc($this->getPersonsHashingCountQuery(), [ $hasher_id2, $kennelKy, $hasher_id2, $kennelKy ]))['THE_COUNT'];
    $statObject = $this->createComparisonObjectWithStatsAsInts($hashingCountH1, $hashingCountH2, $hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], "Hashing Count");
    $returnValue[] = $statObject;

    #Obtain the overall haring count
    $hareCountOverallH1 = ($this->fetchAssoc($this->sqlQueries->getPersonsHaringCount(), [ $hasher_id1, $kennelKy ]))['THE_COUNT'];
    $hareCountOverallH2 = ($this->fetchAssoc($this->sqlQueries->getPersonsHaringCount(), [ $hasher_id2, $kennelKy ]))['THE_COUNT'];
    $statObject = $this-> createComparisonObjectWithStatsAsInts($hareCountOverallH1, $hareCountOverallH2,$hasher1['HASHER_NAME'], $hasher2['HASHER_NAME'], "Overall Haring Count");
    $returnValue[] = $statObject;

    #Obtain the haring counts
    foreach ($hareTypes as &$hareType) {
      $hareCountH1[$hareType['HARE_TYPE']] = ($this->fetchAssoc($this->sqlQueries->getPersonsHaringTypeCount(), [ $hasher_id1, $kennelKy, $hareType['HARE_TYPE'] ]))['THE_COUNT'];
      $hareCountH2[$hareType['HARE_TYPE']] = ($this->fetchAssoc($this->sqlQueries->getPersonsHaringTypeCount(), [ $hasher_id2, $kennelKy, $hareType['HARE_TYPE'] ]))['THE_COUNT'];
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
    $virginHashH1 = $this->fetchAssoc($this->sqlQueries->getSelectHashersVirginHash(), [ $hasher_id1, $kennelKy ]);
    $virginHashH2 = $this->fetchAssoc($this->sqlQueries->getSelectHashersVirginHash(), [ $hasher_id2, $kennelKy ]);
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
    $latestHashH1 = $this->fetchAssoc($this->sqlQueries->getSelectHashersMostRecentHash(), [ $hasher_id1, $kennelKy ]);
    $latestHashH2 = $this->fetchAssoc($this->sqlQueries->getSelectHashersMostRecentHash(), [ $hasher_id2, $kennelKy ]);
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

    return $returnValue;
  }

  #[Route('/{kennel_abbreviation}/hashers/comparison/{hasher_id}/{hasher_id2}/',
    methods: ['GET'],
    requirements: ['kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hasher_id' => '%app.pattern.hasher_id%',
      'hasher_id2' => '%app.pattern.hasher_id%']
  )]
  public function twoPersonComparisonAction(string $kennel_abbreviation, int $hasher_id, int $hasher_id2) {

    $pageTitle = "Hasher Showdown";

    $sql = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Make a database call to obtain the hasher information
    $hasher1 = $this->fetchAssoc($sql, [ $hasher_id ]);
    $hasher2 = $this->fetchAssoc($sql, [ $hasher_id2 ]);
    $pageSubtitle = $hasher1['HASHER_NAME'] . " VS " . $hasher2['HASHER_NAME'];

    $listOfStats = $this->twoPersonComparisonDataFetch($kennelKy, $hasher_id, $hasher_id2);

    return $this->render('hasher_comparison_fluid_results.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => $pageSubtitle,
      'pageHeader' => 'Why is this so complicated ?',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hasherName1' => $hasher1['HASHER_NAME'],
      'hasherName2' => $hasher2['HASHER_NAME'],
      'tempList' => $listOfStats ]);
  }
}
