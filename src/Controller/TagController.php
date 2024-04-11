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
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Validator\ConstraintValidator;

class TagController extends BaseController
{
  public function __construct(ManagerRegistry $doctrine) {
    parent::__construct($doctrine);
  }

  #[Route('/admin/tags/manageeventtags',
    methods: ['GET']
  )]
  public function manageEventTagsPreAction(Request $request) {

    $eventTagListSQL = "
      SELECT TAG_TEXT, COUNT(HTJ.HASHES_KY) AS THE_COUNT
        FROM  HASHES_TAGS HT
   LEFT JOIN HASHES_TAG_JUNCTION HTJ
          ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
       GROUP BY TAG_TEXT
       ORDER BY THE_COUNT DESC";

    #Execute the SQL statement; create an array of rows
    $eventTagList = $this->fetchAll($eventTagListSQL);

    return $this->render('manage_event_tag_json.twig', [
      'pageTitle' => "Event Tags",
      'pageSubTitle' => 'Create Event Tags. (Add them to the events sometime later).',
      'pageHeader' => 'Why is this so complicated ?',
      'tagList' => $eventTagList,
      'csrf_token' => $this->getCsrfToken('tag') ]);
  }

  #[Route('/admin/tags/getmatchingeventtags',
    methods: ['GET']
  )]
  public function getMatchingEventTagsJsonAction(Request $request) {

    //Default the search term to an empty string
    $searchTerm = "";

    //Check the format of the search string
    if(isset($_GET['term']) && ctype_alnum(trim(str_replace(' ','',$_GET['term'])))) {
      $searchTerm = $_GET['term'];
      $searchTerm = "%$searchTerm%";
    }

    #Define the SQL to execute
    $tagListSQL = "
      SELECT HASHES_TAGS_KY AS id, TAG_TEXT AS label, TAG_TEXT AS value
        FROM HASHES_TAGS HT
       WHERE TAG_TEXT LIKE ?
       ORDER BY TAG_TEXT ASC";

    #Obtain the tag list
    $tagList = $this->fetchAll($tagListSQL, [ $searchTerm ]);

    return new JsonResponse($tagList);
  }

  private function addNewEventTagAfterDbChecking(Request $request, string $theTagText) {

    #Define the sql insert statement
    $sql = "INSERT INTO HASHES_TAGS (TAG_TEXT, CREATED_BY) VALUES (?, ?);";

    #Execute the sql insert statement
    $this->getWriteConnection()->executeUpdate($sql, [ $theTagText,$this->getUsername() ]);

    #Audit the action
    $tempActionType = "Created Event Tag";
    $tempActionDescription = "Created event tag: $theTagText";
    $this->auditTheThings($request, $tempActionType, $tempActionDescription);
  }

  #[Route('/admin/tags/addneweventtag',
    methods: ['POST']
  )]
  public function addNewEventTag(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('tag', $token);

    #Obtain the post values
    $theTagText = $_POST['tag_text'];
    $theTagText = trim($theTagText);

    #Validate the post values; ensure that they are both numbers
    if(ctype_alnum(trim(str_replace(' ','', $theTagText)))) {

      if(($this->doesTagTextExistAlready($request, $theTagText))) {
        #Set the return value
        $returnMessage = "Uh oh! This tag already exists: $theTagText";

      } else {
        #Add the tag into the tags table
        $this->addNewEventTagAfterDbChecking($request, $theTagText);

        #Set the return value
        $returnMessage = "Success! You've created the tag: $theTagText";
      }
    } else {
      $returnMessage = "Something is wrong with the input $theTagText";
    }

    return new JsonResponse($returnMessage);
  }

  private function doesTagTextExistAlready(Request $request, string $theTagText){

    #Ensure the entry does not already exist
    $existsSql = "SELECT 1 AS X FROM HASHES_TAGS WHERE TAG_TEXT = ? ;";

    #Retrieve the existing record
    $matchingTags = $this->fetchAll($existsSql, [ $theTagText ]);

    #Check if there are 0 results
    return count($matchingTags) != 0;
  }

  #[Route('/admin/tags/eventscreen/{hash_id}',
    methods: ['GET'],
    requirements: [
      'hash_id' => '%app.pattern.hash_id%']
  )]
  public function showEventForTaggingPreAction(int $hash_id) {

    #Define the SQL to execute
    $eventTagListSQL = "
      SELECT TAG_TEXT
        FROM HASHES_TAGS HT
        JOIN HASHES_TAG_JUNCTION HTJ
          ON HTJ.HASHES_TAGS_KY = HT.HASHES_TAGS_KY
       WHERE HTJ.HASHES_KY = ?";

    #Execute the SQL statement; create an array of rows
    $eventTagList = $this->fetchAll($eventTagListSQL, [ $hash_id ]);

    # Declare the SQL used to retrieve this information
    $sql = "
      SELECT *, DATE_FORMAT(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, DATE_FORMAT(event_date, '%k:%i:%S') AS EVENT_DATE_TIME
        FROM HASHES_TABLE
        JOIN KENNELS
          ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
       WHERE HASH_KY = ?";

    # Make a database call to obtain the hasher information
    $hashValue = $this->fetchAssoc($sql, [ $hash_id ]);

    return $this->render('show_hash_for_tagging.twig', [
      'pageTitle' => 'Tag this hash event!',
      'pageHeader' => '(really)',
      'hashValue' => $hashValue,
      'hashKey' => $hash_id,
      'tagList' => $eventTagList,
      'hashTypes' => $this->getHashTypes($hashValue['KENNEL_KY'], 0),
      'csrf_token' => $this->getCsrfToken('tag') ]);
  }

  #[Route('/admin/tags/addtagtoevent',
    methods: ['POST']
  )]
  public function addTagToEventJsonAction(Request $request) {

    #Establish the return message
    $returnMessage = "";

    #Obtain the post values
    $theTagText = trim($_POST['tag_text']);
    $theEventKey = intval($_POST['event_key']);

    $token = $_POST['csrf_token'];
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
      $username = $this->getUserName();

      #Execute the sql insert statement
      $this->getWriteConnection()->executeUpdate($junctionInsertSql, [ $theEventKey, $tagKey, $username ]);

      # Declare the SQL used to retrieve this information
      $hashValueSql = "
        SELECT *, DATE_FORMAT(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, DATE_FORMAT(event_date, '%k:%i:%S') AS EVENT_DATE_TIME
          FROM HASHES_TABLE
          JOIN KENNELS
            ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
         WHERE HASH_KY = ?";

      # Make a database call to obtain the hasher information
      $hashValue = $this->fetchAssoc($hashValueSql, [ $theEventKey ]);

      #Audit the action
      $kennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];
      $kennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
      $tempActionType = "Create Event Tagging";
      $tempActionDescription = "Create event tagging: $theTagText on $kennelAbbreviation:$kennelEventNumber";
      $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      #Set the return message
      $returnMessage = "Success! $theTagText has been added as a tag for this event.";

    } else {
      #Set the return message
      $returnMessage =  "Something is up";
    }

    return new JsonResponse($returnMessage);
  }

  #[Route('/admin/tags/removetagfromevent',
    methods: ['POST']
  )]
  public function removeTagFromEventJsonAction(Request $request) {

    $token = $_POST['csrf_token'];
    $this->validateCsrfToken('tag', $token);

    #Establish the return message
    $returnMessage = "This has not been set yet...";

    #Obtain the post values
    $theTagText = trim($_POST['tag_text']);
    $theEventKey = intval($_POST['event_key']);

    #Determine if the tag text is valid (as in, doesn't have sql injection in it)
    $tagTextIsValid = $this->isTagTextValid($theTagText);

    #Obtain the tag key
    $tagKey = $tagTextIsValid ? ($this->getTagTextKey($theTagText)) : null;

    #Determine if the event key is valid
    $eventKeyIsValid = $this->isEventKeyValid($theEventKey);

    if($tagTextIsValid && (!(is_null($tagKey))) && $eventKeyIsValid ) {

      #Define the sql delete statement
      $sql = "DELETE FROM HASHES_TAG_JUNCTION WHERE HASHES_KY= ? AND HASHES_TAGS_KY = ?;";

      #Execute the sql insert statement
      $this->getWriteConnection()->executeUpdate($sql, [ $theEventKey, $tagKey ]);

      # Declare the SQL used to retrieve this information
      $hashValueSql = "
        SELECT *, DATE_FORMAT(event_date, '%Y-%m-%d' ) AS EVENT_DATE_DATE, DATE_FORMAT(event_date, '%k:%i:%S') AS EVENT_DATE_TIME
          FROM HASHES_TABLE
          JOIN KENNELS
            ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY
         WHERE HASH_KY = ?";

      # Make a database call to obtain the hasher information
      $hashValue = $this->fetchAssoc($hashValueSql, [ $theEventKey ]);

      #Audit the action
      $kennelAbbreviation = $hashValue['KENNEL_ABBREVIATION'];
      $kennelEventNumber = $hashValue['KENNEL_EVENT_NUMBER'];
      $tempActionType = "Delete Event Tagging";
      $tempActionDescription = "Delete event tagging: $theTagText on $kennelAbbreviation:$kennelEventNumber";
      $this->auditTheThings($request, $tempActionType, $tempActionDescription);

      #Set the return message
      $returnMessage = "Success! $theTagText has been removed as a tag from this event.";

    } else {
      #Set the return message
      $returnMessage =  "Something is up";
    }

    return new JsonResponse($returnMessage);
  }

  private function isTagTextValid(string $tagText){
    return ctype_alnum(trim(str_replace(' ','',$tagText)));
  }

  private function isEventKeyValid(int $eventKey) {

    #Establish the return value
    $returnValue = FALSE;

    #Query the database for the event
    $getEventValueSql = "SELECT * FROM HASHES_TABLE WHERE HASH_KY = ? ;";
    $eventValues = $this->fetchAll($getEventValueSql, [ $eventKey ]);

    #Determine if the event exists
    return count($eventValues) > 0;
  }

  private function getTagTextKey(string $tagText) {

    #Establish the return value
    $returnValue = null;

    #Set the return value
    $getTagValueSql = "SELECT * FROM HASHES_TAGS WHERE TAG_TEXT = ? ;";

    #Retrieve the existing record
    $matchingTagValue = $this->fetchAssoc($getTagValueSql, [ $tagText ]);
    if(!(is_null($matchingTagValue))){
      $returnValue = $matchingTagValue['HASHES_TAGS_KY'];
    }

    #Return the return value
    return $returnValue;
  }

  #[Route('/{kennel_abbreviation}/listhashes/byeventtag/{event_tag_ky}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'event_tag_ky' => '%app.pattern.event_tag_ky%']
  )]
  public function listHashesByEventTagAction(int $event_tag_ky, string $kennel_abbreviation) {

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #Define the SQL to execute
    $sql = "
      SELECT HASHES.HASH_KY, KENNEL_EVENT_NUMBER, EVENT_DATE, DAYNAME(EVENT_DATE) AS EVENT_DAY_NAME, EVENT_LOCATION,
             EVENT_CITY, EVENT_STATE, SPECIAL_EVENT_DESCRIPTION, HASH_TYPE_NAME
        FROM HASHES
        JOIN HASHES_TAG_JUNCTION
          ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HASH_TYPES
          ON HASHES.HASH_TYPE = HASH_TYPES.HASH_TYPE
       WHERE HASHES_TAGS_KY = ?
         AND KENNEL_KY = ?
       ORDER BY HASHES.EVENT_DATE DESC";

    #Execute the SQL statement; create an array of rows
    $hashList = $this->fetchAll($sql, [ $event_tag_ky, $kennelKy ]);

    # Declare the SQL used to retrieve this information
    $sql_for_tag_lookup = "SELECT * FROM HASHES_TAGS WHERE HASHES_TAGS_KY = ?";

    # Make a database call to obtain the hasher information
    $eventTag = $this->fetchAssoc($sql_for_tag_lookup, array((int) $event_tag_ky));

    # Establish and set the return value
    $tagText = $eventTag['TAG_TEXT'];
    $pageSubtitle = "Hashes with the tag: $tagText";

    return $this->render('hash_list.twig', [
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => $pageSubtitle,
      'theList' => $hashList,
      'tableCaption' => '',
      'kennel_abbreviation' => $kennel_abbreviation ]);
  }

  #[Route('/{kennel_abbreviation}/chartsGraphs/byeventtag/{event_tag_ky}',
    methods: ['GET'],
    requirements: [
      'kennel_abbreviation' => '%app.pattern.kennel_abbreviation%',
      'event_tag_ky' => '%app.pattern.event_tag_ky%']
  )]
  public function chartsGraphsByEventTagAction(int $event_tag_ky, string $kennel_abbreviation) {

    # Declare the SQL used to retrieve this information
    $sql = "SELECT * FROM HASHES_TAGS WHERE HASHES_TAGS_KY = ?";

    #Obtain the kennel key
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    # Make a database call to obtain the hasher information
    $tagValue = $this->fetchAssoc($sql, [ $event_tag_ky ]);

    # Obtain their hashes
    $sqlTheHashes = "
      SELECT HASHES.*
        FROM HASHES
        JOIN HASHES_TAG_JUNCTION
          ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
       WHERE HASHES_TAGS_KY = ?
         AND KENNEL_KY = ?
        AND LAT IS NOT NULL
        AND LNG IS NOT NULL";
    $theHashes = $this->fetchAll($sqlTheHashes, [ $event_tag_ky, $kennelKy ]);

    #Obtain the average lat
    $sqlTheAverageLatLong = "
      SELECT AVG(LAT) AS THE_LAT, AVG(LNG) AS THE_LNG
        FROM HASHES
        JOIN HASHES_TAG_JUNCTION
          ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
       WHERE HASHES_TAGS_KY = ?
         AND KENNEL_KY = ?  
         AND LAT IS NOT NULL 
         AND LNG IS NOT NULL";
    $theAverageLatLong = $this->fetchAssoc($sqlTheAverageLatLong, [ $event_tag_ky, $kennelKy ]);
    $avgLat = $theAverageLatLong['THE_LAT'];
    $avgLng = $theAverageLatLong['THE_LNG'];

    #Obtain the hashes by year
    $sqlHashesByYear = "
      SELECT TEMP_A.YEAR_A AS THE_VALUE, COUNT(TEMP_B.YEAR_B) AS THE_COUNT
        FROM (SELECT DISTINCT(YEAR(EVENT_DATE)) AS YEAR_A
                FROM HASHES
               WHERE KENNEL_KY = ?
                 AND EVENT_DATE >= (SELECT MIN(EVENT_DATE)
                                      FROM HASHES
                                      JOIN HASHES_TAG_JUNCTION
                                        ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                                     WHERE HASHES_TAGS_KY = ?
                                       AND KENNEL_KY = ?)
                 AND EVENT_DATE <= (SELECT MAX(EVENT_DATE)
                                      FROM HASHES
                                      JOIN HASHES_TAG_JUNCTION
                                        ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                                     WHERE HASHES_TAGS_KY = ? AND KENNEL_KY = ?)) TEMP_A
        LEFT JOIN (SELECT Year(EVENT_DATE) AS YEAR_B
                     FROM HASHES
                     JOIN HASHES_TAG_JUNCTION
                       ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
                    WHERE HASHES_TAGS_KY = ? AND KENNEL_KY = ?) TEMP_B
          ON TEMP_A.YEAR_A = TEMP_B.YEAR_B
       GROUP BY TEMP_A.YEAR_A";
    $hashesByYearList = $this->fetchAll($sqlHashesByYear, [
      $kennelKy, $event_tag_ky, $kennelKy, $event_tag_ky, $kennelKy, $event_tag_ky, $kennelKy ]);

    #Hasher Counts
    $sqlHasherCounts = "
      SELECT HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHES
        JOIN HASHES_TAG_JUNCTION
          ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HASHINGS
          ON HASHINGS.HASH_KY = HASHES.HASH_KY
        JOIN HASHERS
          ON HASHINGS.HASHER_KY = HASHERS.HASHER_KY
       WHERE HASHES_TAGS_KY = ? AND KENNEL_KY = ?
       GROUP BY HASHER_NAME
       ORDER BY THE_COUNT DESC";
    $hasherCountList = $this->fetchAll($sqlHasherCounts, [ $event_tag_ky, $kennelKy ]);

    #Hare Counts
    $sqlHareCounts = "
      SELECT HASHER_NAME AS THE_VALUE, COUNT(*) AS THE_COUNT
        FROM HASHES
        JOIN HASHES_TAG_JUNCTION
          ON HASHES.HASH_KY = HASHES_TAG_JUNCTION.HASHES_KY
        JOIN HARINGS
          ON HARINGS_HASH_KY = HASHES.HASH_KY
        JOIN HASHERS
          ON HARINGS_HASHER_KY = HASHERS.HASHER_KY
       WHERE HASHES_TAGS_KY = ? AND KENNEL_KY = ?
       GROUP BY HASHER_NAME
       ORDER BY THE_COUNT DESC";
    $hareCountList = $this->fetchAll($sqlHareCounts, [ $event_tag_ky, $kennelKy ]);

    return $this->render('eventtag_chart_details.twig', [
      'pageTitle' => 'Tag Charts and Details',
      'firstHeader' => 'Basic Details',
      'secondHeader' => 'Statistics',
      'tag_value' => $tagValue,
      'kennel_abbreviation' => $kennel_abbreviation,
      'hashes_by_year_list' => $hashesByYearList,
      'hasher_count_list' => $hasherCountList,
      'hare_count_list' => $hareCountList,
      'the_hashes' => $theHashes,
      'geocode_api_value' => $this->getGoogleMapsJavascriptApiKey(),
      'avg_lat' => $avgLat,
      'avg_lng' => $avgLng ]);
  }
}
