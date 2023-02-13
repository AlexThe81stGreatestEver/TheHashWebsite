<?php

namespace App\Controller;

use App\Controller\BaseController;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HashEventController extends BaseController {

  public function __construct(ManagerRegistry $doctrine) {
    parent::__construct($doctrine);
  }

  protected function getHareTypesForHashType(int $kennelKy, int $hashType) {

    #Define the SQL to RuntimeException
    $sql = "SELECT HARE_TYPE, HARE_TYPE_NAME, CHART_COLOR
              FROM HARE_TYPES
              JOIN KENNELS
                ON KENNELS.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
              JOIN HASH_TYPES
                ON HASH_TYPES.HARE_TYPE_MASK & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE
             WHERE KENNELS.KENNEL_KY = ?
               AND HASH_TYPES.HASH_TYPE = ?
             ORDER BY HARE_TYPES.SEQ";

    #Query the database
    $hareTypes = $this->fetchAll($sql, array($kennelKy, $hashType));

    #return the return value
    return $hareTypes;
  }

  protected function getAllHashTypes() {

    #Define the SQL to RuntimeException
    $sql = "SELECT HASH_TYPE, HASH_TYPE_NAME
              FROM HASH_TYPES
             ORDER BY SEQ";

    #Query the database
    $hashTypes = $this->fetchAll($sql);

    #return the return value
    return $hashTypes;
  }

  #[Route('/admin/{hash_id}/duplicateHash',
    methods: ['GET'],
    requirements: [
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function adminDuplicateHash(Request $request, int $hash_id) {

    $sql = "
      SELECT HASHES_TABLE.*, KENNEL_ABBREVIATION, DATE_FORMAT(EVENT_DATE, '%Y-%m-%d') AS THE_EVENT_DATE,
             DATE_FORMAT(EVENT_DATE, '%H:%i:%s') AS THE_EVENT_TIME
        FROM HASHES_TABLE
        JOIN KENNELS
          ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
       WHERE HASH_KY = ?";

    $eventDetails = $this->fetchAssoc($sql, [ $hash_id ]);

    $kennelKy = $eventDetails['KENNEL_KY'];
    $kennel_abbreviation = $eventDetails['KENNEL_ABBREVIATION'];

    return $this->render('duplicate_hash_form_ajax.twig', [
      'pageTitle' => 'Duplicate an Event!',
      'pageHeader' => 'Page Header',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashTypes' => $this->getHashTypes($kennelKy, 0),
      'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey(),
      'eventDetails' => $eventDetails,
      'csrf_token' => $this->getCsrfToken('create_event') ]);
  }

  #[Route('/admin/{kennel_abbreviation}/newhash/ajaxform',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function adminCreateHashAjaxPreAction(Request $request, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $timesQuery = "
      SELECT *
        FROM (SELECT event_time
                FROM (SELECT date_format(EVENT_DATE, '%H:%i:%S') AS event_time, COUNT(*) as counts
                        FROM HASHES_TABLE
                       WHERE KENNEL_KY = ?
                       GROUP BY date_format(EVENT_DATE, '%H:%i:%S')
                     ) AS TIMES_AND_COUNTS
               ORDER BY counts DESC
               LIMIT 3) AS results
       ORDER BY 1";

    $hashEventNumberQuery = "
      SELECT MAX(CAST(KENNEL_EVENT_NUMBER AS UNSIGNED)) AS event_number
        FROM HASHES_TABLE
       WHERE KENNEL_KY = ?
         AND KENNEL_EVENT_NUMBER REGEXP '^[0-9]+$'";

    $times = $this->fetchAll($timesQuery, [ $kennelKy ]);
    $defaultEventNumber = 1 + (int) $this->fetchOne($hashEventNumberQuery, [ $kennelKy ]);

    return $this->render('new_hash_form_ajax.twig', [
      'pageTitle' => 'Create an Event!',
      'times' => $times,
      'defaultEventNumber' => $defaultEventNumber,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashTypes' => $this->getHashTypes($kennelKy, 0),
      'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey(),
      'csrf_token' => $this->getCsrfToken('create_event') ]);
  }

  #[Route('/admin/{kennel_abbreviation}/newhash/ajaxform',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function adminCreateHashAjaxPostAction(Request $request, $kennel_abbreviation) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('create_event', $token);

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $theHashEventNumber = trim(strip_tags($_POST['hashEventNumber']));
    $theHashEventDescription = trim(strip_tags($_POST['hashEventDescription']));
    $theHashType = trim(strip_tags($_POST['hashType']));
    $theEventDate = trim(strip_tags($_POST['eventDate']));
    $theEventTime = trim(strip_tags($_POST['eventTime']));
    $theEventDateAndTime = $theEventDate." ".$theEventTime;
    $theLocationDescription = trim(strip_tags($_POST['locationDescription']));
    $theStreet_number = trim(strip_tags($_POST['street_number']));
    $theRoute = trim(strip_tags($_POST['route']));
    $theLocality = trim(strip_tags($_POST['locality']));
    $theAdministrative_area_level_1 = trim(strip_tags($_POST['administrative_area_level_1']));
    $theAdministrative_area_level_2 = trim(strip_tags($_POST['administrative_area_level_2']));
    $thePostal_code = trim(strip_tags($_POST['postal_code']));
    $theNeighborhood = trim(strip_tags($_POST['neighborhood']));
    $theCountry = trim(strip_tags($_POST['country']));
    $theLat = trim(strip_tags($_POST['lat']));
    $theLng = trim(strip_tags($_POST['lng']));
    $theFormatted_address = trim(strip_tags($_POST['formatted_address']));
    $thePlace_id = trim(strip_tags($_POST['place_id']));

    $theEventToCopy = array_key_exists('eventToCopy', $_POST) ? trim(strip_tags($_POST['eventToCopy'])) : null;

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if(!(is_numeric($theLat)||empty($theLat))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the lat";
    }

    if(!(is_numeric($theLng)||empty($theLng))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the lng";
    }

    if(!(is_numeric($thePostal_code)||empty($thePostal_code))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the postal code";
    }

    if(!is_numeric($theLat)) {
      $theLat = NULL;
    }

    if(!is_numeric($theLng)) {
      $theLng = NULL;
    }

    // Ensure the following is a date
    // $theEventDate
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$theEventDate)) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the event date";
    }

    // Ensure the following is a time
    // $theEventTime
    if (!preg_match("/^([01]\d|2[0-3]):([0-5][0-9]):([0-5][0-9])$/",$theEventTime)) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the event time";
    }

    if($passedValidation) {

      $sql = "
        INSERT INTO HASHES_TABLE(KENNEL_KY, KENNEL_EVENT_NUMBER, EVENT_DATE, EVENT_LOCATION, EVENT_CITY, EVENT_STATE,
                    SPECIAL_EVENT_DESCRIPTION, HASH_TYPE, STREET_NUMBER, ROUTE, COUNTY, POSTAL_CODE, NEIGHBORHOOD, COUNTRY,
                    FORMATTED_ADDRESS, PLACE_ID, LAT, LNG)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $this->getWriteConnection()->executeUpdate($sql, [ $kennelKy, $theHashEventNumber, $theEventDateAndTime,
          $theLocationDescription, $theLocality, $theAdministrative_area_level_1, $theHashEventDescription, $theHashType,
          $theStreet_number, $theRoute, $theAdministrative_area_level_2, $thePostal_code, $theNeighborhood, $theCountry,
          $theFormatted_address, $thePlace_id, $theLat, $theLng ]);

      if($theEventToCopy != null) {

        // Get the hash key for the event that was just created
        $hashKy = $this->getWriteConnection()->lastInsertId();

        $sql = "
          INSERT INTO HASHINGS(HASH_KY, HASHER_KY)
          SELECT ?, HASHER_KY
            FROM HASHINGS
           WHERE HASH_KY = ?";

        $this->getWriteConnection()->executeUpdate($sql, [ $hashKy, $theEventToCopy ]);

        $sql = "
          INSERT INTO HARINGS(HARINGS_HASH_KY, HARINGS_HASHER_KY, HARE_TYPE)
          SELECT ?, HARINGS_HASHER_KY, HARE_TYPE
            FROM HARINGS
           WHERE HARINGS_HASH_KY = ?";

        $this->getWriteConnection()->executeUpdate($sql, [ $hashKy, (int)$theEventToCopy ]);

        $auditAddl = " from event key ".$theEventToCopy;
      } else {
        $auditAddl = "";
      }

      #Audit this activity
      $actionType = "Event Creation (Ajax)";
      $actionDescription = "Created event ($kennel_abbreviation # $theHashEventNumber)".$auditAddl;
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/edithash/ajaxform/{hash_id}',
    methods: ['GET'],
    requirements: [
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function adminModifyHashAjaxPreAction(int $hash_id) {

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *, date_format(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE,
             date_format(event_date, '%k:%i:%S') AS EVENT_DATE_TIME
        FROM HASHES_TABLE
        JOIN HASH_TYPES
          ON HASHES_TABLE.HASH_TYPE = HASH_TYPES.HASH_TYPE
        JOIN KENNELS
          ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
       WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $hashValue = $this->fetchAssoc($sql, [ $hash_id ]);

    return $this->render('edit_hash_form_ajax.twig', [
      'pageTitle' => 'Modify an Event!',
      'pageHeader' => 'Page Header',
      'hashTypes' => $this->getHashTypes($hashValue['KENNEL_KY'], 0),
      'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey(),
      'hashValue' => $hashValue,
      'hashKey' => $hash_id,
      'csrf_token' => $this->getCsrfToken('modify_event'.$hash_id) ]);
  }

  #[Route('/admin/edithash/ajaxform/{hash_id}',
    methods: ['POST'],
    requirements: [
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function adminModifyHashAjaxPostAction(Request $request, int $hash_id) {
    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('modify_event'.$hash_id, $token);

    $theHashEventNumber = trim(strip_tags($_POST['hashEventNumber']));
    $theHashEventDescription = trim(strip_tags($_POST['hashEventDescription']));
    $theHashType= trim(strip_tags($_POST['hashType']));
    $theEventDate= trim(strip_tags($_POST['eventDate']));
    $theEventTime= trim(strip_tags($_POST['eventTime']));
    $theEventDateAndTime = $theEventDate." ".$theEventTime;
    $theLocationDescription= trim(strip_tags($_POST['locationDescription']));
    $theStreet_number= trim(strip_tags($_POST['street_number']));
    $theRoute= trim(strip_tags($_POST['route']));
    $theLocality= trim(strip_tags($_POST['locality']));
    $theAdministrative_area_level_1= trim(strip_tags($_POST['administrative_area_level_1']));
    $theAdministrative_area_level_2= trim(strip_tags($_POST['administrative_area_level_2']));
    $thePostal_code= trim(strip_tags($_POST['postal_code']));
    $theNeighborhood= trim(strip_tags($_POST['neighborhood']));
    $theCountry= trim(strip_tags($_POST['country']));
    $theLat= trim(strip_tags($_POST['lat']));
    $theLng= trim(strip_tags($_POST['lng']));
    $theFormatted_address= trim(strip_tags($_POST['formatted_address']));
    $thePlace_id= trim(strip_tags($_POST['place_id']));

    // Establish a "passed validation" variable
    $passedValidation = TRUE;

    // Establish the return message value as empty (at first)
    $returnMessage = "";

    if(!(is_numeric($theLat) || empty($theLat))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the lat";
    }

    if(!(is_numeric($theLng)||empty($theLng))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the lng";
    }

    if(!(is_numeric($thePostal_code)||empty($thePostal_code))) {
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the postal code";
    }

    if(!is_numeric($theLat)) {
      $theLat = NULL;
    }

    if(!is_numeric($theLng)) {
      $theLng = NULL;
    }

    // Ensure the following is a date
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$theEventDate)){
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the event date";
    }

    // Ensure the following is a time
    if (!preg_match("/^([01]\d|2[0-3]):([0-5][0-9]):([0-5][0-9])$/",$theEventTime)){
      $passedValidation = FALSE;
      $returnMessage .= " |Failed validation on the event time";
    }

    if($passedValidation) {

      $sql = "
        UPDATE HASHES_TABLE
           SET KENNEL_EVENT_NUMBER = ?,
               EVENT_DATE = ?,
               EVENT_LOCATION = ?,
               EVENT_CITY = ?,
               EVENT_STATE = ?,
               SPECIAL_EVENT_DESCRIPTION = ?,
               HASH_TYPE = ?,
               STREET_NUMBER = ?,
               ROUTE = ?,
               COUNTY = ?,
               POSTAL_CODE = ?,
               NEIGHBORHOOD = ?,
               COUNTRY = ?,
               FORMATTED_ADDRESS = ?,
               PLACE_ID = ?,
               LAT = ?,
               LNG = ?
         WHERE HASH_KY = ?";

        $this->getWriteConnection()->executeUpdate($sql, [
          $theHashEventNumber, $theEventDateAndTime, $theLocationDescription, $theLocality, $theAdministrative_area_level_1,
          $theHashEventDescription, $theHashType, $theStreet_number, $theRoute, $theAdministrative_area_level_2, $thePostal_code,
          $theNeighborhood, $theCountry, $theFormatted_address, $thePlace_id, $theLat, $theLng, $hash_id ]);

        # Declare the SQL used to retrieve this information
        $sqlOriginal = "
          SELECT * 
            FROM HASHES_TABLE 
            JOIN KENNELS 
              ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
           WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($sqlOriginal, [ $hash_id ]);

      #Audit this activity
      $tempEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
      $actionType = "Event Modification (Ajax)";
      $tempKennelAbbreviation2 = $hashValue['KENNEL_ABBREVIATION'];
      $actionDescription = "Modified event ($tempKennelAbbreviation2 # $tempEventNumber)";
      $this->auditTheThings($request, $actionType, $actionDescription);

      // Establish the return value message
      $returnMessage = "Success! Great, it worked";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/hash/manageparticipation2/{hash_id}',
    methods: ['GET'],
    requirements: [
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function hashParticipationJsonPreAction(Request $request, int $hash_id) {
    #Define the SQL to execute
    $hasherListSQL = "
      SELECT *
        FROM HASHINGS
        JOIN HASHERS
          ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
       WHERE HASHINGS.HASH_KY = ? ";

    $hareListSQL = "
      SELECT *
        FROM HARINGS
        JOIN HASHERS
          ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
       WHERE HARINGS.HARINGS_HASH_KY = ?";

    #Obtain hash event information
    $hashEventInfoSQL = "
      SELECT *, EVENT_DATE < NOW() AS SHOW_EVENT_LINK
        FROM HASHES_TABLE
        JOIN KENNELS
          ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
       WHERE HASH_KY = ?";

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($hasherListSQL, [ $hash_id ]);
    $hareList = $this->fetchAll($hareListSQL, [ $hash_id ]);
    $hashEvent = $this->fetchAssoc($hashEventInfoSQL, [ $hash_id ]);

    $kennelAbbreviation = $hashEvent['KENNEL_ABBREVIATION'];
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennelAbbreviation);
    $kennelEventNumber = $hashEvent['KENNEL_EVENT_NUMBER'];
    $eventDate = $hashEvent['EVENT_DATE'];
    $pageTitle = "Participation: $kennelAbbreviation # $kennelEventNumber ($eventDate)";

    #Establish the return value
    return $this->render('event_participation_json.twig', [
      'pageTitle' => $pageTitle,
      'pageSubTitle' => 'Not Sure',
      'pageHeader' => 'Why is this so complicated ?',
      'hasherList' => $hasherList,
      'hareList' => $hareList,
      'hareTypes' => $this->getHareTypesForHashType($kennelKy, $hashEvent['HASH_TYPE']),
      'hash_key'=> $hash_id,
      'kennel_abbreviation' => $kennelAbbreviation,
      'kennel_event_number' => $kennelEventNumber,
      'show_event_link' => $hashEvent['SHOW_EVENT_LINK'],
      'csrf_token' => $this->getCsrfToken('participation'.$hash_id) ]);
  }

  #[Route('/admin/hash/addHasherToHash',
    methods: ['POST']
  )]
  public function addHashParticipant(Request $request) {

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];
    $hashKey = $_POST['hash_key'];

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('participation'.$hashKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey) && ctype_digit($hashKey)){

      #Determine the hasher identity
      $hasherIdentitySql =
         "SELECT HASHER_NAME, BANNED FROM HASHERS WHERE HASHERS.HASHER_KY = ?";

      # Make a database call to obtain the hasher information
      $data = $this->fetchAssoc($hasherIdentitySql, [ $hasherKey ]);

      #Obtain the hasher name from the object
      $tempHasherName = $data['HASHER_NAME'];

      #Ensure the entry does not already exist
      $existsSql = "
        SELECT 1 AS IGNORED
          FROM HASHINGS
         WHERE HASHINGS.HASHER_KY = ?
           AND HASH_KY = ?";

      #Retrieve the existing record
      $hasherToAdd = $this->fetchAll($existsSql, [ $hasherKey, $hashKey ]);

      if($data['BANNED'] == 1) {

        $returnMessage = "Fail! $tempHasherName is banned.";

      } else if(count($hasherToAdd) < 1){

        #Define the sql insert statement
        $sql = "INSERT INTO HASHINGS (HASHER_KY, HASH_KY) VALUES (?, ?);";

        #Execute the sql insert statement
        $this->getWriteConnection()->executeUpdate($sql, [ $hasherKey,$hashKey ]);

        #Audit the activity

        # Declare the SQL used to retrieve this information
        $sql = "
          SELECT KENNEL_EVENT_NUMBER, KENNEL_ABBREVIATION
            FROM HASHES_TABLE
            JOIN KENNELS
              ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
           WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($sql, [ $hashKey ]);
        $tempKennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $tempKennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];

        $tempActionType = "Add Hound to Hash";
        $tempActionDescription = "Added $tempHasherName to $tempKennelAbbreviation # $tempKennelEventNumber";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        #Set the return message
        $returnMessage = "Success! $tempHasherName has been added as a hound.";
      } else {

        #Set the return message
        $returnMessage = "$tempHasherName has already added as a hound.";
      }

    } else{
      $returnMessage = "Something is wrong with the input.$hasherKey and $hashKey";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/hash/addHareToHash',
    methods: ['POST']
  )]
  public function addHashOrganizer(Request $request) {

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];
    $hashKey = $_POST['hash_key'];
    $hareType = $_POST['hare_type'];
    $token = $_POST['csrf_token'];

    $this->validateCsrfToken('participation'.$hashKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey)  && ctype_digit($hashKey) && ctype_digit($hareType)) {

      #Determine the hasher identity
      $hasherIdentitySql = "SELECT HASHER_NAME, BANNED FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

      # Make a database call to obtain the hasher information
      $data = $this->fetchAssoc($hasherIdentitySql, [ $hasherKey ]);

      #Obtain the hasher name from the object
      $tempHasherName = $data['HASHER_NAME'];

      #Ensure the entry does not already exist
      $existsSql = "
        SELECT 1 AS IGNORED
          FROM HARINGS
         WHERE HARINGS.HARINGS_HASHER_KY = ?
           AND HARINGS.HARINGS_HASH_KY = ?
           AND HARINGS.HARE_TYPE = ?;";

      #Retrieve the existing record
      $hareToAdd = $this->fetchAll($existsSql, [ $hasherKey, $hashKey, $hareType ]);

      if($data['BANNED'] == 1) {

        $returnMessage = "Fail! $tempHasherName is banned.";

      } else if(count($hareToAdd) < 1){

        #Define the sql insert statement
        $sql = "INSERT INTO HARINGS (HARINGS_HASHER_KY, HARINGS_HASH_KY, HARE_TYPE) VALUES (?, ?, ?);";

        #Execute the sql insert statement
        $this->getWriteConnection()->executeUpdate($sql, [ $hasherKey, $hashKey, $hareType ]);

        #Add the audit statement
        # Declare the SQL used to retrieve this information
        $sql = "
          SELECT KENNEL_EVENT_NUMBER, KENNEL_ABBREVIATION
            FROM HASHES_TABLE
            JOIN KENNELS
              ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
           WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($sql, [ $hashKey ]);
        $tempKennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $tempKennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];

        $tempActionType = "Add Hare to Hash";
        $tempActionDescription = "Added $tempHasherName to $tempKennelAbbreviation # $tempKennelEventNumber";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        $returnMessage = "Success! $tempHasherName has been added as a hare.";

      } else {
        $returnMessage = "$tempHasherName has already added as a hare.";
      }

    } else{
      $returnMessage = "Something is wrong with the input.$hasherKey and $hashKey";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/hash/deleteHasherFromHash',
    methods: ['POST']
  )]
  #Delete a participant from a hash
  public function deleteHashParticipant(Request $request) {

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];
    $hashKey = $_POST['hash_key'];

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('participation'.$hashKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey) && ctype_digit($hashKey)){

      #Check if this exists
      $existsSql = "
        SELECT HASHER_NAME
          FROM HASHINGS
          JOIN HASHERS
            ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
         WHERE HASHERS.HASHER_KY = ? AND HASH_KY = ?;";

      #Retrieve the existing record
      $hasherToDelete = $this->fetchAll($existsSql, [ $hasherKey, $hashKey ]);
      if(count($hasherToDelete) > 0){

        #Obtain the name of the person being deleted
        $tempHasherName = $hasherToDelete[0];
        $tempHasherName = $tempHasherName['HASHER_NAME'];
        $returnMessage = "Success! Removed $tempHasherName as hasher at this event.";

        #Define the sql insert statement
        $sql = "DELETE FROM HASHINGS WHERE HASHER_KY = ? AND HASH_KY = ?;";

        #Execute the sql insert statement
        $this->getWriteConnection()->executeUpdate($sql, [ $hasherKey,$hashKey ]);

        #Add the audit statement
        # Declare the SQL used to retrieve this information
        $sql = "
          SELECT *
            FROM HASHES_TABLE
            JOIN KENNELS
              ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
           WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($sql, [ $hashKey ]);
        $tempKennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $tempKennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];

        $tempActionType = "Delete Hound From Event";
        $tempActionDescription = "Deleted $tempHasherName from $tempKennelAbbreviation # $tempKennelEventNumber";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      }  else{
        $returnMessage = "Record cannot be deleted; doesn't exist!";
      }
    } else{
      $returnMessage = "Something is wrong with the input.$hasherKey and $hashKey";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/hash/deleteHareFromHash',
    methods: ['POST']
  )]
  public function deleteHashOrganizer(Request $request) {

    #Obtain the post values
    $hasherKey = $_POST['hasher_key'];
    $hashKey = $_POST['hash_key'];

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('participation'.$hashKey, $token);

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey) && ctype_digit($hashKey)) {

      #Check if this exists
      $existsSql = "
        SELECT HASHER_NAME
          FROM HARINGS
          JOIN HASHERS
            ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
         WHERE HARINGS.HARINGS_HASHER_KY = ?
           AND HARINGS.HARINGS_HASH_KY = ?;";

      #Retrieve the existing record
      $hareToDelete = $this->fetchAll($existsSql, [ $hasherKey, $hashKey ]);
      if(count($hareToDelete) > 0){

        #Obtain the name of the person being deleted
        $tempHasherName = $hareToDelete[0];
        $tempHasherName = $tempHasherName['HASHER_NAME'];
        $returnMessage = "Success! Removed $tempHasherName as hare from this event.";

        #Define the sql insert statement
        $sql = "DELETE FROM HARINGS WHERE HARINGS_HASHER_KY = ? AND HARINGS_HASH_KY = ?;";

        #Execute the sql insert statement
        $this->getWriteConnection()->executeUpdate($sql, [ $hasherKey,$hashKey ]);

        #Add the audit statement
        # Declare the SQL used to retrieve this information
        $sql = "
          SELECT *
            FROM HASHES_TABLE
            JOIN KENNELS
              ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
           WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($sql, [ $hashKey ]);
        $tempKennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $tempKennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];

        $tempActionType = "Delete Hare From Event";
        $tempActionDescription = "Deleted $tempHasherName from $tempKennelAbbreviation # $tempKennelEventNumber";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      }  else{
        $returnMessage = "Record cannot be deleted; doesn't exist!";
      }
    } else{
      $returnMessage = "Something is wrong with the input.$hasherKey and $hashKey";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/hash/getHaresForEvent',
    methods: ['POST']
  )]
  #Obtain hashers for an event
  public function getHaresForEvent() {

    #Obtain the post values
    $hashKey = $_POST['hash_key'];

    #Define the SQL to execute
    $hareListSQL = "
      SELECT HASHER_KY, HASHER_NAME, (
             SELECT GROUP_CONCAT(HARE_TYPE_NAME)
               FROM HARE_TYPES
              WHERE HARINGS.HARE_TYPE & HARE_TYPES.HARE_TYPE = HARE_TYPES.HARE_TYPE) AS HARE_TYPE_NAMES
        FROM HARINGS
        JOIN HASHERS
          ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
       WHERE HARINGS.HARINGS_HASH_KY = ? ";

    #Obtain the hare list
    $hareList = $this->fetchAll($hareListSQL, [ $hashKey ]);

    return new JsonResponse($hareList);
  }

  #[Route('/admin/hash/getHashersForEvent',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  #Obtain hashers for an event
  public function getHashersForEvent() {

    #Obtain the post values
    $hashKey = $_POST['hash_key'];

    #Define the SQL to execute
    $hareListSQL = "
      SELECT HASHERS.HASHER_KY AS HASHER_KY, HASHERS.HASHER_NAME AS HASHER_NAME
        FROM HASHINGS
        JOIN HASHERS
          ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
       WHERE HASHINGS.HASH_KY = ? ";

    #Obtain the hare list
    $hareList = $this->fetchAll($hareListSQL, [ $hashKey ]);

    return new JsonResponse($hareList);
  }

  #[Route('/{kennel_abbreviation}/listhashes2',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function listHashesPreActionJson(string $kennel_abbreviation) {

    return $this->render('hash_list_json.twig', [
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => '',
      'kennel_abbreviation' => $kennel_abbreviation
    ]);
  }

  #[Route('/{kennel_abbreviation}/listhashes2',
    methods: ['POST'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%']
  )]
  public function listHashesPostActionJson(string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
    $inputColumns = $_POST['columns'];
    $inputSearch = $_POST['search'];
    $inputSearchValue = $inputSearch['value'];

    #-------------- Begin: Validate the post parameters ------------------------
    if(!is_numeric($inputStart)){
      $inputStart = 0;
    }

    if(!is_numeric($inputLength)){
      $inputStart = "0";
      $inputLength = "50";
    } else if($inputLength == "-1"){
      $inputStart = "0";
      $inputLength = "1000000000";
    }

    #---------------- End: Validate the post parameters ------------------------

    #-------------- Begin: Modify the input parameters  ------------------------
    $inputSearchValueModified = "%$inputSearchValue%";

    $inputOrderRaw = isset($_POST['order']) ? $_POST['order'] : null;
    $inputOrderColumnExtracted = "13";
    $inputOrderColumnIncremented = "13";
    $inputOrderDirectionExtracted = "desc";

    if(!is_null($inputOrderRaw)) {
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------

    #-------------- Begin: Define the SQL used here   --------------------------

    $sql = "
      SELECT KENNEL_EVENT_NUMBER,
             (SELECT COUNT(*) FROM HASHINGS WHERE HASHINGS.HASH_KY = HASHES.HASH_KY) AS HOUND_COUNT,
             (SELECT COUNT(*) FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY) AS HARE_COUNT,
             EVENT_LOCATION, SPECIAL_EVENT_DESCRIPTION, EVENT_DATE, EVENT_CITY, EVENT_STATE, FORMATTED_ADDRESS,
             HASH_KY, KENNEL_KY,
             DATE_FORMAT(event_date,'%Y/%m/%d') AS EVENT_DATE_FORMATTED,
             DATE_FORMAT(event_date,'%Y/%m/%d %h:%i %p') AS EVENT_DATE_FORMATTED2,
             HASH_TYPE_NAME
        FROM HASHES
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
       WHERE KENNEL_KY = ?
         AND (KENNEL_EVENT_NUMBER LIKE ? OR
              EVENT_LOCATION LIKE ? OR
              SPECIAL_EVENT_DESCRIPTION LIKE ? OR
              EVENT_CITY LIKE ? OR
              EVENT_STATE LIKE ?)
       ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
       LIMIT $inputStart,$inputLength";


    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "
      SELECT COUNT(*) AS THE_COUNT
        FROM HASHES
       WHERE KENNEL_KY = ?
         AND (KENNEL_EVENT_NUMBER LIKE ? OR
              EVENT_LOCATION LIKE ? OR
              SPECIAL_EVENT_DESCRIPTION LIKE ? OR
              EVENT_CITY LIKE ? OR
              EVENT_STATE LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES WHERE KENNEL_KY = ?";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $this->fetchAll($sql,array($kennelKy, $inputSearchValueModified, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified));

    #Perform the unfiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount, [$kennelKy]))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount, [$kennelKy, $inputSearchValueModified,
      $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified, $inputSearchValueModified]))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    return new JsonResponse([
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    ]);
  }
}
