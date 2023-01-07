<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Helper;
use App\SqlQueries;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Wamania\Snowball\StemmerFactory;

class ObscureStatisticsController extends BaseController {

  private SqlQueries $sqlQueries;
  private Helper $helper;

  public function __construct(ManagerRegistry $doctrine, SqlQueries $sqlQueries, Helper $helper) {
    parent::__construct($doctrine);
    $this->sqlQueries = $sqlQueries;
    $this->helper = $helper;
  }

  #[Route('/{kennel_abbreviation}/eventsHeatMap',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function kennelEventsHeatMap(string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the hashes
    $sqlTheHashes = "
      SELECT HASHES.*
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theHashes = $this->fetchAll($sqlTheHashes, [ $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $kennelKy ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    # Establish and set the return value
    return $this->render('generic_heat_map_page.twig', [
      'pageTitle' => 'The Kennel Heat Map',
      'pageSubTitle' => 'Location of all the hashes',
      'kennel_abbreviation' => $kennel_abbreviation,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng ]);
  }

  #[Route('/{kennel_abbreviation}/eventsClusterMap',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function kennelEventsClusterMap(string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the hashes
    $sqlTheHashes = "
      SELECT HASHES.*
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theHashes = $this->fetchAll($sqlTheHashes, [ $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $kennelKy ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    # Establish and set the return value
    return $this->render('generic_cluster_map_page.twig', [
      'pageTitle' => 'The Kennel Cluster Map',
      'pageSubTitle' => 'Location of all the hashes',
      'kennel_abbreviation' => $kennel_abbreviation,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng ]);
  }

  #[Route('/{kennel_abbreviation}/eventsMarkerMap',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function kennelEventsMarkerMap(string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the hashes
    $sqlTheHashes = "
      SELECT HASHES.*
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theHashes = $this->fetchAll($sqlTheHashes, [ $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $kennelKy ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    # Establish and set the return value
    return $this->render('generic_marker_map_page.twig', [
      'pageTitle' => 'The Kennel Marker Map',
      'pageSubTitle' => 'Location of all the hashes',
      'kennel_abbreviation' => $kennel_abbreviation,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng ]);
  }

    #Landing screen for year in review
    public function getYearInReviewAction(Request $request, int $year_value, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      $hashTypes = $this->getHashTypes($kennelKy, 0);
      $hareTypes = $this->getHareTypes($kennelKy);

      #Establish the page title
      $pageTitle = "$year_value: Year in review";

      #Obtain number of hashes
      $hashCount = ($this->fetchAssoc(PER_KENNEL_HASH_COUNTS_BY_YEAR,array((int)$year_value, $kennelKy)))['THE_COUNT'];

      foreach($hashTypes as &$hashType) {
        #Obtain number of hashtype hashes
        $hashCounts[$hashType['HASH_TYPE_NAME']] = ($this->fetchAssoc(PER_KENNEL_HASH_COUNTS_BY_YEAR . " AND HASHES.HASH_TYPE = ?",
          array((int)$year_value, $kennelKy, $hashType['HASH_TYPE'])))['THE_COUNT'];
      }

      #Obtain number of hashers
      $hasherCount = ($this->fetchAssoc(PER_KENNEL_HASHERS_COUNT_BY_YEAR,array((int)$year_value, $kennelKy)))['THE_COUNT'];

      #Obtain number of overall hares
      $overallHareCount = ($this->fetchAssoc(PER_KENNEL_HARES_COUNT_BY_YEAR,array((int)$year_value, $kennelKy)))['THE_COUNT'];

      foreach($hareTypes as &$hareType) {
        $hareCounts[$hareType['HARE_TYPE_NAME']] = ($this->fetchAssoc(PER_KENNEL_HARES_COUNT_BY_YEAR . "AND HARINGS.HARE_TYPE & ? != 0",
          array((int)$year_value, $kennelKy, $hareType['HARE_TYPE'])))['THE_COUNT'];
      }

      # Obtain the number of newbie hashers
      $newHashers = $this->fetchAll(NEW_HASHERS_FOR_THIS_YEAR, array($kennelKy, $kennelKy, (int)$year_value));
      $newHashersCount = count($newHashers);

      foreach($hareTypes as &$hareType) {
        $newHareCounts[$hareType['HARE_TYPE_NAME']] = count($this->fetchAll(NEW_HARES_FOR_THIS_YEAR_BY_HARE_TYPE,
          array($hareType['HARE_TYPE'], $kennelKy,$hareType['HARE_TYPE'], $kennelKy, $hareType['HARE_TYPE'],(int)$year_value)));
      }

      # Obtain the number of new overall hares
      $newOverallHares = $this->fetchAll(NEW_HARES_FOR_THIS_YEAR, array($kennelKy, $kennelKy,(int)$year_value));
      $newOverallHaresCount = count($newOverallHares);

      #Establish the return value
      $returnValue = $this->render('year_in_review.twig', array (
        'pageTitle' => $pageTitle,
        'yearValue' => $year_value,
        'kennel_abbreviation' => $kennel_abbreviation,
        'hash_types' => $hashTypes,
        'hare_types' => count($hareTypes) > 1 ? $hareTypes : array(),
        'hash_count' => $hashCount,
        'hash_counts' => $hashCounts,
        'hasher_count' => $hasherCount,
        'overall_hare_count' => $overallHareCount,
        'hare_counts' => $hareCounts,
        'newbie_hashers_count' => $newHashersCount,
        'newbie_hare_counts' => $newHareCounts,
        'newbie_overall_hares_count' => $newOverallHaresCount
      ));

      #Return the return value
      return $returnValue;
    }

    #Obtain hashers for an event
    public function getHasherCountsByYear(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hasherCountSQL = HASHER_COUNTS_BY_YEAR;

      #Obtain the hare list
      $hasherCountList = $this->fetchAll($hasherCountSQL,array((int)$theYear, (int) $kennelKy));

      return new JsonResponse($hasherCountList);
    }

    #Obtain total hare counts per year
    public function getTotalHareCountsByYear(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hareCountSQL = TOTAL_HARE_COUNTS_BY_YEAR;

      #Obtain the hare list
      $hareCountList = $this->fetchAll($hareCountSQL,array((int)$theYear, (int) $kennelKy));

      return new JsonResponse($hareCountList);
    }

    #Obtain hare counts per year
    public function getHareCountsByYear(Request $request, int $hare_type, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hareCountSQL = HARE_COUNTS_BY_YEAR_BY_HARE_TYPE;

      #Obtain the hare list
      $hareCountList = $this->fetchAll($hareCountSQL,array((int)$theYear, $hare_type, (int) $kennelKy));

      return new JsonResponse($hareCountList);
    }

    #Obtain total hare counts per year
    public function getNewbieHasherListByYear(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hareCountSQL = NEW_HASHERS_FOR_THIS_YEAR;

      #Obtain the hare list
      $hareCountList = $this->fetchAll($hareCountSQL,array((int) $kennelKy,(int) $kennelKy,(int)$theYear));

      return new JsonResponse($hareCountList);
    }

    public function getNewbieHareListByYear(Request $request, int $hare_type, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hareCountSQL = NEW_HARES_FOR_THIS_YEAR_BY_HARE_TYPE;

      #Obtain the hare list
      $hareCountList = $this->fetchAll($hareCountSQL,array(
        $hare_type, (int) $kennelKy, $hare_type, (int) $kennelKy, $hare_type, (int)$theYear));

      return new JsonResponse($hareCountList);
    }

    public function getNewbieOverallHareListByYear(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the post values
      $theYear = $request->request->get('year_value');

      #Define the SQL to execute
      $hareCountSQL = NEW_HARES_FOR_THIS_YEAR;

      #Obtain the hare list
      $hareCountList = $this->fetchAll($hareCountSQL,array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $theYear));

      return new JsonResponse($hareCountList);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/firstHash',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  #Obtain the first hash of a given hasher
  public function getHashersVirginHash(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theHasherKey = $_POST['hasher_id'];

    $theSql = $this->sqlQueries->getSelectHashersVirginHash();

    $theirVirginHash = $this->fetchAssoc($theSql, [ $theHasherKey, $kennelKy ]);

    return new JsonResponse($theirVirginHash);
  }

  #[Route('/{kennel_abbreviation}/statistics/hasher/firstHare',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  #Obtain the first haring of a given hasher
  public function getHashersVirginHare(string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theHasherKey = (int) $_POST['hasher_id'];
    $theHareType = (int) $_POST['hare_type'];

    #Define the sql statement to execute
    $theSql = $this->sqlQueries->getSelectHashersVirginHare();

    #Query the database
    $theirVirginHash = $this->fetchAssoc($theSql, [ $theHasherKey, $kennelKy, $theHareType, $theHareType ]);

    return new JsonResponse($theirVirginHash);
  }

  #[Route('/{kennel_abbreviation}/statistics/kennel/firstHash',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getKennelsVirginHash(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getSelectKennelsVirginHash();

    $theirVirginHash = $this->fetchAssoc($theSql, array((int) $kennelKy));

    return new JsonResponse($theirVirginHash);
  }

  #Obtain the latest hash of a given hasher
  #[Route('/{kennel_abbreviation}/statistics/hasher/mostRecentHash',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHashersLatestHash(string $kennel_abbreviation) {

    $theHasherKey = $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getSelectHashersMostRecentHash();

    $theirLatestHash = $this->fetchAssoc($theSql, [ $theHasherKey, $kennelKy ]);

    return new JsonResponse($theirLatestHash);
  }

  #Obtain the latest haring of a given hasher
  #[Route('/{kennel_abbreviation}/statistics/hasher/mostRecentHare',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHashersLatestHare(string $kennel_abbreviation) {

    $theHasherKey = (int) $_POST['hasher_id'];
    $theHareType = (int) $_POST['hare_type'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getSelectHashersMostRecentHare();

    $theirLatestHash = $this->fetchAssoc($theSql, [ $theHasherKey, $kennelKy, $theHareType, $theHareType ]);

    return new JsonResponse($theirLatestHash);
  }

  #[Route('/{kennel_abbreviation}/statistics/kennel/mostRecentHash',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getKennelsLatestHash(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getSelectKennelsMostRecentHash();

    $theirLatestHash = $this->fetchAssoc($theSql, array((int) $kennelKy));

    return new JsonResponse($theirLatestHash);
  }


    #Obtain the hasher hashes attended by year
    public function getHasherHashesByYear(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HASH_COUNTS_BY_YEAR;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

    #Obtain the hasher hashes attended by quarter
    public function getHasherHashesByQuarter(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HASH_COUNTS_BY_QUARTER;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }


    #Obtain the hasher hashes attended by quarter
    public function getHasherHashesByMonth(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HASH_COUNTS_BY_MONTH;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }


    #Obtain the hasher hashes attended by day name
    public function getHasherHashesByDayName(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HASH_COUNTS_BY_DAYNAME;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

    #Obtain the hasher hashes attended by state
    public function getHasherHashesByState(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HASH_COUNTS_BY_STATE;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/hashes/by/city',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  #Obtain the hasher hashes attended by city
  public function getHasherHashesByCity(string $kennel_abbreviation) {

    $theHasherKey = $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherHashCountsByCity();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/statistics/kennel/hashes/by/city',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getKennelHashesByCity(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getKennelHashCountsByCity();

    $theResults = $this->fetchAll($theSql, [ $kennelKy ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/statistics/kennel/hashes/by/county',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getKennelHashesByCounty(string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getKennelHashCountsByCounty();

    $theResults = $this->fetchAll($theSql, [ $kennelKy ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/statistics/kennel/hashes/by/postalcode',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getKennelHashesByPostalcode(string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getKennelHashCountsByPostalCode();

    $theResults = $this->fetchAll($theSql, [ $kennelKy ]);

    return new JsonResponse($theResults);
  }


    public function getHasherAllHaringsByYear(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_ALL_HARING_COUNTS_BY_YEAR;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/all/harings/by/quarter',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHasherAllHaringsByQuarter(string $kennel_abbreviation){

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherAllHaringCountsByQuarter();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy ]);

    return new JsonResponse($theResults);
  }

    public function getHasherAllHaringsByMonth(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_ALL_HARING_COUNTS_BY_MONTH;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

    public function getHasherAllHaringsByDayName(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_ALL_HARING_COUNTS_BY_DAYNAME;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

    public function getHasherAllHaringsByState(Request $request, string $kennel_abbreviation){

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_ALL_HARING_COUNTS_BY_STATE;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy));

      return new JsonResponse($theResults);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/all/harings/by/city',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHasherAllHaringsByCity(string $kennel_abbreviation) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherAllHaringCountsByCity();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy ]);

    return new JsonResponse($theResults);
  }


    # Mappings for hasher (non hyper) harings by (year/month/state/etc)
    public function getHasherHaringsByYear(Request $request, string $kennel_abbreviation, int $hare_type) {

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HARING_COUNTS_BY_YEAR;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy, (int) $hare_type));

      return new JsonResponse($theResults);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/quarter',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%' ]
  )]
  public function getHasherHaringsByQuarter(string $kennel_abbreviation, int $hare_type) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherHaringCountsByQuarter();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy, $hare_type ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/month',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%' ]
  )]
  public function getHasherHaringsByMonth(string $kennel_abbreviation, $hare_type) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherHaringCountsByMonth();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy, $hare_type ]);

    return new JsonResponse($theResults);
  }

    public function getHasherHaringsByDayName(Request $request, string $kennel_abbreviation, $hare_type) {

      #Obtain the post values
      $theHasherKey = $request->request->get('hasher_id');

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = HASHER_HARING_COUNTS_BY_DAYNAME;

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $theHasherKey, (int) $kennelKy, (int) $hare_type));

      return new JsonResponse($theResults);
    }

  #[Route('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/state',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%' ]
  )]
  public function getHasherHaringsByState(string $kennel_abbreviation, $hare_type) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherHaringCountsByState();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy, $hare_type ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/statistics/hasher/{hare_type}/harings/by/city',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%' ]
  )]
  public function getHasherHaringsByCity(string $kennel_abbreviation, $hare_type) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getHasherHaringCountsByCity();

    $theResults = $this->fetchAll($theSql, [ $theHasherKey, $kennelKy, $hare_type ]);

    return new JsonResponse($theResults);
  }

  #[Route('/{kennel_abbreviation}/coharecount/byhare/{hare_type}',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%' ]
  )]
  public function getCohareCountByHare(string $kennel_abbreviation, int $hare_type) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getCohareCountByHare();

    $theResults = $this->fetchAll($theSql, [ $kennelKy, $theHasherKey, $theHasherKey, $hare_type ]);

    return new JsonResponse($theResults);
  }


  #[Route('/{kennel_abbreviation}/coharecount/byhare/allhashes',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%' ]
  )]
  public function getCohareCountByHareAllHashes(string $kennel_abbreviation) {

    $theHasherKey = (int) $_POST['hasher_id'];

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theSql = $this->sqlQueries->getOverallCohareCountByHare();

    $theResults = $this->fetchAll($theSql, [ $kennelKy, $theHasherKey, $theHasherKey ]);

    return new JsonResponse($theResults);
  }




    public function quickestToReachAnalversaryByDaysAction(Request $request, string $kennel_abbreviation, int $analversary_number){




            #Obtain the kennel key
            $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

            #Obtain the analversary number, then subtract one (for the query requires it)
            $modifiedAnalversaryNumber = $analversary_number -1;

            #Define the sql statement to execute
            $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber,FASTEST_HASHERS_TO_ANALVERSARIES2);
            $theSql = str_replace("XORDERX","ASC",$theSql);
            $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);

            #Query the database
            $theResults = $this->fetchAll($theSql, array((int) $kennelKy, (int) $kennelKy,(int) $kennelKy));

            #Define the page title
            $pageTitle = "Quickest to reach $analversary_number hashes";

            #Set the return value
            $returnValue = $this->render('analversaries_achievements_non_json.twig',array(
              'pageTitle' => $pageTitle,
              'tableCaption' => 'Faster is better',
              'pageSubTitle' => 'Measured in days',
              #'subTitle1' => 'Standard Statistics',
              #'subTitle2' => 'Analversary Statistics',
              #'subTitle3' => 'Hare Statistics',
              #'subTitle4' => 'Other Statistics',
              #'url_value' => $urlValue,
              'theList' => $theResults,
              'analversary_number' => $analversary_number,
              'kennel_abbreviation' => $kennel_abbreviation
            ));

            return $returnValue;
          }

          public function quickestToReachAnalversaryByDate(Request $request, string $kennel_abbreviation, int $analversary_number){

                  #Obtain the kennel key
                  $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

                  #Obtain the analversary number, then subtract one (for the query requires it)
                  $modifiedAnalversaryNumber = $analversary_number -1;

                  #Define the sql statement to execute
                  $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber,FASTEST_HASHERS_TO_ANALVERSARIES2);
                  $theSql = str_replace("XORDERX","ASC",$theSql);
                  $theSql = str_replace("XORDERCOLUMNX","ANALVERSARY_DATE",$theSql);

                  #Query the database
                  $theResults = $this->fetchAll($theSql, array((int) $kennelKy, (int) $kennelKy,(int) $kennelKy));

                  #Define the page title
                  $pageTitle = "Chronological order of analversaries";
                  $pageSubTitle = "($analversary_number hashes)";

                  #Set the return value
                  $returnValue = $this->render('analversaries_achievements_chronological.twig',array(
                    'pageTitle' => $pageTitle,
                    'tableCaption' => '',
                    'pageSubTitle' => $pageSubTitle,
                    #'subTitle1' => 'Standard Statistics',
                    #'subTitle2' => 'Analversary Statistics',
                    #'subTitle3' => 'Hare Statistics',
                    #'subTitle4' => 'Other Statistics',
                    #'url_value' => $urlValue,
                    'theList' => $theResults,
                    'analversary_number' => $analversary_number,
                    'kennel_abbreviation' => $kennel_abbreviation
                  ));

                  return $returnValue;
                }


    public function slowestToReachAnalversaryByDaysAction(Request $request, string $kennel_abbreviation, int $analversary_number){


      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Obtain the analversary number, then subtract one (for the query requires it)
      $modifiedAnalversaryNumber = $analversary_number -1;

      #Define the sql statement to execute
      $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber,FASTEST_HASHERS_TO_ANALVERSARIES2);
      $theSql = str_replace("XORDERX","DESC",$theSql);
      $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $kennelKy, (int) $kennelKy,(int) $kennelKy));

      #Define the page title
      $pageTitle = "Slowest to reach $analversary_number hashes";

      #Set the return value
      $returnValue = $this->render('analversaries_achievements_non_json.twig',array(
        'pageTitle' => $pageTitle,
        'tableCaption' => 'Faster is better',
        'pageSubTitle' => 'Measured in days',
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      return $returnValue;
    }


    public function getLongestStreaksAction(Request $request, string $kennel_abbreviation){


      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql statement to execute
      $theSql = THE_LONGEST_STREAKS." LIMIT 25";

      #Query the database
      $theResults = $this->fetchAll($theSql, array((int) $kennelKy));

      #Define the page title
      $pageTitle = "The longest streaks";

      #Set the return value
      $returnValue = $this->render('name_number_list.twig',array(
        'pageTitle' => $pageTitle,
        'tableCaption' => 'Longest streak per hasher',

        'columnOneName' => 'Hasher Name',
        'columnTwoName' => 'Streak Length',
        'theList' => $theResults,
        'kennel_abbreviation' => $kennel_abbreviation,
        'pageTracking' => 'LongestStreaks'
      ));

      return $returnValue;
    }

    public function longestCareerAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LONGEST_HASHING_CAREER_IN_DAYS;
      $theSql = str_replace("XORDERCOLUMNX","DIFFERENCE",LONGEST_HASHING_CAREER_IN_DAYS);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Define the minimum hashing count
      $minHashingCount = 4;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int)$minHashingCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days between first hashes and most recent hashes";

      #Define the table caption
      $tableCaption = "Minimum hashing count: $minHashingCount";

      #Add the results into the twig template
      $returnValue = $this->render('career_length_by_day.twig',array(
        'pageTitle' => "Longest Hashing Career (By Days)",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        #'pageCaption' => $pageCaption,
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        #'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }



    public function everyonesLatestHashesAction(Request $request, string $kennel_abbreviation, int $min_hash_count){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LONGEST_HASHING_CAREER_IN_DAYS;
      $theSql = str_replace("XORDERCOLUMNX","LATEST_HASH_DATE",LONGEST_HASHING_CAREER_IN_DAYS);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $min_hash_count
      ));

      #Define the page sub title
      $pageSubTitle = "Everyone's latest hash, sorted by date";

      #Define the table caption
      $tableCaption = "Minimum hashing count: $min_hash_count";

      #Add the results into the twig template
      $returnValue = $this->render('career_length_by_day.twig',array(
        'pageTitle' => $pageSubTitle,
        'pageSubTitle' => "",
        'tableCaption' => $tableCaption,
        #'pageCaption' => $pageCaption,
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        #'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }

    public function everyonesFirstHashesAction(Request $request, string $kennel_abbreviation, int $min_hash_count){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LONGEST_HASHING_CAREER_IN_DAYS;
      $theSql = str_replace("XORDERCOLUMNX","FIRST_HASH_DATE",LONGEST_HASHING_CAREER_IN_DAYS);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int)$min_hash_count
      ));

      #Define the page sub title
      $pageSubTitle = "Everyone's first hash, sorted by date";

      #Define the table caption
      $tableCaption = "Minimum hashing count: $min_hash_count";

      #Add the results into the twig template
      $returnValue = $this->render('career_length_by_day.twig',array(
        'pageTitle' => $pageSubTitle,
        'pageSubTitle' => "",
        'tableCaption' => $tableCaption,
        #'pageCaption' => $pageCaption,
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        #'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }

    public function highestAverageDaysBetweenHashesAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LONGEST_HASHING_CAREER_IN_DAYS;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HASHES",LONGEST_HASHING_CAREER_IN_DAYS);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Define the minimum hashing count
      $minHashingCount = 2;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int)$minHashingCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days between first and last hashes, divided by pi";

      #Define the table caption
      $tableCaption = "Minimum hashing count: $minHashingCount";

      #Add the results into the twig template
      $returnValue = $this->render('career_length_by_day.twig',array(
        'pageTitle' => "Average days between hashing",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        #'pageCaption' => $pageCaption,
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        #'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }


    public function lowestAverageDaysBetweenHashesAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LONGEST_HASHING_CAREER_IN_DAYS;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HASHES",LONGEST_HASHING_CAREER_IN_DAYS);
      $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

      #Define the minimum hashing count
      $minHashingCount = 6;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int)$minHashingCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days between first and last hashes, divided by pi";

      #Define the table caption
      $tableCaption = "Minimum hashing count: $minHashingCount";

      #Add the results into the twig template
      $returnValue = $this->render('career_length_by_day.twig',array(
        'pageTitle' => "Average days between hashing",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        #'pageCaption' => $pageCaption,
        #'subTitle1' => 'Standard Statistics',
        #'subTitle2' => 'Analversary Statistics',
        #'subTitle3' => 'Hare Statistics',
        #'subTitle4' => 'Other Statistics',
        #'url_value' => $urlValue,
        'theList' => $theResults,
        #'analversary_number' => $analversary_number,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }



    public function lowestAverageDaysBetweenAllHaringsAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LOWEST_NUMBER_OF_DAYS_BETWEEN_HARINGS;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
      $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

      #Define the minimum haring count
      $minHaringCount = 2;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $minHaringCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days between first and last harings, divided by pi";

      #Define the table caption
      $tableCaption = "Minimum haring count: $minHaringCount";

      #Add the results into the twig template
      $returnValue = $this->render('haring_career_length_by_day.twig',array(
        'pageTitle' => "Average days between harings",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        'theList' => $theResults,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }

    public function highestAverageDaysBetweenAllHaringsAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the sql
      $theSql = LOWEST_NUMBER_OF_DAYS_BETWEEN_HARINGS;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Define the minimum haring count
      $minHaringCount = 2;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $minHaringCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days between first and last harings, divided by pi";

      #Define the table caption
      $tableCaption = "Minimum haring count: $minHaringCount";

      #Add the results into the twig template
      $returnValue = $this->render('haring_career_length_by_day.twig',array(
        'pageTitle' => "Average days between harings",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        'theList' => $theResults,
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }



    public function lowestAverageDaysBetweenHaringsAction(Request $request, int $hare_type, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      $hareTypeName = $this->getHareTypeName($hare_type);

      #Define the sql
      $theSql = LOWEST_NUMBER_OF_DAYS_BETWEEN_HARINGS_BY_TYPE;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
      $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

      #Define the minimum haring count
      $minHaringCount = 5;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $minHaringCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days Between First and Last ".$hareTypeName." Harings";

      #Define the table caption
      $tableCaption = "Minimum haring count: $minHaringCount";

      #Add the results into the twig template
      $returnValue = $this->render('haring_career_length_by_day.twig',array(
        'pageTitle' => "Average days between harings",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        'theList' => $theResults,
        'kennel_abbreviation' => $kennel_abbreviation,
        'hare_type_name' => $hareTypeName
      ));

      #Return the return value
      return $returnValue;

    }


    public function highestAverageDaysBetweenHaringsAction(Request $request, int $hare_type, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      $hareTypeName = $this->getHareTypeName($hare_type);

      #Define the sql
      $theSql = LOWEST_NUMBER_OF_DAYS_BETWEEN_HARINGS_BY_TYPE;
      $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
      $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

      #Define the minimum haring count
      $minHaringCount = 2;

      #Query the database
      $theResults = $this->fetchAll($theSql, array(
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $kennelKy,
        (int) $hare_type,
        (int) $minHaringCount
      ));

      #Define the page sub title
      $pageSubTitle = "Days Between First and Last ".$hareTypeName." Harings";

      #Define the table caption
      $tableCaption = "Minimum haring count: $minHaringCount";

      #Add the results into the twig template
      $returnValue = $this->render('haring_career_length_by_day.twig',array(
        'pageTitle' => "Average days between harings",
        'pageSubTitle' => $pageSubTitle,
        'tableCaption' => $tableCaption,
        'theList' => $theResults,
        'kennel_abbreviation' => $kennel_abbreviation,
        'hare_type_name' => $hareTypeName
      ));

      #Return the return value
      return $returnValue;

    }




    public function viewAttendanceChartsAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      # Obtain the average and total event attendance per year
      $sqlAvgTotalEvtParticipationByYear = "SELECT
            YEAR(THE_DATE) AS THE_VALUE,
            SUM(THE_COUNT) AS TOT_COUNT,
            AVG(THE_COUNT) AS AVG_COUNT
        FROM (
        		SELECT
        			HASHES.HASH_KY AS THE_KEY,
        			HASHES.EVENT_DATE AS THE_DATE,
        			COUNT(*) AS THE_COUNT
        		FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
        		WHERE KENNEL_KY = ?
        		GROUP BY HASHES.HASH_KY
            ) TEMPORARY_TABLE
        GROUP BY YEAR(THE_DATE)";
      $avgTotalEvtParticipationByYear = $this->fetchAll($sqlAvgTotalEvtParticipationByYear, array((int) $kennelKy));

      # Obtain the average event attendance per (year/month)
      $sqlAvgEvtParticipationByYearMonth = "SELECT
            DATE_FORMAT(THE_DATE,'%Y/%m') AS THE_VALUE,
            AVG(THE_COUNT) AS THE_COUNT
        FROM (
            SELECT
              HASHES.HASH_KY AS THE_KEY,
              HASHES.EVENT_DATE AS THE_DATE,
              COUNT(*) AS THE_COUNT
            FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
            WHERE KENNEL_KY = ?
            GROUP BY HASHES.HASH_KY
            ) TEMPORARY_TABLE
        GROUP BY DATE_FORMAT(THE_DATE,'%Y/%m')";
      $avgEvtParticipationByYearMonth = $this->fetchAll($sqlAvgEvtParticipationByYearMonth, array((int) $kennelKy));

      # Obtain the average event attendance per (year/quarter)
      $sqlAvgEvtParticipationByYearQuarter = "SELECT
            CONCAT_WS('/',YEAR(THE_DATE),QUARTER(THE_DATE)) AS THE_VALUE,
            AVG(THE_COUNT) AS THE_COUNT
        FROM (
            SELECT
              HASHES.HASH_KY AS THE_KEY,
              HASHES.EVENT_DATE AS THE_DATE,
              COUNT(*) AS THE_COUNT
            FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
            WHERE KENNEL_KY = ?
            GROUP BY HASHES.HASH_KY
            ) TEMPORARY_TABLE
        GROUP BY CONCAT_WS('/',YEAR(THE_DATE),QUARTER(THE_DATE))";
      $avgEvtParticipationByYearQuarter = $this->fetchAll($sqlAvgEvtParticipationByYearQuarter, array((int) $kennelKy));


      # Obtain the average event attendance per (year/month)
      $sqlAvgEvtParticipationByMonth = "SELECT
            DATE_FORMAT(THE_DATE,'%m') AS THE_VALUE,
            AVG(THE_COUNT) AS THE_COUNT
        FROM (
            SELECT
              HASHES.HASH_KY AS THE_KEY,
              HASHES.EVENT_DATE AS THE_DATE,
              COUNT(*) AS THE_COUNT
            FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
            WHERE KENNEL_KY = ?
            GROUP BY HASHES.HASH_KY
            ) TEMPORARY_TABLE
        GROUP BY DATE_FORMAT(THE_DATE,'%m')";
      $avgEvtParticipationByMonth = $this->fetchAll($sqlAvgEvtParticipationByMonth, array((int) $kennelKy));

      # Obtain the total event attendance by hasher
      $sqlTotEvtParticipationByHasher =
        "SELECT *
          FROM (
         SELECT
            HASHERS.HASHER_NAME AS THE_VALUE,
            COUNT(*) AS THE_COUNT
            FROM HASHES JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
            JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
            WHERE KENNEL_KY = ?
            GROUP BY HASHERS.HASHER_NAME
            ORDER BY 2 DESC, 1) X
         LIMIT 100";
      $totEvtParticipationByHasher = $this->fetchAll($sqlTotEvtParticipationByHasher, array((int) $kennelKy));

      # Establish and set the return value
      $returnValue = $this->render('event_participation_charts.twig',array(
        'pageTitle' => 'Event Participation Statistics',
        'firstHeader' => 'FIRST HEADER',
        'secondHeader' => 'SECOND HEADER',
        'kennel_abbreviation' => $kennel_abbreviation,
        'AvgTotal_Evt_Participation_By_Year_List' => $avgTotalEvtParticipationByYear,
        'Avg_Evt_Participation_By_YearMonth_List' => $avgEvtParticipationByYearMonth,
        'Avg_Evt_Participation_By_YearQuarter_List' => $avgEvtParticipationByYearQuarter,
        'Avg_Evt_Participation_By_Month_List' => $avgEvtParticipationByMonth,
        'Tot_Evt_Participation_By_Hasher_List' => $totEvtParticipationByHasher
      ));

      # Return the return value
      return $returnValue;

    }


    public function viewFirstTimersChartsAction(Request $request, string $kennel_abbreviation, int $min_hash_count){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      # Obtain the average event attendance per year
      $sqlNewComersByYear = NEWCOMERS_BY_YEAR;
      $newComersByYear = $this->fetchAll($sqlNewComersByYear, array((int) $kennelKy, (int) $kennelKy,(int) $kennelKy, $min_hash_count));

      # Obtain the average event attendance per (year/month)
      $sqlNewComersByYearQuarter = NEWCOMERS_BY_YEAR_QUARTER;
      $newComersByYearQuarter = $this->fetchAll($sqlNewComersByYearQuarter, array((int) $kennelKy, (int) $kennelKy, $min_hash_count));

      # Obtain the average event attendance per (year/quarter)
      $sqlNewComersByYearMonth = NEWCOMERS_BY_YEAR_MONTH;
      $newComersByYearMonth = $this->fetchAll($sqlNewComersByYearMonth, array((int) $kennelKy, (int) $kennelKy, $min_hash_count));


      # Obtain the average event attendance per (year/month)
      $sqlNewComersByMonth = NEWCOMERS_BY_MONTH;
      $newComersByMonth = $this->fetchAll($sqlNewComersByMonth, array((int) $kennelKy,(int) $kennelKy, $min_hash_count));

      # Establish and set the return value
      $returnValue = $this->render('newcomers_charts.twig',array(
        'pageTitle' => 'First Timers / New Comers Statistics',
        'firstHeader' => 'FIRST HEADER',
        'secondHeader' => 'SECOND HEADER',
        'kennel_abbreviation' => $kennel_abbreviation,
        'New_Comers_By_Year_List' => $newComersByYear,
        'New_Comers_By_YearMonth_List' => $newComersByYearMonth,
        'New_Comers_By_YearQuarter_List' => $newComersByYearQuarter,
        'New_Comers_By_Month_List' => $newComersByMonth,
        'Min_Hash_Count' => $min_hash_count
      ));

      # Return the return value
      return $returnValue;

    }




        public function virginHaringsChartsAction(Request $request, int $hare_type, string $kennel_abbreviation){

          #Obtain the kennel key
          $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

          $hareTypeName = $this->getHareTypeName($hare_type);

          # Obtain the average event attendance per year
          $sqlByYear = VIRGIN_HARINGS_BY_YEAR;
          $listByYear = $this->fetchAll($sqlByYear, array((int) $kennelKy, (int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/month)
          $sqlByYearQuarter = VIRGIN_HARINGS_BY_YEAR_QUARTER;
          $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, array((int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/quarter)
          $sqlByYearMonth = VIRGIN_HARINGS_BY_YEAR_MONTH;
          $listByYearMonth = $this->fetchAll($sqlByYearMonth, array((int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/month)
          $sqlByMonth = VIRGIN_HARINGS_BY_MONTH;
          $listByMonth = $this->fetchAll($sqlByMonth, array((int) $kennelKy,$hare_type));

          # Establish and set the return value
          $returnValue = $this->render('generic_charts_template.twig',array(
            'pageTitle' => 'Virgin ('.$hareTypeName.') Harings Statistics',
            'kennel_abbreviation' => $kennel_abbreviation,
            'List_By_Year_List' => $listByYear,
            'List_By_YearMonth_List' => $listByYearMonth,
            'List_By_YearQuarter_List' => $listByYearQuarter,
            'List_By_Month_List' => $listByMonth,
            'BY_YEAR_BAR_LABEL' => 'Total Number of Virgin Harings',
            'BY_YEAR_TITLE' => 'Virgin Harings Per Year',
            'BY_MONTH_BAR_LABEL' => 'Total Virgin Harings By Month',
            'BY_MONTH_TITLE' => 'Virgin Harings Per Month',
            'BY_YEAR_QUARTER_BAR_LABEL' => 'Total Virgin Harings By Year/Quarter',
            'BY_YEAR_QUARTER_TITLE' => 'Virgin Harings Per Year/Quarter',
            'BY_YEAR_MONTH_BAR_LABEL' => 'Total Virgin Harings By Year/Month',
            'BY_YEAR_MONTH_TITLE' => 'Virgin Harings Per Year/Month',
          ));

          # Return the return value
          return $returnValue;

        }



    public function distinctHasherChartsAction(Request $request, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      # Obtain the average event attendance per year
      $sqlByYear = DISTINCT_HASHERS_BY_YEAR;
      $listByYear = $this->fetchAll($sqlByYear, array((int) $kennelKy));

      # Obtain the average event attendance per (year/month)
      $sqlByYearQuarter = DISTINCT_HASHERS_BY_YEAR_QUARTER;
      $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, array((int) $kennelKy));

      # Obtain the average event attendance per (year/quarter)
      $sqlByYearMonth = DISTINCT_HASHERS_BY_YEAR_MONTH;
      $listByYearMonth = $this->fetchAll($sqlByYearMonth, array((int) $kennelKy));


      # Obtain the average event attendance per (year/month)
      $sqlByMonth = DISTINCT_HASHERS_BY_MONTH;
      $listByMonth = $this->fetchAll($sqlByMonth, array((int) $kennelKy));

      # Establish and set the return value
      $returnValue = $this->render('generic_charts_template.twig',array(
        'pageTitle' => 'Distinct Hashers Statistics',
        'firstHeader' => 'FIRST HEADER',
        'secondHeader' => 'SECOND HEADER',
        'kennel_abbreviation' => $kennel_abbreviation,
        'List_By_Year_List' => $listByYear,
        'List_By_YearMonth_List' => $listByYearMonth,
        'List_By_YearQuarter_List' => $listByYearQuarter,
        'List_By_Month_List' => $listByMonth,
        'BY_YEAR_BAR_LABEL' => 'Number of Unique Hashers',
        'BY_YEAR_TITLE' => 'Distinct Hashers Per Year',
        'BY_MONTH_BAR_LABEL' => 'Number of Unique Hashers',
        'BY_MONTH_TITLE' => 'Distinct Hashers Per Month',
        'BY_YEAR_QUARTER_BAR_LABEL' => 'Number of Unique Hashers',
        'BY_YEAR_QUARTER_TITLE' => 'Distinct Hashers Per Year/Quarter',
        'BY_YEAR_MONTH_BAR_LABEL' => 'Number of Unique Hashers',
        'BY_YEAR_MONTH_TITLE' => 'Distinct Hashers Per Year/Month',
      ));

      # Return the return value
      return $returnValue;

    }


        public function distinctHaresChartsAction(Request $request, int $hare_type, string $kennel_abbreviation){

          #Obtain the kennel key
          $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

          $hareTypeName = $this->getHareTypeName($hare_type);

          # Obtain the average event attendance per year
          $sqlByYear = DISTINCT_HARES_BY_YEAR;
          $listByYear = $this->fetchAll($sqlByYear, array((int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/month)
          $sqlByYearQuarter = DISTINCT_HARES_BY_YEAR_QUARTER;
          $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, array((int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/quarter)
          $sqlByYearMonth = DISTINCT_HARES_BY_YEAR_MONTH;
          $listByYearMonth = $this->fetchAll($sqlByYearMonth, array((int) $kennelKy,$hare_type));

          # Obtain the average event attendance per (year/month)
          $sqlByMonth = DISTINCT_HARES_BY_MONTH;
          $listByMonth = $this->fetchAll($sqlByMonth, array((int) $kennelKy,$hare_type));

          # Establish and set the return value
          $returnValue = $this->render('generic_charts_template.twig',array(
            'pageTitle' => 'Distinct '.$hareTypeName.' Hare Statistics',
            'kennel_abbreviation' => $kennel_abbreviation,
            'List_By_Year_List' => $listByYear,
            'List_By_YearMonth_List' => $listByYearMonth,
            'List_By_YearQuarter_List' => $listByYearQuarter,
            'List_By_Month_List' => $listByMonth,
            'BY_YEAR_BAR_LABEL' => 'Number of Unique '.$hareTypeName.' Hares',
            'BY_YEAR_TITLE' => 'Distinct '.$hareTypeName.' Hares Per Year',
            'BY_MONTH_BAR_LABEL' => 'Number of Unique '.$hareTypeName.' Hares',
            'BY_MONTH_TITLE' => 'Distinct '.$hareTypeName.' Hares Per Month',
            'BY_YEAR_QUARTER_BAR_LABEL' => 'Number of Unique '.$hareTypeName.' Hares',
            'BY_YEAR_QUARTER_TITLE' => 'Distinct '.$hareTypeName.' Hares Per Year/Quarter',
            'BY_YEAR_MONTH_BAR_LABEL' => 'Number of Unique '.$hareTypeName.' Hares',
            'BY_YEAR_MONTH_TITLE' => 'Distinct '.$hareTypeName.' Hares Per Year/Month',
          ));

          # Return the return value
          return $returnValue;

        }


    public function viewLastTimersChartsAction(Request $request, string $kennel_abbreviation, int $min_hash_count, int $month_count){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      # Obtain the average event attendance per year
      $sqlLastComersByYear = DEPARTERS_BY_YEAR;
      $lastComersByYear = $this->fetchAll($sqlLastComersByYear, array((int) $kennelKy,(int) $kennelKy, $min_hash_count, $month_count));

      # Obtain the average event attendance per (year/month)
      $sqlLastComersByYearQuarter = DEPARTERS_BY_YEAR_QUARTER;
      $lastComersByYearQuarter = $this->fetchAll($sqlLastComersByYearQuarter, array((int) $kennelKy, (int) $kennelKy, $min_hash_count, $month_count));

      # Obtain the average event attendance per (year/quarter)
      $sqlLastComersByYearMonth = DEPARTERS_BY_YEAR_MONTH;
      $lastComersByYearMonth = $this->fetchAll($sqlLastComersByYearMonth, array((int) $kennelKy, (int) $kennelKy, $min_hash_count, $month_count));


      # Obtain the average event attendance per (year/month)
      $sqlLastComersByMonth = DEPARTERS_BY_MONTH;
      $lastComersByMonth = $this->fetchAll($sqlLastComersByMonth, array((int) $kennelKy,(int) $kennelKy, $min_hash_count, $month_count));

      # Establish and set the return value
      $returnValue = $this->render('lastcomers_charts.twig',array(
        'pageTitle' => 'Last Comers Statistics',
        'firstHeader' => 'FIRST HEADER',
        'secondHeader' => 'SECOND HEADER',
        'kennel_abbreviation' => $kennel_abbreviation,
        'Last_Comers_By_Year_List' => $lastComersByYear,
        'Last_Comers_By_YearMonth_List' => $lastComersByYearMonth,
        'Last_Comers_By_YearQuarter_List' => $lastComersByYearQuarter,
        'Last_Comers_By_Month_List' => $lastComersByMonth,
        'Min_Hash_Count' => $min_hash_count,
        'Month_Count' => $month_count
      ));

      # Return the return value
      return $returnValue;

    }


  #[Route('/{kennel_abbreviation}/trendingHashers/{day_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'day_count' => '%app.pattern.day_count%' ]
  )]
  public function trendingHashersAction(string $kennel_abbreviation, int $day_count) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Establish the row limit
    $rowLimit = 15;

    # Obtain the average event attendance per year
    $sqlTrendingHashers = "
      SELECT HASHERS.HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHERS
        JOIN HASHINGS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE HASHES.KENNEL_KY = ?
         AND EVENT_DATE >= (CURRENT_DATE - INTERVAL ? DAY)
       GROUP BY HASHERS.HASHER_NAME
       ORDER BY THE_COUNT DESC
       LIMIT $rowLimit";
    $trendingHashersList = $this->fetchAll($sqlTrendingHashers, [ $kennelKy, $day_count ]);

    # Establish and set the return value
    return $this->render('trending_hashers_charts.twig', [
      'pageTitle' => 'Trending Hashers',
      'firstHeader' => 'FIRST HEADER',
      'secondHeader' => 'SECOND HEADER',
      'kennel_abbreviation' => $kennel_abbreviation,
      'trending_hashers_list' => $trendingHashersList,
      'day_count' => $day_count,
      'row_limit' => $rowLimit ]);
  }

  #[Route('/{kennel_abbreviation}/trendingHares/{hare_type}/{day_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%',
      'day_count' => '%app.pattern.day_count%' ]
  )]
  public function trendingHaresAction(int $hare_type, string $kennel_abbreviation, int $day_count) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    #Establish the row limit
    $rowLimit = 15;

    # Obtain the average event attendance per year
    $sqlTrendingTrueHares = "
      SELECT HASHERS.HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHERS
        JOIN HARINGS
          ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
        JOIN HASHES
          ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
       WHERE HASHES.KENNEL_KY = ?
         AND HARINGS.HARE_TYPE & ? != 0
         AND EVENT_DATE >= (CURRENT_DATE - INTERVAL ? DAY)
       GROUP BY HASHERS.HASHER_NAME
       ORDER BY THE_COUNT DESC
       LIMIT $rowLimit";
    $trendingTrueHaresList = $this->fetchAll($sqlTrendingTrueHares, [ $kennelKy, $hare_type, $day_count ]);

    # Establish and set the return value
    return $this->render('trending_hares_charts.twig', [
      'pageTitle' => 'Trending '.$hareTypeName.' Hares',
      'kennel_abbreviation' => $kennel_abbreviation,
      'trending_hares_list' => $trendingTrueHaresList,
      "hare_type_name" => $hareTypeName,
      'day_count' => $day_count,
      'row_limit' => $rowLimit ]);
  }

  #[Route('/{kennel_abbreviation}/unTrendingHaresJsonPre/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%',
      'day_count' => '%app.pattern.day_count%',
      'min_hash_count' => '%app.pattern.min_hash_count%',
      'max_percentage' => '%app.pattern.max_percentage%',
      'row_limit' => '%app.pattern.row_limit%' ]
  )]
  public function unTrendingHaresJsonPreAction(string $kennel_abbreviation, int $hare_type, int $day_count, int $min_hash_count,
      int $max_percentage, int $row_limit) {

    $hareTypeName = $this->getHareTypeName($hare_type);

    return $this->render('un_trending_hares_charts_json.twig', [
      'pageTitle' => 'Un-Trending '.$hareTypeName.' Hares',
      'kennel_abbreviation' => $kennel_abbreviation,
      'day_count' => $day_count,
      'row_limit' => $row_limit,
      'min_hash_count' => $min_hash_count,
      'max_percentage' => $max_percentage,
      'hare_type' => $hare_type,
      "hare_type_name" => $hareTypeName ]);
  }

  #[Route('/{kennel_abbreviation}/unTrendingHaresJsonPost/{hare_type}/{day_count}/{min_hash_count}/{max_percentage}/{row_limit}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%',
      'day_count' => '%app.pattern.day_count%',
      'min_hash_count' => '%app.pattern.min_hash_count%',
      'max_percentage' => '%app.pattern.max_percentage%',
      'row_limit' => '%app.pattern.row_limit%' ]
  )]
  public function unTrendingHaresJsonPostAction(string $kennel_abbreviation, int $hare_type, int $day_count, int $min_hash_count,
      int $max_percentage, int $row_limit) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the average event attendance per year
    $sqlUnTrendingTrueHares = "
      SELECT HASHER_NAME, ((HARE_COUNT/HASH_COUNT)*100) AS HARING_TO_HASHING_PERCENTAGE, HASH_COUNT, HARE_COUNT, HASHER_KY
        FROM (SELECT HASHERS.*, HASHERS.HASHER_KY AS OUTER_HASHER_KY, (
                     SELECT COUNT(*)
                       FROM HASHINGS
                       JOIN HASHES
                         ON HASHINGS.HASH_KY = HASHES.HASH_KY
                      WHERE HASHINGS.HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND EVENT_DATE >= (CURRENT_DATE - INTERVAL ? DAY)) AS HASH_COUNT, (
                     SELECT COUNT(*)
                       FROM HARINGS
                       JOIN HASHES
                         ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
                      WHERE HARINGS_HASHER_KY = OUTER_HASHER_KY
                        AND HASHES.KENNEL_KY = ?
                        AND HARINGS.HARE_TYPE & ? != 0
                        AND EVENT_DATE >= (CURRENT_DATE - INTERVAL ? DAY)) AS HARE_COUNT
                FROM HASHERS) MAIN_TABLE
       WHERE HASH_COUNT > ?
         AND ((HARE_COUNT/HASH_COUNT)*100) < ?
       ORDER BY HARING_TO_HASHING_PERCENTAGE, HASH_COUNT DESC
       LIMIT $row_limit";

    $unTrendingTrueHaresList = $this->fetchAll(
      $sqlUnTrendingTrueHares, [ $kennelKy, $day_count, $kennelKy, $hare_type, $day_count, $min_hash_count, $max_percentage ]);

    #Establish the output
    $output = [
      "day_count" => $day_count,
      "row_limit" => $row_limit,
      "min_hash_count" => $min_hash_count,
      "max_percentage" => $max_percentage,
      "resultList" => $unTrendingTrueHaresList ];

    return new JsonResponse($output);
  }

  #[Route('/{kennel_abbreviation}/aboutContact',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function aboutContactAction(string $kennel_abbreviation) {

    $pageTitle = "About this application";

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    return $this->render('about.twig', [
      'pageTitle' => $pageTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'adminEmail' => str_rot13($this->getAdministratorEmail()) ]);
  }

  #[Route('/{kennel_abbreviation}/hasherNameAnalysis',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hasherNameAnalysisAction(string $kennel_abbreviation) {

    #Establish the page title
    $pageTitle = "Hasher Nickname Substring Frequency Analysis";
    $pageSubTitle = "sub title";
    $pageTableCaption = "page table caption";

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $SQL = "
      SELECT HASHER_NAME, HASHER_KY
        FROM HASHERS
       WHERE HASHER_KY IN (SELECT HASHER_KY
                            FROM HASHINGS
       	                    JOIN HASHES
                              ON HASHINGS.HASH_KY = HASHES.HASH_KY
                           WHERE KENNEL_KY = ?
                             AND HASHER_NAME NOT LIKE '%NHN%'
                             AND HASHER_NAME NOT LIKE 'JUST %')";

    #Obtain the hare list
    $hasherNameList = $this->fetchAll($SQL, [ $kennelKy ]);
    $tokenizerString = " -\'&,!?().";

    #Create an array that will be used to store the sub strings
    $theArrayOfSubstrings = [];

    #Iterate through the hasher name list
    foreach($hasherNameList as $hasherName) {
      $tempName = $hasherName['HASHER_NAME'];
      $tempKey = $hasherName['HASHER_KY'];
      $token = strtok($tempName, $tokenizerString);
      while($token !== false){

        #Log the substring
        $lowerToken = strtolower($token);

        #Create a hasher name and hasher key pair
        $tempNameKey = [ 'NAME'=> $tempName, 'KEY' => $tempKey ];

        #Check if substring exists in the substring array
        if(array_key_exists($lowerToken,$theArrayOfSubstrings)) {

          #Grab the entry corresponding to this key (substring)
          $tempEntry = $theArrayOfSubstrings[$lowerToken];

          #Push the entry onto the array
          array_push($tempEntry, $tempNameKey);

          #Replace the old value with the new value
          $theArrayOfSubstrings[$lowerToken] = $tempEntry;

        } else {
          $theArrayOfSubstrings[$lowerToken] = [ $tempNameKey ];
        }

        #Grab the next substring
        $token = strtok($tokenizerString);
      }
    }

    uasort($theArrayOfSubstrings, function ($a, $b) {
      $a = count($a);
      $b = count($b);
      return ($a == $b) ? 0 : (($a < $b) ? 1 : -1);
    });

    return $this->render('hasher_name_substring_analysis.twig', [
      'pageTitle' => $pageTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'subStringArray' => $theArrayOfSubstrings,
      'pageSubTitle' => "The individual words in the hashernames, from most common to least common",
      'tableCaption1' => "Hashername sub-word",
      'tableCaption2' => "All names containing the sub-word" ]);
  }

  private function extractRootWordFromToken($tokenValue) {

    #establish the return value
    $returnValue = null;

    #Define the list of root words and their exceptions
    #$rootArray = array (
    #  "shit" => null,
    #  "dick" => null,
    #  "cum" => array("scum"),
    #  "pussy" => null
    #);

    #Iterate through the list of exceptions; see if there is a match; see if there is an exception match

    $stemmer = StemmerFactory::create("en");
    $stem = $stemmer->stem($tokenValue);

    return $stem;
  }

  #[Route('/{kennel_abbreviation}/chartsAndDetails',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function viewKennelChartsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypes = $this->getHareTypes($kennelKy);

    #Obtain the kennel value
    $kennelValueSql = "SELECT KENNELS.* FROM KENNELS WHERE KENNEL_KY = ?";
    $kennelValue = $this->fetchAssoc($kennelValueSql, [ $kennelKy ]);

    # Obtain their hashes
    $sqlTheHashes = "
      SELECT HASHES.*
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theHashes = $this->fetchAll($sqlTheHashes, [ $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHINGS
        JOIN HASHES
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
       WHERE KENNEL_KY = ?
         AND LAT IS NOT NULL
         AND LNG IS NOT NULL";
    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $kennelKy ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    #Obtain the number of hashes for this kennel
    $sqlHashCountForKennel = "SELECT COUNT(*) AS THE_COUNT FROM HASHES WHERE KENNEL_KY = ?";
    $hashCountValueForKennel = $this->fetchAssoc($sqlHashCountForKennel, [ $kennelKy ]);
    $hashCountForKennel = $hashCountValueForKennel['THE_COUNT'];

    #Obtain the number of distinct hashers
    $distinctHasherCountValueForKennel = $this->fetchAssoc($this->sqlQueries->getKennelNumOfDistinctHashers(), [ $kennelKy ]);
    $distinctHasherCountForKennel = $distinctHasherCountValueForKennel['THE_COUNT'];

    #Obtain the number of distinct overall hares
    $distinctOverallHareCountValueForKennel = $this->fetchAssoc($this->sqlQueries->getKennelNumOfDistinctOverallHares(), [ $kennelKy ]);
    $distinctOverallHareCountForKennel = $distinctOverallHareCountValueForKennel['THE_COUNT'];

    #Obtain the number of distinct hares by type
    $distinctHareCounts = [];
    foreach($hareTypes as &$hareType) {
      $distinctHareCountValueForKennel = $this->fetchAssoc($this->sqlQueries->getKennelNumOfDistinctHares(), [ $kennelKy, $hareType['HARE_TYPE'] ]);
      $distinctHareCounts[$hareType['HARE_TYPE_NAME']] = $distinctHareCountValueForKennel['THE_COUNT'];
    }

    # Obtain the hashes by month (name)
    $theHashesByMonthNameList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByMonthName(), [ $kennelKy ]);

    # Obtain the hashes by quarter
    $theHashesByQuarterList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByQuarter(), [ $kennelKy ]);

    # Obtain the hashes by quarter
    $theHashesByStateList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByState(), [ $kennelKy ]);

    # Obtain the hashes by county
    $theHashesByCountyList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByCounty(), [ $kennelKy ]);

    # Obtain the hashes by postal code
    $theHashesByPostalCodeList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByPostalCode(), [ $kennelKy ]);

    # Obtain the hashes by day name
    $theHashesByDayNameList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByDayname(), [ $kennelKy ]);

    #Obtain the hashes by year
    $sqlHashesByYear = "
      SELECT YEAR(EVENT_DATE) AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE HASHES.KENNEL_KY = ?
       GROUP BY YEAR(EVENT_DATE)
       ORDER BY YEAR(EVENT_DATE)";
    $hashesByYearList = $this->fetchAll($sqlHashesByYear, [ $kennelKy ]);

    #Query the database
    $cityHashingsCountList = $this->fetchAll($this->sqlQueries->getKennelHashCountsByCity(), [ $kennelKy ]);

    #Obtain largest entry from the list
    $cityHashingsCountMax = 1;
    if(isset($cityHashingsCountList[0]['THE_COUNT'])){
      $cityHashingsCountMax = $cityHashingsCountList[0]['THE_COUNT'];
    }

    #0. Define the query for the state / county / city / neighborhood chart
    $locationBreakdownSql = "
      SELECT CASE WHEN NEIGHBORHOOD ='' THEN CONCAT(EVENT_STATE,'/',COUNTY,'/',EVENT_CITY,'/','123BLANK123','/',THE_COUNT)
                  ELSE CONCAT(EVENT_STATE,'/',COUNTY,'/',EVENT_CITY,'/',NEIGHBORHOOD,'/',THE_COUNT)
              END AS THE_VALUE, THE_COUNT
        FROM (SELECT EVENT_STATE, COUNTY, EVENT_CITY, NEIGHBORHOOD, COUNT(*) AS THE_COUNT
                FROM HASHES
               WHERE HASHES.KENNEL_KY = ?
               GROUP BY EVENT_STATE, COUNTY, EVENT_CITY,NEIGHBORHOOD
               ORDER BY EVENT_STATE, COUNTY, EVENT_CITY,NEIGHBORHOOD) TEMPTABLE
       WHERE EVENT_STATE IS NOT NULL
         AND EVENT_STATE != ''
         AND COUNTY IS NOT NULL
         AND COUNTY != ''
         AND EVENT_CITY IS NOT NULL
         AND EVENT_CITY != ''
       ORDER BY THE_COUNT DESC";

    #1. Query the db
    $locationBreakdownValues = $this->fetchAll($locationBreakdownSql, [ $kennelKy ]);
    #4. Create the formatted data for the sunburst graph
    $locationBreakdownFormattedData = $this->helper->convertToFormattedHiarchyV2($locationBreakdownValues);

    return $this->render('kennel_chart_details.twig', [
      'pageTitle' => 'Kennel Charts and Details',
      'kennelName' => $kennelValue['KENNEL_NAME'],
      'location_breakdown_formatted_data' => $locationBreakdownFormattedData,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashes_by_year_list' => $hashesByYearList,
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
      'hash_count' => $hashCountForKennel,
      'distinct_hasher_count' => $distinctHasherCountForKennel,
      'distinct_hare_counts' => $distinctHareCounts,
      'distinct_overall_hare_count' =>$distinctOverallHareCountForKennel,
      'hareTypes' => count($hareTypes) > 1 ? $hareTypes : [],
      'overall' => count($hareTypes) > 1 ? "Overall " : "" ]);
  }

  #[Route('/{kennel_abbreviation}/hasherNameAnalysis2',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hasherNameAnalysisAction2(string $kennel_abbreviation) {

    #Establish the page title
    $pageTitle = "Hasher Nickname Stemmed Substring Frequency Analysis";
    $pageSubTitle = "sub title";
    $pageTableCaption = "page table caption";

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $SQL = "
      SELECT HASHER_NAME, HASHER_KY
        FROM HASHERS
       WHERE HASHER_KY IN (SELECT HASHER_KY
                             FROM HASHINGS
                             JOIN HASHES
                               ON HASHINGS.HASH_KY = HASHES.HASH_KY
                            WHERE KENNEL_KY = ?
                              AND HASHER_NAME NOT LIKE '%NHN%'
                              AND HASHER_NAME NOT LIKE 'JUST %')";

    #Obtain the hare list
    $hasherNameList = $this->fetchAll($SQL, [ $kennelKy ]);
    $tokenizerString = " -\'&,!?().";

    #Create an array that will be used to store the sub strings
    $theArrayOfSubstrings = [];

    #Iterate through the hasher name list
    foreach($hasherNameList as $hasherName) {
      $tempName = $hasherName['HASHER_NAME'];
      $tempKey = $hasherName['HASHER_KY'];
      $token = strtok($tempName, $tokenizerString);
      while($token !== false) {

        #Log the substring
        $lowerToken = strtolower($token);

        #test function call to stemmer function
        $stemmedLowerToken = $this->extractRootWordFromToken($lowerToken);
        $lowerToken = $stemmedLowerToken;

        #Create a hasher name and hasher key pair
        $tempNameKey = [ 'NAME'=> $tempName, 'KEY' => $tempKey ];

        #Check if substring exists in the substring array
        if(array_key_exists($lowerToken, $theArrayOfSubstrings)) {

          #Grab the entry corresponding to this key (substring)
          $tempEntry = $theArrayOfSubstrings[$lowerToken];

          #Push the entry onto the array
          array_push($tempEntry, $tempNameKey);

          #Replace the old value with the new value
          $theArrayOfSubstrings[$lowerToken] = $tempEntry;

        } else {
          $theArrayOfSubstrings[$lowerToken] = [ $tempNameKey ];
        }

        #Grab the next substring
        $token = strtok($tokenizerString);
      }
    }

    uasort($theArrayOfSubstrings, function ($a, $b) {
      $a = count($a);
      $b = count($b);
      return ($a == $b) ? 0 : (($a < $b) ? 1 : -1);
    });

    #Establish the return value
    return $this->render('hasher_name_substring_analysis2.twig', [
      'pageTitle' => $pageTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'subStringArray' => $theArrayOfSubstrings,
      'pageSubTitle' => "The individual words in the hashernames, from most common to least common",
      'tableCaption1' => "Hashername sub-word",
      'tableCaption2' => "All names containing the sub-word" ]);
  }


  #[Route('/{kennel_abbreviation}/hasherNameAnalysisWordCloud',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function hasherNameAnalysisWordCloudAction(string $kennel_abbreviation) {

    #Establish the page title
    $pageTitle = "Hasher Nickname Stemmed Substring Frequency Analysis";
    $pageSubTitle = "sub title";
    $pageTableCaption = "page table caption";

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $SQL = "
      SELECT HASHER_NAME, HASHER_KY
        FROM HASHERS
       WHERE HASHER_KY IN (SELECT HASHER_KY
                             FROM HASHINGS
                             JOIN HASHES
                               ON HASHINGS.HASH_KY = HASHES.HASH_KY
                            WHERE KENNEL_KY = ?
                              AND HASHER_NAME NOT LIKE '%NHN%'
                              AND HASHER_NAME NOT LIKE 'JUST %')";

    #Obtain the hare list
    $hasherNameList = $this->fetchAll($SQL,array((int) $kennelKy));
    $tokenizerString = " -\'&,!?().";

    #Create an array that will be used to store the sub strings
    $theArrayOfSubstrings = [];

    #Iterate through the hasher name list
    foreach($hasherNameList as $hasherName) {
      $tempName = $hasherName['HASHER_NAME'];
      $tempKey = $hasherName['HASHER_KY'];
      $token = strtok($tempName, $tokenizerString);
      while($token !== false) {

        #Log the substring
        $lowerToken = strtolower($token);

        #test function call to stemmer function
        $stemmedLowerToken = $this->extractRootWordFromToken($lowerToken);
        $lowerToken = $stemmedLowerToken;

        #Create a hasher name and hasher key pair
        $tempNameKey = [ 'NAME'=> $tempName, 'KEY' => $tempKey ];

        #Check if substring exists in the substring array
        if(array_key_exists($lowerToken, $theArrayOfSubstrings)){

          #Grab the entry corresponding to this key (substring)
          $tempEntry = $theArrayOfSubstrings[$lowerToken];

          #Push the entry onto the array
          array_push($tempEntry, $tempNameKey);

          #Replace the old value with the new value
          $theArrayOfSubstrings[$lowerToken] = $tempEntry;

        } else {
          $theArrayOfSubstrings[$lowerToken] = [ $tempNameKey ];
        }

        #Grab the next substring
        $token = strtok($tokenizerString);
      }
    }

    uasort($theArrayOfSubstrings, function ($a, $b) {
      $a = count($a);
      $b = count($b);
      return ($a == $b) ? 0 : (($a < $b) ? 1 : -1);
    });

    #Count up the names tied to each substring
    $subStringCounts = [];
    foreach($theArrayOfSubstrings as $keyValue => $valueValue) {
      $tempCount = count($valueValue);
      $temp = [ "THE_VALUE" => $keyValue, "THE_COUNT" => $tempCount ];
      array_push($subStringCounts,$temp);
    }

    return $this->render('wordcloud_hashername_analysis.twig', [
      'pageTitle' => $pageTitle,
      'kennel_abbreviation' => $kennel_abbreviation,
      'subStringArray' => $subStringCounts,
      'pageSubTitle' => "The individual words in the hashernames, from most common to least common",
      'tableCaption1' => "Hashername sub-word",
      'tableCaption2' => "All names containing the sub-word" ]);
  }
}
