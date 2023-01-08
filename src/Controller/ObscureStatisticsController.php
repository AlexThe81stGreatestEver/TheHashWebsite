<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Helper;
use App\SqlQueries;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
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

  #[Route('/{kennel_abbreviation}/statistics/getYearInReview/{year_value}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'year_value' => '%app.pattern.year_value%']
  )]
  public function getYearInReviewAction(int $year_value, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hashTypes = $this->getHashTypes($kennelKy, 0);
    $hareTypes = $this->getHareTypes($kennelKy);

    #Establish the page title
    $pageTitle = "$year_value: Year in review";

    #Obtain number of hashes
    $hashCount = ($this->fetchAssoc($this->sqlQueries->getPerKennelHashCountsByYear(), [ $year_value, $kennelKy ]))['THE_COUNT'];

    foreach($hashTypes as &$hashType) {
      #Obtain number of hashtype hashes
      $hashCounts[$hashType['HASH_TYPE_NAME']] = ($this->fetchAssoc($this->sqlQueries->getPerKennelHashCountsByYear() . " AND HASHES.HASH_TYPE = ?",
        [ $year_value, $kennelKy, $hashType['HASH_TYPE'] ]))['THE_COUNT'];
    }

    #Obtain number of hashers
    $hasherCount = ($this->fetchAssoc($this->sqlQueries->getPerKennelHashersCountByYear(), [ $year_value, $kennelKy ]))['THE_COUNT'];

    #Obtain number of overall hares
    $overallHareCount = ($this->fetchAssoc($this->sqlQueries->getPerKennelHaresCountByYear(), [ $year_value, $kennelKy ]))['THE_COUNT'];

    foreach($hareTypes as &$hareType) {
      $hareCounts[$hareType['HARE_TYPE_NAME']] = ($this->fetchAssoc($this->sqlQueries->getPerKennelHaresCountByYear() . "AND HARINGS.HARE_TYPE & ? != 0",
        [ $year_value, $kennelKy, $hareType['HARE_TYPE'] ]))['THE_COUNT'];
    }

    # Obtain the number of newbie hashers
    $newHashers = $this->fetchAll($this->sqlQueries->getNewHashersForThisYear(), [ $kennelKy, $kennelKy, $year_value ]);
    $newHashersCount = count($newHashers);

    foreach($hareTypes as &$hareType) {
      $newHareCounts[$hareType['HARE_TYPE_NAME']] = count($this->fetchAll($this->sqlQueries->getNewHaresForThisYearByHareType(),
        [ $hareType['HARE_TYPE'], $kennelKy,$hareType['HARE_TYPE'], $kennelKy, $hareType['HARE_TYPE'],(int)$year_value ]));
    }

    # Obtain the number of new overall hares
    $newOverallHares = $this->fetchAll($this->sqlQueries->getNewHaresForThisYear(), [ $kennelKy, $kennelKy, $year_value ]);
    $newOverallHaresCount = count($newOverallHares);

    #Establish the return value
    return $this->render('year_in_review.twig', [
      'pageTitle' => $pageTitle,
      'yearValue' => $year_value,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hash_types' => $hashTypes,
      'hare_types' => count($hareTypes) > 1 ? $hareTypes : [],
      'hash_count' => $hashCount,
      'hash_counts' => $hashCounts,
      'hasher_count' => $hasherCount,
      'overall_hare_count' => $overallHareCount,
      'hare_counts' => $hareCounts,
      'newbie_hashers_count' => $newHashersCount,
      'newbie_hare_counts' => $newHareCounts,
      'newbie_overall_hares_count' => $newOverallHaresCount ]);
  }

  #[Route('/{kennel_abbreviation}/statistics/getHasherCountsByYear',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getHasherCountsByYear(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hasherCountSQL = $this->sqlQueries->getHasherCountsByYear();

    #Obtain the hare list
    $hasherCountList = $this->fetchAll($hasherCountSQL, [ $theYear, $kennelKy ]);

    return new JsonResponse($hasherCountList);
  }

  #[Route('/{kennel_abbreviation}/statistics/getTotalHareCountsByYear',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function getTotalHareCountsByYear(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hareCountSQL = $this->sqlQueries->getTotalHareCountsByYear();

    #Obtain the hare list
    $hareCountList = $this->fetchAll($hareCountSQL, [ $theYear, $kennelKy ]);

    return new JsonResponse($hareCountList);
  }

  #[Route('/{kennel_abbreviation}/statistics/getHareCountsByYear/{hare_type}',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function getHareCountsByYear(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hareCountSQL = $this->sqlQueries->getHareCountsByYearByHareType();

    #Obtain the hare list
    $hareCountList = $this->fetchAll($hareCountSQL, [ $theYear, $hare_type, $kennelKy ]);

    return new JsonResponse($hareCountList);
  }

  #[Route('/{kennel_abbreviation}/statistics/getNewbieHasherListByYear',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getNewbieHasherListByYear(string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hareCountSQL = $this->sqlQueries->getNewHashersForThisYear();

    #Obtain the hare list
    $hareCountList = $this->fetchAll($hareCountSQL, [ $kennelKy, $kennelKy, $theYear ]);

    return new JsonResponse($hareCountList);
  }

  #[Route('/{kennel_abbreviation}/statistics/getNewbieHareListByYear/{hare_type}',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function getNewbieHareListByYear(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hareCountSQL = $this->sqlQueries->getNewHaresForThisYearByHareType();

    #Obtain the hare list
    $hareCountList = $this->fetchAll($hareCountSQL, [
      $hare_type, $kennelKy, $hare_type, $kennelKy, $hare_type, $theYear ]);

    return new JsonResponse($hareCountList);
  }

  #[Route('/{kennel_abbreviation}/statistics/getNewbieOverallHareListByYear',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getNewbieOverallHareListByYear(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the post values
    $theYear = $_POST['year_value'];

    #Define the SQL to execute
    $hareCountSQL = $this->sqlQueries->getNewHaresForThisYear();

    #Obtain the hare list
    $hareCountList = $this->fetchAll($hareCountSQL, [ $kennelKy, $kennelKy, $theYear ]);

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

  #[Route('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/bydays',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'analversary_number' => '%app.pattern.analversary_number%' ]
  )]
  public function quickestToReachAnalversaryByDaysAction(string $kennel_abbreviation, int $analversary_number) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the analversary number, then subtract one (for the query requires it)
    $modifiedAnalversaryNumber = $analversary_number -1;

    #Define the sql statement to execute
    $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber, $this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy ]);

    #Define the page title
    $pageTitle = "Quickest to reach $analversary_number hashes";

    return $this->render('analversaries_achievements_non_json.twig', [
      'pageTitle' => $pageTitle,
      'tableCaption' => 'Faster is better',
      'pageSubTitle' => 'Measured in days',
      'theList' => $theResults,
      'analversary_number' => $analversary_number,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/{analversary_number}/quickest/to/reach/date',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'analversary_number' => '%app.pattern.analversary_number%' ]
  )]
  public function quickestToReachAnalversaryByDate(string $kennel_abbreviation, int $analversary_number) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the analversary number, then subtract one (for the query requires it)
    $modifiedAnalversaryNumber = $analversary_number -1;

    #Define the sql statement to execute
    $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber,$this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","ASC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","ANALVERSARY_DATE",$theSql);

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy,  $kennelKy, $kennelKy ]);

    #Define the page title
    $pageTitle = "Chronological order of analversaries";
    $pageSubTitle = "($analversary_number hashes)";

    return $this->render('analversaries_achievements_chronological.twig',array(
      'pageTitle' => $pageTitle,
      'tableCaption' => '',
      'pageSubTitle' => $pageSubTitle,
      'theList' => $theResults,
      'analversary_number' => $analversary_number,
      'kennel_abbreviation' => $kennel_abbreviation
    ));
  }

  #[Route('/{kennel_abbreviation}/{analversary_number}/slowest/to/reach/bydays',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'analversary_number' => '%app.pattern.analversary_number%' ]
  )]
  public function slowestToReachAnalversaryByDaysAction(string $kennel_abbreviation, int $analversary_number) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Obtain the analversary number, then subtract one (for the query requires it)
    $modifiedAnalversaryNumber = $analversary_number -1;

    #Define the sql statement to execute
    $theSql = str_replace("XLIMITX",$modifiedAnalversaryNumber, $this->sqlQueries->getFastestHashersToAnalversaries2());
    $theSql = str_replace("XORDERX","DESC",$theSql);
    $theSql = str_replace("XORDERCOLUMNX","DAYS_TO_REACH_ANALVERSARY",$theSql);

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy ]);

    #Define the page title
    $pageTitle = "Slowest to reach $analversary_number hashes";

    return $this->render('analversaries_achievements_non_json.twig', [
      'pageTitle' => $pageTitle,
      'tableCaption' => 'Faster is better',
      'pageSubTitle' => 'Measured in days',
      'theList' => $theResults,
      'analversary_number' => $analversary_number,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/longestStreaks',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function getLongestStreaksAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql statement to execute
    $theSql = $this->sqlQueries->getTheLongestStreaks()." LIMIT 25";

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy ]);

    #Define the page title
    $pageTitle = "The longest streaks";

    return $this->render('name_number_list.twig', [
      'pageTitle' => $pageTitle,
      'tableCaption' => 'Longest streak per hasher',

      'columnOneName' => 'Hasher Name',
      'columnTwoName' => 'Streak Length',
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation,
      'pageTracking' => 'LongestStreaks' ]);
  }

  #[Route('/{kennel_abbreviation}/longest/career',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function longestCareerAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLongestHashingCareerInDays();
    $theSql = str_replace("XORDERCOLUMNX", "DIFFERENCE", $theSql);
    $theSql = str_replace("XUPORDOWNX", "DESC", $theSql);

    #Define the minimum hashing count
    $minHashingCount = 4;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $minHashingCount ]);

    #Define the page sub title
    $pageSubTitle = "Days between first hashes and most recent hashes";

    #Define the table caption
    $tableCaption = "Minimum hashing count: $minHashingCount";

    #Add the results into the twig template
    return $this->render('career_length_by_day.twig', [
      'pageTitle' => "Longest Hashing Career (By Days)",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/everyones/latest/hashes/{min_hash_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'min_hash_count' => '%app.pattern.min_hash_count%']
  )]
  public function everyonesLatestHashesAction(string $kennel_abbreviation, int $min_hash_count) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLongestHashingCareerInDays();
    $theSql = str_replace("XORDERCOLUMNX", "LATEST_HASH_DATE", $theSql);
    $theSql = str_replace("XUPORDOWNX", "DESC", $theSql);

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $min_hash_count ]);

    #Define the page sub title
    $pageSubTitle = "Everyone's latest hash, sorted by date";

    #Define the table caption
    $tableCaption = "Minimum hashing count: $min_hash_count";

    #Add the results into the twig template
    return $this->render('career_length_by_day.twig', [
      'pageTitle' => $pageSubTitle,
      'pageSubTitle' => "",
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/everyones/first/hashes/{min_hash_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'min_hash_count' => '%app.pattern.min_hash_count%']
  )]
  public function everyonesFirstHashesAction(string $kennel_abbreviation, int $min_hash_count) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLongestHashingCareerInDays();
    $theSql = str_replace("XORDERCOLUMNX", "FIRST_HASH_DATE", $theSql);
    $theSql = str_replace("XUPORDOWNX", "DESC", $theSql);

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $min_hash_count ]);

    #Define the page sub title
    $pageSubTitle = "Everyone's first hash, sorted by date";

    #Define the table caption
    $tableCaption = "Minimum hashing count: $min_hash_count";

    #Add the results into the twig template
    return $this->render('career_length_by_day.twig', [
      'pageTitle' => $pageSubTitle,
      'pageSubTitle' => "",
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/highest/averageDaysBetweenHashes',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function highestAverageDaysBetweenHashesAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLongestHashingCareerInDays();
    $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HASHES", $theSql);
    $theSql = str_replace("XUPORDOWNX","DESC", $theSql);

    #Define the minimum hashing count
    $minHashingCount = 2;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $minHashingCount ]);

    #Define the page sub title
    $pageSubTitle = "Days between first and last hashes, divided by pi";

    #Define the table caption
    $tableCaption = "Minimum hashing count: $minHashingCount";

    #Add the results into the twig template
    return $this->render('career_length_by_day.twig', [
      'pageTitle' => "Average days between hashing",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/lowest/averageDaysBetweenHashes',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function lowestAverageDaysBetweenHashesAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLongestHashingCareerInDays();
    $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HASHES", $theSql);
    $theSql = str_replace("XUPORDOWNX","ASC", $theSql);

    #Define the minimum hashing count
    $minHashingCount = 6;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $minHashingCount ]);

    #Define the page sub title
    $pageSubTitle = "Days between first and last hashes, divided by pi";

    #Define the table caption
    $tableCaption = "Minimum hashing count: $minHashingCount";

    #Add the results into the twig template
    return $this->render('career_length_by_day.twig', [
      'pageTitle' => "Average days between hashing",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/lowest/allharings/averageDaysBetweenHarings',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function lowestAverageDaysBetweenAllHaringsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLowestNumberOfDaysBetweenHarings();
    $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
    $theSql = str_replace("XUPORDOWNX","ASC",$theSql);

    #Define the minimum haring count
    $minHaringCount = 2;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $minHaringCount ]);

    #Define the page sub title
    $pageSubTitle = "Days between first and last harings, divided by pi";

    #Define the table caption
    $tableCaption = "Minimum haring count: $minHaringCount";

    #Add the results into the twig template
    return $this->render('haring_career_length_by_day.twig', [
      'pageTitle' => "Average days between harings",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/highest/allharings/averageDaysBetweenHarings',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function highestAverageDaysBetweenAllHaringsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the sql
    $theSql = $this->sqlQueries->getLowestNumberOfDaysBetweenHarings();
    $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
    $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

    #Define the minimum haring count
    $minHaringCount = 2;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $kennelKy, $kennelKy, $minHaringCount ]);

    #Define the page sub title
    $pageSubTitle = "Days between first and last harings, divided by pi";

    #Define the table caption
    $tableCaption = "Minimum haring count: $minHaringCount";

    #Add the results into the twig template
    return $this->render('haring_career_length_by_day.twig', [
      'pageTitle' => "Average days between harings",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/lowest/{hare_type}/averageDaysBetweenHarings',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function lowestAverageDaysBetweenHaringsAction(int $hare_type, string $kennel_abbreviation) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    #Define the sql
    $theSql = $this->sqlQueries->getLowestNumberOfDaysBetweenHaringsByType();
    $theSql = str_replace("XORDERCOLUMNX", "DAYS_BETWEEN_HARINGS", $theSql);
    $theSql = str_replace("XUPORDOWNX", "ASC", $theSql);

    #Define the minimum haring count
    $minHaringCount = 5;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $hare_type, $kennelKy, $hare_type, $kennelKy,
      $hare_type, $minHaringCount ]);

    #Define the page sub title
    $pageSubTitle = "Days Between First and Last ".$hareTypeName." Harings";

    #Define the table caption
    $tableCaption = "Minimum haring count: $minHaringCount";

    #Add the results into the twig template
    return $this->render('haring_career_length_by_day.twig', [
      'pageTitle' => "Average days between harings",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hare_type_name' => $hareTypeName ]);
  }

  #[Route('/{kennel_abbreviation}/highest/{hare_type}/averageDaysBetweenHarings',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function highestAverageDaysBetweenHaringsAction(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    #Define the sql
    $theSql = $this->sqlQueries->getLowestNumberOfDaysBetweenHaringsByType();
    $theSql = str_replace("XORDERCOLUMNX","DAYS_BETWEEN_HARINGS",$theSql);
    $theSql = str_replace("XUPORDOWNX","DESC",$theSql);

    #Define the minimum haring count
    $minHaringCount = 2;

    #Query the database
    $theResults = $this->fetchAll($theSql, [ $kennelKy, $kennelKy, $kennelKy, $hare_type, $kennelKy, $hare_type, $kennelKy,
      $hare_type, $minHaringCount ]);

    #Define the page sub title
    $pageSubTitle = "Days Between First and Last ".$hareTypeName." Harings";

    #Define the table caption
    $tableCaption = "Minimum haring count: $minHaringCount";

    #Add the results into the twig template
    return $this->render('haring_career_length_by_day.twig', [
      'pageTitle' => "Average days between harings",
      'pageSubTitle' => $pageSubTitle,
      'tableCaption' => $tableCaption,
      'theList' => $theResults,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hare_type_name' => $hareTypeName
    ]);
  }

  #[Route('/{kennel_abbreviation}/attendanceStatistics',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function viewAttendanceChartsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the average and total event attendance per year
    $sqlAvgTotalEvtParticipationByYear = "
      SELECT YEAR(THE_DATE) AS THE_VALUE, SUM(THE_COUNT) AS TOT_COUNT, AVG(THE_COUNT) AS AVG_COUNT
        FROM (SELECT HASHES.HASH_KY AS THE_KEY, HASHES.EVENT_DATE AS THE_DATE, COUNT(*) AS THE_COUNT
                FROM HASHES
                JOIN HASHINGS
                  ON HASHES.HASH_KY = HASHINGS.HASH_KY
               WHERE KENNEL_KY = ?
               GROUP BY HASHES.HASH_KY) TEMPORARY_TABLE
       GROUP BY YEAR(THE_DATE)";
    $avgTotalEvtParticipationByYear = $this->fetchAll($sqlAvgTotalEvtParticipationByYear, [ $kennelKy ]);

    # Obtain the average event attendance per (year/month)
    $sqlAvgEvtParticipationByYearMonth = "
      SELECT DATE_FORMAT(THE_DATE,'%Y/%m') AS THE_VALUE, AVG(THE_COUNT) AS THE_COUNT
        FROM (SELECT HASHES.HASH_KY AS THE_KEY, HASHES.EVENT_DATE AS THE_DATE, COUNT(*) AS THE_COUNT
                FROM HASHES
                JOIN HASHINGS
                  ON HASHES.HASH_KY = HASHINGS.HASH_KY
               WHERE KENNEL_KY = ?
               GROUP BY HASHES.HASH_KY) TEMPORARY_TABLE
       GROUP BY DATE_FORMAT(THE_DATE,'%Y/%m')";
    $avgEvtParticipationByYearMonth = $this->fetchAll($sqlAvgEvtParticipationByYearMonth, [ $kennelKy ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlAvgEvtParticipationByYearQuarter = "
      SELECT CONCAT_WS('/',YEAR(THE_DATE),QUARTER(THE_DATE)) AS THE_VALUE, AVG(THE_COUNT) AS THE_COUNT
        FROM (SELECT HASHES.HASH_KY AS THE_KEY, HASHES.EVENT_DATE AS THE_DATE, COUNT(*) AS THE_COUNT
                FROM HASHES
                JOIN HASHINGS
                  ON HASHES.HASH_KY = HASHINGS.HASH_KY
               WHERE KENNEL_KY = ?
               GROUP BY HASHES.HASH_KY) TEMPORARY_TABLE
       GROUP BY CONCAT_WS('/',YEAR(THE_DATE),QUARTER(THE_DATE))";
    $avgEvtParticipationByYearQuarter = $this->fetchAll($sqlAvgEvtParticipationByYearQuarter, [ $kennelKy ]);

    # Obtain the average event attendance per (year/month)
    $sqlAvgEvtParticipationByMonth = "
      SELECT DATE_FORMAT(THE_DATE,'%m') AS THE_VALUE, AVG(THE_COUNT) AS THE_COUNT
        FROM (SELECT HASHES.HASH_KY AS THE_KEY, HASHES.EVENT_DATE AS THE_DATE, COUNT(*) AS THE_COUNT
                FROM HASHES
                JOIN HASHINGS ON HASHES.HASH_KY = HASHINGS.HASH_KY
               WHERE KENNEL_KY = ?
               GROUP BY HASHES.HASH_KY) TEMPORARY_TABLE
       GROUP BY DATE_FORMAT(THE_DATE,'%m')";
    $avgEvtParticipationByMonth = $this->fetchAll($sqlAvgEvtParticipationByMonth, [ $kennelKy ]);

    # Obtain the total event attendance by hasher
    $sqlTotEvtParticipationByHasher =
      "SELECT *
         FROM (SELECT HASHERS.HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
                 FROM HASHES
                 JOIN HASHINGS
                   ON HASHES.HASH_KY = HASHINGS.HASH_KY
                 JOIN HASHERS
                   ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
                WHERE KENNEL_KY = ?
                GROUP BY HASHERS.HASHER_NAME
                ORDER BY 2 DESC, 1) X
        LIMIT 100";
    $totEvtParticipationByHasher = $this->fetchAll($sqlTotEvtParticipationByHasher, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('event_participation_charts.twig', [
      'pageTitle' => 'Event Participation Statistics',
      'firstHeader' => 'FIRST HEADER',
      'secondHeader' => 'SECOND HEADER',
      'kennel_abbreviation' => $kennel_abbreviation,
      'AvgTotal_Evt_Participation_By_Year_List' => $avgTotalEvtParticipationByYear,
      'Avg_Evt_Participation_By_YearMonth_List' => $avgEvtParticipationByYearMonth,
      'Avg_Evt_Participation_By_YearQuarter_List' => $avgEvtParticipationByYearQuarter,
      'Avg_Evt_Participation_By_Month_List' => $avgEvtParticipationByMonth,
      'Tot_Evt_Participation_By_Hasher_List' => $totEvtParticipationByHasher ]);
  }

  #[Route('/{kennel_abbreviation}/firstTimersStatistics/{min_hash_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'min_hash_count' => '%app.pattern.min_hash_count%']
  )]
  public function viewFirstTimersChartsAction(string $kennel_abbreviation, int $min_hash_count) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the average event attendance per year
    $sqlNewComersByYear = $this->sqlQueries->getNewcomersByYear();
    $newComersByYear = $this->fetchAll($sqlNewComersByYear, [ $kennelKy, $kennelKy, $kennelKy, $min_hash_count ]);

    # Obtain the average event attendance per (year/month)
    $sqlNewComersByYearQuarter = $this->sqlQueries->getNewcomersByYearQuarter();
    $newComersByYearQuarter = $this->fetchAll($sqlNewComersByYearQuarter, [ $kennelKy, $kennelKy, $min_hash_count ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlNewComersByYearMonth = $this->sqlQueries->getNewcomersByYearMonth();
    $newComersByYearMonth = $this->fetchAll($sqlNewComersByYearMonth, [ $kennelKy, (int) $kennelKy, $min_hash_count ]);

    # Obtain the average event attendance per (year/month)
    $sqlNewComersByMonth = $this->sqlQueries->getNewcomersByMonth();
    $newComersByMonth = $this->fetchAll($sqlNewComersByMonth, [ $kennelKy, $kennelKy, $min_hash_count ]);

    # Establish and set the return value
    return $this->render('newcomers_charts.twig', [
      'pageTitle' => 'First Timers / New Comers Statistics',
      'firstHeader' => 'FIRST HEADER',
      'secondHeader' => 'SECOND HEADER',
      'kennel_abbreviation' => $kennel_abbreviation,
      'New_Comers_By_Year_List' => $newComersByYear,
      'New_Comers_By_YearMonth_List' => $newComersByYearMonth,
      'New_Comers_By_YearQuarter_List' => $newComersByYearQuarter,
      'New_Comers_By_Month_List' => $newComersByMonth,
      'Min_Hash_Count' => $min_hash_count ]);
  }

  #[Route('/{kennel_abbreviation}/virginHaringsStatistics/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function virginHaringsChartsAction(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    # Obtain the average event attendance per year
    $sqlByYear = $this->sqlQueries->getVirginHaringsByYear();
    $listByYear = $this->fetchAll($sqlByYear, [ $kennelKy, $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/month)
    $sqlByYearQuarter = $this->sqlQueries->getVirginHaringsByYearQuarter();
    $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, [ $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlByYearMonth = $this->sqlQueries->getVirginHaringsByYearMonth();
    $listByYearMonth = $this->fetchAll($sqlByYearMonth, [ $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/month)
    $sqlByMonth = $this->sqlQueries->getVirginHaringsByMonth();
    $listByMonth = $this->fetchAll($sqlByMonth, [ $kennelKy, $hare_type ]);

    # Establish and set the return value
    return $this->render('generic_charts_template.twig', [
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
      'BY_YEAR_MONTH_TITLE' => 'Virgin Harings Per Year/Month' ]);
  }

  #[Route('/{kennel_abbreviation}/distinctHasherStatistics',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function distinctHasherChartsAction(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the average event attendance per year
    $sqlByYear = $this->sqlQueries->getDistinctHashersByYear();
    $listByYear = $this->fetchAll($sqlByYear, [ $kennelKy ]);

    # Obtain the average event attendance per (year/month)
    $sqlByYearQuarter = $this->sqlQueries->getDistinctHashersByYearQuarter();
    $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, [ $kennelKy ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlByYearMonth = $this->sqlQueries->getDistinctHashersByYearMonth();
    $listByYearMonth = $this->fetchAll($sqlByYearMonth, [ $kennelKy ]);

    # Obtain the average event attendance per (year/month)
    $sqlByMonth = $this->sqlQueries->getDistinctHashersByMonth();
    $listByMonth = $this->fetchAll($sqlByMonth, [ $kennelKy ]);

    # Establish and set the return value
    return $this->render('generic_charts_template.twig', [
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
      'BY_YEAR_MONTH_TITLE' => 'Distinct Hashers Per Year/Month' ]);
  }

  #[Route('/{kennel_abbreviation}/distinctHareStatistics/{hare_type}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'hare_type' => '%app.pattern.hare_type%']
  )]
  public function distinctHaresChartsAction(int $hare_type, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $hareTypeName = $this->getHareTypeName($hare_type);

    # Obtain the average event attendance per year
    $sqlByYear = $this->sqlQueries->getDistinctHaresByYear();
    $listByYear = $this->fetchAll($sqlByYear, [ $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/month)
    $sqlByYearQuarter = $this->sqlQueries->getDistinctHaresByYearQuarter();
    $listByYearQuarter = $this->fetchAll($sqlByYearQuarter, [ $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlByYearMonth = $this->sqlQueries->getDistinctHaresByYearMonth();
    $listByYearMonth = $this->fetchAll($sqlByYearMonth, [ $kennelKy, $hare_type ]);

    # Obtain the average event attendance per (year/month)
    $sqlByMonth = $this->sqlQueries->getDistinctHaresByMonth();
    $listByMonth = $this->fetchAll($sqlByMonth, [ $kennelKy, $hare_type ]);

    # Establish and set the return value
    return $this->render('generic_charts_template.twig', [
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
      'BY_YEAR_MONTH_TITLE' => 'Distinct '.$hareTypeName.' Hares Per Year/Month' ]);
  }

  #[Route('/{kennel_abbreviation}/lastTimersStatistics/{min_hash_count}/{month_count}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'min_hash_count' => '%app.pattern.min_hash_count%',
      'month_count' => '%app.pattern.month_count%']
  )]
  public function viewLastTimersChartsAction(string $kennel_abbreviation, int $min_hash_count, int $month_count) {

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Obtain the average event attendance per year
    $sqlLastComersByYear = $this->sqlQueries->getDepartersByYear();
    $lastComersByYear = $this->fetchAll($sqlLastComersByYear, [ $kennelKy, $kennelKy, $min_hash_count, $month_count ]);

    # Obtain the average event attendance per (year/month)
    $sqlLastComersByYearQuarter = $this->sqlQueries->getDepartersByYearQuarter();
    $lastComersByYearQuarter = $this->fetchAll($sqlLastComersByYearQuarter, [ $kennelKy, $kennelKy, $min_hash_count, $month_count ]);

    # Obtain the average event attendance per (year/quarter)
    $sqlLastComersByYearMonth = $this->sqlQueries->getDepartersByYearMonth();
    $lastComersByYearMonth = $this->fetchAll($sqlLastComersByYearMonth, [ $kennelKy, $kennelKy, $min_hash_count, $month_count ]);

    # Obtain the average event attendance per (year/month)
    $sqlLastComersByMonth = $this->sqlQueries->getDepartersByMonth();
    $lastComersByMonth = $this->fetchAll($sqlLastComersByMonth, [ $kennelKy, $kennelKy, $min_hash_count, $month_count ]);

    # Establish and set the return value
    return $this->render('lastcomers_charts.twig', [
      'pageTitle' => 'Last Comers Statistics',
      'firstHeader' => 'FIRST HEADER',
      'secondHeader' => 'SECOND HEADER',
      'kennel_abbreviation' => $kennel_abbreviation,
      'Last_Comers_By_Year_List' => $lastComersByYear,
      'Last_Comers_By_YearMonth_List' => $lastComersByYearMonth,
      'Last_Comers_By_YearQuarter_List' => $lastComersByYearQuarter,
      'Last_Comers_By_Month_List' => $lastComersByMonth,
      'Min_Hash_Count' => $min_hash_count,
      'Month_Count' => $month_count ]);
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
