<?php

namespace HASH\Controller;

require_once "BaseController.php";

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class HashEventController extends BaseController {

  public function __construct(Application $app) {
    parent::__construct($app);
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

  public function adminDuplicateHash(Request $request, int $hash_id) {

    $sql = "SELECT HASHES_TABLE.*, KENNEL_ABBREVIATION,
                   DATE_FORMAT(EVENT_DATE, '%Y-%m-%d') AS THE_EVENT_DATE,
                   DATE_FORMAT(EVENT_DATE, '%H:%i:%s') AS THE_EVENT_TIME
              FROM HASHES_TABLE
              JOIN KENNELS
                ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
             WHERE HASH_KY = ?";

    $eventDetails = $this->fetchAssoc($sql, array($hash_id));

    $kennelKy = (int) $eventDetails['KENNEL_KY'];
    $kennel_abbreviation = $eventDetails['KENNEL_ABBREVIATION'];

    $returnValue = $this->render('duplicate_hash_form_ajax.twig', array(
      'pageTitle' => 'Duplicate an Event!',
      'pageHeader' => 'Page Header',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashTypes' => $this->getHashTypes($kennelKy, 0),
      'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey(),
      'eventDetails' => $eventDetails
    ));

    #Return the return value
    return $returnValue;
  }

  #Define action
  public function adminCreateHashAjaxPreAction(Request $request, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $returnValue = $this->render('new_hash_form_ajax.twig', array(
      'pageTitle' => 'Create an Event!',
      'pageHeader' => 'Page Header',
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashTypes' => $this->getHashTypes($kennelKy, 0),
      'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey()
    ));

    #Return the return value
    return $returnValue;
  }

    public function adminCreateHashAjaxPostAction(Request $request, $kennel_abbreviation) {

      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      $theHashEventNumber = trim(strip_tags($request->request->get('hashEventNumber')));
      $theHashEventDescription = trim(strip_tags($request->request->get('hashEventDescription')));
      $theHashType= trim(strip_tags($request->request->get('hashType')));
      $theEventDate= trim(strip_tags($request->request->get('eventDate')));
      $theEventTime= trim(strip_tags($request->request->get('eventTime')));
      $theEventDateAndTime = $theEventDate." ".$theEventTime;
      $theLocationDescription= trim(strip_tags($request->request->get('locationDescription')));
      $theStreet_number= trim(strip_tags($request->request->get('street_number')));
      $theRoute= trim(strip_tags($request->request->get('route')));
      $theLocality= trim(strip_tags($request->request->get('locality')));
      $theAdministrative_area_level_1= trim(strip_tags($request->request->get('administrative_area_level_1')));
      $theAdministrative_area_level_2= trim(strip_tags($request->request->get('administrative_area_level_2')));
      $thePostal_code= trim(strip_tags($request->request->get('postal_code')));
      $theNeighborhood= trim(strip_tags($request->request->get('neighborhood')));
      $theCountry= trim(strip_tags($request->request->get('country')));
      $theLat= trim(strip_tags($request->request->get('lat')));
      $theLng= trim(strip_tags($request->request->get('lng')));
      $theFormatted_address= trim(strip_tags($request->request->get('formatted_address')));
      $thePlace_id= trim(strip_tags($request->request->get('place_id')));

      $theEventToCopy= trim(strip_tags($request->request->get('eventToCopy')));

      // Establish a "passed validation" variable
      $passedValidation = TRUE;

      // Establish the return message value as empty (at first)
      $returnMessage = "";

      if(!(is_numeric($theLat)||empty($theLat))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the lat";
        //$this->app['monolog']->addDebug("--- theLat failed validation: $theLat");
      }

      if(!(is_numeric($theLng)||empty($theLng))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the lng";
        //$this->app['monolog']->addDebug("--- theLng failed validation: $theLng");
      }

      if(!(is_numeric($thePostal_code)||empty($thePostal_code))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the postal code";
        //$this->app['monolog']->addDebug("--- thePostal_code failed validation: $thePostal_code");
      }

      if(!is_numeric($theLat)){
        $theLat = NULL;
      }

      if(!is_numeric($theLng)){
        $theLng = NULL;
      }

      // Ensure the following is a date
      // $theEventDate
      if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$theEventDate)){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the event date";
        //$this->app['monolog']->addDebug("--- the date failed validation $theEventDate");
      }


      // Ensure the following is a time
      // $theEventTime
      if (!preg_match("/^([01]\d|2[0-3]):([0-5][0-9]):([0-5][0-9])$/",$theEventTime)){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the event time";
        //$this->app['monolog']->addDebug("--- the time failed validation $theEventTime");

      }


      if($passedValidation){

        $sql = "
          INSERT INTO HASHES_TABLE (
            KENNEL_KY,
            KENNEL_EVENT_NUMBER,
            EVENT_DATE,
            EVENT_LOCATION,
            EVENT_CITY,
            EVENT_STATE,
            SPECIAL_EVENT_DESCRIPTION,
            HASH_TYPE,
            STREET_NUMBER,
            ROUTE,
            COUNTY,
            POSTAL_CODE,
            NEIGHBORHOOD,
            COUNTRY,
            FORMATTED_ADDRESS,
            PLACE_ID,
            LAT,
            LNG
          ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

          $this->app['dbs']['mysql_write']->executeUpdate($sql,array(
            $kennelKy,
            $theHashEventNumber,
            $theEventDateAndTime,
            $theLocationDescription,
            $theLocality,
            $theAdministrative_area_level_1,
            $theHashEventDescription,
            $theHashType,
            $theStreet_number,
            $theRoute,
            $theAdministrative_area_level_2,
            $thePostal_code,
            $theNeighborhood,
            $theCountry,
            $theFormatted_address,
            $thePlace_id,
            $theLat,
            $theLng
          ));

        if($theEventToCopy != null) {

          // Get the hash key for the event that was just created
          $hashKy = $this->app['dbs']['mysql_write']->lastInsertId();

          $sql = "INSERT INTO HASHINGS(HASH_KY, HASHER_KY)
                  SELECT ?, HASHER_KY
                    FROM HASHINGS
                   WHERE HASH_KY = ?";

          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hashKy, (int)$theEventToCopy));

          $sql = "INSERT INTO HARINGS(HARINGS_HASH_KY, HARINGS_HASHER_KY, HARE_TYPE)
                  SELECT ?, HARINGS_HASHER_KY, HARE_TYPE
                    FROM HARINGS
                   WHERE HARINGS_HASH_KY = ?";

          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hashKy, (int)$theEventToCopy));

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

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;
    }

    #Define action
    public function adminModifyHashAjaxPreAction(Request $request, int $hash_id){

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
      $hashValue = $this->fetchAssoc($sql, array((int) $hash_id));

      $returnValue = $this->render('edit_hash_form_ajax.twig', array(
        'pageTitle' => 'Modify an Event!',
        'pageHeader' => 'Page Header',
        'hashTypes' => $this->getHashTypes($hashValue['KENNEL_KY'], 0),
        'geocode_api_value' => $this->getGooglePlacesApiWebServiceKey(),
        'hashValue' => $hashValue,
        'hashKey' => $hash_id
      ));

      #Return the return value
      return $returnValue;

    }


    public function adminModifyHashAjaxPostAction(Request $request, int $hash_id){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      $theHashEventNumber = trim(strip_tags($request->request->get('hashEventNumber')));
      $theHashEventDescription = trim(strip_tags($request->request->get('hashEventDescription')));
      $theHashType= trim(strip_tags($request->request->get('hashType')));
      $theEventDate= trim(strip_tags($request->request->get('eventDate')));
      $theEventTime= trim(strip_tags($request->request->get('eventTime')));
      $theEventDateAndTime = $theEventDate." ".$theEventTime;
      $theLocationDescription= trim(strip_tags($request->request->get('locationDescription')));
      $theStreet_number= trim(strip_tags($request->request->get('street_number')));
      $theRoute= trim(strip_tags($request->request->get('route')));
      $theLocality= trim(strip_tags($request->request->get('locality')));
      $theAdministrative_area_level_1= trim(strip_tags($request->request->get('administrative_area_level_1')));
      $theAdministrative_area_level_2= trim(strip_tags($request->request->get('administrative_area_level_2')));
      $thePostal_code= trim(strip_tags($request->request->get('postal_code')));
      $theNeighborhood= trim(strip_tags($request->request->get('neighborhood')));
      $theCountry= trim(strip_tags($request->request->get('country')));
      $theLat= trim(strip_tags($request->request->get('lat')));
      $theLng= trim(strip_tags($request->request->get('lng')));
      $theFormatted_address= trim(strip_tags($request->request->get('formatted_address')));
      $thePlace_id= trim(strip_tags($request->request->get('place_id')));
      //$this->app['monolog']->addDebug("--- thePlace_id: $thePlace_id");

      // Establish a "passed validation" variable
      $passedValidation = TRUE;

      // Establish the return message value as empty (at first)
      $returnMessage = "";

      if(!(is_numeric($theLat)||empty($theLat))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the lat";
        //$this->app['monolog']->addDebug("--- theLat failed validation: $theLat");
      }

      if(!(is_numeric($theLng)||empty($theLng))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the lng";
        //$this->app['monolog']->addDebug("--- theLng failed validation: $theLng");
      }

      if(!(is_numeric($thePostal_code)||empty($thePostal_code))){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the postal code";
        //$this->app['monolog']->addDebug("--- thePostal_code failed validation: $thePostal_code");
      }

      if(!is_numeric($theLat)){
        $theLat = NULL;
      }

      if(!is_numeric($theLng)){
        $theLng = NULL;
      }

      // Ensure the following is a date
      // $theEventDate
      if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$theEventDate)){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the event date";
        //$this->app['monolog']->addDebug("--- the date failed validation $theEventDate");
      }


      // Ensure the following is a time
      // $theEventTime
      if (!preg_match("/^([01]\d|2[0-3]):([0-5][0-9]):([0-5][0-9])$/",$theEventTime)){
        $passedValidation = FALSE;
        $returnMessage .= " |Failed validation on the event time";
        //$this->app['monolog']->addDebug("--- the time failed validation $theEventTime");

      }


      if($passedValidation){

        $sql = "
          UPDATE HASHES_TABLE
            SET
              KENNEL_EVENT_NUMBER = ?,
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

          $this->app['dbs']['mysql_write']->executeUpdate($sql,array(
            $theHashEventNumber,
            $theEventDateAndTime,
            $theLocationDescription,
            $theLocality,
            $theAdministrative_area_level_1,
            $theHashEventDescription,
            $theHashType,
            $theStreet_number,
            $theRoute,
            $theAdministrative_area_level_2,
            $thePostal_code,
            $theNeighborhood,
            $theCountry,
            $theFormatted_address,
            $thePlace_id,
            $theLat,
            $theLng,
            $hash_id
          ));

          # Declare the SQL used to retrieve this information
          $sqlOriginal = "SELECT * FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

          # Make a database call to obtain the hasher information
          $hashValue = $this->fetchAssoc($sqlOriginal, array((int) $hash_id));

        #Audit this activity
        $tempEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $actionType = "Event Modification (Ajax)";
        $tempKennelAbbreviation2 = $hashValue['KENNEL_ABBREVIATION'];
        $actionDescription = "Modified event ($tempKennelAbbreviation2 # $tempEventNumber)";
        $this->auditTheThings($request, $actionType, $actionDescription);

        // Establish the return value message
        $returnMessage = "Success! Great, it worked";
      }

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;
    }

    public function hashParticipationJsonPreAction(Request $request, int $hash_id){


      #Define the SQL to execute
      $hasherListSQL = "SELECT *
        FROM HASHINGS
        JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
        WHERE HASHINGS.HASH_KY = ? ";

      $hareListSQL = "SELECT *
        FROM HARINGS
        JOIN HASHERS ON HARINGS.HARINGS_HASHER_KY = HASHERS.HASHER_KY
        WHERE HARINGS.HARINGS_HASH_KY = ?";


      #Obtain hash event information
      $hashEventInfoSQL = "SELECT *, EVENT_DATE < NOW() AS SHOW_EVENT_LINK FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

      #Execute the SQL statement; create an array of rows
      $hasherList = $this->fetchAll($hasherListSQL,array((int)$hash_id));
      $hareList = $this->fetchAll($hareListSQL,array((int)$hash_id));
      $hashEvent = $this->fetchAssoc($hashEventInfoSQL,array((int)$hash_id));

      $kennelAbbreviation = $hashEvent['KENNEL_ABBREVIATION'];
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennelAbbreviation);
      $kennelEventNumber = $hashEvent['KENNEL_EVENT_NUMBER'];
      $eventDate = $hashEvent['EVENT_DATE'];
      $pageTitle = "Participation: $kennelAbbreviation # $kennelEventNumber ($eventDate)";

      #Establish the return value
      $returnValue = $this->render('event_participation_json.twig', array (
        'pageTitle' => $pageTitle,
        'pageSubTitle' => 'Not Sure',
        'pageHeader' => 'Why is this so complicated ?',
        'hasherList' => $hasherList,
        'hareList' => $hareList,
        'hareTypes' => $this->getHareTypesForHashType($kennelKy, $hashEvent['HASH_TYPE']),
        'hash_key'=> $hash_id,
        'kennel_abbreviation' => $kennelAbbreviation,
        'kennel_event_number' => $kennelEventNumber,
        'show_event_link' => $hashEvent['SHOW_EVENT_LINK']
      ));

      #Return the return value
      return $returnValue;

    }

    #Test function
    public function addHashParticipant (Request $request){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      #Obtain the post values
      $hasherKey = $request->request->get('hasher_key');
      $hashKey = $request->request->get('hash_key');

      #Validate the post values; ensure that they are both numbers
      if(ctype_digit($hasherKey)  && ctype_digit($hashKey)){

        #Determine the hasher identity
        $hasherIdentitySql = "SELECT * FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

        # Make a database call to obtain the hasher information
        $hasherValue = $this->fetchAssoc($hasherIdentitySql, array((int) $hasherKey));

        #Obtain the object from the database results
        $data = array(
            'HASHER_KY' => $hasherValue['HASHER_KY'],
            'HASHER_NAME' => $hasherValue['HASHER_NAME'],
            'HASHER_ABBREVIATION' => $hasherValue['HASHER_ABBREVIATION'],
            'LAST_NAME' => $hasherValue['LAST_NAME'],
            'FIRST_NAME' => $hasherValue['FIRST_NAME'],
            'HOME_KENNEL' => $hasherValue['HOME_KENNEL'],
            'HOME_KENNEL_KY' => $hasherValue['HOME_KENNEL_KY'],
            'DECEASED' => $hasherValue['DECEASED'],
        );

        #Obtain the hasher name from the object
        $tempHasherName = $data['HASHER_NAME'];

        #Ensure the entry does not already exist
        $existsSql = "SELECT HASHER_NAME
          FROM HASHINGS
          JOIN HASHERS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          WHERE HASHERS.HASHER_KY = ? AND HASH_KY = ?;";

        #Retrieve the existing record
        $hasherToAdd = $this->fetchAll($existsSql,array((int)$hasherKey,(int)$hashKey));
        if(count($hasherToAdd) < 1){

          #Define the sql insert statement
          $sql = "INSERT INTO HASHINGS (HASHER_KY, HASH_KY) VALUES (?, ?);";

          #Execute the sql insert statement
          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hasherKey,$hashKey));

          #Audit the activity

          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

          # Make a database call to obtain the hasher information
          $hashValue = $this->fetchAssoc($sql, array((int) $hashKey));
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

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;
    }

    #Test function
    public function addHashOrganizer (Request $request){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      #Obtain the post values
      $hasherKey = $request->request->get('hasher_key');
      $hashKey = $request->request->get('hash_key');
      $hareType = $request->request->get('hare_type');

      #Validate the post values; ensure that they are both numbers
      if(ctype_digit($hasherKey)  && ctype_digit($hashKey) && ctype_digit($hareType)){

        #Determine the hasher identity
        $hasherIdentitySql = "SELECT * FROM HASHERS WHERE HASHERS.HASHER_KY = ? ;";

        # Make a database call to obtain the hasher information
        $hasherValue = $this->fetchAssoc($hasherIdentitySql, array((int) $hasherKey));

        #Obtain the object from the database results
        $data = array(
            'HASHER_KY' => $hasherValue['HASHER_KY'],
            'HASHER_NAME' => $hasherValue['HASHER_NAME'],
            'HASHER_ABBREVIATION' => $hasherValue['HASHER_ABBREVIATION'],
            'LAST_NAME' => $hasherValue['LAST_NAME'],
            'FIRST_NAME' => $hasherValue['FIRST_NAME'],
            'HOME_KENNEL' => $hasherValue['HOME_KENNEL'],
            'HOME_KENNEL_KY' => $hasherValue['HOME_KENNEL_KY'],
            'DECEASED' => $hasherValue['DECEASED'],
        );

        #Obtain the hasher name from the object
        $tempHasherName = $data['HASHER_NAME'];

        #Ensure the entry does not already exist
        $existsSql = "SELECT 1 AS IGNORED
          FROM HARINGS
          JOIN HASHERS ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
          WHERE HASHERS.HASHER_KY = ? AND HARINGS.HARINGS_HASH_KY = ? AND HARINGS.HARE_TYPE = ?;";

        #Retrieve the existing record
        $hareToAdd = $this->fetchAll($existsSql,array((int)$hasherKey, (int)$hashKey, (int)$hareType));
        if(count($hareToAdd) < 1){

          #Define the sql insert statement
          $sql = "INSERT INTO HARINGS (HARINGS_HASHER_KY, HARINGS_HASH_KY, HARE_TYPE) VALUES (?, ?, ?);";

          #Execute the sql insert statement
          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hasherKey,$hashKey,$hareType));

          #Add the audit statement
          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

          # Make a database call to obtain the hasher information
          $hashValue = $this->fetchAssoc($sql, array((int) $hashKey));
          $tempKennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
          $tempKennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];

          $tempActionType = "Add Hare to Hash";
          $tempActionDescription = "Added $tempHasherName to $tempKennelAbbreviation # $tempKennelEventNumber";
          $this->auditTheThings($request, $tempActionType, $tempActionDescription);

          #Set the return message
          $returnMessage = "Success! $tempHasherName has been added as a hare.";

        } else {

          #Set the return message
          $returnMessage = "$tempHasherName has already added as a hare.";

        }

      } else{
        $returnMessage = "Something is wrong with the input.$hasherKey and $hashKey";
      }

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;
    }


    #Delete a participant from a hash
    public function deleteHashParticipant (Request $request){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      #Obtain the post values
      $hasherKey = $request->request->get('hasher_key');
      $hashKey = $request->request->get('hash_key');

      #Validate the post values; ensure that they are both numbers
      if(ctype_digit($hasherKey)  && ctype_digit($hashKey)){

        #Check if this exists
        $existsSql = "SELECT HASHER_NAME
          FROM HASHINGS
          JOIN HASHERS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
          WHERE HASHERS.HASHER_KY = ? AND HASH_KY = ?;";

        #Retrieve the existing record
        $hasherToDelete = $this->fetchAll($existsSql,array((int)$hasherKey,(int)$hashKey));
        if(count($hasherToDelete) > 0){

          #Obtain the name of the person being deleted
          $tempHasherName = $hasherToDelete[0];
          $tempHasherName = $tempHasherName['HASHER_NAME'];
          $returnMessage = "Success! Removed $tempHasherName as hasher at this event.";

          #Define the sql insert statement
          $sql = "DELETE FROM HASHINGS WHERE HASHER_KY = ? AND HASH_KY = ?;";

          #Execute the sql insert statement
          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hasherKey,$hashKey));

          #Add the audit statement
          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

          # Make a database call to obtain the hasher information
          $hashValue = $this->fetchAssoc($sql, array((int) $hashKey));
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

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;

    }


    #Delete a participant from a hash
    public function deleteHashOrganizer (Request $request){

      #Establish the return message
      $returnMessage = "This has not been set yet...";

      #Obtain the post values
      $hasherKey = $request->request->get('hasher_key');
      $hashKey = $request->request->get('hash_key');

      #Validate the post values; ensure that they are both numbers
      if(ctype_digit($hasherKey)  && ctype_digit($hashKey)){

        #Check if this exists
        $existsSql = "SELECT HASHER_NAME
          FROM HARINGS
          JOIN HASHERS ON HASHERS.HASHER_KY = HARINGS.HARINGS_HASHER_KY
          WHERE HARINGS.HARINGS_HASHER_KY = ? AND HARINGS.HARINGS_HASH_KY = ?;";

        #Retrieve the existing record
        $hareToDelete = $this->fetchAll($existsSql,array((int)$hasherKey,(int)$hashKey));
        if(count($hareToDelete) > 0){

          #Obtain the name of the person being deleted
          $tempHasherName = $hareToDelete[0];
          $tempHasherName = $tempHasherName['HASHER_NAME'];
          $returnMessage = "Success! Removed $tempHasherName as hare from this event.";

          #Define the sql insert statement
          $sql = "DELETE FROM HARINGS WHERE HARINGS_HASHER_KY = ? AND HARINGS_HASH_KY = ?;";

          #Execute the sql insert statement
          $this->app['dbs']['mysql_write']->executeUpdate($sql,array($hasherKey,$hashKey));

          #Add the audit statement
          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

          # Make a database call to obtain the hasher information
          $hashValue = $this->fetchAssoc($sql, array((int) $hashKey));
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

      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;

    }

    #Obtain hashers for an event
    public function getHaresForEvent(Request $request){

      #Obtain the post values
      $hashKey = $request->request->get('hash_key');

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
      $hareList = $this->fetchAll($hareListSQL,array((int)$hashKey));

      #Set the return value
      $returnValue =  $this->app->json($hareList, 200);
      return $returnValue;
    }

    #Obtain hashers for an event
    public function getHashersForEvent(Request $request){

      #Obtain the post values
      $hashKey = $request->request->get('hash_key');

      #Define the SQL to execute
      $hareListSQL = "SELECT HASHERS.HASHER_KY AS HASHER_KY, HASHERS.HASHER_NAME AS HASHER_NAME
        FROM HASHINGS
        JOIN HASHERS ON HASHERS.HASHER_KY = HASHINGS.HASHER_KY
        WHERE HASHINGS.HASH_KY = ? ";

      #Obtain the hare list
      $hareList = $this->fetchAll($hareListSQL,array((int)$hashKey));

      #Set the return value
      $returnValue =  $this->app->json($hareList, 200);
      return $returnValue;
    }



    #Define the action
    public function listHashesPreActionJson(Request $request, string $kennel_abbreviation) {

      # Establish and set the return value
      $returnValue = $this->render('hash_list_json.twig',array(
        'pageTitle' => 'The List of Hashes',
        'pageSubTitle' => '',
        #'theList' => $hasherList,
        'kennel_abbreviation' => $kennel_abbreviation,
        'pageCaption' => "",
        'tableCaption' => ""
      ));

      #Return the return value
      return $returnValue;
    }






    public function listHashesPostActionJson(Request $request, string $kennel_abbreviation){

      #$this->app['monolog']->addDebug("Entering the function------------------------");

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);


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
        #$this->app['monolog']->addDebug("input start is not numeric: $inputStart");
        $inputStart = 0;
      }

      #Validate input length
      if(!is_numeric($inputLength)){
        #$this->app['monolog']->addDebug("input length is not numeric");
        $inputStart = "0";
        $inputLength = "50";
      } else if($inputLength == "-1"){
        #$this->app['monolog']->addDebug("input length is negative one (all rows selected)");
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
      $inputOrderColumnExtracted = "13";
      $inputOrderColumnIncremented = "13";
      $inputOrderDirectionExtracted = "desc";
      if(!is_null($inputOrderRaw)){
        #$this->app['monolog']->addDebug("inside inputOrderRaw not null");
        $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
        $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
        $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
      }else{
        #$this->app['monolog']->addDebug("inside inputOrderRaw is null");
      }

      #-------------- End: Modify the input parameters  --------------------------


      #-------------- Begin: Define the SQL used here   --------------------------

      #Define the sql that performs the filtering
      $sql = "SELECT
          KENNEL_EVENT_NUMBER AS KENNEL_EVENT_NUMBER,
          (SELECT COUNT(*) FROM HASHINGS WHERE HASHINGS.HASH_KY = HASHES.HASH_KY) AS HOUND_COUNT,
          (SELECT COUNT(*) FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY) AS HARE_COUNT,
          EVENT_LOCATION AS EVENT_LOCATION,
          SPECIAL_EVENT_DESCRIPTION AS SPECIAL_EVENT_DESCRIPTION,
          EVENT_DATE AS EVENT_DATE,
          EVENT_CITY AS EVENT_CITY,
          EVENT_STATE AS EVENT_STATE,
          FORMATTED_ADDRESS,
          HASH_KY AS HASY_KY,
          KENNEL_KY AS KENNEL_KY,
          DATE_FORMAT(event_date,'%Y/%m/%d') AS EVENT_DATE_FORMATTED,
          DATE_FORMAT(event_date,'%Y/%m/%d %h:%i %p') AS EVENT_DATE_FORMATTED2,
          HASH_TYPE_NAME AS HASH_TYPE_NAME
        FROM HASHES
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
        WHERE
          KENNEL_KY = $kennelKy AND
          (
            KENNEL_EVENT_NUMBER LIKE ? OR
            EVENT_LOCATION LIKE ? OR
            SPECIAL_EVENT_DESCRIPTION LIKE ? OR
            EVENT_CITY LIKE ? OR
            EVENT_STATE LIKE ?)
        ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
        LIMIT $inputStart,$inputLength";


      #Define the SQL that gets the count for the filtered results
      $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
        FROM HASHES
        WHERE
        KENNEL_KY = $kennelKy AND
        (
          KENNEL_EVENT_NUMBER LIKE ? OR
          EVENT_LOCATION LIKE ? OR
          SPECIAL_EVENT_DESCRIPTION LIKE ? OR
          EVENT_CITY LIKE ? OR
          EVENT_STATE LIKE ?)";

      #Define the sql that gets the overall counts
      $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES WHERE KENNEL_KY = $kennelKy";

      #-------------- End: Define the SQL used here   ----------------------------

      #-------------- Begin: Query the database   --------------------------------
      #Perform the filtered search
      $theResults = $this->fetchAll($sql,array(
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified));

      #Perform the untiltered count
      $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];

      #Perform the filtered count
      $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,array(
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified,
        (string) $inputSearchValueModified,
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
      $returnValue = $this->app->json($output,200);

      #Return the return value
      return $returnValue;
    }
}
