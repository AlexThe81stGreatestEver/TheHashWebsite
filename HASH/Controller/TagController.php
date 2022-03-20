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

class TagController extends BaseController
{
  public function __construct(Application $app) {
    parent::__construct($app);
  }

    public function manageEventTagsPreAction(Request $request){
      #Define the SQL to execute
      $eventTagListSQL = "SELECT TAG_TEXT, COUNT(HTJ.HASHES_KY) AS THE_COUNT
        FROM  HASHES_TAGS HT LEFT JOIN HASHES_TAG_JUNCTION HTJ ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
        GROUP BY TAG_TEXT
        ORDER BY THE_COUNT DESC";

      #Execute the SQL statement; create an array of rows
      $eventTagList = $this->fetchAll($eventTagListSQL);


      #Establish the return value
      $returnValue = $this->render('manage_event_tag_json.twig', array (
        'pageTitle' => "Event Tags",
        'pageSubTitle' => 'Create Event Tags. (Add them to the events sometime later).',
        'pageHeader' => 'Why is this so complicated ?',
        'tagList' => $eventTagList,
        'csrf_token' => $this->getCsrfToken('tag')
      ));

      #Return the return value
      return $returnValue;
    }

public function getEventTagsWithCountsJsonAction(Request $request){

  #Define the SQL to execute
  $tagListSQL = "SELECT TAG_TEXT, COUNT(HTJ.HASHES_KY) AS THE_COUNT
    FROM  HASHES_TAGS HT LEFT JOIN HASHES_TAG_JUNCTION HTJ ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
    GROUP BY TAG_TEXT
    ORDER BY THE_COUNT DESC";

  #Obtain the hare list
  $tagList = $this->fetchAll($tagListSQL);

  #Set the return value
  $returnValue =  $this->app->json($tagList, 200);
  return $returnValue;
}


public function getAllEventTagsJsonAction(Request $request){

  #Define the SQL to execute
  $tagListSQL = "SELECT HASHES_TAGS_KY AS id, TAG_TEXT AS label, TAG_TEXT AS value
    FROM  HASHES_TAGS HT
    ORDER BY TAG_TEXT ASC";

  #Obtain the hare list
  $tagList = $this->fetchAll($tagListSQL);

  #Set the return value
  $returnValue =  $this->app->json($tagList, 200);
  return $returnValue;
}




public function getMatchingEventTagsJsonAction(Request $request){

  //Default the search term to an empty string
  $searchTerm = "";

  //Check the format of the search string
  if(isset($_GET['term'])  &&  ctype_alnum(trim(str_replace(' ','',$_GET['term'])))  ){
    $searchTerm = $_GET['term'];
    $searchTerm = "%$searchTerm%";
  }


  #Define the SQL to execute
  $tagListSQL = "SELECT HASHES_TAGS_KY AS id, TAG_TEXT AS label, TAG_TEXT AS value
    FROM  HASHES_TAGS HT
    WHERE TAG_TEXT LIKE ?
    ORDER BY TAG_TEXT ASC";

  #Obtain the tag list
  $tagList = $this->fetchAll($tagListSQL,array((string) $searchTerm));

  #Set the return value
  $returnValue =  $this->app->json($tagList, 200);
  return $returnValue;
}


private function addNewEventTagAfterDbChecking(Request $request, string $theTagText){

        #Define the sql insert statement
        $sql = "INSERT INTO HASHES_TAGS (TAG_TEXT, CREATED_BY) VALUES (?, ?);";

        #Determine the username
        $token = $this->app['security.token_storage']->getToken();
        if (null !== $token) {
          $user = $token->getUser();
        }

        #Execute the sql insert statement
        $this->app['dbs']['mysql_write']->executeUpdate($sql,array($theTagText,$user));

        #Audit the action
        $tempActionType = "Created Event Tag";
        $tempActionDescription = "Created event tag: $theTagText";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        #Set the return message
        $returnMessage = "Success! $theTagText has been created as an event tag.";

}

public function addNewEventTag(Request $request){

        $token = $request->request->get('csrf_token');
        $this->validateCsrfToken('tag', $token);

        #Establish the return message
        $returnMessage = null;

        #Obtain the post values
        $theTagText = $request->request->get('tag_text');
        $theTagText = trim($theTagText);

        #Validate the post values; ensure that they are both numbers
        if(ctype_alnum(trim(str_replace(' ','',$theTagText)))){

          if(($this->doesTagTextExistAlready($request,$theTagText))){
            #Set the return value
            $returnMessage = "Uh oh! This tag already exists: $theTagText";

          }else{
            #Add the tag into the tags table
            $this->addNewEventTagAfterDbChecking($request, $theTagText);

            #Set the return value
            $returnMessage = "Success! You've created the tag: $theTagText";
          }
        } else{
          $returnMessage = "Something is wrong with the input $theTagText";
        }

        #Set the return value
        $returnValue =  $this->app->json($returnMessage, 200);
        return $returnValue;


}

  private function doesTagTextExistAlready(Request $request, string $theTagText){

    #Ensure the entry does not already exist
    $existsSql = "SELECT * FROM HASHES_TAGS WHERE TAG_TEXT = ? ;";

    #Retrieve the existing record
    $matchingTags = $this->fetchAll($existsSql,array($theTagText));

    #Check if there are 0 results
    if(count($matchingTags) < 1){
        return false;
    }else{
        return true;
    }

  }



    #Define action
    public function showEventForTaggingPreAction(Request $request, int $hash_id){


      #Define the SQL to execute
      $eventTagListSQL = "SELECT TAG_TEXT
        FROM  HASHES_TAGS HT JOIN HASHES_TAG_JUNCTION HTJ ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
        WHERE HTJ.HASHES_KY = ?";

      #Execute the SQL statement; create an array of rows
      $eventTagList = $this->fetchAll($eventTagListSQL, array((int) $hash_id));

      # Declare the SQL used to retrieve this information
      $sql = "SELECT * ,date_format(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, date_format(event_date, '%k:%i:%S') AS EVENT_DATE_TIME FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

      # Make a database call to obtain the hasher information
      $hashValue = $this->fetchAssoc($sql, array((int) $hash_id));

      $returnValue = $this->render('show_hash_for_tagging.twig', array(
        'pageTitle' => 'Tag this hash event!',
        'pageHeader' => '(really)',
        'hashValue' => $hashValue,
        'hashKey' => $hash_id,
        'tagList' => $eventTagList,
        'hashTypes' => $this->getHashTypes($hashValue['KENNEL_KY'], 0),
        'csrf_token' => $this->getCsrfToken('tag')
      ));

      #Return the return value
      return $returnValue;

    }




    public function addTagToEventJsonAction(Request $request){

      #Establish the return message
      $returnMessage = "";

      #Obtain the post values
      $theTagText = trim($request->request->get('tag_text'));
      $theEventKey = intval($request->request->get('event_key'));

      $token = $request->request->get('csrf_token');
      $this->validateCsrfToken('tag', $token);

      #Determine if the tag text is valid (as in, doesn't have sql injection in it)
      $tagTextIsValid = $this->isTagTextValid($theTagText);

      #Determine if the event key is valid
      $eventKeyIsValid = $this->isEventKeyValid($theEventKey);

      if($tagTextIsValid && $eventKeyIsValid ){

        #If the tag doesn't already exist, create it
        if(!($this->doesTagTextExistAlready($request,$theTagText))){
          #Add the tag into the tags table
          $this->addNewEventTagAfterDbChecking($request, $theTagText);
        }

        #Obtain the tag key
        $tagKey = $this->getTagTextKey($theTagText);

        #Add the event/tag pair into the junction table
        $junctionInsertSql = "INSERT INTO HASHES_TAG_JUNCTION (HASHES_KY, HASHES_TAGS_KY, CREATED_BY) VALUES (?, ?, ?);";

        #Get the user name
        $user = $this->getUserName();

        #Execute the sql insert statement
        $this->app['dbs']['mysql_write']->executeUpdate($junctionInsertSql,array((int)$theEventKey,(int)$tagKey,(string)$user));

        # Declare the SQL used to retrieve this information
        $hashValueSql = "SELECT * ,date_format(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, date_format(event_date, '%k:%i:%S') AS EVENT_DATE_TIME FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

        # Make a database call to obtain the hasher information
        $hashValue = $this->fetchAssoc($hashValueSql, array((int) $theEventKey));

        #Audit the action
        $kennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];
        $kennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
        $tempActionType = "Create Event Tagging";
        $tempActionDescription = "Create event tagging: $theTagText on $kennelAbbreviation:$kennelEventNumber";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        #Set the return message
        $returnMessage = "Success! $theTagText has been added as a tag for this event.";

      }else{
        #Set the return message
        $returnMessage =  "Something is up";
      }



      #Set the return value
      $returnValue =  $this->app->json($returnMessage, 200);
      return $returnValue;



    }



    public function removeTagFromEventJsonAction(Request $request){

            $token = $request->request->get('csrf_token');
            $this->validateCsrfToken('tag', $token);

            #Establish the return message
            $returnMessage = "This has not been set yet...";

            #Obtain the post values
            $theTagText = trim($request->request->get('tag_text'));
            $theEventKey = intval($request->request->get('event_key'));

            #Determine if the tag text is valid (as in, doesn't have sql injection in it)
            $tagTextIsValid = $this->isTagTextValid($theTagText);

            #Obtain the tag key
            $tagKey = $tagTextIsValid ? ($this->getTagTextKey($theTagText)) : null;

            #Determine if the event key is valid
            $eventKeyIsValid = $this->isEventKeyValid($theEventKey);

            if($tagTextIsValid && (!(is_null($tagKey))) && $eventKeyIsValid ){

              #Define the sql delete statement
              $sql = "DELETE FROM HASHES_TAG_JUNCTION WHERE HASHES_KY= ? AND HASHES_TAGS_KY = ?;";

              #Execute the sql insert statement
              $this->app['dbs']['mysql_write']->executeUpdate($sql,array($theEventKey,$tagKey));

              #Get the user name
              #$user = $this->getUserName();

              # Declare the SQL used to retrieve this information
              $hashValueSql = "SELECT * ,date_format(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, date_format(event_date, '%k:%i:%S') AS EVENT_DATE_TIME FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";

              # Make a database call to obtain the hasher information
              $hashValue = $this->fetchAssoc($hashValueSql, array((int) $theEventKey));

              #Audit the action
              $kennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];
              $kennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
              $tempActionType = "Delete Event Tagging";
              $tempActionDescription = "Delete event tagging: $theTagText on $kennelAbbreviation:$kennelEventNumber";
              $this->auditTheThings($request, $tempActionType, $tempActionDescription);

              #Set the return message
              $returnMessage = "Success! $theTagText has been removed as a tag from this event.";

            }else{
              #Set the return message
              $returnMessage =  "Something is up";
            }



            #Set the return value
            $returnValue =  $this->app->json($returnMessage, 200);
            return $returnValue;


    }

    private function isTagTextValid(string $tagText){

      #Establish the return value
      $returnValue = FALSE;

      #Set the return value
      $returnValue = (ctype_alnum(trim(str_replace(' ','',$tagText))));

      #Return the return value
      return $returnValue;
    }

    private function isEventKeyValid(int $eventKey){

      #Establish the return value
      $returnValue = FALSE;

      #Query the database for the event
      $getEventValueSql = "SELECT * FROM HASHES_TABLE WHERE HASH_KY = ? ;";
      $eventValues = $this->fetchAll($getEventValueSql,array((int) $eventKey));

      #Determine if the event exists
      if(count($eventValues) > 0){
        $returnValue = TRUE;
      }

      #Return the return value
      return $returnValue;
    }

    private function getTagTextKey(string $tagText){

      #Establish the return value
      $returnValue = null;

      #Set the return value
      $getTagValueSql = "SELECT * FROM HASHES_TAGS WHERE TAG_TEXT = ? ;";
      //$hashValue = $this->fetchAssoc($sql, array((int) $hash_id));

      #Retrieve the existing record
      $matchingTagValue = $this->fetchAssoc($getTagValueSql,array((string) $tagText));
      if(!(is_null($matchingTagValue))){
        $returnValue = $matchingTagValue['HASHES_TAGS_KY'];
      }


      #Return the return value
      return $returnValue;
    }

    private function getUserName(){
      #Set the return value
      $returnValue = null;

      #Establish the return value
      $token = $this->app['security.token_storage']->getToken();
      if (null !== $token) {
        $returnValue = $token->getUser();
      }

      #Return the return value
      return $returnValue;
    }

    public function listHashesByEventTagAction(Request $request, int $event_tag_ky, string $kennel_abbreviation){

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      #Define the SQL to execute
      $sql = "SELECT
            HASHES.HASH_KY,
            KENNEL_EVENT_NUMBER,
            EVENT_DATE,
            DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME,
            EVENT_LOCATION,
            EVENT_CITY,
            EVENT_STATE,
            SPECIAL_EVENT_DESCRIPTION,
            HASH_TYPE_NAME
      FROM
        HASHES JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HASH_TYPES ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
      WHERE
        HASHES_TAGS_KY = ? AND KENNEL_KY = ?
      ORDER BY HASHES.EVENT_DATE DESC";

      #Execute the SQL statement; create an array of rows
      $hashList = $this->fetchAll($sql,array((int) $event_tag_ky, $kennelKy));

      # Declare the SQL used to retrieve this information
      $sql_for_tag_lookup = "SELECT * FROM HASHES_TAGS WHERE HASHES_TAGS_KY = ?";

      # Make a database call to obtain the hasher information
      $eventTag = $this->fetchAssoc($sql_for_tag_lookup, array((int) $event_tag_ky));

      # Establish and set the return value
      #$hasherName = $hasher['HASHER_NAME'];
      $tagText = $eventTag['TAG_TEXT'];
      $pageSubtitle = "Hashes with the tag: $tagText";
      $returnValue = $this->render('hash_list.twig',array(
        'pageTitle' => 'The List of Hashes',
        'pageSubTitle' => $pageSubtitle,
        'theList' => $hashList,
        'tableCaption' => '',
        'kennel_abbreviation' => $kennel_abbreviation
      ));

      #Return the return value
      return $returnValue;

    }



    public function chartsGraphsByEventTagAction(Request $request, int $event_tag_ky, string $kennel_abbreviation){

      # Declare the SQL used to retrieve this information
      $sql = "SELECT * FROM HASHES_TAGS WHERE HASHES_TAGS_KY = ?";

      #Obtain the kennel key
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

      # Make a database call to obtain the hasher information
      $tagValue = $this->fetchAssoc($sql, array((int) $event_tag_ky));

      # Obtain their hashes
      $sqlTheHashes = "SELECT
        HASHES.*
        FROM
        HASHES
        JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        WHERE
        HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        AND LAT IS NOT NULL AND LNG IS NOT NULL";
      $theHashes = $this->fetchAll($sqlTheHashes, array((int) $event_tag_ky, $kennelKy));

      #Obtain the average lat
      $sqlTheAverageLatLong = "SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG FROM
        HASHES
        JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        WHERE
        HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        AND LAT IS NOT NULL AND LNG IS NOT NULL";
      $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, array((int) $event_tag_ky, $kennelKy));
      $avgLat = $theAverageLatLong['THE_LAT'];
      $avgLng = $theAverageLatLong['THE_LNG'];

      # Obtain the number of hashings
      #$hashCountValue = $this->fetchAssoc($this->getPersonsHashingCountQuery(), array((int) $hasher_id, $kennelKy));

      # Obtain the number of harings
      #$hareCountValue = $this->fetchAssoc(PERSONS_HARING_COUNT_FLEXIBLE, array((int) $hasher_id, $kennelKy,  (int) 0, (int) 1));

      # Obtain the hashes by month (name)
      #$theHashesByMonthNameList = $this->fetchAll(HASHER_HASH_COUNTS_BY_MONTH_NAME, array((int) $hasher_id, $kennelKy));

      # Obtain the hashes by quarter
      #$theHashesByQuarterList = $this->fetchAll(HASHER_HASH_COUNTS_BY_QUARTER, array((int) $hasher_id, $kennelKy));

      # Obtain the hashes by quarter
      #$theHashesByStateList = $this->fetchAll(HASHER_HASH_COUNTS_BY_STATE, array((int) $hasher_id, $kennelKy));

      # Obtain the hashes by county
      #$theHashesByCountyList = $this->fetchAll(HASHER_HASH_COUNTS_BY_COUNTY, array((int) $hasher_id, $kennelKy));

      # Obtain the hashes by postal code
      #$theHashesByPostalCodeList = $this->fetchAll(HASHER_HASH_COUNTS_BY_POSTAL_CODE, array((int) $hasher_id, $kennelKy));

      # Obtain the hashes by day name
      #$theHashesByDayNameList = $this->fetchAll(HASHER_HASH_COUNTS_BY_DAYNAME, array((int) $hasher_id, $kennelKy));

      #Obtain the hashes by year
      $sqlHashesByYear = "SELECT TEMP_A.YEAR_A AS THE_VALUE, COUNT(TEMP_B.YEAR_B) AS THE_COUNT
        FROM
        (
        SELECT
        	DISTINCT(YEAR(EVENT_DATE)) AS YEAR_A
        FROM
        	HASHES
        WHERE KENNEL_KY = ?
        	AND EVENT_DATE >= (
        		SELECT MIN(EVENT_DATE)
                FROM
        			HASHES
        			JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                WHERE
        			HASHES_TAGS_KY = ? AND KENNEL_KY = ?)
        	AND EVENT_DATE <= (
        		SELECT MAX(EVENT_DATE)
                FROM
        			HASHES
        			JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                WHERE
        			HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        )) TEMP_A LEFT JOIN (
        SELECT Year(EVENT_DATE) AS YEAR_B
                FROM
                HASHES
                JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                WHERE
                HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        ) TEMP_B ON TEMP_A.YEAR_A = TEMP_B.YEAR_B
        GROUP BY TEMP_A.YEAR_A";
      $hashesByYearList = $this->fetchAll($sqlHashesByYear, array(
        $kennelKy,
        (int) $event_tag_ky,
        $kennelKy,
        (int) $event_tag_ky,
        $kennelKy,
        (int) $event_tag_ky,
        $kennelKy)
      );

      #Hasher Counts
      $sqlHasherCounts = "SELECT HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM
          HASHES
        JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HASHINGS ON HASHINGS.HASH_KY = HASHES.HASH_KY
        JOIN HASHERS ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
        WHERE
          HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        GROUP BY HASHER_NAME
        ORDER BY THE_COUNT DESC";
      $hasherCountList = $this->fetchAll($sqlHasherCounts, array((int) $event_tag_ky, $kennelKy));

      #Hare Counts
      $sqlHareCounts = "SELECT HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
      FROM
        HASHES
        JOIN HASHES_TAG_JUNCTION ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HARINGS ON HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN HASHERS ON HARINGS_HASHER_KY = HASHERS.HASHER_KY
        WHERE
          HASHES_TAGS_KY = ? AND KENNEL_KY = ?
        GROUP BY HASHER_NAME
        ORDER BY THE_COUNT DESC";
      $hareCountList = $this->fetchAll($sqlHareCounts, array((int) $event_tag_ky, $kennelKy));

      #Obtain the harings by year
      #$sqlHaringsByYear = "SELECT
      #	  YEAR(EVENT_DATE) AS THE_VALUE,
      #    SUM(CASE WHEN HASHES.IS_HYPER IN (0)  THEN 1 ELSE 0 END) NON_HYPER_COUNT,
      #	  SUM(CASE WHEN HASHES.IS_HYPER IN (1)  THEN 1 ELSE 0 END) HYPER_COUNT,
      #    COUNT(*) AS TOTAL_HARING_COUNT
      #FROM
      #    HARINGS
      #	  JOIN HASHES ON HARINGS.HARINGS_HASH_KY = HASHES.HASH_KY
      #WHERE
      #    HARINGS.HARINGS_HASHER_KY = ? AND
      #    HASHES.KENNEL_KY = ?
      #GROUP BY YEAR(EVENT_DATE)
      #ORDER BY YEAR(EVENT_DATE)";
      #$haringsByYearList = $this->fetchAll($sqlHaringsByYear, array((int) $hasher_id, $kennelKy));

      #Query the database
      #$cityHashingsCountList = $this->fetchAll(HASHER_HASH_COUNTS_BY_CITY, array((int) $hasher_id, $kennelKy));

      #Obtain largest entry from the list
      #$cityHashingsCountMax = 1;
      #if(isset($cityHashingsCountList[0]['THE_COUNT'])){
      #  $cityHashingsCountMax = $cityHashingsCountList[0]['THE_COUNT'];
      #}

      #Obtain their largest streak
      #$longestStreakValue = $this->fetchAssoc(THE_LONGEST_STREAKS_FOR_HASHER, array($kennelKy , (int) $hasher_id));

      # Establish and set the return value
      $returnValue = $this->render('eventtag_chart_details.twig',array(
        'pageTitle' => 'Tag Charts and Details',
        'firstHeader' => 'Basic Details',
        'secondHeader' => 'Statistics',
        'tag_value' => $tagValue,
        #'hashCount' => $hashCountValue['THE_COUNT'],
        #'hareCount' => $hareCountValue['THE_COUNT'],
        'kennel_abbreviation' => $kennel_abbreviation,
        'hashes_by_year_list' => $hashesByYearList,
        'hasher_count_list' => $hasherCountList,
        'hare_count_list' => $hareCountList,
        #'harings_by_year_list' => $haringsByYearList,
        #'hashes_by_month_name_list' => $theHashesByMonthNameList,
        #'hashes_by_quarter_list' => $theHashesByQuarterList,
        #'hashes_by_state_list' => $theHashesByStateList,
        #'hashes_by_county_list' => $theHashesByCountyList,
        #'hashes_by_postal_code_list' => $theHashesByPostalCodeList,
        #'hashes_by_day_name_list' => $theHashesByDayNameList,
        #'city_hashings_count_list' => $cityHashingsCountList,
        #'city_hashings_max_value' => $cityHashingsCountMax,
        'the_hashes' => $theHashes,
        'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
        'avg_lat' => $avgLat,
        'avg_lng' => $avgLng,
        #'longest_streak' => $longestStreakValue['MAX_STREAK']
      ));

      # Return the return value
      return $returnValue;

    }
}
