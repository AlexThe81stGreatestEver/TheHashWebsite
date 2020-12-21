<?php

namespace HASH\Controller;

require_once realpath(__DIR__ . '/../..').'/config/SQL_Queries.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;



class AdminController
{

  public static $stateDropdownArray = array(
      'Ohio' => 'OH',
      'Alabama' => 'AL',
      'Alaska' => 'AK',
      'Arizona' => 'AZ',
      'Arkansas' => 'AR',
      'Colorado' => 'CO',
      'California' => 'CA',
      'Connecticut' => 'CT',
      'Delaware' => 'DE',
      'District Of Columbia' => 'DC',
      'Florida' => 'FL',
      'Georgia' => 'GA',
      'Hawaii' => 'HI',
      'Idaho' => 'ID',
      'Illinois' => 'IL',
      'Indiana' => 'IN',
      'Iowa' => 'IA',
      'Kansas' => 'KS',
      'Kentucky' => 'KY',
      'Louisiana' => 'LA',
      'Maine' => 'ME',
      'Maryland' => 'MD',
      'Massachusetts' => 'MA',
      'Michigan' => 'MI',
      'Minnesota' => 'MN',
      'Mississippi' => 'MS',
      'Missouri' => 'MO',
      'Montana' => 'MT',
      'Nebraska' => 'NE',
      'Nevada' => 'NV',
      'New Hampshire' => 'NH',
      'New Jersey' => 'NJ',
      'New Mexico' => 'NM',
      'New York' => 'NY',
      'North Carolina' => 'NC',
      'North Dakota' => 'ND',
      'Oklahoma' => 'OK',
      'Oregon' => 'OR',
      'Pennsylvania' => 'PA',
      'Rhode Island' => 'RI',
      'South Carolina' => 'SC',
      'South Dakota' => 'SD',
      'Tennessee' => 'TN',
      'Texas' => 'TX',
      'Utah' => 'UT',
      'Vermont' => 'VT',
      'Virginia' => 'VA',
      'Washington' => 'WA',
      'West Virginia' => 'WV',
      'Wisconsin' => 'WI',
      'Wyoming' => 'WY'
  );

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
        'subTitle1' => 'This is the admin landing screen',
    ));
  }

  #Define the action
  public function adminHelloAction(Request $request, Application $app){

      #return $app->redirect('/');
      return $app['twig']->render('admin_landing.twig', array (
        'pageTitle' => 'Site Administration',
        'subTitle1' => 'This is the admin hello landing screen (sub title 1)',
      ));
  }


  #Define the action
  public function userHelloAction(Request $request, Application $app){

      #return $app->redirect('/');
      return $app['twig']->render('admin_landing.twig', array (
        'pageTitle' => 'This is the user hello landing screen (page title)',
        'subTitle1' => 'This is the user hello landing screen (sub title 1)',
      ));
  }

    public function listOrphanedHashersAction(Request $request, Application $app){

      #Define the SQL to execute
      $sql = "SELECT *
              FROM
              	HASHERS
              WHERE
              	HASHERS.HASHER_KY NOT IN (SELECT HASHER_KY FROM HASHINGS)
                  AND
                  HASHERS.HASHER_KY NOT IN (SELECT HARINGS_HASHER_KY FROM HARINGS)";

      #Execute the SQL statement; create an array of rows
      $theList = $app['db']->fetchAll($sql);

      # Establish and set the return value
      $returnValue = $app['twig']->render('admin_orphaned_hashers.twig',array(
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
  public function d3testAction(Request $request, Application $app){

    # Establish and set the return value
    $returnValue = $app['twig']->render('d3_test.twig',array(
      'pageTitle' => 'D3 Test Title',
      'pageSubTitle' => 'D3 Test Subtitle',
      'pageCaption' => 'D3 Test Page Caption',
      'tableCaption' => 'D3 Test Table Caption'
    ));

    # Return the return value
    return $returnValue;

  }

  #Define the action
  public function eventBudgetPreAction(Request $request, Application $app, int $hash_id){

    #Obtain the hash event information

    #Obtain the default cost information
    $virginCost= 0;
    $houndCost = 8;
    $hareCost = 0;

    #Obtain the number of hounds
    $houndCountSQL = HOUND_COUNT_BY_HASH_KEY;
    $theHoundCountValue = $app['db']->fetchAssoc($houndCountSQL, array((int) $hash_id));
    $theHoundCount = $theHoundCountValue['THE_COUNT'];

    #Obtain the number of hares
    $hareCountSQL = HARE_COUNT_BY_HASH_KEY;
    $theHareCountValue = $app['db']->fetchAssoc($hareCountSQL, array((int) $hash_id));
    $theHareCount = $theHareCountValue['THE_COUNT'];

    # Establish and set the return value
    $returnValue = $app['twig']->render('event_budget.twig',array(
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


  public function newPasswordAction(Request $request, Application $app){


    $formFactoryThing = $app['form.factory']->createBuilder(FormType::class, $data)

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
          $token = $app['security.token_storage']->getToken();
          if (null !== $token) {
            $userid = $token->getUser();
          }

          // find the encoder for a UserInterface instance
          $encoder = $app['security.encoder_factory']->getEncoder($userid);

          // compute the encoded password for the new password
          $encodedNewPassword = $encoder->encodePassword($tempNewPasswordInitial, $userid->getSalt());

          // compute the encoded password for the current password
          $encodedCurrentPassword = $encoder->encodePassword($tempCurrentPassword, $userid->getSalt());



          #Check if the current password is valid
          # Declare the SQL used to retrieve this information
          $sql = "SELECT * FROM USERS WHERE USERNAME = ? AND PASSWORD = ?";

          # Make a database call to obtain the hasher information
          $retrievedUserValue = $app['db']->fetchAssoc($sql, array((string) $userid, (string) $encodedCurrentPassword));
          $sizeOfRetrievedUserValueArray = sizeof($retrievedUserValue);


          # If there are more than one columns, then it is valid
          $foundValidationError=FALSE;
          $validCurrentPassword = FALSE;
          if($sizeOfRetrievedUserValueArray > 1){
            $validCurrentPassword = TRUE;
          }else{
            $app['session']->getFlashBag()->add('danger', 'Wrong! You screwed up your current password.');
            $foundValidationError=TRUE;
          }

          #Check if the initial new password and the confirmation new password match
          $validNewPasswordsMatch = FALSE;
          if($tempNewPasswordInitial == $tempNewPasswordConfirmation){
            $validNewPasswordsMatch = TRUE;
          }else{
            $app['session']->getFlashBag()->add('danger', 'Wrong! The new passwords do not match.');
            $foundValidationError=TRUE;
          }


          #Check if the new password matches password complexity requirements
          $validPasswordComplexity = FALSE;
          if (preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$', $tempNewPasswordInitial)){
            $validPasswordComplexity = TRUE;
          }else{
            $app['session']->getFlashBag()->add('danger', 'Wrong! Your proposed password is too simple. It must be 8 characters long, contain a lower case letter, an upper case letter, a digit, and a special character!');
            $foundValidationError=TRUE;
          }

          if(!$foundValidationError){
            #Define the SQL for the password update
            $updateSql = "UPDATE USERS SET PASSWORD = ? WHERE USERNAME = ?";

            #Run the update SQL
            $app['dbs']['mysql_write']->executeUpdate($updateSql,array($encodedNewPassword,$userid));

            #Audit this activity
            $actionType = "Password Change";
            $actionDescription = "Changed their password";
            $this->auditTheThings($request, $app, $actionType, $actionDescription);

            #Show the confirmation message
            $app['session']->getFlashBag()->add('success', 'Success! You updated your password. Probably.');
          }

      } else{
        $app['session']->getFlashBag()->add('danger', 'Wrong! You screwed up.');
      }

    }

    #Establish the userid value
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
      $userid = $token->getUser();
    }

    $returnValue = $app['twig']->render('admin_change_password_form.twig', array (
      'pageTitle' => 'Password change',
      'pageHeader' => 'Your new password must contain letters, numbers, an odd number of prime numbers.',
      'form' => $form->createView(),
      'userid' => $userid,
    ));

    #Return the return value
    return $returnValue;

  }




  #Define the action
  public function viewAuditRecordsPreActionJson(Request $request, Application $app){

    # Establish and set the return value
    $returnValue = $app['twig']->render('audit_records_json.twig',array(
      'pageTitle' => 'The audit records',
      'pageSubTitle' => 'Stuff that the admins have done',
    ));

    #Return the return value
    return $returnValue;

  }


  public function viewAuditRecordsJson(Request $request, Application $app){

    #$app['monolog']->addDebug("Entering the function viewAuditRecordsJson");

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
      #$app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
      FROM AUDIT
      WHERE
          USERNAME LIKE ? OR
          AUDIT_TIME LIKE ? OR
          ACTION_TYPE LIKE ? OR
          ACTION_DESCRIPTION LIKE ? OR
          IP_ADDR LIKE ?";
    #$app['monolog']->addDebug("sqlFilteredCount: $sqlFilteredCount");

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM AUDIT";
    #$app['monolog']->addDebug("sqlUnfilteredCount: $sqlUnfilteredCount");

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];
    #$app['monolog']->addDebug("theUnfilteredCount: $theUnfilteredCount");

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified)))['THE_COUNT'];
    #$app['monolog']->addDebug("theFilteredCount: $theFilteredCount");
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
    #$app['monolog']->addDebug("returnValue: $returnValue");

    #Return the return value
    return $returnValue;
  }


  public static function auditTheThings(Request $request, Application $app, string $actionType, string $actionDescription){

    #Define the client ip address
    $theClientIP = $request->getClientIp();

    #Establish the datetime representation of "now"
    date_default_timezone_set('US/Eastern');
    $nowDateTime = date("Y-m-d H:i:s");

    #Define the username (default to UNKNOWN)
    $user = "UNKNOWN";

    #Determine the username
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
      $user = $token->getUser();
    }

    #Define the sql insert statement
    $sql = "
      INSERT INTO AUDIT (
        USERNAME,
        AUDIT_TIME,
        ACTION_TYPE,
        ACTION_DESCRIPTION,
        IP_ADDR
      ) VALUES (?, ?, ?, ?, ?)";

    #Execute the insert statement
    $app['dbs']['mysql_write']->executeUpdate($sql,array(
      $user,
      $nowDateTime,
      $actionType,
      $actionDescription,
      $theClientIP
    ));

  }




  #Define the action
  public function listHashesPreActionJson(Request $request, Application $app){



    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES";

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];

    #Define the sql that gets the overall counts
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES WHERE PLACE_ID is null";

    #Perform the untiltered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array()))['THE_COUNT'];




    # Establish and set the return value
    $returnValue = $app['twig']->render('admin_hash_list_json.twig',array(
      'pageTitle' => 'The List of Hashes (Experimental Page)',
      'pageSubTitle' => 'The List of *ALL* Hashes',
      'pageCaption' => "",
      'tableCaption' => "",
      'totalHashes' => $theUnfilteredCount,
      'totalHashesToUpdate' => $theFilteredCount
    ));

    #Return the return value
    return $returnValue;

  }



  public function getHashListJson(Request $request, Application $app){

    #$app['monolog']->addDebug("Entering the function------------------------");

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
      #$app['monolog']->addDebug("inputOrderColumnExtracted $inputOrderColumnExtracted");
      #$app['monolog']->addDebug("inputOrderColumnIncremented $inputOrderColumnIncremented");
      #$app['monolog']->addDebug("inputOrderDirectionExtracted $inputOrderDirectionExtracted");
    }else{
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
    }

    #-------------- End: Modify the input parameters  --------------------------


    #-------------- Begin: Define the SQL used here   --------------------------

    #Define the sql that performs the filtering
    $sql = "SELECT
        KENNEL_EVENT_NUMBER,
        KENNEL_ABBREVIATION,
        HASH_KY,
        DATE_FORMAT(EVENT_DATE,\"%Y/%m/%d\") AS EVENT_DATE,
        EVENT_LOCATION,
        SPECIAL_EVENT_DESCRIPTION,
        PLACE_ID
      FROM HASHES JOIN KENNELS on HASHES.KENNEL_KY = KENNELS.KENNEL_KY
      WHERE
        (
          KENNEL_EVENT_NUMBER LIKE ? OR
          KENNEL_ABBREVIATION LIKE ? OR
          EVENT_DATE LIKE ? OR
          EVENT_LOCATION LIKE ?  OR
          SPECIAL_EVENT_DESCRIPTION LIKE ?
        )
      ORDER BY $inputOrderColumnIncremented $inputOrderDirectionExtracted
      LIMIT $inputStart,$inputLength";
      #$app['monolog']->addDebug("sql: $sql");

    #Define the SQL that gets the count for the filtered results
    $sqlFilteredCount = "SELECT COUNT(*) AS THE_COUNT
    FROM HASHES JOIN KENNELS on HASHES.KENNEL_KY = KENNELS.KENNEL_KY
    WHERE
      (
        KENNEL_EVENT_NUMBER LIKE ? OR
        KENNEL_ABBREVIATION LIKE ? OR
        EVENT_DATE LIKE ? OR
        EVENT_LOCATION LIKE ? OR
        SPECIAL_EVENT_DESCRIPTION LIKE ?)";

    #Define the sql that gets the overall counts
    $sqlUnfilteredCount = "SELECT COUNT(*) AS THE_COUNT FROM HASHES";

    #-------------- End: Define the SQL used here   ----------------------------

    #-------------- Begin: Query the database   --------------------------------
    #Perform the filtered search
    $theResults = $app['db']->fetchAll($sql,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
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
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }


  #Define the action
  public function listHashersPreActionJson(Request $request, Application $app){

    # Establish and set the return value
    $returnValue = $app['twig']->render('admin_hasher_list_json.twig',array(
      'pageTitle' => 'The List of Hashers',
      'pageSubTitle' => '',
      'pageCaption' => "",
      'tableCaption' => ""
    ));

    #Return the return value
    return $returnValue;

  }

  public function getHashersListJson(Request $request, Application $app){

    #$app['monolog']->addDebug("Entering the function------------------------");

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
    $inputOrderDirectionExtracted = "desc";
    if(!is_null($inputOrderRaw)){
      #$app['monolog']->addDebug("inside inputOrderRaw not null");
      $inputOrderColumnExtracted = $inputOrderRaw[0]['column'];
      $inputOrderColumnIncremented = $inputOrderColumnExtracted + 1;
      $inputOrderDirectionExtracted = $inputOrderRaw[0]['dir'];
      #$app['monolog']->addDebug("inputOrderColumnExtracted $inputOrderColumnExtracted");
      #$app['monolog']->addDebug("inputOrderColumnIncremented $inputOrderColumnIncremented");
      #$app['monolog']->addDebug("inputOrderDirectionExtracted $inputOrderDirectionExtracted");
    }else{
      #$app['monolog']->addDebug("inside inputOrderRaw is null");
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
      #$app['monolog']->addDebug("sql: $sql");

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
    $theResults = $app['db']->fetchAll($sql,array(
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified,
      (string) $inputSearchValueModified));

    #Perform the untiltered count
    $theUnfilteredCount = ($app['db']->fetchAssoc($sqlUnfilteredCount,array()))['THE_COUNT'];

    #Perform the filtered count
    $theFilteredCount = ($app['db']->fetchAssoc($sqlFilteredCount,array(
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
    $returnValue = $app->json($output,200);

    #Return the return value
    return $returnValue;
  }




  public function hasherDetailsKennelSelection(Request $request, Application $app, int $hasher_id){


    #Obtain the kennels that are being tracked in this website instance
    $listOfKennelsSQL = "SELECT * FROM KENNELS WHERE IN_RECORD_KEEPING = 1";
    $kennelValues = $app['db']->fetchAll($listOfKennelsSQL);

    # Declare the SQL used to retrieve this information
    $sql_for_hasher_lookup = "SELECT HASHER_NAME FROM HASHERS WHERE HASHER_KY = ?";

    # Make a database call to obtain the hasher information
    $hasher = $app['db']->fetchAssoc($sql_for_hasher_lookup, array((int) $hasher_id));

    # Derive the hasher name
    $hasherName = $hasher['HASHER_NAME'];

    # Establish and set the return value
    $returnValue = $app['twig']->render('hasher_details_select_kennel.twig',array(
      'pageTitle' => 'Hasher Details: Select Kennel',
      'kennelValues' => $kennelValues,
      'hasherId' => $hasher_id,
      'hasherName' => $hasherName
    ));

    #Return the return value
    return $returnValue;

  }





}
