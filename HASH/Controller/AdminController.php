<?php

namespace HASH\Controller;

require_once realpath(__DIR__ . '/../..').'/config/SQL_Queries.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;



class AdminController extends BaseController
{
  public function __construct(Application $app) {
    parent::__construct($app);
  }

  public function logoutAction(Request $request){

    # Invalidate the session
    $this->app['session']->invalidate();

    # Redirect the user to the root url
    return $this->app->redirect('/');

  }

  #Define the action
  public function helloAction(Request $request){

      return $this->render('admin_landing.twig', array (
        'pageTitle' => 'This is the admin landing screen',
        'subTitle1' => 'This is the admin landing screen',
        'showAwardsPage' => $this->showAwardsPage(),
        'hasLegacyHashCounts' => $this->hasLegacyHashCounts()
    ));
  }

    public function listOrphanedHashersAction(Request $request){

      #Define the SQL to execute
      $sql = "SELECT *
              FROM
              	HASHERS
              WHERE
              	HASHERS.HASHER_KY NOT IN (SELECT HASHER_KY FROM HASHINGS)
                  AND
                  HASHERS.HASHER_KY NOT IN (SELECT HARINGS_HASHER_KY FROM HARINGS)";

      if($this->hasLegacyHashCounts()) {
        $sql .= " AND HASHERS.HASHER_KY NOT IN (SELECT HASHER_KY FROM LEGACY_HASHINGS)";
      }

      #Execute the SQL statement; create an array of rows
      $theList = $this->fetchAll($sql);

      # Establish and set the return value
      $returnValue = $this->render('admin_orphaned_hashers.twig',array(
        'pageTitle' => 'The List of Orphaned Hashers',
        'pageSubTitle' => 'Hashers who have never hashed or hared',
        'theList' => $theList,
        'tableCaption' => 'A list of all hashes ever, since forever.',
        'kennel_abbreviation' => 'XXX'
      ));


      #Return the return value
      return $returnValue;
    }

  #Define the action
  public function eventBudgetPreAction(Request $request, int $hash_id){

    #Obtain the hash event information

    #Obtain the default cost information
    $virginCost= 0;
    $houndCost = 8;
    $hareCost = 0;

    #Obtain the number of hounds
    $houndCountSQL = HOUND_COUNT_BY_HASH_KEY;
    $theHoundCountValue = $this->fetchAssoc($houndCountSQL, array((int) $hash_id));
    $theHoundCount = $theHoundCountValue['THE_COUNT'];

    #Obtain the number of hares
    $hareCountSQL = HARE_COUNT_BY_HASH_KEY;
    $theHareCountValue = $this->fetchAssoc($hareCountSQL, array((int) $hash_id));
    $theHareCount = $theHareCountValue['THE_COUNT'];

    # Establish and set the return value
    $returnValue = $this->render('event_budget.twig',array(
      'pageTitle' => 'Event Budget',
      'pageSubTitle' => 'Online Calculator',
      'pageCaption' => 'Event Budget Test Page Caption',
      'tableCaption' => 'Event Budget Test Table Caption',

      'defaultBeveragePrice' => 7.00,
      'defaultHareExpense' => 0,
      'defaultTreasuryDeposit' => 0,
      'defaultVirginCount' => 0,
      'defaultCashCollected' => 0,
      'defaultHareCost' => $hareCost,
      'defaultHoundCost' => $houndCost,
      'defaultVirginCost' => $virginCost,
      'defaultCharitableDonation' => 0,
      'defaultTipPercentage' => 20,
      'houndCount' => $theHoundCount ,
      'hareCount' => $theHareCount
    ));

    # Return the return value
    return $returnValue;

  }


  public function newPasswordAction(Request $request){


    $formFactoryThing = $this->app['form.factory']->createBuilder(FormType::class, $data)

      ->add('Current_Password', TextType::class, array('constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 3)))))
      ->add('New_Password_Initial', TextType::class, array('constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 8)))))
      ->add('New_Password_Confirmation', TextType::class, array('constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 8)))));


    $formFactoryThing->add('save', SubmitType::class, array('label' => 'Change your password!'));
    $formFactoryThing->setAction('#');
    $formFactoryThing->setMethod('POST');
    $form=$formFactoryThing->getForm();


    $form->handleRequest($request);

    if($request->getMethod() == 'POST'){

      if ($form->isValid()) {
          #Obtain the name/value pairs from the form
          $data = $form->getData();

          #Establish the values from the form
          $tempCurrentPassword = $data['Current_Password'];
          $tempNewPasswordInitial = $data['New_Password_Initial'];
          $tempNewPasswordConfirmation = $data['New_Password_Confirmation'];

          #Establish the userid value
          $token = $this->app['security.token_storage']->getToken();
          if (null !== $token) {
            $userid = $token->getUser();
          }

          // find the encoder for a UserInterface instance
          $encoder = $this->app['security.encoder_factory']->getEncoder($userid);

          // compute the encoded password for the new password
          $encodedNewPassword = $encoder->encodePassword($tempNewPasswordInitial, $userid->getSalt());

          // compute the encoded password for the current password
          $encodedCurrentPassword = $encoder->encodePassword($tempCurrentPassword, $userid->getSalt());



          #Check if the current password is valid
          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM USERS WHERE USERNAME = ? AND PASSWORD = ?";

          # Make a database call to obtain the hasher information
          $retrievedUserValue = $this->fetchAssoc($sql, array((string) $userid, (string) $encodedCurrentPassword));
          $sizeOfRetrievedUserValueArray = sizeof($retrievedUserValue);


          # If there are more than one columns, then it is valid
          $foundValidationError=FALSE;
          $validCurrentPassword = FALSE;
          if($sizeOfRetrievedUserValueArray > 1){
            $validCurrentPassword = TRUE;
          }else{
            $this->app['session']->getFlashBag()->add('danger', 'Wrong! You screwed up your current password.');
            $foundValidationError=TRUE;
          }

          #Check if the initial new password and the confirmation new password match
          $validNewPasswordsMatch = FALSE;
          if($tempNewPasswordInitial == $tempNewPasswordConfirmation){
            $validNewPasswordsMatch = TRUE;
          }else{
            $this->app['session']->getFlashBag()->add('danger', 'Wrong! The new passwords do not match.');
            $foundValidationError=TRUE;
          }


          #Check if the new password matches password complexity requirements
          $validPasswordComplexity = FALSE;
          if (preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$', $tempNewPasswordInitial)){
            $validPasswordComplexity = TRUE;
          }else{
            $this->app['session']->getFlashBag()->add('danger', 'Wrong! Your proposed password is too simple. It must be 8 characters long, contain a lower case letter, an upper case letter, a digit, and a special character!');
            $foundValidationError=TRUE;
          }

          if(!$foundValidationError){
            #Define the SQL for the password update
            $updateSql = "UPDATE USERS SET PASSWORD = ? WHERE USERNAME = ?";

            #Run the update SQL
            $this->app['dbs']['mysql_write']->executeUpdate($updateSql,array($encodedNewPassword,$userid));

            #Audit this activity
            $actionType = "Password Change";
            $actionDescription = "Changed their password";
            $this->auditTheThings($request, $actionType, $actionDescription);

            #Show the confirmation message
            $this->app['session']->getFlashBag()->add('success', 'Success! You updated your password. Probably.');
          }

      } else{
        $this->app['session']->getFlashBag()->add('danger', 'Wrong! You screwed up.');
      }

    }

    #Establish the userid value
    $token = $this->app['security.token_storage']->getToken();
    if (null !== $token) {
      $userid = $token->getUser();
    }

    $returnValue = $this->render('admin_change_password_form.twig', array (
      'pageTitle' => 'Password change',
      'pageHeader' => 'Your new password must contain letters, numbers, an odd number of prime numbers.',
      'form' => $form->createView(),
      'userid' => $userid,
    ));

    #Return the return value
    return $returnValue;

  }




  #Define the action
  public function viewAuditRecordsPreActionJson(Request $request){

    # Establish and set the return value
    $returnValue = $this->render('audit_records_json.twig',array(
      'pageTitle' => 'The audit records',
      'pageSubTitle' => 'Stuff that the admins have done',
    ));

    #Return the return value
    return $returnValue;

  }


  public function viewAuditRecordsJson(Request $request){

    #$this->app['monolog']->addDebug("Entering the function viewAuditRecordsJson");

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
    $inputOrderColumnExtracted = "1";
    $inputOrderColumnIncremented = "1";
    $inputOrderDirectionExtracted = "asc";
    if(!is_null($inputOrderRaw)){
      #$this->app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }else{
      #$this->app['monolog']->addDebug("inside inputOrderRaw is null");
      $inputOrderColumnIncremented =2;
      $inputOrderDirectionExtracted = "desc";
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT
      USERNAME,
      AUDIT_TIME,
      ACTION_TYPE,
      ACTION_DESCRIPTION,
      IP_ADDR,
      AUDIT_KY,
      DATE_FORMAT(AUDIT_TIME,'%m/%d/%y %h:%i:%s %p') AS AUDIT_TIME_FORMATTED
      FROM AUDIT
      WHERE
        (
          USERNAME LIKE ? OR
          AUDIT_TIME LIKE ? OR
          ACTION_TYPE LIKE ? OR
          ACTION_DESCRIPTION LIKE ? OR
          IP_ADDR LIKE ?)
      ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
      LIMIT $inputStart,$inputLength";
      #$this->app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM AUDIT
      WHERE
          USERNAME LIKE ? OR
          AUDIT_TIME LIKE ? OR
          ACTION_TYPE LIKE ? OR
          ACTION_DESCRIPTION LIKE ? OR
          IP_ADDR LIKE ?";
    #$this->app['monolog']->addDebug("sqlFilteredCount: $sqlFilteredCount");

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM AUDIT";
    #$this->app['monolog']->addDebug("sqlUnfilteredCount: $sqlUnfilteredCount");

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
    #$this->app['monolog']->addDebug("theUnfilteredCount: $theUnfilteredCount");

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #$this->app['monolog']->addDebug("theFilteredCount: $theFilteredCount");
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
    #$this->app['monolog']->addDebug("returnValue: $returnValue");

    #Return the return value
    return $returnValue;
  }

  public function deleteHash(Request $request, int $hash_id) {

    $sql = "SELECT KENNEL_EVENT_NUMBER, KENNEL_ABBREVIATION FROM HASHES_TABLE JOIN KENNELS ON HASHES_TABLE.KENNEL_KY = KENNELS.KENNEL_KY WHERE HASH_KY = ?";
    $eventDetails = $this->fetchAssoc($sql, array($hash_id));
    $kennel_event_number = $eventDetails['KENNEL_EVENT_NUMBER'];
    $kennel_abbreviation = $eventDetails['KENNEL_ABBREVIATION'];

    $sql = "DELETE FROM HASHES_TABLE WHERE HASH_KY = ?";
    $this->app['dbs']['mysql_write']->executeUpdate($sql, array($hash_id));

    $actionType = "Event Deletion (Ajax)";
    $actionDescription = "Deleted event ($kennel_abbreviation # $kennel_event_number)";

    $this->auditTheThings($request, $actionType, $actionDescription);

    header("Location: /admin/hello");
    return $this->app->json("", 302);
  }

  #Define the action
  public function listHashesPreActionJson(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig',array(
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'listhashes2'));
      }
    }

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE KENNEL_KY = ?";

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,array($kennelKy)))['THE_COUNT'];

    #Define the sql that gets the overall counts
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE PLACE_ID is null AND KENNEL_KY = ?";

    #Perform the untiltered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,array($kennelKy)))['THE_COUNT'];

    # Establish and set the return value
    $returnValue = $this->render('admin_hash_list_json.twig',array(
      'pageTitle' => 'The List of Hashes',
      'pageSubTitle' => 'The List of *ALL* Hashes',
      'pageCaption' => "",
      'tableCaption' => "",
      'kennel_abbreviation' => $kennel_abbreviation,
      'totalHashes' => $theUnfilteredCount,
      'totalHashesToUpdate' => $theFilteredCount,
      'showBudgetPage' => $this->showBudgetPage()
    ));

    #Return the return value
    return $returnValue;
  }

  public function getHashListJson(Request $request, string $kennel_abbreviation){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    #$this->app['monolog']->addDebug("Entering the function------------------------");

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
    $inputOrderColumnExtracted = "3";
    $inputOrderColumnIncremented = "3";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)){
      #$this->app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
      #$this->app['monolog']->addDebug("inputOrderColumnExtracted $inputOrderColumnExtracted");
      #$this->app['monolog']->addDebug("inputOrderColumnIncremented $inputOrderColumnIncremented");
      #$this->app['monolog']->addDebug("inputOrderDirectionExtracted $inputOrderDirectionExtracted");
    }else{
      #$this->app['monolog']->addDebug("inside inputOrderRaw is null");
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT
        KENNEL_EVENT_NUMBER,
        HASH_KY,
        DATE_FORMAT(EVENT_DATE,\"%Y/%m/%d\") AS EVENT_DATE,
        EVENT_LOCATION,
        SPECIAL_EVENT_DESCRIPTION,
        PLACE_ID,
        COALESCE(
          (SELECT 0 FROM HARINGS WHERE HARINGS.HARINGS_HASH_KY = HASHES_TABLE.HASH_KY LIMIT 1),
          (SELECT 0 FROM HASHINGS WHERE HASHINGS.HASH_KY = HASHES_TABLE.HASH_KY LIMIT 1),
          1) AS CAN_DELETE
      FROM HASHES_TABLE
      WHERE
        (
          KENNEL_EVENT_NUMBER LIKE ? OR
          EVENT_DATE LIKE ? OR
          EVENT_LOCATION LIKE ?  OR
          SPECIAL_EVENT_DESCRIPTION LIKE ?
        )
        AND KENNEL_KY = ?
      ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
      LIMIT $inputStart,$inputLength";
      #$this->app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
    FROM HASHES_TABLE
    WHERE
      (
        KENNEL_EVENT_NUMBER LIKE ? OR
        EVENT_DATE LIKE ? OR
        EVENT_LOCATION LIKE ? OR
        SPECIAL_EVENT_DESCRIPTION LIKE ?)
        AND KENNEL_KY = ?";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES_TABLE WHERE KENNEL_KY = ?";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $this->fetchAll($sql,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      $kennelKy));

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,array($kennelKy)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      $kennelKy)))['THE_COUNT'];
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


  #Define the action
  public function listHashersPreActionJson(Request $request){

    # Establish and set the return value
    $returnValue = $this->render('admin_hasher_list_json.twig',array(
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => '',
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }

  public function getHashersListJson(Request $request){

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
    $inputOrderColumnExtracted = "2";
    $inputOrderColumnIncremented = "2";
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)){
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT
        HASHER_NAME AS NAME,
        HASHER_KY AS THE_KEY,
        FIRST_NAME,
        LAST_NAME,
        HASHER_ABBREVIATION
      FROM HASHERS
      WHERE
        (
          HASHER_NAME LIKE ? OR
          FIRST_NAME LIKE ? OR
          LAST_NAME LIKE ? OR
          HASHER_ABBREVIATION LIKE ?)
      ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
      LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
    FROM HASHERS
    WHERE
      (
        HASHER_NAME LIKE ? OR
        FIRST_NAME LIKE ? OR
        LAST_NAME LIKE ? OR
        HASHER_ABBREVIATION LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHERS";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $this->fetchAll($sql,array(
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

  public function getHashersParticipationListJson(Request $request){

    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($_POST['kennel_abbreviation']);
    $hashKy = $_POST['hash_key'];

    #Obtain the post parameters
    $inputStart = $_POST['start'] ;
    $inputLength = $_POST['length'] ;
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

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    $hashersAlreadyAddedToEventSql =
      "SELECT HASHER_KY FROM HASHINGS WHERE HASH_KY = ?";

    #Define the sql that performs the filtering
    $sql = "SELECT
        HASHER_NAME AS NAME,
        HASHER_KY AS THE_KEY,
        FIRST_NAME,
        LAST_NAME,
        HASHER_ABBREVIATION,
        (SELECT COUNT(*)
           FROM HASHINGS
          WHERE HASHINGS.HASHER_KY = HASHERS.HASHER_KY
            AND HASHINGS.HASH_KY != ?
            AND HASHINGS.HASH_KY IN (
            SELECT HASH_KY
              FROM HASHES_TABLE
             WHERE KENNEL_KY = ?
               AND EVENT_DATE > DATE_SUB((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 3 MONTH)
               AND EVENT_DATE < DATE_ADD((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 3 MONTH))) AS RECENT_HASH_COUNT,
        (SELECT COUNT(*)
           FROM HASHINGS
          WHERE HASHINGS.HASHER_KY = HASHERS.HASHER_KY
            AND HASHINGS.HASH_KY != ?
            AND HASHINGS.HASH_KY IN (
            SELECT HASH_KY
              FROM HASHES_TABLE
             WHERE KENNEL_KY = ?
               AND EVENT_DATE > DATE_SUB((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 1 YEAR)
               AND EVENT_DATE < DATE_ADD((SELECT EVENT_DATE FROM HASHES_TABLE WHERE HASH_KY = ?), INTERVAL 1 YEAR))) AS SORTA_RECENT_HASH_COUNT
      FROM HASHERS
      WHERE
          HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")
        AND
        (
          HASHER_NAME LIKE ? OR
          FIRST_NAME LIKE ? OR
          LAST_NAME LIKE ? OR
          HASHER_ABBREVIATION LIKE ?)
      ORDER BY RECENT_HASH_COUNT DESC, SORTA_RECENT_HASH_COUNT DESC
      LIMIT $inputStart,$inputLength";

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
    FROM HASHERS
    WHERE
        HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")
      AND
      (
        HASHER_NAME LIKE ? OR
        FIRST_NAME LIKE ? OR
        LAST_NAME LIKE ? OR
        HASHER_ABBREVIATION LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHERS WHERE HASHER_KY NOT IN (".$hashersAlreadyAddedToEventSql.")";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $this->fetchAll($sql,array(
      $hashKy, $kennelKy, $hashKy, $hashKy,
      $hashKy, $kennelKy, $hashKy, $hashKy, $hashKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($this->fetchAssoc($sqlUnfilteredCount,array($hashKy)))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($this->fetchAssoc($sqlFilteredCount,array($hashKy,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #-------------- End: Query the database   --------------------------------

    #Establish the output
    $output = array(
      "iTotalRecords" => $theUnfilteredCount,
      "iTotalDisplayRecords" => $theFilteredCount,
      "aaData" => $theResults
    );

    return $this->app->json($output,200);
  }

  public function hasherDetailsKennelSelection(Request $request, int $hasher_id){


    #Obtain the kennels that are being tracked in this website instance
    $listOfKennelsSQL = "SELECT * FROM KENNELS WHERE IN_RECORD_KEEPING = 1";
    $kennelValues = $this->fetchAll($listOfKennelsSQL);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $this->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Derive the hasher name
    $hasherName = $hasher['HASHER_NAME'];

    # Establish and set the return value
    $returnValue = $this->render('hasher_details_select_kennel.twig',array(
      'pageTitle' => 'Hasher Details: Select Kennel',
      'kennelValues' => $kennelValues,
      'hasherId' => $hasher_id,
      'hasherName' => $hasherName
    ));

    #Return the return value
    return $returnValue;

  }

  public function roster(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
      } else {
        return $this->render('admin_select_kennel.twig',array(
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'roster'));
      }
    }

    // Start with 5 minimum hashes in the last 6 months...
    // if <15 results, widen the search
    for($j=5; $j>0; $j--) {
      for($i=1; $i<3; $i++) {

        #Define the SQL to execute
        $sql = "
          SELECT HASHER_NAME
            FROM HASHERS
           WHERE HASHERS.HASHER_KY IN (
                 SELECT HASHER_KY
                   FROM HASHINGS
                  WHERE HASH_KY IN (
                        SELECT HASH_KY
                          FROM HASHES
                         WHERE EVENT_DATE >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                           AND KENNEL_KY = ?)
                  GROUP BY HASHER_KY
                 HAVING COUNT(*) >= ?)
           ORDER BY HASHER_NAME";

        #Execute the SQL statement; create an array of rows
        $theList = $this->fetchAll($sql, array($i * 6, $kennelKy, $j));

        if(count($theList) > 15) break;
      }
      if(count($theList) > 15) break;
    }

    # Establish and set the return value
    $returnValue = $this->render('admin_roster.twig',array(
      'theList' => $theList
    ));
    #Return the return value
    return $returnValue;
  }

  public function legacy(Request $request, string $kennel_abbreviation = null) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig',array(
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'legacy'));
      }
    }

    $sql = "
      SELECT HASHERS.HASHER_KY, HASHERS.HASHER_NAME,
             COALESCE(LEGACY_HASHINGS.LEGACY_HASHINGS_COUNT, 0) AS LEGACY_HASHINGS_COUNT
        FROM HASHERS
   LEFT JOIN LEGACY_HASHINGS
          ON LEGACY_HASHINGS.HASHER_KY = HASHERS.HASHER_KY
       WHERE LEGACY_HASHINGS.KENNEL_KY IS NULL OR LEGACY_HASHINGS.KENNEL_KY = ?
       ORDER BY HASHERS.HASHER_NAME";

    #Execute the SQL statement; create an array of rows
    $theList = $this->fetchAll($sql, array($kennelKy));

    # Establish and set the return value
    $returnValue = $this->render('admin_legacy_hashings.twig',array(
      'theList' => $theList,
      'pageTitle' => 'Legacy Hashing Counts',
      'tableCaption' => 'Legacy Hashing Counts',
      'kennelAbbreviation' => $kennel_abbreviation
    ));

    #Return the return value
    return $returnValue;
  }

  private function processLegacyCountChange(int $kennelKy, int $k, int $c) {
    if($c == 0) {
      $sql = "DELETE FROM LEGACY_HASHINGS WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      $this->app['dbs']['mysql_write']->executeUpdate($sql, array($k, $kennelKy));
    } else {
      $sql = "UPDATE LEGACY_HASHINGS SET LEGACY_HASHINGS_COUNT = ? WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      if($this->app['dbs']['mysql_write']->executeUpdate($sql, array($c, $k, $kennelKy)) == 0) {
        $sql = "SELECT 'exists' AS x FROM LEGACY_HASHINGS WHERE HASHER_KY = ? AND KENNEL_KY = ?";
        if($this->fetchOne($sql, array($k, $kennelKy)) != 'exists') {
          $sql = "INSERT INTO LEGACY_HASHINGS(LEGACY_HASHINGS_COUNT, HASHER_KY, KENNEL_KY) VALUES(?,?,?)";
          $this->app['dbs']['mysql_write']->executeUpdate($sql, array($c, $k, $kennelKy));
        }
      }
    }
  }

  public function legacyUpdate(Request $request, string $kennel_abbreviation) {
    $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);

    $k = $request->request->get('k');
    $c = $request->request->get('c');

    if(is_array($k)) {
      for($i=0; $i < count($k); $i++) {
        $this->processLegacyCountChange($kennelKy, $k[$i], $c[$i]);
      }
    } else {
      $this->processLegacyCountChange($kennelKy, $k, $c);
    }
    return new Response("OK", 200, array('Content-Type' => 'text/plain'));
  }

  private function getKennels() {
    $sql = "
      SELECT KENNEL_KY, KENNEL_ABBREVIATION
        FROM KENNELS
       WHERE IN_RECORD_KEEPING = 1
       ORDER BY KENNEL_ABBREVIATION";

    return $this->fetchAll($sql, array());
  }


  public function awards(Request $request, string $kennel_abbreviation = null, string $type, int $horizon = -1) {

    if($kennel_abbreviation) {
      $kennelKy = $this->obtainKennelKeyFromKennelAbbreviation($kennel_abbreviation);
    } else {
      $kennels = $this->getKennels();

      if(count($kennels) == 1) {
        $kennelKy = (int) $kennels[0]['KENNEL_KY'];
        $kennel_abbreviation = $kennels[0]['KENNEL_ABBREVIATION'];
      } else {
        return $this->render('admin_select_kennel.twig',array(
          'kennels' => $kennels,
          'pageTracking' => 'AdminSelectKennel',
          'pageTitle' => 'Select Kennel',
          'urlSuffix' => 'awards/'.$type));
      }
    }

    if($horizon == -1) {
      $horizon = $this->getDefaultAwardEventHorizon();
    }

    # Declare the SQL used to retrieve this information
    $sql =
      "SELECT THE_KEY, NAME, VALUE,
              HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED AS LAST_AWARD,".
              ($type == "pending" ? "MAX" : "MIN")."(AWARD_LEVELS.AWARD_LEVEL) AS NEXT_AWARD_LEVEL
         FROM (".$this->getHashingCountsQuery().") HASHER_COUNTS
         LEFT JOIN HASHER_AWARDS
           ON HASHER_COUNTS.THE_KEY = HASHER_AWARDS.HASHER_KY
          AND HASHER_COUNTS.KENNEL_KY = HASHER_AWARDS.KENNEL_KY
         JOIN AWARD_LEVELS
           ON AWARD_LEVELS.KENNEL_KY = HASHER_COUNTS.KENNEL_KY
        WHERE AWARD_LEVELS.AWARD_LEVEL > COALESCE(HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED, 0)".
              ($type == "pending" ? "
          AND (VALUE + ?) >= AWARD_LEVELS.AWARD_LEVEL" : "
          AND ? != -1
          AND VALUE <= AWARD_LEVELS.AWARD_LEVEL
          AND AWARD_LEVELS.AWARD_LEVEL > COALESCE(HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED, 0)")."
        GROUP BY THE_KEY, NAME, VALUE, HASHER_AWARDS.LAST_AWARD_LEVEL_RECOGNIZED
        ORDER BY VALUE DESC, NAME";

    #Execute the SQL statement; create an array of rows
    $hasherList = $this->fetchAll($sql, array($kennelKy, $kennelKy, $horizon));

    # Establish and set the return value
    $returnValue = $this->render('admin_awards.twig',array(
      'pageTitle' => ($type=="pending" ? 'Pending' : 'All')." Hasher Awards",
      'tableCaption' => $type=="pending" ? 'Hashers, awards due, and last awards given.  Click the checkbox when a hasher receives the award they are due.' :
        'All hashers and the last award they received.',
      'subTitle' => $type=="pending" ? "All hashers that are due to receive an award." : "All hashers and the last award they have received.",
      'theList' => $hasherList,
      'kennel_abbreviation' => $kennel_abbreviation,
      'kennel_key' => $kennelKy,
      'pageTracking' => 'Hasher Awards',
      'type' => $type,
      'horizon' => $horizon
    ));

    #Return the return value
    return $returnValue;
  }

  public function updateHasherAwardAjaxAction(Request $request) {

    #Establish the return message
    $returnMessage = "This has not been set yet...";

    #Obtain the post values
    $hasherKey = $request->request->get('hasher_key');
    $kennelKey = $request->request->get('kennel_key');
    $awardLevel = $request->request->get('award_level');

    #Obtain the csrf token
    $csrfToken = $request->request->get('csrf_token');

    #Check if the csrf token is valid
    /*
    if($this->isCsrfTokenValid('delete',$csrfToken)){
      $returnValue =  $this->app->json("valid", 200);
      return $returnValue;
    }else{
      $returnValue =  $this->app->json("not valid", 200);
      return $returnValue;
    }
    */

    #Validate the post values; ensure that they are both numbers
    if(ctype_digit($hasherKey) & ctype_digit($kennelKey)) {

      $sql = "SELECT 1 FROM HASHER_AWARDS WHERE HASHER_KY = ? AND KENNEL_KY = ?";

      $exists = $this->fetchAssoc($sql, array((int) $hasherKey, (int) $kennelKey));

      if($exists) {
        $sql = "UPDATE HASHER_AWARDS SET LAST_AWARD_LEVEL_RECOGNIZED = ? WHERE HASHER_KY = ? AND KENNEL_KY = ?";
      } else {
        $sql = "INSERT INTO HASHER_AWARDS(LAST_AWARD_LEVEL_RECOGNIZED, HASHER_KY, KENNEL_KY) VALUES(?,?,?)";
      }

      try {
        $this->app['dbs']['mysql_write']->executeUpdate($sql, array((int) $awardLevel, (int) $hasherKey, (int) $kennelKey));

        $returnMessage = "Success!";
      } catch (\Exception $theException) {

        $tempActionType = "Update Hasher Award";
        $tempActionDescription = "Failed to update hasher award for $hasherKey";
        $this->auditTheThings($request, $tempActionType, $tempActionDescription);

        #Define the return message
        $returnMessage = "Oh crap. Something bad happened.";
      }
    }

    #Set the return value
    $returnValue =  $this->app->json($returnMessage, 200);
    return $returnValue;
  }
}
